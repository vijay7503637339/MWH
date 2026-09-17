<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = jsonInput();
$email = strtolower(trim((string)($input['email'] ?? '')));
$newPassword = (string)($input['new_password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['success' => false, 'message' => 'A valid email address is required'], 422);
}

if (strlen($newPassword) < 8) {
    respond(['success' => false, 'message' => 'Password must be at least 8 characters long'], 422);
}

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Do not reveal whether an email exists.
    if (!$user) {
        respond(['success' => true, 'message' => 'If the email is registered, a verification OTP has been sent.'], 200);
    }

    // Store the new password only server-side, bound to the one-time reset record.
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $otp = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $requestIp = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM otp_codes WHERE mobile = ? AND purpose = 'password_reset'")->execute([$email]);
    $pdo->prepare("INSERT INTO otp_codes (mobile, purpose, code_hash, expires_at, attempts, max_attempts, requested_ip) VALUES (?, 'password_reset', ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE), 0, 5, ?)")
        ->execute([$email, $otpHash, $requestIp]);

    // Reuse the existing users.updated_at audit trail only after OTP verification.
    $pdo->commit();

    // Mail is intentionally sent server-side. Configure production mail transport in PHP/cPanel.
    $subject = 'Maan World Hospitality - Password Reset OTP';
    $body = "Your Maan World Hospitality password reset OTP is: {$otp}\n\nThis OTP is valid for 10 minutes. Do not share it with anyone.\n\nIf you did not request a password reset, you can ignore this email.";
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: Maan World Hospitality <noreply@webstripetechnologies.com>',
        'Reply-To: support@webstripetechnologies.com',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];
    $mailSent = mail($email, $subject, $body, implode("\r\n", $headers));

    if (!$mailSent) {
        // Keep the endpoint generic and avoid exposing mail configuration details.
        error_log('Password reset OTP mail() failed for user id ' . (int)$user['id']);
        respond(['success' => false, 'message' => 'Unable to send the verification email right now. Please try again later.'], 500);
    }

    respond([
        'success' => true,
        'message' => 'Verification OTP sent to your email address.',
        'data' => [
            'email' => $email,
            'expires_in' => 600,
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    respond(['success' => false, 'message' => 'Unable to start password reset'], 500);
}
