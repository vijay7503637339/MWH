<?php
declare(strict_types=1);
require_once __DIR__.'/../config/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') respond(['success'=>false,'message'=>'Method not allowed'],405);
$u = requireUser($pdo);

$profileStmt = $pdo->prepare("SELECT cp.first_name,cp.last_name,cp.city,cp.country,cp.availability_status,c.name category_name,jr.name job_role_name FROM users u LEFT JOIN candidate_profiles cp ON cp.user_id=u.id LEFT JOIN job_preferences jp ON jp.user_id=u.id LEFT JOIN categories c ON c.id=jp.category_id LEFT JOIN job_roles jr ON jr.id=jp.job_role_id WHERE u.id=? LIMIT 1");
$profileStmt->execute([$u['user_id']]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$shiftStmt = $pdo->prepare("SELECT j.id job_id,j.status,j.start_date,j.end_date,j.start_time,j.end_time,j.payout_amount,v.id vacancy_id,v.title,h.id hotel_id,h.name hotel_name,h.address_line,h.city,h.state,c.name category_name,jr.name job_role_name FROM jobs j INNER JOIN vacancies v ON v.id=j.vacancy_id INNER JOIN hotels h ON h.id=j.hotel_id INNER JOIN categories c ON c.id=v.category_id INNER JOIN job_roles jr ON jr.id=v.job_role_id WHERE j.user_id=? AND j.start_date IS NOT NULL AND j.start_date >= CURDATE() AND j.status NOT IN ('completed','cancelled') ORDER BY j.start_date ASC, j.start_time ASC, j.id ASC LIMIT 5");
$shiftStmt->execute([$u['user_id']]);
$items = $shiftStmt->fetchAll(PDO::FETCH_ASSOC);

$firstName = trim((string)($profile['first_name'] ?? ''));
$lastName = trim((string)($profile['last_name'] ?? ''));
respond([
    'success'=>true,
    'data'=>[
        'user'=>[
            'id'=>(int)$u['user_id'],
            'first_name'=>$firstName,
            'last_name'=>$lastName,
            'name'=>trim($firstName.' '.$lastName),
            'category_name'=>$profile['category_name'] ?? null,
            'job_role_name'=>$profile['job_role_name'] ?? null,
            'city'=>$profile['city'] ?? null,
            'country'=>$profile['country'] ?? null,
        ],
        'availability_status'=>$profile['availability_status'] ?? 'available',
        'available'=>($profile['availability_status'] ?? 'available') === 'available',
        'upcoming_shifts'=>array_map(static function(array $row): array {
            return [
                'job_id'=>(int)$row['job_id'],
                'vacancy_id'=>(int)$row['vacancy_id'],
                'title'=>$row['title'],
                'status'=>$row['status'],
                'hotel_name'=>$row['hotel_name'],
                'address_line'=>$row['address_line'],
                'city'=>$row['city'],
                'state'=>$row['state'],
                'category_name'=>$row['category_name'],
                'job_role_name'=>$row['job_role_name'],
                'start_date'=>$row['start_date'],
                'end_date'=>$row['end_date'],
                'start_time'=>$row['start_time'],
                'end_time'=>$row['end_time'],
                'payout_amount'=>(float)$row['payout_amount'],
            ];
        }, $items),
    ],
]);
