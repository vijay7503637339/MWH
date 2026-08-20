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
    <title><?= htmlspecialchars($pageTitle) ?> | MWH</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-page">
<div class="app-shell">
