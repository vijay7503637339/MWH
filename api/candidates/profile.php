<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GET request required']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$mobile = trim((string)($_GET['mobile'] ?? ''));
$userId = (int)($_GET['user_id'] ?? 0);

if ($mobile === '' && $userId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'mobile or user_id is required']);
    exit;
}

try {
    if ($userId > 0) {
        $where = 'u.id = ?';
        $value = $userId;
    } else {
        $where = 'u.mobile = ?';
        $value = $mobile;
    }

    $sql = "SELECT
                u.id,
                u.mobile,
                u.email,
                u.account_status,
                u.rejection_reason,
                u.verified_at,
                cp.first_name,
                cp.last_name,
                cp.date_of_birth,
                cp.gender,
                cp.full_address,
                cp.city,
                cp.country,
                cp.postcode,
                pd.experience_years,
                pd.previous_company_name,
                jp.category_id,
                c.name AS category_name,
                jp.job_role_id,
                jr.name AS job_role_name,
                jp.max_travel_distance_km,
                jp.preferred_working_hours,
                cpp.payment_type,
                cpp.account_holder_name,
                cpp.bank_name,
                cpp.ifsc_code,
                cpp.branch_name,
                cpp.upi_id
            FROM users u
            LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
            LEFT JOIN professional_details pd ON pd.user_id = u.id
            LEFT JOIN job_preferences jp ON jp.user_id = u.id
            LEFT JOIN categories c ON c.id = jp.category_id
            LEFT JOIN job_roles jr ON jr.id = jp.job_role_id
            LEFT JOIN candidate_payment_profiles cpp ON cpp.user_id = u.id
            WHERE {$where}
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$value]);
    $candidate = $stmt->fetch();

    if (!$candidate) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Candidate not found']);
        exit;
    }

    unset($candidate['account_number_encrypted']);
    echo json_encode(['success' => true, 'candidate' => $candidate]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load candidate profile']);
}
