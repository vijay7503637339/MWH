<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    localJson(['success' => false, 'message' => 'POST request required'], 405);
}

$data = localInput();

try {
    $role = localRole($data['role'] ?? '');
    $name = trim((string)($data['name'] ?? ''));
    $mobile = localMobile($data['mobile'] ?? $data['whatsapp_mobile'] ?? '');
    $password = localPassword($data['password'] ?? '');
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $aadhaar = preg_replace('/\D+/', '', (string)($data['aadhaar_number'] ?? ''));

    if ($name === '' || strlen($name) > 150) {
        throw new InvalidArgumentException('Name is required (maximum 150 characters)');
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150)) {
        throw new InvalidArgumentException('Invalid email address');
    }
    if ($aadhaar !== '' && !preg_match('/^\d{12}$/', $aadhaar)) {
        throw new InvalidArgumentException('Aadhaar number must contain 12 digits');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT id FROM local_users WHERE mobile = ? AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([$mobile]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $pdo->rollBack();
        localJson([
            'success' => false,
            'code' => 'ACCOUNT_EXISTS',
            'message' => 'An account already exists with this mobile number. Please log in instead.',
            'data' => ['user_id' => (int)$existing['id']],
        ], 409);
    }

    if ($email !== '') {
        $stmt = $pdo->prepare(
            'SELECT id FROM local_users WHERE email = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$email]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new InvalidArgumentException('Email is already registered');
        }
    }

    $accountStatus = 'pending_verification';
    $stmt = $pdo->prepare(
        'INSERT INTO local_users
            (name, mobile, email, aadhaar_number, role, password_hash, account_status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $name,
        $mobile,
        $email !== '' ? $email : null,
        $aadhaar !== '' ? $aadhaar : null,
        $role,
        password_hash($password, PASSWORD_DEFAULT),
        $accountStatus,
    ]);

    $userId = (int)$pdo->lastInsertId();
    $pdo->commit();

    localJson([
        'success' => true,
        'message' => 'Account created and submitted for verification',
        'data' => [
            'user_id' => $userId,
            'name' => $name,
            'mobile' => $mobile,
            'email' => $email !== '' ? $email : null,
            'role' => $role,
            'account_status' => $accountStatus,
        ],
    ], 201);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    localJson(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    localJson(['success' => false, 'message' => 'Unable to complete signup'], 500);
}
