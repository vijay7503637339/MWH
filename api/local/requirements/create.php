<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    localJson(['success' => false, 'message' => 'POST request required'], 405);
}

function localAuthUserId(PDO $pdo): int
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        localJson(['success' => false, 'message' => 'Authorization token required'], 401);
    }

    $tokenHash = hash('sha256', trim($matches[1]));
    $stmt = $pdo->prepare(
        'SELECT user_id FROM api_tokens
         WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > CURRENT_TIMESTAMP
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        localJson(['success' => false, 'message' => 'Invalid or expired authorization token'], 401);
    }

    return (int)$row['user_id'];
}

$data = localInput();

try {
    $userId = localAuthUserId($pdo);

    $accessStmt = $pdo->prepare(
        'SELECT 1 FROM user_app_access
         WHERE user_id = ? AND app_key = ? AND role_key = ? AND status = ?
         LIMIT 1'
    );
    $accessStmt->execute([$userId, 'mwh_local', 'contractor', 'active']);
    if (!$accessStmt->fetchColumn()) {
        localJson(['success' => false, 'message' => 'Contractor access is not enabled for this account'], 403);
    }

    $title = trim((string)($data['title'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $staffRole = trim((string)($data['staff_role'] ?? $data['role_name'] ?? ''));
    $categoryId = (int)($data['category_id'] ?? 0);
    $jobRoleId = (int)($data['job_role_id'] ?? 0);
    $headcount = (int)($data['headcount'] ?? $data['staff_count'] ?? $data['count'] ?? 0);
    $location = trim((string)($data['location'] ?? $data['work_location'] ?? ''));
    $shiftDate = trim((string)($data['shift_date'] ?? $data['date'] ?? ''));
    $shiftStart = trim((string)($data['shift_start'] ?? $data['start_time'] ?? ''));
    $shiftEnd = trim((string)($data['shift_end'] ?? $data['end_time'] ?? ''));
    $experience = trim((string)($data['minimum_experience'] ?? $data['experience'] ?? ''));
    $payout = (float)($data['payout_amount'] ?? $data['pay'] ?? 0);
    $payoutPeriod = trim((string)($data['payout_period'] ?? 'per_shift'));
    $address = trim((string)($data['address'] ?? ''));
    $notes = trim((string)($data['notes'] ?? ''));

    if ($staffRole === '' && $jobRoleId <= 0) {
        throw new InvalidArgumentException('Staff role is required');
    }
    if ($headcount < 1 || $headcount > 500) {
        throw new InvalidArgumentException('Headcount must be between 1 and 500');
    }
    if ($location === '') {
        throw new InvalidArgumentException('Work location is required');
    }
    if ($shiftDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $shiftDate)) {
        throw new InvalidArgumentException('shift_date must use YYYY-MM-DD format');
    }
    if ($payout <= 0) {
        throw new InvalidArgumentException('Payout amount must be greater than zero');
    }
    if ($title === '') {
        $title = $headcount . ' × ' . ($staffRole !== '' ? $staffRole : 'Kitchen Staff');
    }
    if (strlen($title) > 190) {
        throw new InvalidArgumentException('Title is too long');
    }

    $roleName = $staffRole;
    if ($jobRoleId > 0) {
        $roleStmt = $pdo->prepare(
            'SELECT jr.name, jr.category_id
             FROM job_roles jr
             WHERE jr.id = ? AND jr.is_active = 1 AND jr.deleted_at IS NULL
             LIMIT 1'
        );
        $roleStmt->execute([$jobRoleId]);
        $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
        if (!$roleRow) {
            throw new InvalidArgumentException('Selected job role is invalid');
        }
        if ($categoryId > 0 && (int)$roleRow['category_id'] !== $categoryId) {
            throw new InvalidArgumentException('Selected job role does not match the selected category');
        }
        $categoryId = (int)$roleRow['category_id'];
        $roleName = $roleName !== '' ? $roleName : (string)$roleRow['name'];
    }

    $descriptionParts = [];
    if ($roleName !== '') {
        $descriptionParts[] = 'Staff role: ' . $roleName;
    }
    if ($shiftStart !== '' || $shiftEnd !== '') {
        $descriptionParts[] = 'Shift: ' . trim($shiftStart . ' - ' . $shiftEnd, ' -');
    }
    if ($experience !== '') {
        $descriptionParts[] = 'Minimum experience: ' . $experience;
    }
    if ($address !== '') {
        $descriptionParts[] = 'Address: ' . $address;
    }
    if ($notes !== '') {
        $descriptionParts[] = $notes;
    }
    $fullDescription = $description !== ''
        ? $description . (count($descriptionParts) ? "\n\n" . implode("\n", $descriptionParts) : '')
        : implode("\n", $descriptionParts);

    // contractor_profiles is the source of contractor ownership/business context.
    $contractorStmt = $pdo->prepare(
        'SELECT id FROM contractor_profiles WHERE user_id = ? LIMIT 1'
    );
    $contractorStmt->execute([$userId]);
    if (!$contractorStmt->fetchColumn()) {
        throw new InvalidArgumentException('Contractor profile not found');
    }

    // Local requirements are stored separately from admin vacancies because the
    // contractor owns the requirement and can manage its lifecycle.
    $pdo->beginTransaction();

    $create = $pdo->prepare(
        'INSERT INTO local_requirements
         (contractor_user_id, category_id, job_role_id, title, description, openings_count,
          work_location, work_address, shift_date, shift_start, shift_end,
          minimum_experience, payout_amount, payout_period, status, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $create->execute([
        $userId,
        $categoryId > 0 ? $categoryId : null,
        $jobRoleId > 0 ? $jobRoleId : null,
        $title,
        $fullDescription !== '' ? $fullDescription : null,
        $headcount,
        $location,
        $address !== '' ? $address : null,
        $shiftDate,
        $shiftStart !== '' ? $shiftStart : null,
        $shiftEnd !== '' ? $shiftEnd : null,
        $experience !== '' ? $experience : null,
        $payout,
        $payoutPeriod !== '' ? $payoutPeriod : 'per_shift',
        'open',
        $notes !== '' ? $notes : null,
    ]);
    $requirementId = (int)$pdo->lastInsertId();
    $pdo->commit();

    localJson([
        'success' => true,
        'message' => 'Requirement created successfully',
        'data' => [
            'requirement_id' => $requirementId,
            'status' => 'open',
            'title' => $title,
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
    localJson(['success' => false, 'message' => 'Unable to create requirement'], 500);
}
