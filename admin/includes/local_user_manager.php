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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $postedToken)) {
        http_response_code(403);
        exit('Invalid request token. Please reload and try again.');
    }
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $action = (string)($_POST['action'] ?? '');
    if (!$id || $id < 1) {
        $flash = 'Invalid account ID.';
    } else {
        $scope = "id = ? AND role = ? AND deleted_at IS NULL";
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
                $flash = $stmt->rowCount() >= 0 ? 'Account details saved.' : 'No account updated.';
            }
        } elseif ($action === 'status') {
            $newStatus = (string)($_POST['account_status'] ?? '');
            if (!in_array($newStatus, $allowedStatuses, true)) {
                $flash = 'Invalid account status.';
            } else {
                $stmt = $pdo->prepare("UPDATE local_users SET account_status=?, updated_at=NOW() WHERE {$scope}");
                $stmt->execute([$newStatus, $id, $localRole]);
                $flash = 'Account status updated.';
            }
        } elseif ($action === 'delete') {
            // Soft-delete only; the account record remains recoverable in the database.
            $stmt = $pdo->prepare("UPDATE local_users SET deleted_at=NOW(), updated_at=NOW() WHERE {$scope}");
            $stmt->execute([$id, $localRole]);
            $flash = 'Account soft-deleted.';
        } else {
            $flash = 'Unknown action.';
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$status = (string)($_GET['status'] ?? 'all');
$detailId = filter_var($_GET['view'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$baseSql = "SELECT id,name,mobile,email,aadhaar_number,account_status,created_at,updated_at FROM local_users WHERE role=? AND deleted_at IS NULL";
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

$selected = null;
if ($detailId > 0 || $editId > 0) {
    $stmt = $pdo->prepare('SELECT id,name,mobile,email,aadhaar_number,account_status,created_at,updated_at FROM local_users WHERE id=? AND role=? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$detailId ?: $editId, $localRole]);
    $selected = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$pageTitle = $localRole === 'staff' ? 'Local Staff & Chefs' : 'Local Contractors';
$heading = $localRole === 'staff' ? 'Local Staff & Chefs' : 'Local Contractors';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<main class="content">
 <div class="topbar"><div><div class="page-kicker"><i class="bi bi-person-badge-fill"></i> MWH Local</div><h1><?= $e($heading) ?></h1><p class="muted">Role-scoped accounts from local_users.</p></div></div>
 <?php if ($flash !== ''): ?><div class="alert alert-info" role="status"><?= $e($flash) ?></div><?php endif; ?>
 <?php if ($selected && $detailId): ?>
 <section class="panel"><div class="panel-header"><h2>Account Details #<?= (int)$selected['id'] ?></h2><a class="button-secondary" href="<?= $e(basename($_SERVER['PHP_SELF'])) ?>">Back to list</a></div>
 <dl class="row"><dt class="col-sm-3">Name</dt><dd class="col-sm-9"><?= $e($selected['name']) ?></dd><dt class="col-sm-3">Mobile</dt><dd class="col-sm-9"><?= $e($selected['mobile']) ?></dd><dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= $e($selected['email'] ?: '—') ?></dd><dt class="col-sm-3">Aadhaar Number</dt><dd class="col-sm-9"><?= $e($selected['aadhaar_number'] ?: '—') ?></dd><dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= $e($selected['account_status']) ?></dd><dt class="col-sm-3">Registered</dt><dd class="col-sm-9"><?= $e($selected['created_at']) ?></dd><dt class="col-sm-3">Updated</dt><dd class="col-sm-9"><?= $e($selected['updated_at']) ?></dd></dl></section>
 <?php elseif ($selected && $editId): ?>
 <section class="panel"><div class="panel-header"><h2>Edit Account #<?= (int)$selected['id'] ?></h2></div><form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="save"><div class="row g-3"><div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= $e($selected['name']) ?>"></div><div class="col-md-6"><label class="form-label">Mobile</label><input class="form-control" name="mobile" required value="<?= $e($selected['mobile']) ?>"></div><div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= $e($selected['email']) ?>"></div><div class="col-md-6"><label class="form-label">Aadhaar Number</label><input class="form-control" name="aadhaar_number" value="<?= $e($selected['aadhaar_number']) ?>"></div></div><div class="mt-3"><button class="button-primary" type="submit">Save Changes</button> <a class="button-secondary" href="<?= $e(basename($_SERVER['PHP_SELF'])) ?>">Cancel</a></div></form></section>
 <?php endif; ?>
 <section class="panel"><div class="panel-header"><div><h2><?= $e($localRole === 'staff' ? 'Staff Registrations' : 'Contractor Registrations') ?></h2><p class="muted small">Only <?= $e($localRole) ?> accounts are shown.</p></div><form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><input name="search" value="<?= $e($search) ?>" placeholder="Search name, mobile, email, Aadhaar..." class="form-control" style="min-width:240px"><select name="status" class="form-select" style="width:190px"><option value="all">All statuses</option><?php foreach ($allowedStatuses as $s): ?><option value="<?= $e($s) ?>" <?= $status===$s?'selected':'' ?>><?= $e(ucwords(str_replace('_',' ',$s))) ?></option><?php endforeach; ?></select><button class="button-secondary" type="submit">Filter</button></form></div>
 <div class="table-wrap"><table class="data-table"><thead><tr><th>Name</th><th>Contact</th><th>Aadhaar</th><th>Status</th><th>Registered</th><th>Actions</th></tr></thead><tbody>
 <?php if (!$accounts): ?><tr><td colspan="6" class="text-center muted">No local accounts found.</td></tr><?php else: foreach ($accounts as $row): ?><tr><td><strong><?= $e($row['name'] ?: ucfirst($localRole).' #'.$row['id']) ?></strong></td><td><?= $e($row['mobile']) ?><div class="muted small"><?= $e($row['email'] ?: '—') ?></div></td><td><?= $e($row['aadhaar_number'] ?: '—') ?></td><td><span class="status-badge <?= $e($row['account_status']) ?>"><?= $e(ucwords(str_replace('_',' ',$row['account_status']))) ?></span></td><td><?= $e($row['created_at']) ?></td><td style="min-width:260px"><a class="button-secondary" href="?view=<?= (int)$row['id'] ?>">View</a> <a class="button-secondary" href="?edit=<?= (int)$row['id'] ?>">Edit</a><form method="post" style="display:inline-flex;gap:4px;margin-top:5px"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="status"><select name="account_status" class="form-select form-select-sm" aria-label="Set account status"><?php foreach ($allowedStatuses as $s): ?><option value="<?= $e($s) ?>" <?= $row['account_status']===$s?'selected':'' ?>><?= $e(ucwords(str_replace('_',' ',$s))) ?></option><?php endforeach; ?></select><button class="button-secondary" type="submit">Set</button></form><form method="post" style="display:inline" onsubmit="return confirm('Soft-delete this local account?')"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="delete"><button class="button-secondary" type="submit">Delete</button></form></td></tr><?php endforeach; endif; ?>
 </tbody></table></div></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
