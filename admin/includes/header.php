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
    <style>
        .mwh-topbar-actions{display:flex;align-items:center;gap:12px}
        .mwh-account-actions{display:flex;align-items:center;gap:8px}
        .mwh-account-action{display:inline-flex;align-items:center;gap:8px;padding:9px 13px;border:1px solid #e1e7f0;border-radius:10px;background:#fff;color:#34435c;text-decoration:none;font-size:13px;font-weight:700;white-space:nowrap;transition:background .18s,border-color .18s,transform .18s}
        .mwh-account-action:hover{background:#f5f8ff;border-color:#cbd7ef;color:#244fbd;transform:translateY(-1px)}
        .mwh-account-action.logout{color:#b4232d;border-color:#f1d5d7}
        .mwh-account-action.logout:hover{background:#fff4f4;border-color:#e9b9bd;color:#a31c25}
        @media(max-width:700px){.mwh-topbar-actions{gap:6px}.mwh-topbar-user-copy{display:none}.mwh-account-action{padding:9px 10px;font-size:12px}.mwh-account-action span{display:none}.mwh-account-action i{font-size:16px}}
    </style>
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
        <nav class="mwh-account-actions" aria-label="Account actions">
            <a class="mwh-account-action" href="change_password.php" title="Change Password">
                <i class="bi bi-key"></i><span>Change Password</span>
            </a>
            <a class="mwh-account-action logout" href="../api/auth/logout.php" id="topbarLogoutLink" title="Logout">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </a>
        </nav>
    </div>
</header>
<script src="assets/module-ui.js?v=3" defer></script>