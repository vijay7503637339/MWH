<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$attendanceId = (int)($_GET['id'] ?? 0);

if ($attendanceId <= 0) {
    http_response_code(400);
    exit('Invalid attendance record.');
}

$stmt = $pdo->prepare(
    'SELECT check_in_photo_path
     FROM attendance_records
     WHERE id=?
     LIMIT 1'
);
$stmt->execute([$attendanceId]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record || empty($record['check_in_photo_path'])) {
    http_response_code(404);
    exit('Check-in photo not found.');
}

$storedPath = trim((string)$record['check_in_photo_path']);

if ($storedPath === '' || str_contains($storedPath, '..') || str_contains($storedPath, '\\')) {
    http_response_code(404);
    exit('Check-in photo not found.');
}

$apiRoot = realpath(__DIR__ . '/../api');
$checkinRoot = realpath(__DIR__ . '/../api/uploads/checkins');

if ($apiRoot === false || $checkinRoot === false) {
    http_response_code(500);
    exit('Check-in photo storage is unavailable.');
}

$realFile = realpath($storedPath);

if ($realFile === false || !is_file($realFile)) {
    // Handle paths stored relative to the API directory as well as absolute paths.
    $realFile = realpath($apiRoot . '/' . ltrim($storedPath, '/'));
}

$allowedRoot = rtrim($checkinRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

if (
    $realFile === false ||
    !is_file($realFile) ||
    !str_starts_with($realFile, $allowedRoot)
) {
    http_response_code(404);
    exit('Check-in photo not found.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($realFile);

if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit('Unsupported check-in photo type.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($realFile));
header('Content-Disposition: inline; filename="checkin-photo-' . $attendanceId . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');

readfile($realFile);
exit;
