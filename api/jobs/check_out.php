<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = requireUser($pdo);
$input = jsonInput();
$jobId = isset($input['job_id']) ? (int)$input['job_id'] : 0;

if ($jobId <= 0) {
    respond(['success' => false, 'message' => 'job_id is required'], 422);
}

try {
    $pdo->beginTransaction();

    $jobStmt = $pdo->prepare(
        'SELECT j.id, j.status, j.payout_amount, v.title, h.name AS hotel_name
         FROM jobs j
         INNER JOIN vacancies v ON v.id = j.vacancy_id
         INNER JOIN hotels h ON h.id = j.hotel_id
         WHERE j.id = ? AND j.user_id = ?
         LIMIT 1
         FOR UPDATE'
    );
    $jobStmt->execute([$jobId, (int)$user['user_id']]);
    $job = $jobStmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'Job not found'], 404);
    }

    if ((string)$job['status'] !== 'checked_in') {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'You are not currently checked in for this job'], 409);
    }

    $attendanceStmt = $pdo->prepare(
        'SELECT id, check_in_at, check_out_at
         FROM attendance_records
         WHERE job_id = ? AND user_id = ? AND check_out_at IS NULL
         ORDER BY id DESC LIMIT 1
         FOR UPDATE'
    );
    $attendanceStmt->execute([$jobId, (int)$user['user_id']]);
    $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

    if (!$attendance) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'No active check-in found for this job'], 409);
    }

    $notes = isset($input['notes']) ? trim((string)$input['notes']) : null;
    $updateAttendance = $pdo->prepare(
        'UPDATE attendance_records
         SET check_out_at = UTC_TIMESTAMP(), check_out_notes = ?
         WHERE id = ?'
    );
    $updateAttendance->execute([$notes, (int)$attendance['id']]);

    $updateJob = $pdo->prepare(
        "UPDATE jobs
         SET status = 'completed', completed_at = UTC_TIMESTAMP(), updated_at = CURRENT_TIMESTAMP
         WHERE id = ?"
    );
    $updateJob->execute([$jobId]);

    $pdo->commit();

    $resultStmt = $pdo->prepare(
        'SELECT check_in_at, check_out_at,
                ROUND(TIMESTAMPDIFF(SECOND, check_in_at, check_out_at) / 3600, 2) AS worked_hours
         FROM attendance_records WHERE id = ? LIMIT 1'
    );
    $resultStmt->execute([(int)$attendance['id']]);
    $result = $resultStmt->fetch(PDO::FETCH_ASSOC);

    respond([
        'success' => true,
        'message' => 'Checked out successfully',
        'data' => [
            'job_id' => $jobId,
            'attendance_id' => (int)$attendance['id'],
            'status' => 'completed',
            'checked_in_at' => $result['check_in_at'] ?? null,
            'checked_out_at' => $result['check_out_at'] ?? null,
            'worked_hours' => $result['worked_hours'] !== null ? (float)$result['worked_hours'] : 0,
            'payout_amount' => $job['payout_amount'] !== null ? (float)$job['payout_amount'] : null,
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(['success' => false, 'message' => 'Could not check out'], 500);
}
