<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/database.php';

function jsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : $_POST;
}
function respond(array $payload, int $status = 200): never {
    http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit;
}
function bearerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) return trim($m[1]);
    return null;
}
function requireUser(PDO $pdo): array {
    $token = bearerToken();
    if (!$token) respond(['success'=>false,'message'=>'Authentication required'],401);
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT t.user_id,u.mobile,u.email,u.account_status FROM api_tokens t INNER JOIN users u ON u.id=t.user_id WHERE t.token_hash=? AND t.expires_at>UTC_TIMESTAMP() AND t.revoked_at IS NULL AND u.deleted_at IS NULL LIMIT 1');
    $stmt->execute([$hash]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) respond(['success'=>false,'message'=>'Invalid or expired token'],401);
    return $user;
}
function issueToken(PDO $pdo, int $userId): string {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256',$token);
    $stmt=$pdo->prepare('INSERT INTO api_tokens(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY))');
    $stmt->execute([$userId,$hash]); return $token;
}

function publicUploadUrl(string $storagePath): string {
    $storagePath = ltrim($storagePath, '/');
    return 'https://webstripetechnologies.com/MWH/api/' . $storagePath;
}

function mapHotelImages(array $images): array {
    return array_map(static function (array $image): array {
        $storagePath = ltrim((string)$image['storage_path'], '/');
        return [
            'id' => (int)$image['id'],
            'storage_path' => $storagePath,
            'url' => publicUploadUrl($storagePath),
            'original_file_name' => $image['original_file_name'],
            'is_primary' => (bool)$image['is_primary'],
            'sort_order' => (int)$image['sort_order'],
        ];
    }, $images);
}
