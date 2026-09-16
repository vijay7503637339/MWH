<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/account/delete.php';

$pageTitle = 'Account Deletion Requests';
$admin = currentAdmin();
if (empty($_SESSION['deletion_requests_csrf'])) $_SESSION['deletion_requests_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['deletion_requests_csrf'];
$errors = [];
$success = $_SESSION['deletion_requests_success'] ?? '';
unset($_SESSION['deletion_requests_success']);

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirectDeletionRequests(): never { header('Location: account_deletion_requests.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['deletion_requests_csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Invalid request token.');
    }

    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $note = trim((string)($_POST['admin_note'] ?? ''));

    try {
        if ($requestId <= 0) throw new RuntimeException('Invalid deletion request.');
        $stmt = $pdo->prepare('SELECT * FROM account_deletion_requests WHERE id=? LIMIT 1');
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) throw new RuntimeException('Deletion request not found.');
        if ((string)$request['status'] !== 'pending') throw new RuntimeException('This request has already been processed.');

        if ($action === 'reject') {
            $update = $pdo->prepare("UPDATE account_deletion_requests SET status='rejected', admin_note=?, processed_at=NOW(), processed_by_admin_id=? WHERE id=? AND status='pending'");
            $update->execute([$note !== '' ? $note : null, (int)$admin['id'], $requestId]);
            $_SESSION['deletion_requests_success'] = "Deletion request #{$requestId} rejected.";
            redirectDeletionRequests();
        }

        if ($action !== 'approve') throw new RuntimeException('Invalid action.');
        if (empty($request['user_id'])) throw new RuntimeException('No matching active account was found for this request. Verify the candidate before approving.');

        // deleteCandidateAccount() performs the actual permanent account/data deletion.
        $result = deleteCandidateAccount($pdo, (int)$request['user_id']);

        $update = $pdo->prepare("UPDATE account_deletion_requests SET status='completed', admin_note=?, processed_at=NOW(), processed_by_admin_id=? WHERE id=? AND status='pending'");
        $update->execute([$note !== '' ? $note : null, (int)$admin['id'], $requestId]);

        try {
            $audit = $pdo->prepare('INSERT INTO audit_logs(admin_user_id,action,entity_type,entity_id,new_values,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)');
            $audit->execute([
                (int)$admin['id'], 'account_deletion.completed', 'account_deletion_request', $requestId,
                json_encode(['user_id'=>(int)$request['user_id'],'files_deleted'=>(int)($result['files_deleted'] ?? 0)], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (Throwable $auditError) {
            error_log('MWH deletion audit failed: ' . $auditError->getMessage());
        }

        $_SESSION['deletion_requests_success'] = "Deletion request #{$requestId} completed and the candidate account was permanently deleted.";
        redirectDeletionRequests();
    } catch (Throwable $ex) {
        $errors[] = $ex->getMessage();
    }
}

$filter = (string)($_GET['status'] ?? 'pending');
$allowedFilters = ['pending','completed','rejected','all'];
if (!in_array($filter, $allowedFilters, true)) $filter = 'pending';

$where = '';
$params = [];
if ($filter !== 'all') {
    $where = 'WHERE r.status=?';
    $params[] = $filter;
}

$listStmt = $pdo->prepare(
    "SELECT r.*, CONCAT(COALESCE(cp.first_name,''),' ',COALESCE(cp.last_name,'')) AS account_name,
            u.account_status, a.name AS processed_by_name
     FROM account_deletion_requests r
     LEFT JOIN users u ON u.id=r.user_id
     LEFT JOIN candidate_profiles cp ON cp.user_id=r.user_id
     LEFT JOIN admin_users a ON a.id=r.processed_by_admin_id
     {$where}
     ORDER BY CASE WHEN r.status='pending' THEN 0 ELSE 1 END, r.requested_at DESC, r.id DESC
     LIMIT 200"
);
$listStmt->execute($params);
$requests = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$counts = [];
$countRows = $pdo->query("SELECT status, COUNT(*) AS total FROM account_deletion_requests GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
foreach ($countRows as $row) $counts[(string)$row['status']] = (int)$row['total'];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<style>
.deletion-page{max-width:1500px;margin:0 auto}.deletion-heading{margin-bottom:20px}.deletion-heading .kicker{display:flex;align-items:center;gap:8px;color:#b42318;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}.deletion-heading h1{font-size:31px;margin:0 0 6px}.deletion-heading p{color:#7a8498;font-size:13px}.deletion-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}.deletion-stat{background:#fff;border:1px solid #e7ebf2;border-radius:14px;padding:16px}.deletion-stat span{display:block;color:#8b95a7;font-size:10px;text-transform:uppercase;font-weight:800;letter-spacing:.06em}.deletion-stat strong{display:block;font-size:25px;margin-top:5px;color:#172033}.deletion-card{background:#fff;border:1px solid #e7ebf2;border-radius:16px;box-shadow:0 4px 14px rgba(20,35,60,.04);overflow:hidden}.deletion-toolbar{display:flex;justify-content:space-between;align-items:center;padding:17px 20px;border-bottom:1px solid #edf0f5;gap:12px}.filters{display:flex;gap:7px;flex-wrap:wrap}.filters a{padding:7px 11px;border-radius:999px;background:#f3f5f9;color:#657188;text-decoration:none;font-size:10px;font-weight:800}.filters a.active{background:#4169e1;color:#fff}.table-wrap{overflow:auto}.data-table{width:100%;border-collapse:collapse;min-width:1050px}.data-table th{background:#fafbfc;color:#7b8699;font-size:10px;text-transform:uppercase;letter-spacing:.05em;text-align:left;padding:12px 14px;border-bottom:1px solid #e8ecf2;white-space:nowrap}.data-table td{padding:13px 14px;border-bottom:1px solid #eef1f5;color:#39455a;font-size:11px;vertical-align:top}.data-table tr:last-child td{border-bottom:0}.person strong{display:block;color:#1d293d;font-size:12px}.person span{display:block;color:#8a94a5;margin-top:3px}.reason{max-width:220px;color:#66738a;line-height:1.45}.status{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:9px;font-weight:800;text-transform:uppercase}.status.pending{background:#fff5df;color:#9a6200}.status.completed{background:#edf9f3;color:#087b50}.status.rejected{background:#fff1f2;color:#b42318}.actions{display:flex;gap:6px;flex-wrap:wrap}.actions form{display:inline}.btn-small{border:0;border-radius:8px;padding:7px 9px;font-size:10px;font-weight:800;cursor:pointer}.btn-approve{background:#087b50;color:#fff}.btn-reject{background:#fff1f2;color:#b42318}.btn-small:disabled{opacity:.6;cursor:not-allowed}.note-input{width:150px;border:1px solid #dfe5ee;border-radius:8px;padding:7px 8px;font:inherit;font-size:10px}.alert{padding:11px 14px;border-radius:10px;margin-bottom:15px;font-size:12px}.alert.success{background:#edf9f3;color:#087b50;border:1px solid #c7eddc}.alert.error{background:#fff3f3;color:#b42318;border:1px solid #f1d0d3}.empty{padding:45px;text-align:center;color:#8b95a7;font-size:12px}@media(max-width:900px){.deletion-stats{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.deletion-stats{grid-template-columns:1fr 1fr}.deletion-heading h1{font-size:26px}}
</style>

<main class="content deletion-page">
<div class="deletion-heading"><div class="kicker"><i class="bi bi-person-x-fill"></i> Privacy & Account Management</div><h1>Account Deletion Requests</h1><p>Review external deletion requests submitted from the public MWH account-deletion page. Approving a request permanently deletes the candidate account and associated data.</p></div>
<?php if ($success): ?><div class="alert success"><i class="bi bi-check-circle-fill me-1"></i><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert error"><i class="bi bi-exclamation-circle-fill me-1"></i><?= e($error) ?></div><?php endforeach; ?>

<div class="deletion-stats">
<div class="deletion-stat"><span>Pending</span><strong><?= (int)($counts['pending'] ?? 0) ?></strong></div>
<div class="deletion-stat"><span>Completed</span><strong><?= (int)($counts['completed'] ?? 0) ?></strong></div>
<div class="deletion-stat"><span>Rejected</span><strong><?= (int)($counts['rejected'] ?? 0) ?></strong></div>
<div class="deletion-stat"><span>Total</span><strong><?= array_sum($counts) ?></strong></div>
</div>

<section class="deletion-card">
<div class="deletion-toolbar"><strong>Requests</strong><div class="filters">
<a class="<?= $filter==='pending'?'active':'' ?>" href="?status=pending">Pending</a>
<a class="<?= $filter==='completed'?'active':'' ?>" href="?status=completed">Completed</a>
<a class="<?= $filter==='rejected'?'active':'' ?>" href="?status=rejected">Rejected</a>
<a class="<?= $filter==='all'?'active':'' ?>" href="?status=all">All</a>
</div></div>
<div class="table-wrap">
<?php if (!$requests): ?><div class="empty">No account deletion requests found.</div>
<?php else: ?>
<table class="data-table"><thead><tr><th>Request</th><th>Candidate</th><th>Contact</th><th>Reason</th><th>Status</th><th>Requested</th><th>Admin Action</th></tr></thead><tbody>
<?php foreach ($requests as $row): ?>
<tr>
<td><strong>#<?= (int)$row['id'] ?></strong><div style="color:#9aa3b2;margin-top:4px">ID <?= e((string)($row['user_id'] ?? '—')) ?></div></td>
<td class="person"><strong><?= e(trim((string)($row['account_name'] ?? '')) ?: $row['full_name']) ?></strong><span>Submitted: <?= e($row['full_name']) ?></span></td>
<td><div><?= e($row['mobile']) ?></div><div style="margin-top:4px;color:#8a94a5"><?= e($row['email'] ?: 'No email') ?></div></td>
<td class="reason"><?= e($row['reason'] ?: 'No reason provided') ?></td>
<td><span class="status <?= e($row['status']) ?>"><?= e($row['status']) ?></span><?php if ($row['admin_note']): ?><div style="margin-top:6px;color:#8a94a5;max-width:180px"><?= e($row['admin_note']) ?></div><?php endif; ?></td>
<td><?= e($row['requested_at']) ?><?php if ($row['processed_at']): ?><div style="margin-top:4px;color:#8a94a5">Processed: <?= e($row['processed_at']) ?></div><?php endif; ?></td>
<td>
<?php if ($row['status']==='pending'): ?>
<form method="post" onsubmit="return confirm('Approve this request? The candidate account and associated data will be permanently deleted. This cannot be undone.');" style="margin-bottom:6px"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="approve"><input class="note-input" name="admin_note" maxlength="500" placeholder="Optional note"><button class="btn-small btn-approve" type="submit">Approve & Delete</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="reject"><input class="note-input" name="admin_note" maxlength="500" placeholder="Rejection note"><button class="btn-small btn-reject" type="submit">Reject</button></form>
<?php else: ?><span style="color:#8a94a5;font-size:10px">Processed by <?= e($row['processed_by_name'] ?: 'Admin') ?></span><?php endif; ?>
</td>
</tr>
<?php endforeach; ?></tbody></table>
<?php endif; ?>
</div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
