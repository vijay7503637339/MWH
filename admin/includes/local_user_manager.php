<?php
declare(strict_types=1);

// Shared manager for local_users only. Caller must set $localRole ('staff' or 'contractor').
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../../api/config/database.php';

if (!isset($localRole) || !in_array($localRole, ['staff', 'contractor'], true)) {
    http_response_code(400);
    exit('Invalid local account role.');
}
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['local_user_csrf'])) $_SESSION['local_user_csrf'] = bin2hex(random_bytes(32));
$csrf = (string)$_SESSION['local_user_csrf'];
$flash = '';
$allowedStatuses = ['pending_verification', 'verified', 'rejected', 'blocked'];
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$roleLabel = $localRole === 'staff' ? 'Staff & Chefs' : 'Contractors';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token. Please reload and try again.');
    }
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $action = (string)($_POST['action'] ?? '');
    if (!$id || $id < 1) {
        $flash = 'Invalid account ID.';
    } else {
        $scope = 'id = ? AND role = ? AND deleted_at IS NULL';
        if ($action === 'save') {
            $name = trim((string)($_POST['name'] ?? ''));
            $mobile = trim((string)($_POST['mobile'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $aadhaar = trim((string)($_POST['aadhaar_number'] ?? ''));
            if ($name === '' || $mobile === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $flash = 'Name and mobile are required; email must be valid.';
            } else {
                $stmt = $pdo->prepare("UPDATE local_users SET name=?, mobile=?, email=?, aadhaar_number=?, updated_at=NOW() WHERE {$scope}");
                $stmt->execute([$name, $mobile, $email !== '' ? $email : null, $aadhaar !== '' ? $aadhaar : null, $id, $localRole]);
                $flash = 'Account details saved.';
            }
        } elseif ($action === 'status') {
            $newStatus = (string)($_POST['account_status'] ?? '');
            if (!in_array($newStatus, $allowedStatuses, true)) {
                $flash = 'Invalid account status.';
            } else {
                $stmt = $pdo->prepare("UPDATE local_users SET account_status=?, updated_at=NOW() WHERE {$scope}");
                $stmt->execute([$newStatus, $id, $localRole]);
                $flash = $stmt->rowCount() ? 'Account status updated.' : 'No account changes were needed.';
            }
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("UPDATE local_users SET deleted_at=NOW(), updated_at=NOW() WHERE {$scope}");
            $stmt->execute([$id, $localRole]);
            $flash = $stmt->rowCount() ? 'Account moved to deleted records.' : 'Account was not found.';
        } else {
            $flash = 'Unknown action.';
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$status = (string)($_GET['status'] ?? 'all');
$detailId = filter_var($_GET['view'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$baseSql = 'SELECT id,name,mobile,email,aadhaar_number,account_status,created_at,updated_at FROM local_users WHERE role=? AND deleted_at IS NULL';
$params = [$localRole];
if ($search !== '') {
    $baseSql .= ' AND (name LIKE ? OR mobile LIKE ? OR email LIKE ? OR aadhaar_number LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}
if (in_array($status, $allowedStatuses, true)) {
    $baseSql .= ' AND account_status=?';
    $params[] = $status;
}
$baseSql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($baseSql);
$stmt->execute($params);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$counts = ['total' => count($accounts), 'verified' => 0, 'pending_verification' => 0, 'blocked' => 0, 'rejected' => 0];
foreach ($accounts as $account) {
    $key = (string)($account['account_status'] ?? '');
    if (isset($counts[$key])) $counts[$key]++;
}
$selected = null;
if ($detailId > 0 || $editId > 0) {
    $stmt = $pdo->prepare('SELECT id,name,mobile,email,aadhaar_number,account_status,created_at,updated_at FROM local_users WHERE id=? AND role=? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$detailId ?: $editId, $localRole]);
    $selected = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$pageTitle = 'Local ' . $roleLabel;
$heading = 'Local ' . $roleLabel;
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<style>
.local-accounts{--la-ink:#14213d;--la-muted:#718096;--la-line:#e7edf5;--la-blue:#1e5eff;color:var(--la-ink)}
.local-accounts .hero{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:26px 28px;margin-bottom:22px;border-radius:22px;background:linear-gradient(120deg,#102653 0%,#1e5eff 68%,#6c8fff 100%);color:#fff;box-shadow:0 16px 38px #163d8526}
.local-accounts .hero .page-kicker{color:#d7e3ff;letter-spacing:.12em;text-transform:uppercase;font-size:11px;font-weight:800}
.local-accounts .hero h1{margin:7px 0;font-size:clamp(25px,3vw,34px);font-weight:800;color:#fff}
.local-accounts .hero p{margin:0;color:#e2eaff}
.local-accounts .hero-mark{width:58px;height:58px;border-radius:18px;background:#ffffff24;display:grid;place-items:center;font-size:27px;flex:0 0 auto}
.local-accounts .stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}
.local-accounts .stat-card{background:#fff;border:1px solid var(--la-line);border-radius:17px;padding:17px 19px;box-shadow:0 5px 18px #10265308}
.local-accounts .stat-label{font-size:12px;color:var(--la-muted);font-weight:700;letter-spacing:.03em}
.local-accounts .stat-value{font-size:28px;font-weight:800;margin-top:5px;line-height:1.2}
.local-accounts .stat-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:7px;background:var(--la-blue)}
.local-accounts .panel{border:1px solid var(--la-line);border-radius:20px;background:#fff;box-shadow:0 8px 28px #1026530a;overflow:hidden;margin-bottom:22px}
.local-accounts .panel-header{padding:21px 23px;border-bottom:1px solid var(--la-line);display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}
.local-accounts .panel-header h2{font-size:19px;font-weight:800;margin:0;color:var(--la-ink)}
.local-accounts .panel-header p{margin:5px 0 0}
.local-accounts .filterbar{padding:18px 22px;background:#f8faff;border-bottom:1px solid var(--la-line);display:flex;gap:11px;align-items:end;flex-wrap:wrap}
.local-accounts .filterbar .form-control,.local-accounts .filterbar .form-select{min-height:43px;border-color:#dce5f2;border-radius:11px}
.local-accounts .search-field{flex:1 1 280px;min-width:210px}
.local-accounts .table-wrap{overflow-x:auto}
.local-accounts .data-table{width:100%;border-collapse:separate;border-spacing:0;min-width:920px}
.local-accounts .data-table th{background:#fbfcff;color:#718096;text-transform:uppercase;letter-spacing:.07em;font-size:10px;font-weight:800;padding:14px 17px;border-bottom:1px solid var(--la-line);white-space:nowrap}
.local-accounts .data-table td{padding:16px 17px;border-bottom:1px solid #edf1f7;vertical-align:middle;font-size:13px}
.local-accounts .data-table tbody tr:hover{background:#f9fbff}
.local-accounts .person-cell{display:flex;align-items:center;gap:11px;min-width:180px}
.local-accounts .avatar{width:39px;height:39px;display:grid;place-items:center;border-radius:13px;background:#eaf0ff;color:#1e5eff;font-weight:800;font-size:14px;flex:0 0 auto}
.local-accounts .person-name{font-weight:800;color:#182746}
.local-accounts .muted{color:var(--la-muted)}
.local-accounts .status-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:800;background:#f0f3f8;color:#5c6a80;white-space:nowrap}
.local-accounts .status-badge.verified{background:#e5f8ef;color:#16804a}
.local-accounts .status-badge.pending_verification{background:#fff4d9;color:#966200}
.local-accounts .status-badge.blocked{background:#ffe7e7;color:#b42332}
.local-accounts .status-badge.rejected{background:#f0eaff;color:#7042b8}
.local-accounts .button-primary,.local-accounts .button-secondary{display:inline-flex;align-items:center;justify-content:center;border-radius:10px;padding:9px 13px;font-size:12px;font-weight:800;text-decoration:none;border:1px solid transparent;white-space:nowrap}
.local-accounts .button-primary{background:#1e5eff;color:#fff}
.local-accounts .button-secondary{background:#fff;color:#31415f;border-color:#dce5f2}
.local-accounts .button-secondary:hover{background:#f2f6ff;border-color:#b9ccf6}
.local-accounts .row-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.local-accounts .inline-status{display:flex;gap:5px;align-items:center;margin-top:7px}
.local-accounts .inline-status .form-select{width:155px;font-size:11px;border-radius:9px;border-color:#dce5f2}
.local-accounts .detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:22px}
.local-accounts .detail-item{padding:14px 16px;background:#f8faff;border:1px solid var(--la-line);border-radius:13px;min-width:0}
.local-accounts .detail-item dt{font-size:11px;color:var(--la-muted);margin-bottom:6px}
.local-accounts .detail-item dd{font-size:14px;font-weight:700;margin:0;overflow-wrap:anywhere}
.local-accounts .edit-form{padding:22px}
.local-accounts .edit-form .form-control{border-radius:11px;min-height:43px}
.local-accounts .empty-state{text-align:center;padding:42px 18px;color:var(--la-muted)}
.local-accounts .empty-state i{font-size:28px;color:#91a7cc;display:block;margin-bottom:10px}
@media(max-width:900px){.local-accounts .stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.local-accounts .hero{padding:22px}}
@media(max-width:560px){.local-accounts .stat-grid{grid-template-columns:1fr 1fr;gap:9px}.local-accounts .stat-card{padding:13px}.local-accounts .stat-value{font-size:23px}.local-accounts .hero-mark{display:none}.local-accounts .detail-grid{grid-template-columns:1fr}}
</style>
<main class="content local-accounts">
 <section class="hero"><div><div class="page-kicker"><i class="bi bi-grid-1x2-fill"></i> MWH LOCAL · ACCOUNT MANAGEMENT</div><h1><?= $e($heading) ?></h1><p>Review registrations, verify accounts and manage local <?= $e($localRole) ?> profiles.</p></div><div class="hero-mark"><i class="bi bi-person-vcard-fill"></i></div></section>
 <?php if ($flash !== ''): ?><div class="alert alert-info" role="status"><?= $e($flash) ?></div><?php endif; ?>
 <section class="stat-grid" aria-label="Account summary">
  <div class="stat-card"><div class="stat-label"><span class="stat-dot"></span>Matching accounts</div><div class="stat-value"><?= number_format($counts['total']) ?></div></div>
  <div class="stat-card"><div class="stat-label"><span class="stat-dot" style="background:#16a765"></span>Verified</div><div class="stat-value"><?= number_format($counts['verified']) ?></div></div>
  <div class="stat-card"><div class="stat-label"><span class="stat-dot" style="background:#e6a008"></span>Awaiting review</div><div class="stat-value"><?= number_format($counts['pending_verification']) ?></div></div>
  <div class="stat-card"><div class="stat-label"><span class="stat-dot" style="background:#df4857"></span>Blocked</div><div class="stat-value"><?= number_format($counts['blocked']) ?></div></div>
 </section>
 <?php if ($selected && $detailId): ?>
 <section class="panel"><div class="panel-header"><div><h2><i class="bi bi-person-lines-fill"></i> Account profile #<?= (int)$selected['id'] ?></h2><p class="muted small">Full details for this local account.</p></div><a class="button-secondary" href="<?= $e(basename($_SERVER['PHP_SELF'])) ?>"><i class="bi bi-arrow-left"></i>&nbsp; Back to list</a></div>
 <dl class="detail-grid"><div class="detail-item"><dt>Name</dt><dd><?= $e($selected['name'] ?: '—') ?></dd></div><div class="detail-item"><dt>Mobile</dt><dd><?= $e($selected['mobile'] ?: '—') ?></dd></div><div class="detail-item"><dt>Email</dt><dd><?= $e($selected['email'] ?: '—') ?></dd></div><div class="detail-item"><dt>Aadhaar number</dt><dd><?= $e($selected['aadhaar_number'] ?: '—') ?></dd></div><div class="detail-item"><dt>Account status</dt><dd><span class="status-badge <?= $e($selected['account_status']) ?>"><?= $e(ucwords(str_replace('_',' ',$selected['account_status']))) ?></span></dd></div><div class="detail-item"><dt>Registered</dt><dd><?= $e($selected['created_at'] ?: '—') ?></dd></div><div class="detail-item"><dt>Last updated</dt><dd><?= $e($selected['updated_at'] ?: '—') ?></dd></div></dl></section>
 <?php elseif ($selected && $editId): ?>
 <section class="panel"><div class="panel-header"><div><h2><i class="bi bi-pencil-square"></i> Edit account #<?= (int)$selected['id'] ?></h2><p class="muted small">Update the account's contact and identity details.</p></div></div><form method="post" class="edit-form"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="save"><div class="row g-3"><div class="col-md-6"><label class="form-label" for="local-name">Name</label><input id="local-name" class="form-control" name="name" required value="<?= $e($selected['name']) ?>"></div><div class="col-md-6"><label class="form-label" for="local-mobile">Mobile</label><input id="local-mobile" class="form-control" name="mobile" required inputmode="tel" value="<?= $e($selected['mobile']) ?>"></div><div class="col-md-6"><label class="form-label" for="local-email">Email</label><input id="local-email" class="form-control" type="email" name="email" value="<?= $e($selected['email']) ?>"></div><div class="col-md-6"><label class="form-label" for="local-aadhaar">Aadhaar number</label><input id="local-aadhaar" class="form-control" name="aadhaar_number" inputmode="numeric" value="<?= $e($selected['aadhaar_number']) ?>"></div></div><div class="mt-4 d-flex gap-2"><button class="button-primary" type="submit"><i class="bi bi-check2-circle"></i>&nbsp; Save changes</button><a class="button-secondary" href="<?= $e(basename($_SERVER['PHP_SELF'])) ?>">Cancel</a></div></form></section>
 <?php endif; ?>
 <section class="panel"><div class="panel-header"><div><h2><i class="bi bi-people-fill"></i> <?= $e($localRole === 'staff' ? 'Staff registrations' : 'Contractor registrations') ?></h2><p class="muted small">Role-restricted listing · <?= number_format(count($accounts)) ?> result(s)</p></div></div>
 <form method="get" class="filterbar" role="search"><div class="search-field"><label class="form-label" for="local-search">Search accounts</label><input id="local-search" name="search" value="<?= $e($search) ?>" placeholder="Name, mobile, email or Aadhaar" class="form-control"></div><div style="min-width:190px"><label class="form-label" for="local-status">Account status</label><select id="local-status" name="status" class="form-select"><option value="all">All statuses</option><?php foreach ($allowedStatuses as $s): ?><option value="<?= $e($s) ?>" <?= $status===$s?'selected':'' ?>><?= $e(ucwords(str_replace('_',' ',$s))) ?></option><?php endforeach; ?></select></div><button class="button-primary" type="submit"><i class="bi bi-funnel-fill"></i>&nbsp; Apply</button><a class="button-secondary" href="<?= $e(basename($_SERVER['PHP_SELF'])) ?>">Reset</a></form>
 <div class="table-wrap"><table class="data-table"><thead><tr><th>Account</th><th>Contact</th><th>Aadhaar</th><th>Status</th><th>Registered</th><th>Manage</th></tr></thead><tbody>
 <?php if (!$accounts): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-person-x"></i><strong>No matching local accounts</strong><div>Try changing the search term or status filter.</div></div></td></tr><?php else: foreach ($accounts as $row): $initial = mb_strtoupper(mb_substr(trim((string)($row['name'] ?? '')) ?: $localRole, 0, 1)); ?><tr><td><div class="person-cell"><div class="avatar"><?= $e($initial) ?></div><div><div class="person-name"><?= $e($row['name'] ?: ucfirst($localRole).' #'.$row['id']) ?></div><div class="muted small">Account #<?= (int)$row['id'] ?></div></div></div></td><td><strong><?= $e($row['mobile'] ?: '—') ?></strong><div class="muted small"><?= $e($row['email'] ?: 'No email added') ?></div></td><td><?= $e($row['aadhaar_number'] ?: '—') ?></td><td><span class="status-badge <?= $e($row['account_status']) ?>"><i class="bi bi-circle-fill" style="font-size:6px"></i><?= $e(ucwords(str_replace('_',' ',$row['account_status']))) ?></span></td><td><?= $e($row['created_at'] ?: '—') ?></td><td><div class="row-actions"><a class="button-secondary" href="?view=<?= (int)$row['id'] ?>" aria-label="View account <?= (int)$row['id'] ?>"><i class="bi bi-eye"></i> View</a><a class="button-secondary" href="?edit=<?= (int)$row['id'] ?>" aria-label="Edit account <?= (int)$row['id'] ?>"><i class="bi bi-pencil"></i> Edit</a><form method="post" class="inline-status"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="status"><select name="account_status" class="form-select form-select-sm" aria-label="Set account status"><?php foreach ($allowedStatuses as $s): ?><option value="<?= $e($s) ?>" <?= $row['account_status']===$s?'selected':'' ?>><?= $e(ucwords(str_replace('_',' ',$s))) ?></option><?php endforeach; ?></select><button class="button-secondary" type="submit" aria-label="Update status">Set</button></form><form method="post" onsubmit="return confirm('Move this local account to deleted records?')"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="delete"><button class="button-secondary" type="submit" aria-label="Delete account <?= (int)$row['id'] ?>"><i class="bi bi-trash3"></i></button></form></div></td></tr><?php endforeach; endif; ?>
 </tbody></table></div></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
