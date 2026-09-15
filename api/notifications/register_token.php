<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/api.php';

$user = requireUser($pdo);
$userId = (int)$user['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success'=>false,'message'=>'Method not allowed'],405);
}

$data = jsonInput();
$token = trim((string)($data['token'] ?? ''));
$platform = strtolower(trim((string)($data['platform'] ?? 'android')));
$language = strtolower(trim((string)($data['language'] ?? 'en')));

if ($token === '' || strlen($token) < 20 || strlen($token) > 4096) {
    respond(['success'=>false,'message'=>'A valid FCM token is required'],422);
}
if (!in_array($platform, ['android','ios'], true)) $platform = 'android';
if (!in_array($language, ['en','hi'], true)) $language = 'en';

$hash = hash('sha256', $token);
$stmt = $pdo->prepare(
    'INSERT INTO notification_devices(user_id,fcm_token,token_hash,platform,app_language,last_seen_at)
     VALUES(?,?,?,?,?,UTC_TIMESTAMP())
     ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),fcm_token=VALUES(fcm_token),platform=VALUES(platform),app_language=VALUES(app_language),last_seen_at=UTC_TIMESTAMP()'
);
$stmt->execute([$userId,$token,$hash,$platform,$language]);

respond(['success'=>true,'message'=>'Notification device registered']);
