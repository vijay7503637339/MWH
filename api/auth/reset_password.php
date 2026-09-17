<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = jsonInput();
$email = strtolower(trim((string)($input['email'] ?? '')));
$otp = trim((string)($input['otp'] ?? ''));
$newPassword = (string)($input['new_password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['success' => false, 'message' => 'A valid email address is required'], 422);
}
if (!preg_match('/^\d{4}$/', $otp)) {
    respond(['success' => false, 'message' => 'Enter the 4-digit OTP'], 422);
}
if (strlen($newPassword) < 8) {
    respond(['success' => false, 'message' => 'Password must be at least 8 characters long'], 422);
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email)=? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        respond(['success' => false, 'message' => 'Invalid or expired OTP'], 422);
    }

    $otpStmt = $pdo->prepare("SELECT id, code_hash, expires_at, attempts, max_attempts, verified_at FROM otp_codes WHERE mobile=? AND purpose='password_reset' ORDER BY id DESC LIMIT 1");
    $otpStmt->execute([$email]);
    $otpRow = $otpStmt->fetch(PDO::FETCH_ASSOC);

    if (!$otpRow || !empty($otpRow['verified_at']) || strtotime((string)$otpRow['expires_at']) < time()) {
        respond(['success' => false, 'message' => 'Invalid or expired OTP'], 422);
    }
    if ((int)$otpRow['attempts'] >= (int)$otpRow['max_attempts']) {
        respond(['success' => false, 'message' => 'Too many OTP attempts. Please request a new OTP.'], 429);
    }

    if (!password_verify($otp, (string)$otpRow['code_hash'])) {
        $pdo->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id=?')->execute([(int)$otpRow['id']]);
        respond(['success' => false, 'message' => 'Invalid OTP'], 422);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE users SET password_hash=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND deleted_at IS NULL')->execute([$newHash, (int)$user['id']]);
    $pdo->prepare('UPDATE otp_codes SET verified_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$otpRow['id']]);
    // Revoke all existing mobile API sessions so the password reset invalidates prior access tokens.
    $pdo->prepare('UPDATE api_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE user_id=? AND revoked_at IS NULL')->execute([(int)$user['id']]);
    $pdo->commit();

    respond(['success' => true, 'message' => 'Password reset successfully. You can now sign in with your new password.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    respond(['success' => false, 'message' => 'Unable to reset password'], 500);
}
