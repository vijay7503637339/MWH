<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

function tableCount(PDO $pdo, string $table): int
{
    $allowed = ['hotels', 'vacancies', 'users', 'applications', 'attendance_records', 'payments'];
    if (!in_array($table, $allowed, true)) return 0;
    try { return (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(); }
    catch (Throwable $e) { return 0; }
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

<main class="content dashboard-modern">
    <div class="dashboard-welcome">
        <div>
            <div class="page-kicker"><i class="bi bi-grid-1x2-fill"></i> Workforce Management</div>
            <h1>Dashboard</h1>
            <p>Welcome back, <?= htmlspecialchars($admin['name']) ?>. Here's your MWH overview.</p>
        </div>
        <div class="applications-count"><strong><?= number_format(array_sum($counts)) ?></strong><span>Tracked Records</span></div>
    </div>

    <div class="dashboard-cards">
        <a class="dashboard-card" href="hotels.php" style="text-decoration:none"><div class="icon"><i class="bi bi-building"></i></div><div class="label">Hotels</div><div class="value"><?= number_format($counts['hotels']) ?></div><span class="link">Manage hotels →</span></a>
        <a class="dashboard-card" href="vacancies.php" style="text-decoration:none"><div class="icon"><i class="bi bi-briefcase"></i></div><div class="label">Vacancies</div><div class="value"><?= number_format($counts['vacancies']) ?></div><span class="link">Manage vacancies →</span></a>
        <a class="dashboard-card" href="candidates.php" style="text-decoration:none"><div class="icon"><i class="bi bi-people"></i></div><div class="label">Candidates</div><div class="value"><?= number_format($counts['candidates']) ?></div><span class="link">Review candidates →</span></a>
        <a class="dashboard-card" href="applications.php" style="text-decoration:none"><div class="icon"><i class="bi bi-file-earmark-text"></i></div><div class="label">Applications</div><div class="value"><?= number_format($counts['applications']) ?></div><span class="link">Review applications →</span></a>
    </div>

    <div class="dashboard-cards">
        <a class="dashboard-card" href="attendance.php" style="text-decoration:none"><div class="icon"><i class="bi bi-clock-history"></i></div><div class="label">Attendance</div><div class="value"><?= number_format($counts['attendance']) ?></div><span class="link">View attendance →</span></a>
        <a class="dashboard-card" href="payments.php" style="text-decoration:none"><div class="icon"><i class="bi bi-wallet2"></i></div><div class="label">Payments</div><div class="value"><?= number_format($counts['payments']) ?></div><span class="link">View payments →</span></a>
        <div class="dashboard-card"><div class="icon"><i class="bi bi-activity"></i></div><div class="label">Operations</div><div class="value">Live</div><span class="link">MWH admin system</span></div>
        <div class="dashboard-card"><div class="icon"><i class="bi bi-shield-check"></i></div><div class="label">Security</div><div class="value">Active</div><span class="link">Protected admin area</span></div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div><h2>Quick Access</h2><p class="muted">Jump directly to the core MWH management modules.</p></div>
        </div>
        <div class="quick-links" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:0 22px 22px">
            <a class="button-secondary" href="categories.php"><i class="bi bi-diagram-3"></i>&nbsp; Categories & Job Roles</a>
            <a class="button-secondary" href="candidates.php"><i class="bi bi-person-check"></i>&nbsp; Candidate Verification</a>
            <a class="button-secondary" href="attendance.php"><i class="bi bi-calendar-check"></i>&nbsp; Attendance</a>
            <a class="button-secondary" href="payments.php"><i class="bi bi-receipt"></i>&nbsp; Payments</a>
        </div>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
