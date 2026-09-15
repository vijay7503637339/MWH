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
$email = strtolower(trim((string)($input['email'] ?? '')));
$password = (string)($input['password'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid email address is required']);
    exit;
}

if ($password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT u.id, u.email, u.mobile, u.password_hash, u.account_status,
                cp.first_name, cp.last_name
         FROM users u
         LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
         WHERE LOWER(u.email) = LOWER(?) AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }

    $accountStatus = strtolower(trim((string)$user['account_status']));

    if ($accountStatus === 'pending_verification') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Your account is pending admin verification. You can login after your account has been verified.'
        ]);
        exit;
    }

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

    $pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$user['id']]);
    $pdo->commit();

    unset($user['password_hash']);

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
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login failed']);
}
