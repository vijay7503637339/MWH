<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$pageTitle = 'Local Contractors';
$admin = currentAdmin();
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$search = trim((string)($_GET['search'] ?? ''));
$status = (string)($_GET['status'] ?? 'all');

// Contractors are identified by their dedicated contractor profile. Avoid
// querying user_app_access because installations may not have that table.
$sql = "SELECT u.id, u.mobile, u.email, u.account_status, u.created_at, u.verified_at,
               ctp.business_name, ctp.business_type, ctp.whatsapp_mobile,
               ctp.full_address, ctp.city, ctp.state, ctp.postcode, ctp.gstin,
               (SELECT COUNT(*) FROM local_requirements lr WHERE lr.contractor_user_id=u.id AND (lr.deleted_at IS NULL OR lr.deleted_at IS NULL)) AS requirement_count
        FROM users u
        INNER JOIN contractor_profiles ctp ON ctp.user_id=u.id
        WHERE u.deleted_at IS NULL";
$params = [];
if ($search !== '') {
    $sql .= ' AND (ctp.business_name LIKE ? OR ctp.business_type LIKE ? OR ctp.city LIKE ? OR u.mobile LIKE ? OR u.email LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
}
if (in_array($status, ['pending_verification','verified','rejected','blocked'], true)) {
    $sql .= ' AND u.account_status=?';
    $params[] = $status;
}
$sql .= ' ORDER BY u.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contractors = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<main class="content">
    <div class="topbar">
        <div>
            <div class="page-kicker"><i class="bi bi-briefcase-fill"></i> MWH Local</div>
            <h1>Local Contractors</h1>
            <p class="muted">Contractors registered through the Local Contractor & Chef app.</p>
        </div>
    </div>
    <section class="panel">
        <div class="panel-header">
            <div><h2>Contractor Registrations</h2><p class="muted small">Contractor accounts are identified by their contractor profile; no app-access mapping is required.</p></div>
            <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input name="search" value="<?= e($search) ?>" placeholder="Search business, city, mobile..." class="form-control" style="min-width:240px">
                <select name="status" class="form-select" style="width:190px">
                    <option value="all">All statuses</option>
                    <option value="pending_verification" <?= $status==='pending_verification'?'selected':'' ?>>Pending</option>
                    <option value="verified" <?= $status==='verified'?'selected':'' ?>>Verified</option>
                    <option value="rejected' <?= $status==='rejected'?'selected':'' ?>>Rejected</option>
                    <option value="blocked" <?= $status==='blocked'?'selected':'' ?>>Blocked</option>
                </select>
                <button class="button-secondary" type="submit">Filter</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Business</th><th>Contact</th><th>Location</th><th>Type</th><th>Requirements</th><th>Status</th><th>Registered</th></tr></thead>
                <tbody>
                <?php if (!$contractors): ?><tr><td colspan="7" class="text-center muted">No Local contractors found.</td></tr>
                <?php else: foreach ($contractors as $row): ?>
                    <tr>
                        <td><strong><?= e($row['business_name'] ?: 'Contractor #' . $row['id']) ?></strong><div class="muted small"><?= e($row['gstin'] ?: 'No GSTIN') ?></div></td>
                        <td><?= e($row['mobile']) ?><div class="muted small"><?= e($row['email'] ?: '—') ?></div></td>
                        <td><?= e(trim(($row['city'] ?: '') . (($row['state'] ?? '') !== '' ? ', ' . $row['state'] : '')) ?: '—') ?></td>
                        <td><?= e($row['business_type'] ?: '—') ?></td>
                        <td><?= number_format((int)$row['requirement_count']) ?></td>
                        <td><span class="status-badge <?= e($row['account_status']) ?>"><?= e(ucwords(str_replace('_',' ', $row['account_status']))) ?></span></td>
                        <td><?= e($row['created_at']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
