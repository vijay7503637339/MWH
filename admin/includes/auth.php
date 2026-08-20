<?php

declare(strict_types=1);

session_start();

function requireAdmin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function currentAdmin(): array
{
    return [
        'id' => (int)($_SESSION['admin_id'] ?? 0),
        'name' => (string)($_SESSION['admin_name'] ?? ''),
        'email' => (string)($_SESSION['admin_email'] ?? ''),
        'role' => (string)($_SESSION['admin_role'] ?? ''),
    ];
}
