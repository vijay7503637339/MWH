<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = jsonInput();
$email = strtolower(trim((string)($data['email'] ?? '')));
$password = (string)($data['password'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['success' => false, 'message' => 'A valid email address is required'], 422);
}
if ($password === '') {
    respond(['success' => false, 'message' => 'Password is required'], 422);
}

$stmt = $pdo->prepare(
    'SELECT u.id, u.mobile, u.email, u.password_hash, u.account_status,
            cp.first_name, cp.last_name
     FROM users u
     LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
     WHERE LOWER(u.email) = LOWER(?) AND u.deleted_at IS NULL
     LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
    respond(['success' => false, 'message' => 'Invalid email or password'], 401);
}

if ($user['account_status'] === 'blocked') {
    respond(['success' => false, 'message' => 'Account is blocked'], 403);
}
if ($user['account_status'] === 'rejected') {
    respond(['success' => false, 'message' => 'Account is rejected'], 403);
}
if ($user['account_status'] === 'pending_verification') {
    respond([
        'success' => false,
        'message' => 'Your account is pending admin verification. You can login after your account has been verified.',
        'account_status' => $user['account_status'],
    ], 403);
}

$token = issueToken($pdo, (int)$user['id']);
$pdo->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$user['id']]);
unset($user['password_hash']);

respond([
    'success' => true,
    'message' => 'Login successful',
    'data' => [
        'token' => $token,
        'user' => [
            'id' => (int)$user['id'],
            'mobile' => $user['mobile'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'account_status' => $user['account_status'],
        ],
    ],
]);
