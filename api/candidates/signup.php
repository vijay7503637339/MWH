<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST request required']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

function inputData(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : $_POST;
}

function requiredString(array $data, string $key, int $max = 255): string
{
    $value = trim((string)($data[$key] ?? ''));
    if ($value === '' || strlen($value) > $max) {
        throw new InvalidArgumentException("{$key} is required");
    }
    return $value;
}

$data = inputData();

try {
    $mobile = requiredString($data, 'mobile', 20);
    if (!preg_match('/^[0-9+()\- ]{8,20}$/', $mobile)) {
        throw new InvalidArgumentException('Invalid mobile number');
    }

    $email = trim((string)($data['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email address');
    }

    $firstName = requiredString($data, 'first_name', 100);
    $lastName = requiredString($data, 'last_name', 100);
    $dateOfBirth = trim((string)($data['date_of_birth'] ?? '')) ?: null;
    $gender = trim((string)($data['gender'] ?? '')) ?: null;
    $fullAddress = trim((string)($data['full_address'] ?? '')) ?: null;
    $city = trim((string)($data['city'] ?? '')) ?: null;
    $postcode = trim((string)($data['postcode'] ?? '')) ?: null;

    $experienceYears = (float)($data['experience_years'] ?? 0);
    if ($experienceYears < 0 || $experienceYears > 99.9) {
        throw new InvalidArgumentException('Invalid experience_years');
    }

    $categoryId = (int)($data['category_id'] ?? 0);
    $jobRoleId = (int)($data['job_role_id'] ?? 0);
    if ($categoryId <= 0 || $jobRoleId <= 0) {
        throw new InvalidArgumentException('category_id and job_role_id are required');
    }

    $paymentType = trim((string)($data['payment_type'] ?? ''));
    if (!in_array($paymentType, ['bank', 'upi'], true)) {
        throw new InvalidArgumentException('payment_type must be bank or upi');
    }

    $pdo->beginTransaction();

    $check = $pdo->prepare('SELECT id, account_status FROM users WHERE mobile = ? LIMIT 1');
    $check->execute([$mobile]);
    if ($check->fetch()) {
        throw new InvalidArgumentException('Mobile number is already registered');
    }

    if ($email !== '') {
        $checkEmail = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch()) {
            throw new InvalidArgumentException('Email is already registered');
        }
    }

    $roleCheck = $pdo->prepare('SELECT id FROM job_roles WHERE id = ? AND category_id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1');
    $roleCheck->execute([$jobRoleId, $categoryId]);
    if (!$roleCheck->fetch()) {
        throw new InvalidArgumentException('Selected job role is not valid for the selected category');
    }

    $stmt = $pdo->prepare('INSERT INTO users (mobile,email,account_status) VALUES (?,?,?)');
    $stmt->execute([$mobile, $email !== '' ? $email : null, 'pending_verification']);
    $userId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('INSERT INTO candidate_profiles (user_id,first_name,last_name,date_of_birth,gender,full_address,city,country,postcode) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$userId,$firstName,$lastName,$dateOfBirth,$gender,$fullAddress,$city,'India',$postcode]);

    $previousCompany = trim((string)($data['previous_company_name'] ?? '')) ?: null;
    $stmt = $pdo->prepare('INSERT INTO professional_details (user_id,experience_years,previous_company_name) VALUES (?,?,?)');
    $stmt->execute([$userId,$experienceYears,$previousCompany]);

    $travelDistance = isset($data['max_travel_distance_km']) ? (float)$data['max_travel_distance_km'] : null;
    $workingHours = isset($data['preferred_working_hours']) ? (float)$data['preferred_working_hours'] : null;
    $stmt = $pdo->prepare('INSERT INTO job_preferences (user_id,category_id,job_role_id,max_travel_distance_km,preferred_working_hours) VALUES (?,?,?,?,?)');
    $stmt->execute([$userId,$categoryId,$jobRoleId,$travelDistance,$workingHours]);

    $accountNumber = trim((string)($data['account_number'] ?? '')) ?: null;
    $upiId = trim((string)($data['upi_id'] ?? '')) ?: null;
    $ifsc = trim((string)($data['ifsc_code'] ?? '')) ?: null;
    $bankName = trim((string)($data['bank_name'] ?? '')) ?: null;
    $accountHolder = trim((string)($data['account_holder_name'] ?? '')) ?: null;
    $branchName = trim((string)($data['branch_name'] ?? '')) ?: null;

    if ($paymentType === 'bank' && ($accountNumber === null || $ifsc === null)) {
        throw new InvalidArgumentException('Bank account number and IFSC are required');
    }
    if ($paymentType === 'upi' && $upiId === null) {
        throw new InvalidArgumentException('UPI ID is required');
    }

    $encryptedAccount = $accountNumber !== null ? encryptSensitive($accountNumber) : null;
    $stmt = $pdo->prepare('INSERT INTO candidate_payment_profiles (user_id,payment_type,account_holder_name,bank_name,account_number_encrypted,ifsc_code,branch_name,upi_id) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$userId,$paymentType,$accountHolder,$bankName,$encryptedAccount,$ifsc,$branchName,$upiId]);

    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Candidate signup submitted for verification',
        'user_id' => $userId,
        'account_status' => 'pending_verification'
    ]);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to complete signup']);
}
