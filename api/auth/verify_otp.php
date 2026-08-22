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
$otp = trim((string)($input['otp'] ?? ''));

if ($mobile === '' || $otp === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Mobile number and OTP are required']);
    exit;
}

// Temporary development OTP. Replace with real OTP storage/provider before production.
if ($otp !== '111111') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid OTP']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, name, email, mobile, status FROM users WHERE mobile = ? LIMIT 1');
    $stmt->execute([$mobile]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode([
            'success' => true,
            'message' => 'OTP verified. User is not registered.',
            'data' => ['is_registered' => false, 'mobile' => $mobile],
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'OTP verified successfully',
        'data' => [
            'is_registered' => true,
            'user' => $user,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'OTP verification failed']);
}
