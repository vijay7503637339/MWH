<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$admin = currentAdmin();
$pageTitle = $pageTitle ?? 'MWH Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17243b">
    <title><?= htmlspecialchars($pageTitle) ?> | MWH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="assets/metis-theme.css">
    <link rel="stylesheet" href="assets/layout-fix.css">
    <link rel="stylesheet" href="assets/module-theme.css?v=2">
    <link rel="stylesheet" href="assets/responsive-admin.css?v=1">
</head>
<body class="admin-page">
<div class="app-shell">
<header class="mwh-topbar">
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="mwh-icon-button mwh-menu-button" id="mobileMenuButton" aria-label="Open menu" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
        <div class="mwh-topbar-title">Maan World Hospitality &nbsp; / &nbsp; <?= htmlspecialchars($pageTitle) ?></div>
    </div>
    <div class="mwh-topbar-actions">
        <div class="mwh-topbar-user">
            <div class="mwh-topbar-avatar"><?= htmlspecialchars(strtoupper(substr($admin['name'], 0, 1))) ?></div>
            <div class="mwh-topbar-user-copy">
                <strong><?= htmlspecialchars($admin['name']) ?></strong>
                <span><?= htmlspecialchars($admin['role']) ?></span>
            </div>
        </div>
    </div>
</header>
<script src="assets/module-ui.js?v=3" defer></script>