<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = requireUser($pdo);

if (($user['account_status'] ?? '') !== 'verified') {
    respond([
        'success' => false,
        'message' => 'Your account must be verified before applying for jobs'
    ], 403);
}

$input = jsonInput();
$vacancyId = (int)($input['vacancy_id'] ?? 0);

if ($vacancyId <= 0) {
    respond(['success' => false, 'message' => 'vacancy_id is required'], 422);
}

try {
    $stmt = $pdo->prepare(
        "SELECT
            v.id,
            v.title,
            v.openings_count,
            v.published_at,
            v.closing_at
         FROM vacancies v
         INNER JOIN hotels h ON h.id = v.hotel_id
         WHERE v.id = ?
           AND v.status = 'published'
           AND v.deleted_at IS NULL
           AND h.status = 'active'
           AND h.deleted_at IS NULL
         LIMIT 1"
    );
    $stmt->execute([$vacancyId]);
    $vacancy = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vacancy) {
        respond(['success' => false, 'message' => 'Job not found or no longer available'], 404);
    }

    if ($vacancy['published_at'] !== null && strtotime((string)$vacancy['published_at']) > time()) {
        respond(['success' => false, 'message' => 'This job is not open for applications yet'], 409);
    }

    if ($vacancy['closing_at'] !== null && strtotime((string)$vacancy['closing_at']) <= time()) {
        respond(['success' => false, 'message' => 'Applications for this job are closed'], 409);
    }

    $existingStmt = $pdo->prepare(
        'SELECT id, status, applied_at
         FROM applications
         WHERE vacancy_id = ? AND user_id = ?
         LIMIT 1'
    );
    $existingStmt->execute([$vacancyId, (int)$user['user_id']]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        respond([
            'success' => false,
            'message' => 'You have already applied for this job',
            'data' => [
                'application_id' => (int)$existing['id'],
                'vacancy_id' => $vacancyId,
                'status' => $existing['status'],
                'applied_at' => $existing['applied_at'],
            ]
        ], 409);
    }

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM applications
         WHERE vacancy_id = ?
           AND status IN ('applied', 'shortlisted', 'selected')"
    );
    $countStmt->execute([$vacancyId]);
    $activeApplications = (int)$countStmt->fetchColumn();

    $openings = (int)$vacancy['openings_count'];
    if ($openings > 0 && $activeApplications >= $openings) {
        respond(['success' => false, 'message' => 'This job currently has no remaining openings'], 409);
    }

    $insert = $pdo->prepare(
        "INSERT INTO applications (vacancy_id, user_id, status)
         VALUES (?, ?, 'applied')"
    );

    try {
        $insert->execute([$vacancyId, (int)$user['user_id']]);
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') {
            respond(['success' => false, 'message' => 'You have already applied for this job'], 409);
        }
        throw $e;
    }

    $applicationId = (int)$pdo->lastInsertId();
    $appliedAt = date('Y-m-d H:i:s');

    respond([
        'success' => true,
        'message' => 'Application submitted successfully',
        'data' => [
            'application_id' => $applicationId,
            'vacancy_id' => $vacancyId,
            'status' => 'applied',
            'applied_at' => $appliedAt,
            'vacancy' => [
                'id' => (int)$vacancy['id'],
                'title' => $vacancy['title'],
            ],
        ]
    ], 201);
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'Could not submit application'], 500);
}
