<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

try {
    $pdo->query('SELECT 1');
    echo json_encode([
        'success' => true,
        'message' => 'MWH API is running',
        'database' => 'connected',
        'timestamp' => gmdate('c')
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
}
