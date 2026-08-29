<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$categoryId = (int)($_GET['category_id'] ?? 0);

if ($categoryId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id, name, description, sort_order
         FROM job_roles
         WHERE category_id=? AND is_active=1 AND deleted_at IS NULL
         ORDER BY sort_order, name'
    );
    $stmt->execute([$categoryId]);
    $roles = array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'sort_order' => (int)$row['sort_order'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    respond(['success' => true, 'data' => $roles]);
}

$categoriesStmt = $pdo->query(
    'SELECT id, name, description, sort_order
     FROM categories
     WHERE is_active=1 AND deleted_at IS NULL
     ORDER BY sort_order, name'
);
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($categories as $category) {
    $rolesStmt = $pdo->prepare(
        'SELECT id, name, description, sort_order
         FROM job_roles
         WHERE category_id=? AND is_active=1 AND deleted_at IS NULL
         ORDER BY sort_order, name'
    );
    $rolesStmt->execute([(int)$category['id']]);
    $roles = array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'sort_order' => (int)$row['sort_order'],
        ];
    }, $rolesStmt->fetchAll(PDO::FETCH_ASSOC));

    $data[] = [
        'id' => (int)$category['id'],
        'name' => $category['name'],
        'description' => $category['description'],
        'sort_order' => (int)$category['sort_order'],
        'roles' => $roles,
    ];
}

respond([
    'success' => true,
    'message' => 'Categories and job roles fetched successfully',
    'data' => $data,
]);
