<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$categoriesStmt = $pdo->query("SELECT id, name FROM categories WHERE is_active=1 AND deleted_at IS NULL ORDER BY name ASC");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$rolesStmt = $pdo->query("SELECT id, category_id, name FROM job_roles WHERE is_active=1 AND deleted_at IS NULL ORDER BY name ASC");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'categories' => $categories,
    'job_roles' => $roles,
], JSON_UNESCAPED_UNICODE);
