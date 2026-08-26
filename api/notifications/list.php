<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/api.php';

$user = requireUser($pdo);
$userId = (int)$user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT id,type,title,message,data_json,read_at,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unread = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL');
    $unread->execute([$userId]);

    $items = array_map(static function (array $item): array {
        $data = null;
        if (!empty($item['data_json'])) {
            $decoded = json_decode((string)$item['data_json'], true);
            if (is_array($decoded)) $data = $decoded;
        }
        $item['data'] = $data;
        unset($item['data_json']);
        $item['is_read'] = $item['read_at'] !== null;
        return $item;
    }, $items);

    respond(['success'=>true,'data'=>$items,'unread_count'=>(int)$unread->fetchColumn()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = jsonInput();
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) respond(['success'=>false,'message'=>'id is required'],422);
    $stmt = $pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,UTC_TIMESTAMP()) WHERE id=? AND user_id=?');
    $stmt->execute([$id,$userId]);
    respond(['success'=>true,'message'=>'Notification marked as read']);
}

respond(['success'=>false,'message'=>'Method not allowed'],405);
