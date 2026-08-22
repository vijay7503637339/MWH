<?php

declare(strict_types=1);

// Public hotel image stream. Static .jpg under /api/uploads is blocked by the host.
require_once __DIR__ . '/../config/database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    exit('id is required');
}

$stmt = $pdo->prepare(
    'SELECT id, storage_path, original_file_name
     FROM hotel_images
     WHERE id = ?
     LIMIT 1'
);
$stmt->execute([$id]);
$image = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$image) {
    http_response_code(404);
    exit('Image not found');
}

$baseDir = realpath(__DIR__ . '/../uploads/hotels');
if ($baseDir === false || !is_dir($baseDir)) {
    http_response_code(404);
    exit('Image storage unavailable');
}

$filename = basename((string)$image['storage_path']);
if ($filename === '' || !preg_match('/^hotel_[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$/i', $filename)) {
    http_response_code(403);
    exit('Invalid image path');
}

$filePath = realpath($baseDir . DIRECTORY_SEPARATOR . $filename);
$allowedBase = $baseDir . DIRECTORY_SEPARATOR;
if ($filePath === false || !is_file($filePath) || !str_starts_with($filePath, $allowedBase)) {
    http_response_code(404);
    exit('Image file not found');
}

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = match ($extension) {
    'png' => 'image/png',
    'webp' => 'image/webp',
    default => 'image/jpeg',
};

$downloadName = basename((string)($image['original_file_name'] ?? $filename));
$downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) ?: $filename;

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($filePath));
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
readfile($filePath);
exit;
