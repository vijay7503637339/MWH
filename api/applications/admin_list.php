<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'GET request required']);
    exit;
}

try {
    $status = (string)($_GET['status'] ?? 'all');
    $search = trim((string)($_GET['search'] ?? ''));
    $sql = "SELECT a.id,a.status,a.applied_at,a.reviewed_at,a.rejection_reason,a.notes,
        u.id AS user_id,u.mobile,u.email,cp.first_name,cp.last_name,cp.city,
        v.id AS vacancy_id,v.title AS vacancy_title,v.payout_amount,v.payout_period,
        h.id AS hotel_id,h.name AS hotel_name,h.city AS hotel_city,
        c.name AS category_name,jr.name AS job_role_name
        FROM applications a
        INNER JOIN users u ON u.id=a.user_id
        LEFT JOIN candidate_profiles cp ON cp.user_id=u.id
        INNER JOIN vacancies v ON v.id=a.vacancy_id
        INNER JOIN hotels h ON h.id=v.hotel_id
        LEFT JOIN categories c ON c.id=v.category_id
        LEFT JOIN job_roles jr ON jr.id=v.job_role_id
        WHERE u.deleted_at IS NULL AND a.deleted_at IS NULL";
    $params=[];
    if (in_array($status,['applied','contacted','selected','rejected'],true)) { $sql .= ' AND a.status=?'; $params[]=$status; }
    if ($search !== '') {
        $sql .= ' AND (cp.first_name LIKE ? OR cp.last_name LIKE ? OR u.mobile LIKE ? OR v.title LIKE ? OR h.name LIKE ?)';
        $term='%'.$search.'%'; array_push($params,$term,$term,$term,$term,$term);
    }
    $sql .= ' ORDER BY a.applied_at DESC';
    $stmt=$pdo->prepare($sql); $stmt->execute($params);
    echo json_encode(['success'=>true,'applications'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'admin_id'=>$admin['id']]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Unable to load applications']);
}
