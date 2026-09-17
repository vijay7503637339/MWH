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

// Local contractor accounts are stored directly in local_users.
// Keep this page isolated from the legacy hospitality users/profiles tables.
$sql = "SELECT id, name, mobile, email, aadhaar_number, account_status, created_at
        FROM local_users
        WHERE role = 'contractor' AND deleted_at IS NULL";
$params = [];
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR mobile LIKE ? OR email LIKE ? OR aadhaar_number LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}
if (in_array($status, ['pending_verification','verified','rejected','blocked'], true)) {
    $sql .= ' AND account_status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY created_at DESC';
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
            <p class="muted">Contractors registered through the Local Contractor &amp; Chef app.</p>
        </div>
    </div>
    <section class="panel">
        <div class="panel-header">
            <div><h2>Contractor Registrations</h2><p class="muted small">Records are loaded from local_users where role is contractor.</p></div>
            <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input name="search" value="<?= e($search) ?>" placeholder="Search name, mobile, email, Aadhaar..." class="form-control" style="min-width:240px">
                <select name="status" class="form-select" style="width:190px">
                    <option value="all">All statuses</option>
                    <option value="pending_verification" <?= $status==='pending_verification'?'selected':'' ?>>Pending</option>
                    <option value="verified" <?= $status==='verified'?'selected':'' ?>>Verified</option>
                    <option value="rejected" <?= $status==='rejected'?'selected':'' ?>>Rejected</option>
                    <option value="blocked" <?= $status==='blocked'?'selected':'' ?>>Blocked</option>
                </select>
                <button class="button-secondary" type="submit">Filter</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Contact</th><th>Aadhaar Number</th><th>Status</th><th>Registered</th></tr></thead>
                <tbody>
                <?php if (!$contractors): ?><tr><td colspan="5" class="text-center muted">No Local contractors found.</td></tr>
                <?php else: foreach ($contractors as $row): ?>
                    <tr>
                        <td><strong><?= e($row['name'] ?: 'Contractor #' . $row['id']) ?></strong></td>
                        <td><?= e($row['mobile']) ?><div class="muted small"><?= e($row['email'] ?: '—') ?></div></td>
                        <td><?= e($row['aadhaar_number'] ?: '—') ?></td>
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
