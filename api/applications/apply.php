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
    // Load the vacancy by ID only. Allow applications even when the vacancy
    // is unpublished, past its closing date, soft-deleted, or its hotel is inactive.
    $stmt = $pdo->prepare(
        "SELECT v.id, v.title
         FROM vacancies v
         WHERE v.id = ?
         LIMIT 1"
    );
    $stmt->execute([$vacancyId]);
    $vacancy = $stmt->fetch(PDO::FETCH_ASSOC);

    // A real vacancy row is still required because applications.vacancy_id
    // references vacancies.id.
    if (!$vacancy) {
        respond(['success' => false, 'message' => 'Job record not found'], 404);
    }

    // Duplicate applications are allowed. Each submission creates a new row.
    $insert = $pdo->prepare(
        "INSERT INTO applications (vacancy_id, user_id, status)
         VALUES (?, ?, 'applied')"
    );
    $insert->execute([$vacancyId, (int)$user['user_id']]);

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
