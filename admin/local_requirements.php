<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$pageTitle = 'Local Requirements';
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$requirements = [];
try {
    $sql = "SELECT lr.*, c.name AS category_name, jr.name AS job_role_name,
                   ctp.business_name,
                   u.mobile AS contractor_mobile
            FROM local_requirements lr
            INNER JOIN users u ON u.id=lr.contractor_user_id AND u.deleted_at IS NULL
            INNER JOIN user_app_access uaa ON uaa.user_id=u.id
                AND uaa.app_key='mwh_local' AND uaa.role_key='contractor' AND uaa.status='active'
            LEFT JOIN contractor_profiles ctp ON ctp.user_id=u.id
            LEFT JOIN categories c ON c.id=lr.category_id
            LEFT JOIN job_roles jr ON jr.id=lr.job_role_id
            WHERE COALESCE(lr.deleted_at IS NULL, 1)
            ORDER BY lr.created_at DESC";
    $requirements = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $requirements = [];
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<main class="content">
    <div class="topbar">
        <div>
            <div class="page-kicker"><i class="bi bi-clipboard-plus"></i> MWH Local</div>
            <h1>Local Requirements</h1>
            <p class="muted">Staffing requirements created by Local contractors.</p>
        </div>
    </div>
    <section class="panel">
        <div class="panel-header"><div><h2>Requirements</h2><p class="muted small">Only requirements owned by Local contractor accounts are displayed.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Requirement</th><th>Contractor</th><th>Role</th><th>Location</th><th>Date</th><th>Openings</th><th>Payout</th><th>Status</th></tr></thead><tbody>
        <?php if (!$requirements): ?><tr><td colspan="8" class="text-center muted">No Local requirements found.</td></tr><?php else: foreach ($requirements as $row): ?>
            <tr>
                <td><strong><?= e($row['title']) ?></strong><div class="muted small"><?= e(mb_strimwidth((string)$row['description'], 0, 80, '…')) ?></div></td>
                <td><?= e($row['business_name'] ?: 'Contractor #' . $row['contractor_user_id']) ?><div class="muted small"><?= e($row['contractor_mobile']) ?></div></td>
                <td><?= e($row['job_role_name'] ?: $row['category_name'] ?: '—') ?></td>
                <td><?= e($row['work_location']) ?></td>
                <td><?= e($row['shift_date']) ?></td>
                <td><?= number_format((int)$row['openings_count']) ?></td>
                <td><?= number_format((float)$row['payout_amount'], 2) ?> / <?= e($row['payout_period']) ?></td>
                <td><span class="status-badge <?= e($row['status']) ?>"><?= e(ucwords(str_replace('_',' ', $row['status']))) ?></span></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
