<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$mobile = trim((string)($input['mobile'] ?? ''));

if ($mobile === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Mobile number is required']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, email, mobile, account_status
         FROM users
         WHERE mobile = ? AND deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$mobile]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not registered']);
        exit;
    }

    $accountStatus = strtolower((string)$user['account_status']);
    if (in_array($accountStatus, ['blocked', 'rejected'], true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $accountStatus === 'blocked' ? 'Account is blocked' : 'Account is rejected'
        ]);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

    $pdo->beginTransaction();
    $pdo->prepare(
        'UPDATE api_tokens
         SET revoked_at = CURRENT_TIMESTAMP
         WHERE user_id = ? AND revoked_at IS NULL'
    )->execute([$user['id']]);

    $pdo->prepare(
        'INSERT INTO api_tokens (user_id, token_hash, expires_at)
         VALUES (?, ?, ?)'
    )->execute([$user['id'], $tokenHash, $expiresAt]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
            'user' => $user
        ]
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login failed']);
}
