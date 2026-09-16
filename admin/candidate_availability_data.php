<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$stmt = $pdo->query("SELECT u.id, COALESCE(cp.availability_status, 'available') AS availability_status
                     FROM users u
                     LEFT JOIN candidate_profiles cp ON cp.user_id=u.id
                     WHERE u.deleted_at IS NULL");

$availability = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $status = in_array((string)$row['availability_status'], ['available', 'unavailable'], true)
        ? (string)$row['availability_status']
        : 'available';
    $availability[(string)$row['id']] = $status;
}

echo json_encode(['success' => true, 'availability' => $availability], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
