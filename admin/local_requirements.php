<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$pageTitle = 'Local Requirements';
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$requirements = [];
$errorMessage = '';
$allowedStatuses = ['pending', 'open', 'in_progress', 'filled', 'completed', 'cancelled', 'closed'];

try {
    $sql = "SELECT lr.*, c.name AS category_name, jr.name AS job_role_name,
                   ctp.business_name, u.mobile AS contractor_mobile
            FROM local_requirements lr
            INNER JOIN users u ON u.id=lr.contractor_user_id AND u.deleted_at IS NULL
            INNER JOIN user_app_access uaa ON uaa.user_id=u.id
                AND uaa.app_key='mwh_local' AND uaa.role_key='contractor' AND uaa.status='active'
            LEFT JOIN contractor_profiles ctp ON ctp.user_id=u.id
            LEFT JOIN categories c ON c.id=lr.category_id
            LEFT JOIN job_roles jr ON jr.id=lr.job_role_id
            WHERE lr.deleted_at IS NULL";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (lr.title LIKE ? OR lr.description LIKE ? OR lr.work_location LIKE ? OR ctp.business_name LIKE ? OR u.mobile LIKE ? OR jr.name LIKE ? OR c.name LIKE ?)";
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term, $term, $term, $term);
    }
    if ($status !== '' && in_array($status, $allowedStatuses, true)) {
        $sql .= ' AND lr.status = ?';
        $params[] = $status;
    }
    if ($from !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $from)) {
        $sql .= ' AND lr.shift_date >= ?';
        $params[] = $from;
    }
    if ($to !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $to)) {
        $sql .= ' AND lr.shift_date <= ?';
        $params[] = $to;
    }
    $sql .= ' ORDER BY lr.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('Local requirements admin query failed: ' . $exception->getMessage());
    $errorMessage = 'Could not load requirements. Please check the database configuration and try again.';
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<main class="content">
    <div class="topbar"><div><div class="page-kicker"><i class="bi bi-clipboard-plus"></i> MWH Local</div><h1>Local Requirements</h1><p class="muted">Review and filter staffing requirements posted by Local contractors.</p></div></div>
    <?php if ($errorMessage !== ''): ?><div class="alert alert-warning" role="alert"><?= e($errorMessage) ?></div><?php endif; ?>
    <section class="panel">
        <div class="panel-header"><div><h2>Requirements <span class="status-badge"><?= number_format(count($requirements)) ?></span></h2><p class="muted small">Only requirements linked to active MWH Local contractor accounts are displayed.</p></div></div>
        <form method="get" class="row g-2 align-items-end mb-3" role="search">
            <div class="col-xl-4 col-md-6"><label class="form-label" for="req-search">Search</label><input id="req-search" class="form-control" name="search" value="<?= e($search) ?>" placeholder="Title, contractor, mobile, role, location"></div>
            <div class="col-xl-2 col-md-6"><label class="form-label" for="req-status">Status</label><select id="req-status" name="status" class="form-select"><option value="">All statuses</option><?php foreach ($allowedStatuses as $item): ?><option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $item))) ?></option><?php endforeach; ?></select></div>
            <div class="col-xl-2 col-md-6"><label class="form-label" for="req-from">Shift date from</label><input id="req-from" type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
            <div class="col-xl-2 col-md-6"><label class="form-label" for="req-to">Shift date to</label><input id="req-to" type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
            <div class="col-xl-2 col-md-12 d-flex gap-2"><button class="button-primary" type="submit">Apply filters</button><a class="button-secondary" href="local_requirements.php">Reset</a></div>
        </form>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Requirement</th><th>Contractor</th><th>Role</th><th>Location</th><th>Shift date</th><th>Openings</th><th>Payout</th><th>Status</th></tr></thead><tbody>
        <?php if (!$requirements): ?><tr><td colspan="8" class="text-center muted">No requirements match these filters.</td></tr><?php else: foreach ($requirements as $row): ?>
            <tr>
                <td><strong><?= e($row['title'] ?? '') ?></strong><div class="muted small"><?= e(mb_strimwidth((string)($row['description'] ?? ''), 0, 100, '…')) ?></div></td>
                <td><?= e(($row['business_name'] ?? '') ?: 'Contractor #' . ($row['contractor_user_id'] ?? '')) ?><div class="muted small"><?= e($row['contractor_mobile'] ?? '') ?></div></td>
                <td><?= e(($row['job_role_name'] ?? '') ?: (($row['category_name'] ?? '') ?: '—')) ?></td>
                <td><?= e($row['work_location'] ?? '') ?></td>
                <td><?= e($row['shift_date'] ?? '') ?></td>
                <td><?= number_format((int)($row['openings_count'] ?? 0)) ?></td>
                <td><?= number_format((float)($row['payout_amount'] ?? 0), 2) ?> / <?= e($row['payout_period'] ?? '') ?></td>
                <td><span class="status-badge <?= e($row['status'] ?? '') ?>"><?= e(ucwords(str_replace('_', ' ', (string)($row['status'] ?? 'unknown')))) ?></span></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
