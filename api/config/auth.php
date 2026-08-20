<?php

declare(strict_types=1);

function requireAdmin(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['admin_id'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Admin authentication required']);
        exit;
    }

    return [
        'id' => (int)$_SESSION['admin_id'],
        'name' => (string)$_SESSION['admin_name'],
        'email' => (string)$_SESSION['admin_email'],
        'role' => (string)$_SESSION['admin_role']
    ];
}
