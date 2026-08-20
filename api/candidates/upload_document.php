<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST request required']);
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
$documentType = trim((string)($_POST['document_type'] ?? ''));

$allowedTypes = [
    'aadhaar' => 'Aadhaar Card',
    'address_proof' => 'Address Proof',
    'pan' => 'PAN Card',
    'police_verification' => 'Police Verification',
    'other' => 'Other',
];

if ($userId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Valid user_id is required']);
    exit;
}

if (!isset($allowedTypes[$documentType])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid document_type']);
    exit;
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Document file is required']);
    exit;
}

$file = $_FILES['document'];
$maxSize = 5 * 1024 * 1024;

if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxSize) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Maximum document size is 5 MB']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowedMime = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];

if (!isset($allowedMime[$mime])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Only PDF, JPG and PNG documents are allowed']);
    exit;
}

try {
    $userStmt = $pdo->prepare('SELECT id, account_status FROM users WHERE id=? AND deleted_at IS NULL LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Candidate not found']);
        exit;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/documents/' . $userId;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create document upload directory');
    }

    $extension = $allowedMime[$mime];
    $storedName = $documentType . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
    $destination = $uploadDir . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to save uploaded document');
    }

    $storagePath = 'api/uploads/documents/' . $userId . '/' . $storedName;
    $originalName = basename((string)$file['name']);

    $stmt = $pdo->prepare('INSERT INTO candidate_documents (user_id, document_type, storage_path, original_file_name, mime_type, file_size_bytes, review_status) VALUES (?,?,?,?,?,?,\'pending\')');
    $stmt->execute([$userId, $documentType, $storagePath, $originalName, $mime, (int)$file['size']]);

    echo json_encode([
        'success' => true,
        'message' => $allowedTypes[$documentType] . ' uploaded successfully and is pending verification',
        'document_id' => (int)$pdo->lastInsertId(),
        'document_type' => $documentType,
        'review_status' => 'pending'
    ]);
} catch (Throwable $e) {
    if (isset($destination) && is_file($destination)) {
        @unlink($destination);
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to upload document']);
}
