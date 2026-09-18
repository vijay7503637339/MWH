<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$documentId = (int)($_GET['id'] ?? 0);

if ($documentId <= 0) {
    http_response_code(400);
    exit('Invalid document.');
}

$stmt = $pdo->prepare(
    'SELECT id, storage_path, original_file_name, mime_type
     FROM candidate_documents
     WHERE id=? AND deleted_at IS NULL
     LIMIT 1'
);
$stmt->execute([$documentId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

$storagePath = trim((string)($document['storage_path'] ?? ''));

if (
    $storagePath === '' ||
    str_contains($storagePath, '..') ||
    str_contains($storagePath, '\\\\') ||
    str_starts_with($storagePath, '/') ||
    str_starts_with($storagePath, '\\')
) {
    http_response_code(404);
    exit('Document not found.');
}

$apiRoot = realpath(__DIR__ . '/../api');
if ($apiRoot === false) {
    http_response_code(500);
    exit('Document storage is unavailable.');
}

$realFile = realpath($apiRoot . '/' . ltrim($storagePath, '/'));
$allowedRoot = rtrim($apiRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

if (
    $realFile === false ||
    !is_file($realFile) ||
    !str_starts_with($realFile, $allowedRoot)
) {
    http_response_code(404);
    exit('Document not found.');
}

$mime = (string)($document['mime_type'] ?? '');
if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($realFile);
}

if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    http_response_code(415);
    exit('Unsupported document type.');
}

$fileName = basename((string)($document['original_file_name'] ?? 'document'));
$fileName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $fileName) ?: 'document';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($realFile));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $fileName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');

readfile($realFile);
exit;
