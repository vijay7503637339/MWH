<?php

declare(strict_types=1);

// Protected document viewer for the admin panel.
// Candidate documents remain blocked by api/uploads/documents/.htaccess.
require_once __DIR__ . '/../../admin/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$documentId = (int)($_GET['id'] ?? 0);
if ($documentId <= 0) {
    http_response_code(400);
    exit('Invalid document.');
}

$stmt = $pdo->prepare('SELECT id, user_id, storage_path, original_file_name, mime_type FROM candidate_documents WHERE id=? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$documentId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

$baseDir = realpath(__DIR__ . '/../uploads/documents');
if ($baseDir === false) {
    http_response_code(404);
    exit('Document storage unavailable.');
}

$relativePath = ltrim((string)$document['storage_path'], '/');
if ($relativePath === '' || str_contains($relativePath, "\0")) {
    http_response_code(404);
    exit('Invalid document path.');
}

// storage_path is expected to be uploads/documents/<user_id>/<filename>.
$expectedPrefix = 'uploads/documents/' . (int)$document['user_id'] . '/';
if (!str_starts_with($relativePath, $expectedPrefix)) {
    http_response_code(403);
    exit('Forbidden.');
}

$relativeFile = substr($relativePath, strlen($expectedPrefix));
if ($relativeFile === '' || str_contains($relativeFile, '/') || str_contains($relativeFile, '\\')) {
    http_response_code(403);
    exit('Forbidden.');
}

$filePath = realpath($baseDir . '/' . (int)$document['user_id'] . '/' . $relativeFile);
if ($filePath === false || !is_file($filePath)) {
    http_response_code(404);
    exit('Document file not found.');
}

$allowedBase = $baseDir . DIRECTORY_SEPARATOR . (int)$document['user_id'] . DIRECTORY_SEPARATOR;
if (!str_starts_with($filePath, $allowedBase)) {
    http_response_code(403);
    exit('Forbidden.');
}

$mime = (string)($document['mime_type'] ?? 'application/octet-stream');
$allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
if (!in_array($mime, $allowedMime, true)) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($filePath);
}
if (!in_array($mime, $allowedMime, true)) {
    http_response_code(415);
    exit('Unsupported document type.');
}

$downloadName = basename((string)($document['original_file_name'] ?? 'document'));
$downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) ?: 'document';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($filePath));
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($filePath);
exit;
