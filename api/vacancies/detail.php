<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    respond(['success' => false, 'message' => 'id is required'], 422);
}

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
        v.status,
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
        h.longitude AS hotel_longitude,
        h.contact_name AS hotel_contact_name,
        h.contact_phone AS hotel_contact_phone
     FROM vacancies v
     INNER JOIN hotels h ON h.id = v.hotel_id
     INNER JOIN categories c ON c.id = v.category_id
     INNER JOIN job_roles r ON r.id = v.job_role_id
     WHERE v.id = ?
       AND v.status = 'published'
       AND v.deleted_at IS NULL
       AND h.status = 'active'
       AND h.deleted_at IS NULL
     LIMIT 1"
);
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    respond(['success' => false, 'message' => 'Job not found'], 404);
}

$imageStmt = $pdo->prepare(
    'SELECT id, storage_path, original_file_name, is_primary, sort_order
     FROM hotel_images
     WHERE hotel_id = ?
     ORDER BY is_primary DESC, sort_order ASC, id ASC'
);
$imageStmt->execute([(int)$row['hotel_id']]);
$images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

respond([
    'success' => true,
    'message' => 'Vacancy details fetched successfully',
    'data' => [
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
            'currency' => 'INR',
        ],
        'openings_count' => (int)$row['openings_count'],
        'working_hours' => $row['working_hours'] !== null ? (float)$row['working_hours'] : null,
        'schedule' => [
            'date' => $row['shift_date'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'closing_at' => $row['closing_at'],
        ],
        'job_description' => $row['description'],
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
            'contact_name' => $row['hotel_contact_name'],
            'contact_phone' => $row['hotel_contact_phone'],
            'images' => mapHotelImages($images),
        ],
    ],
]);
