<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;
$category = (int)($_GET['category_id'] ?? 0);
$role = (int)($_GET['job_role_id'] ?? 0);
$city = trim((string)($_GET['city'] ?? ''));

// Public URL used by the mobile app for files stored under /MWH/.
$publicBaseUrl = 'https://webstripetechnologies.com/MWH/';

$where = "v.status = 'published' AND v.deleted_at IS NULL
          AND h.status = 'active' AND h.deleted_at IS NULL";
$params = [];

if ($category > 0) {
    $where .= ' AND v.category_id = ?';
    $params[] = $category;
}
if ($role > 0) {
    $where .= ' AND v.job_role_id = ?';
    $params[] = $role;
}
if ($city !== '') {
    $where .= ' AND h.city = ?';
    $params[] = $city;
}

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM vacancies v
     INNER JOIN hotels h ON h.id = v.hotel_id
     WHERE {$where}"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT
        v.id AS vacancy_id,
        v.title,
        v.description,
        v.openings_count,
        v.payout_amount,
        v.payout_period,
        v.working_hours,
        v.requirements,
        v.responsibilities,
        v.dress_code,
        v.shift_date,
        v.start_time,
        v.end_time,
        v.published_at,
        v.closing_at,

        c.id AS category_id,
        c.name AS category_name,
        r.id AS job_role_id,
        r.name AS job_role_name,

        h.id AS hotel_id,
        h.name AS hotel_name,
        h.description AS hotel_description,
        h.address_line AS hotel_address,
        h.city AS hotel_city,
        h.state AS hotel_state,
        h.postcode AS hotel_postcode,
        h.country AS hotel_country,
        h.latitude AS hotel_latitude,
        h.longitude AS hotel_longitude
     FROM vacancies v
     INNER JOIN hotels h ON h.id = v.hotel_id
     INNER JOIN categories c ON c.id = v.category_id
     INNER JOIN job_roles r ON r.id = v.job_role_id
     WHERE {$where}
     ORDER BY
        CASE WHEN v.shift_date IS NULL THEN 1 ELSE 0 END,
        v.shift_date ASC,
        v.published_at DESC,
        v.id DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($rows as $row) {
    $imageStmt = $pdo->prepare(
        'SELECT id, storage_path, original_file_name, is_primary, sort_order
         FROM hotel_images
         WHERE hotel_id = ?
         ORDER BY is_primary DESC, sort_order ASC, id ASC'
    );
    $imageStmt->execute([(int)$row['hotel_id']]);
    $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

    $data[] = [
        'id' => (int)$row['vacancy_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'category' => [
            'id' => (int)$row['category_id'],
            'name' => $row['category_name'],
        ],
        'job_role' => [
            'id' => (int)$row['job_role_id'],
            'name' => $row['job_role_name'],
        ],
        'pay' => [
            'amount' => (float)$row['payout_amount'],
            'period' => $row['payout_period'],
            'currency' => 'USD',
        ],
        'openings_count' => (int)$row['openings_count'],
        'working_hours' => $row['working_hours'] !== null ? (float)$row['working_hours'] : null,
        'schedule' => [
            'date' => $row['shift_date'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'closing_at' => $row['closing_at'],
        ],
        'responsibilities' => $row['responsibilities'],
        'requirements' => $row['requirements'],
        'dress_code' => $row['dress_code'],
        'hotel' => [
            'id' => (int)$row['hotel_id'],
            'name' => $row['hotel_name'],
            'description' => $row['hotel_description'],
            'address' => $row['hotel_address'],
            'city' => $row['hotel_city'],
            'state' => $row['hotel_state'],
            'postcode' => $row['hotel_postcode'],
            'country' => $row['hotel_country'],
            'latitude' => $row['hotel_latitude'] !== null ? (float)$row['hotel_latitude'] : null,
            'longitude' => $row['hotel_longitude'] !== null ? (float)$row['hotel_longitude'] : null,
            'images' => array_map(static function (array $image) use ($publicBaseUrl): array {
                $storagePath = ltrim((string)$image['storage_path'], '/');
                $imageUrl = $publicBaseUrl . $storagePath;

                return [
                    'id' => (int)$image['id'],
                    'storage_path' => $storagePath,
                    'url' => $imageUrl,
                    'original_file_name' => $image['original_file_name'],
                    'is_primary' => (bool)$image['is_primary'],
                    'sort_order' => (int)$image['sort_order'],
                ];
            }, $images),
        ],
    ];
}

respond([
    'success' => true,
    'message' => 'Vacancies fetched successfully',
    'data' => $data,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'last_page' => $total > 0 ? (int)ceil($total / $perPage) : 0,
    ],
]);
