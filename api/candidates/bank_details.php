<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';

$u = requireUser($pdo);

function paymentProfileColumns(PDO $pdo): array
{
    $stmt = $pdo->query('SHOW COLUMNS FROM candidate_payment_profiles');
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[(string)$column['Field']] = true;
    }
    return $columns;
}

$method = $_SERVER['REQUEST_METHOD'];
$userId = (int)$u['user_id'];

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT payment_type, account_holder_name, bank_name, ifsc_code, branch_name, upi_id, account_number_encrypted FROM candidate_payment_profiles WHERE user_id=? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        respond(['success' => true, 'data' => null]);
    }

    $account = (string)($row['account_number_encrypted'] ?? '');
    unset($row['account_number_encrypted']);
    $row['account_number'] = $account !== '' ? '****' . substr($account, -4) : null;
    respond(['success' => true, 'data' => $row]);
}

if ($method === 'PUT' || $method === 'POST' || $method === 'PATCH') {
    $d = jsonInput();
    $paymentType = strtolower(trim((string)($d['payment_type'] ?? 'bank')));
    if (!in_array($paymentType, ['bank', 'upi'], true)) {
        respond(['success' => false, 'message' => 'Invalid payment type'], 422);
    }

    $holder = trim((string)($d['account_holder_name'] ?? ''));
    $bank = trim((string)($d['bank_name'] ?? ''));
    $ifsc = strtoupper(trim((string)($d['ifsc_code'] ?? '')));
    $branch = trim((string)($d['branch_name'] ?? ''));
    $upi = trim((string)($d['upi_id'] ?? ''));
    $account = trim((string)($d['account_number'] ?? ''));

    if ($holder === '') respond(['success' => false, 'message' => 'Account holder name is required'], 422);

    if ($paymentType === 'bank') {
        if ($bank === '') respond(['success' => false, 'message' => 'Bank name is required'], 422);
        if ($account === '') {
            $existing = $pdo->prepare('SELECT account_number_encrypted FROM candidate_payment_profiles WHERE user_id=? LIMIT 1');
            $existing->execute([$userId]);
            $account = (string)($existing->fetchColumn() ?: '');
        }
        if ($account === '') respond(['success' => false, 'message' => 'Account number is required'], 422);
        if (!preg_match('/^[0-9]{6,30}$/', $account)) respond(['success' => false, 'message' => 'Invalid account number'], 422);
        if ($ifsc !== '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) respond(['success' => false, 'message' => 'Invalid IFSC code'], 422);
    } else {
        if ($upi === '' || !preg_match('/^[A-Za-z0-9._-]{2,}@[A-Za-z0-9._-]{2,}$/', $upi)) {
            respond(['success' => false, 'message' => 'Valid UPI ID is required'], 422);
        }
    }

    try {
        $pdo->beginTransaction();
        $exists = $pdo->prepare('SELECT id, account_number_encrypted FROM candidate_payment_profiles WHERE user_id=? LIMIT 1');
        $exists->execute([$userId]);
        $existing = $exists->fetch(PDO::FETCH_ASSOC);

        $accountValue = $paymentType === 'bank' ? $account : null;
        if ($existing) {
            $sql = 'UPDATE candidate_payment_profiles SET payment_type=?, account_holder_name=?, bank_name=?, account_number_encrypted=?, ifsc_code=?, branch_name=?, upi_id=? WHERE user_id=?';
            $pdo->prepare($sql)->execute([$paymentType, $holder, $bank !== '' ? $bank : null, $accountValue, $ifsc !== '' ? $ifsc : null, $branch !== '' ? $branch : null, $upi !== '' ? $upi : null, $userId]);
        } else {
            $sql = 'INSERT INTO candidate_payment_profiles (user_id,payment_type,account_holder_name,bank_name,account_number_encrypted,ifsc_code,branch_name,upi_id) VALUES (?,?,?,?,?,?,?,?)';
            $pdo->prepare($sql)->execute([$userId, $paymentType, $holder, $bank !== '' ? $bank : null, $accountValue, $ifsc !== '' ? $ifsc : null, $branch !== '' ? $branch : null, $upi !== '' ? $upi : null]);
        }
        $pdo->commit();
        respond(['success' => true, 'message' => 'Bank details updated successfully']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['success' => false, 'message' => 'Unable to update bank details'], 500);
    }
}

respond(['success' => false, 'message' => 'Method not allowed'], 405);
