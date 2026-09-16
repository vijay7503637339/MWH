<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

function localJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function localInput(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

function localMobile(mixed $value): string
{
    $mobile = preg_replace('/\D+/', '', (string)$value);
    if (strlen($mobile) === 12 && str_starts_with($mobile, '91')) {
        $mobile = substr($mobile, 2);
    }
    if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        throw new InvalidArgumentException('A valid 10-digit Indian mobile number is required');
    }
    return $mobile;
}

function localPassword(mixed $value): string
{
    $password = (string)$value;
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Password must be at least 8 characters');
    }
    return $password;
}

function localRole(mixed $value): string
{
    $role = strtolower(trim((string)$value));
    $aliases = [
        'chef' => 'staff',
        'cooking_staff' => 'staff',
        'cooking staff' => 'staff',
        'contractor' => 'contractor',
        'caterer' => 'contractor',
        'agency' => 'contractor',
    ];
    $role = $aliases[$role] ?? $role;
    if (!in_array($role, ['staff', 'contractor'], true)) {
        throw new InvalidArgumentException('role must be staff or contractor');
    }
    return $role;
}

function localIssueToken(PDO $pdo, int $userId): array
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

    $pdo->prepare(
        'UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP
         WHERE user_id = ? AND revoked_at IS NULL'
    )->execute([$userId]);

    $pdo->prepare(
        'INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
    )->execute([$userId, $tokenHash, $expiresAt]);

    return [
        'token' => $token,
        'token_type' => 'Bearer',
        'expires_at' => $expiresAt,
    ];
}

function localEnsureAccess(PDO $pdo, int $userId, string $role, string $sourceApp = 'mwh_hospitality'): void
{
    $pdo->prepare(
        'INSERT IGNORE INTO user_app_access (user_id, app_key, role_key, status, source_app)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$userId, 'mwh_local', $role, 'active', $sourceApp]);
}

function localUser(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.mobile, u.email, u.account_status,
                cp.first_name, cp.last_name,
                cp.city,
                ctp.business_name, ctp.business_type
         FROM users u
         LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
         LEFT JOIN contractor_profiles ctp ON ctp.user_id = u.id
         WHERE u.id = ? AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
