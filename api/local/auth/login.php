<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    localJson(['success' => false, 'message' => 'POST request required'], 405);
}

$data = localInput();

try {
    $mobile = localMobile($data['mobile'] ?? $data['whatsapp_mobile'] ?? '');
    $password = (string)($data['password'] ?? '');
    if ($password === '') {
        throw new InvalidArgumentException('Password is required');
    }

    $requestedRole = trim((string)($data['role'] ?? ''));
    $requestedRole = $requestedRole !== '' ? localRole($requestedRole) : '';

    $stmt = $pdo->prepare(
        'SELECT u.id, u.mobile, u.email, u.password_hash, u.account_status,
                cp.user_id AS candidate_profile_id, cp.first_name, cp.last_name, cp.city,
                ctp.user_id AS contractor_profile_id, ctp.business_name, ctp.business_type
         FROM users u
         LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
         LEFT JOIN contractor_profiles ctp ON ctp.user_id = u.id
         WHERE u.mobile = ? AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$mobile]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        localJson(['success' => false, 'message' => 'Invalid mobile number or password'], 401);
    }

    $accountStatus = strtolower(trim((string)$user['account_status']));
    if ($accountStatus === 'pending_verification') {
        localJson([
            'success' => false,
            'code' => 'PENDING_VERIFICATION',
            'message' => 'Your account is pending admin verification.',
            'data' => ['user_id' => (int)$user['id']]
        ], 403);
    }
    if (in_array($accountStatus, ['blocked', 'rejected'], true)) {
        localJson([
            'success' => false,
            'code' => strtoupper($accountStatus),
            'message' => $accountStatus === 'blocked' ? 'Account is blocked' : 'Account is rejected'
        ], 403);
    }

    // A role is available only when its corresponding profile exists.
    $roles = [];
    if (!empty($user['candidate_profile_id'])) {
        $roles[] = 'staff';
    }
    if (!empty($user['contractor_profile_id'])) {
        $roles[] = 'contractor';
    }

    if ($requestedRole !== '' && !in_array($requestedRole, $roles, true)) {
        localJson([
            'success' => false,
            'code' => 'ROLE_NOT_AVAILABLE',
            'message' => 'This account does not have a profile for the selected MWH Local role.',
            'data' => ['available_roles' => $roles]
        ], 403);
    }

    $role = $requestedRole !== '' ? $requestedRole : ($roles[0] ?? '');
    if ($role === '') {
        localJson([
            'success' => false,
            'code' => 'LOCAL_PROFILE_REQUIRED',
            'message' => 'No staff or contractor profile is linked to this account.'
        ], 403);
    }

    $pdo->beginTransaction();
    $tokenData = localIssueToken($pdo, (int)$user['id']);
    $pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int)$user['id']]);
    $pdo->commit();

    unset($user['password_hash'], $user['candidate_profile_id'], $user['contractor_profile_id']);
    $user['id'] = (int)$user['id'];
    $user['roles'] = $roles;
    $user['active_role'] = $role;

    localJson([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            ...$tokenData,
            'user' => $user,
        ],
    ]);
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
    localJson(['success' => false, 'message' => 'Login failed'], 500);
}
