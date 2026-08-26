<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = requireUser($pdo);

try {
    $stmt = $pdo->prepare(
        "SELECT
            a.id AS application_id,
            a.vacancy_id,
            a.status AS application_status,
            a.applied_at,
            a.updated_at,
            v.title,
            v.description,
            v.payout_amount,
            v.payout_period,
            v.working_hours,
            v.openings_count,
            v.published_at,
            v.closing_at,
            h.id AS hotel_id,
            h.name AS hotel_name,
            h.address_line,
            h.city,
            h.state,
            (
                SELECT hi.id
                FROM hotel_images hi
                WHERE hi.hotel_id = h.id
                ORDER BY hi.is_primary DESC, hi.sort_order ASC, hi.id ASC
                LIMIT 1
            ) AS hotel_image_id,
            (
                SELECT hi.storage_path
                FROM hotel_images hi
                WHERE hi.hotel_id = h.id
                ORDER BY hi.is_primary DESC, hi.sort_order ASC, hi.id ASC
                LIMIT 1
            ) AS hotel_image_path,
            j.id AS job_id,
            j.status AS job_status,
            j.start_date,
            j.end_date
         FROM applications a
         INNER JOIN vacancies v ON v.id = a.vacancy_id
         INNER JOIN hotels h ON h.id = v.hotel_id
         LEFT JOIN jobs j ON j.application_id = a.id
         WHERE a.user_id = ?
           AND v.deleted_at IS NULL
           AND h.deleted_at IS NULL
         ORDER BY a.applied_at DESC, a.id DESC"
    );
    $stmt->execute([(int)$user['user_id']]);

    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items[] = [
            'application_id' => (int)$row['application_id'],
            'vacancy_id' => (int)$row['vacancy_id'],
            'status' => $row['application_status'],
            'applied_at' => $row['applied_at'],
            'updated_at' => $row['updated_at'],
            'vacancy' => [
                'id' => (int)$row['vacancy_id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'payout_amount' => $row['payout_amount'] !== null ? (float)$row['payout_amount'] : null,
                'payout_period' => $row['payout_period'],
                'working_hours' => $row['working_hours'] !== null ? (float)$row['working_hours'] : null,
                'openings_count' => (int)$row['openings_count'],
                'published_at' => $row['published_at'],
                'closing_at' => $row['closing_at'],
            ],
            'hotel' => [
                'id' => (int)$row['hotel_id'],
                'name' => $row['hotel_name'],
                'address_line' => $row['address_line'],
                'city' => $row['city'],
                'state' => $row['state'],
                'image_id' => $row['hotel_image_id'] !== null ? (int)$row['hotel_image_id'] : null,
                'image_path' => $row['hotel_image_path'],
            ],
            'job' => $row['job_id'] !== null ? [
                'id' => (int)$row['job_id'],
                'status' => $row['job_status'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
            ] : null,
        ];
    }

    respond([
        'success' => true,
        'message' => 'Applications fetched successfully',
        'data' => [
            'items' => $items,
            'count' => count($items),
        ],
    ]);
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'Could not fetch your jobs'], 500);
}
