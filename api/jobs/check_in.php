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

    $stmt = $pdo->prepare(
        'SELECT j.id, j.status, j.start_date, j.end_date, j.payout_amount,
                v.title, h.id AS hotel_id, h.name AS hotel_name
         FROM jobs j
         INNER JOIN vacancies v ON v.id = j.vacancy_id
         INNER JOIN hotels h ON h.id = j.hotel_id
         WHERE j.id = ? AND j.user_id = ?
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([$jobId, (int)$user['user_id']]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'Job not found'], 404);
    }

    $jobStatus = strtolower(trim((string)$job['status']));
    if (!in_array($jobStatus, ['assigned', 'upcoming', 'scheduled', 'confirmed'], true)) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'This job cannot be checked in now'], 409);
    }

    $today = gmdate('Y-m-d');
    if (!empty($job['start_date']) && $today < $job['start_date']) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'Check-in is not available before the job start date'], 409);
    }
    if (!empty($job['end_date']) && $today > $job['end_date']) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'This job has already ended'], 409);
    }

    $openStmt = $pdo->prepare(
        'SELECT id, check_in_at FROM attendance_records
         WHERE job_id = ? AND user_id = ? AND check_out_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );
    $openStmt->execute([$jobId, (int)$user['user_id']]);
    $open = $openStmt->fetch(PDO::FETCH_ASSOC);
    if ($open) {
        $pdo->rollBack();
        respond([
            'success' => false,
            'message' => 'You are already checked in for this job',
            'data' => [
                'job_id' => $jobId,
                'attendance_id' => (int)$open['id'],
                'status' => 'checked_in',
                'checked_in_at' => $open['check_in_at'],
            ],
        ], 409);
    }

    $latitude = isset($input['latitude']) && $input['latitude'] !== '' ? (float)$input['latitude'] : null;
    $longitude = isset($input['longitude']) && $input['longitude'] !== '' ? (float)$input['longitude'] : null;

    $insert = $pdo->prepare(
        'INSERT INTO attendance_records
            (job_id, user_id, check_in_at, check_in_photo_path, check_in_capture_source,
             check_in_latitude, check_in_longitude, check_in_notes)
         VALUES (?, ?, UTC_TIMESTAMP(), NULL, ?, ?, ?, ?)'
    );
    $insert->execute([
        $jobId,
        (int)$user['user_id'],
        'mobile',
        $latitude,
        $longitude,
        isset($input['notes']) ? trim((string)$input['notes']) : null,
    ]);
    $attendanceId = (int)$pdo->lastInsertId();

    $update = $pdo->prepare("UPDATE jobs SET status = 'checked_in', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $update->execute([$jobId]);

    $pdo->commit();

    $timeStmt = $pdo->prepare('SELECT check_in_at FROM attendance_records WHERE id = ? LIMIT 1');
    $timeStmt->execute([$attendanceId]);
    $attendance = $timeStmt->fetch(PDO::FETCH_ASSOC);

    respond([
        'success' => true,
        'message' => 'Checked in successfully',
        'data' => [
            'job_id' => $jobId,
            'attendance_id' => $attendanceId,
            'status' => 'checked_in',
            'checked_in_at' => $attendance['check_in_at'] ?? null,
            'hotel' => [
                'id' => (int)$job['hotel_id'],
                'name' => $job['hotel_name'],
            ],
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(['success' => false, 'message' => 'Could not check in'], 500);
}
