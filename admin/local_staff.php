<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$pageTitle = 'Local Staff & Chefs';
$admin = currentAdmin();
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$search = trim((string)($_GET['search'] ?? ''));
$status = (string)($_GET['status'] ?? 'all');

$sql = "SELECT u.id, u.mobile, u.email, u.account_status, u.created_at, u.verified_at,
               cp.first_name, cp.last_name, cp.city, cp.country,
               pd.experience_years, c.name AS category_name, jr.name AS job_role_name
        FROM users u
        INNER JOIN user_app_access uaa ON uaa.user_id=u.id
            AND uaa.app_key='mwh_local' AND uaa.role_key='staff' AND uaa.status='active'
        INNER JOIN candidate_profiles cp ON cp.user_id=u.id
        LEFT JOIN professional_details pd ON pd.user_id=u.id
        LEFT JOIN job_preferences jp ON jp.user_id=u.id
        LEFT JOIN categories c ON c.id=jp.category_id
        LEFT JOIN job_roles jr ON jr.id=jp.job_role_id
        WHERE u.deleted_at IS NULL";
$params = [];
if ($search !== '') {
    $sql .= ' AND (cp.first_name LIKE ? OR cp.last_name LIKE ? OR cp.city LIKE ? OR u.mobile LIKE ? OR u.email LIKE ? OR jr.name LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term, $term);
}
if (in_array($status, ['pending_verification','verified','rejected','blocked'], true)) {
    $sql .= ' AND u.account_status=?';
    $params[] = $status;
}
$sql .= ' ORDER BY u.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<main class="content">
    <div class="topbar">
        <div>
            <div class="page-kicker"><i class="bi bi-person-badge-fill"></i> MWH Local</div>
            <h1>Local Staff & Chefs</h1>
            <p class="muted">Staff and chefs enabled for the Local Contractor & Chef app.</p>
        </div>
    </div>
    <section class="panel">
        <div class="panel-header">
            <div><h2>Staff Registrations</h2><p class="muted small">Only users with active Local staff access are shown.</p></div>
            <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input name="search" value="<?= e($search) ?>" placeholder="Search name, role, city, mobile..." class="form-control" style="min-width:240px">
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
                <thead><tr><th>Name</th><th>Contact</th><th>Job Role</th><th>Experience</th><th>Location</th><th>Status</th><th>Registered</th></tr></thead>
                <tbody>
                <?php if (!$staff): ?><tr><td colspan="7" class="text-center muted">No Local staff or chefs found.</td></tr>
                <?php else: foreach ($staff as $row): ?>
                    <tr>
                        <td><strong><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Staff #' . $row['id']) ?></strong></td>
                        <td><?= e($row['mobile']) ?><div class="muted small"><?= e($row['email'] ?: '—') ?></div></td>
                        <td><?= e($row['job_role_name'] ?: $row['category_name'] ?: '—') ?></td>
                        <td><?= e($row['experience_years'] !== null ? $row['experience_years'] . ' years' : '—') ?></td>
                        <td><?= e(trim(($row['city'] ?: '') . (($row['country'] ?? '') !== '' ? ', ' . $row['country'] : '')) ?: '—') ?></td>
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
