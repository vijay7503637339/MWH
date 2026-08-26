<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/api.php';

$user = requireUser($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$stmt = $pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,UTC_TIMESTAMP()) WHERE user_id=? AND read_at IS NULL');
$stmt->execute([(int)$user['user_id']]);

respond([
    'success' => true,
    'message' => 'All notifications marked as read',
    'updated' => $stmt->rowCount(),
]);
