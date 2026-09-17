<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    localJson(['success' => false, 'message' => 'POST request required'], 405);
}

$data = localInput();

try {
    $role = localRole($data['role'] ?? '');
    $mobile = localMobile($data['mobile'] ?? $data['whatsapp_mobile'] ?? '');
    $password = localPassword($data['password'] ?? '');

    $firstName = trim((string)($data['first_name'] ?? ''));
    $lastName = trim((string)($data['last_name'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));

    if ($firstName === '' || strlen($firstName) > 100) {
        throw new InvalidArgumentException('First name is required');
    }
    if ($role === 'staff' && ($lastName === '' || strlen($lastName) > 100)) {
        throw new InvalidArgumentException('Last name is required');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email address');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, password_hash, account_status FROM users WHERE mobile = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$mobile]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $pdo->rollBack();
        localJson([
            'success' => false,
            'code' => 'ACCOUNT_EXISTS',
            'message' => 'An account already exists with this mobile number. Please log in instead.',
            'data' => ['user_id' => (int)$existing['id']]
        ], 409);
    }

    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('Email is already registered');
        }
    }

    $accountStatus = 'pending_verification';
    $stmt = $pdo->prepare(
        'INSERT INTO users (mobile, email, password_hash, account_status)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $mobile,
        $email !== '' ? $email : null,
        password_hash($password, PASSWORD_DEFAULT),
        $accountStatus,
    ]);
    $userId = (int)$pdo->lastInsertId();

    if ($role === 'staff') {
        $dateOfBirth = trim((string)($data['date_of_birth'] ?? $data['dob'] ?? '')) ?: null;
        $gender = trim((string)($data['gender'] ?? '')) ?: null;
        $fullAddress = trim((string)($data['full_address'] ?? '')) ?: null;
        $city = trim((string)($data['city'] ?? $data['preferred_area'] ?? '')) ?: null;
        $postcode = trim((string)($data['postcode'] ?? '')) ?: null;
        $safeLastName = $lastName !== '' ? $lastName : 'Staff';

        $stmt = $pdo->prepare(
            'INSERT INTO candidate_profiles
             (user_id, first_name, last_name, date_of_birth, gender, full_address, city, country, postcode)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $firstName, $safeLastName, $dateOfBirth, $gender, $fullAddress, $city, 'India', $postcode]);

        $experience = (float)($data['experience_years'] ?? $data['experience'] ?? 0);
        if ($experience < 0 || $experience > 99.9) {
            throw new InvalidArgumentException('Invalid experience_years');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO professional_details (user_id, experience_years, previous_company_name)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $experience, trim((string)($data['previous_company_name'] ?? '')) ?: null]);

        $categoryId = (int)($data['category_id'] ?? 0);
        $jobRoleId = (int)($data['job_role_id'] ?? 0);
        if ($categoryId > 0 && $jobRoleId > 0) {
            $roleCheck = $pdo->prepare(
                'SELECT id FROM job_roles
                 WHERE id = ? AND category_id = ? AND is_active = 1 AND deleted_at IS NULL
                 LIMIT 1'
            );
            $roleCheck->execute([$jobRoleId, $categoryId]);
            if (!$roleCheck->fetch()) {
                throw new InvalidArgumentException('Selected job role is not valid for the selected category');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO job_preferences
                 (user_id, category_id, job_role_id, max_travel_distance_km, preferred_working_hours)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $categoryId,
                $jobRoleId,
                isset($data['max_travel_distance_km']) ? (float)$data['max_travel_distance_km'] : null,
                isset($data['preferred_working_hours']) ? (float)$data['preferred_working_hours'] : null,
            ]);
        }
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO contractor_profiles
             (user_id, business_name, business_type, whatsapp_mobile, full_address, city, state, postcode, gstin)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            trim((string)($data['business_name'] ?? '')) ?: null,
            trim((string)($data['business_type'] ?? '')) ?: null,
            $mobile,
            trim((string)($data['full_address'] ?? '')) ?: null,
            trim((string)($data['city'] ?? '')) ?: null,
            trim((string)($data['state'] ?? '')) ?: null,
            trim((string)($data['postcode'] ?? '')) ?: null,
            trim((string)($data['gstin'] ?? '')) ?: null,
        ]);
    }

    // Role membership is represented by the corresponding profile row. Do not use user_app_access.
    $pdo->commit();

    localJson([
        'success' => true,
        'message' => 'Account created and submitted for verification',
        'data' => [
            'user_id' => $userId,
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
