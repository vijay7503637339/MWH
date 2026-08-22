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

// Temporary development OTP. Replace with an SMS provider before production.
$otp = '111111';

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE mobile = ? LIMIT 1');
    $stmt->execute([$mobile]);
    $user = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'OTP sent successfully',
        'data' => [
            'mobile' => $mobile,
            'otp' => $otp,
            'is_registered' => (bool)$user,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to send OTP']);
}
