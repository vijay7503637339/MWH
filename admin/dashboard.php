<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

function tableCount(PDO $pdo, string $table): int
{
    $allowed = ['hotels', 'vacancies', 'users', 'applications', 'attendance_records', 'payments'];
    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$counts = [
    'hotels' => tableCount($pdo, 'hotels'),
    'vacancies' => tableCount($pdo, 'vacancies'),
    'candidates' => tableCount($pdo, 'users'),
    'applications' => tableCount($pdo, 'applications'),
    'attendance' => tableCount($pdo, 'attendance_records'),
    'payments' => tableCount($pdo, 'payments'),
];

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<main class="content">
    <div class="topbar">
        <div>
            <h1>Dashboard</h1>
            <p class="muted">Welcome back, <?= htmlspecialchars($admin['name']) ?>.</p>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Hotels</div><div class="value"><?= $counts['hotels'] ?></div></div>
        <div class="card"><div class="label">Vacancies</div><div class="value"><?= $counts['vacancies'] ?></div></div>
        <div class="card"><div class="label">Candidates</div><div class="value"><?= $counts['candidates'] ?></div></div>
        <div class="card"><div class="label">Applications</div><div class="value"><?= $counts['applications'] ?></div></div>
    </section>

    <section class="cards section">
        <div class="card"><div class="label">Attendance Records</div><div class="value"><?= $counts['attendance'] ?></div></div>
        <div class="card"><div class="label">Payments</div><div class="value"><?= $counts['payments'] ?></div></div>
    </section>

    <section class="section">
        <h2>Quick start</h2>
        <div class="empty-box">The dashboard foundation is ready. Hotel and vacancy management will be added as the next modules.</div>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
