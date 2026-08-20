<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'GET request required']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$userId = (int)($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'user_id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT a.id,a.vacancy_id,a.status,a.applied_at,a.reviewed_at,a.rejection_reason,
        v.title AS vacancy_title,v.payout_amount,v.payout_period,v.status AS vacancy_status,
        h.id AS hotel_id,h.name AS hotel_name,h.city AS hotel_city
        FROM applications a
        INNER JOIN vacancies v ON v.id=a.vacancy_id
        INNER JOIN hotels h ON h.id=v.hotel_id
        WHERE a.user_id=?
        ORDER BY a.applied_at DESC");
    $stmt->execute([$userId]);
    echo json_encode(['success'=>true,'applications'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Unable to load applications']);
}
