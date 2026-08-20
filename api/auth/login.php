<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT au.id, au.name, au.email, au.password_hash, au.status, ar.name AS role_name
     FROM admin_users au
     INNER JOIN admin_roles ar ON ar.id = au.role_id
     WHERE au.email = :email AND au.deleted_at IS NULL
     LIMIT 1'
);
$stmt->execute(['email' => $email]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    exit;
}

if ($admin['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin account is not active']);
    exit;
}

session_regenerate_id(true);
$_SESSION['admin_id'] = (int)$admin['id'];
$_SESSION['admin_name'] = $admin['name'];
$_SESSION['admin_email'] = $admin['email'];
$_SESSION['admin_role'] = $admin['role_name'];

$update = $pdo->prepare('UPDATE admin_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
$update->execute(['id' => $admin['id']]);

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'admin' => [
        'id' => (int)$admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => $admin['role_name']
    ]
]);
