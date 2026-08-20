<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GET request required']);
    exit;
}

$userId = (int)($_GET['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Valid user_id is required']);
    exit;
}

try {
    $userStmt = $pdo->prepare('SELECT id FROM users WHERE id=? AND deleted_at IS NULL LIMIT 1');
    $userStmt->execute([$userId]);

    if (!$userStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Candidate not found']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, document_type, original_file_name, mime_type, file_size_bytes, review_status, rejection_reason, reviewed_at, created_at FROM candidate_documents WHERE user_id=? AND deleted_at IS NULL ORDER BY created_at DESC');
    $stmt->execute([$userId]);

    $documents = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'documents' => $documents
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load documents']);
}
