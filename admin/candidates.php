<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$pageTitle = 'Candidates';
$admin = currentAdmin();

if (empty($_SESSION['candidate_csrf'])) {
    $_SESSION['candidate_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['candidate_csrf'];
$errors = [];
$success = $_SESSION['candidate_success'] ?? '';
unset($_SESSION['candidate_success']);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectCandidates(?int $viewId = null): never
{
    header('Location: candidates.php' . ($viewId ? '?view=' . $viewId : ''));
    exit;
}

function requireCandidateCsrf(string $token): void
{
    if (!hash_equals($_SESSION['candidate_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid request token. Please go back and try again.');
    }
}

function auditCandidate(PDO $pdo, array $admin, string $action, int $userId, ?array $old = null, ?array $new = null): void
{
    $stmt = $pdo->prepare('INSERT INTO audit_logs (admin_user_id,user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $admin['id'], $userId, $action, 'candidate', $userId,
        $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
        $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCandidateCsrf((string)($_POST['csrf'] ?? ''));
    $action = (string)($_POST['action'] ?? '');
    $userId = (int)($_POST['user_id'] ?? 0);
    $documentId = (int)($_POST['document_id'] ?? 0);

    try {
        if ($userId <= 0) throw new RuntimeException('User not found.');
        $find = $pdo->prepare('SELECT id, account_status FROM users WHERE id=? AND deleted_at IS NULL LIMIT 1');
        $find->execute([$userId]);
        $candidate = $find->fetch(PDO::FETCH_ASSOC);
        if (!$candidate) throw new RuntimeException('User not found.');

        if ($action === 'verify') {
            $stmt = $pdo->prepare("UPDATE users SET account_status='verified', rejection_reason=NULL, verified_at=NOW(), verified_by_admin_id=?, blocked_at=NULL, blocked_by_admin_id=NULL, block_reason=NULL WHERE id=?");
            $stmt->execute([$admin['id'], $userId]);
            auditCandidate($pdo, $admin, 'candidate.verified', $userId, ['account_status'=>$candidate['account_status']], ['account_status'=>'verified']);
            $_SESSION['candidate_success'] = 'Candidate verified successfully.';
            redirectCandidates($userId);
        }
        if ($action === 'reject') {
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($reason === '') throw new RuntimeException('Rejection reason is required.');
            if (mb_strlen($reason) > 500) throw new RuntimeException('Rejection reason is too long.');
            $stmt = $pdo->prepare("UPDATE users SET account_status='rejected', rejection_reason=?, verified_at=NULL, verified_by_admin_id=NULL WHERE id=?");
            $stmt->execute([$reason, $userId]);
            auditCandidate($pdo, $admin, 'candidate.rejected', $userId, ['account_status'=>$candidate['account_status']], ['account_status'=>'rejected','reason'=>$reason]);
            $_SESSION['candidate_success'] = 'Candidate rejected.';
            redirectCandidates($userId);
        }
        if ($action === 'block') {
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($reason === '') throw new RuntimeException('Block reason is required.');
            if (mb_strlen($reason) > 500) throw new RuntimeException('Block reason is too long.');
            $stmt = $pdo->prepare("UPDATE users SET account_status='blocked', blocked_at=NOW(), blocked_by_admin_id=?, block_reason=? WHERE id=?");
            $stmt->execute([$admin['id'], $reason, $userId]);
            auditCandidate($pdo, $admin, 'candidate.blocked', $userId, ['account_status'=>$candidate['account_status']], ['account_status'=>'blocked','reason'=>$reason]);
            $_SESSION['candidate_success'] = 'Candidate blocked.';
            redirectCandidates($userId);
        }
        if ($action === 'unblock') {
            $stmt = $pdo->prepare("UPDATE users SET account_status='verified', blocked_at=NULL, blocked_by_admin_id=NULL, block_reason=NULL WHERE id=?");
            $stmt->execute([$userId]);
            auditCandidate($pdo, $admin, 'candidate.unblocked', $userId, ['account_status'=>$candidate['account_status']], ['account_status'=>'verified']);
            $_SESSION['candidate_success'] = 'Candidate unblocked.';
            redirectCandidates($userId);
        }
        if ($action === 'document_review') {
            if ($documentId <= 0) throw new RuntimeException('Document not found.');
            $reviewStatus = (string)($_POST['review_status'] ?? '');
            if (!in_array($reviewStatus, ['approved','rejected'], true)) throw new RuntimeException('Invalid document review status.');
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($reviewStatus === 'rejected' && $reason === '') throw new RuntimeException('Rejection reason is required.');
            $stmt = $pdo->prepare('UPDATE candidate_documents SET review_status=?, rejection_reason=?, reviewed_at=NOW(), reviewed_by_admin_id=? WHERE id=? AND user_id=?');
            $stmt->execute([$reviewStatus, $reviewStatus === 'rejected' ? $reason : null, $admin['id'], $documentId, $userId]);
            auditCandidate($pdo, $admin, 'candidate.document_' . $reviewStatus, $userId, null, ['document_id'=>$documentId,'review_status'=>$reviewStatus]);
            $_SESSION['candidate_success'] = 'Document review updated.';
            redirectCandidates($userId);
        }
        throw new RuntimeException('Invalid candidate action.');
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$viewId = (int)($_GET['view'] ?? 0);
$viewCandidate = null;
$viewDocuments = [];
$viewPayment = null;
$viewPreference = null;
$viewProfessional = null;

if ($viewId > 0) {
    $stmt = $pdo->prepare("SELECT u.*, cp.first_name, cp.last_name, cp.date_of_birth, cp.gender, cp.full_address, cp.city, cp.country, cp.postcode, cp.referral_code,
        pd.experience_years, pd.previous_company_name,
        jp.max_travel_distance_km, jp.preferred_working_hours, c.name AS category_name, jr.name AS job_role_name,
        cpp.payment_type, cpp.account_holder_name, cpp.bank_name, cpp.account_number_encrypted, cpp.ifsc_code, cpp.branch_name, cpp.upi_id
        FROM users u
        LEFT JOIN candidate_profiles cp ON cp.user_id=u.id
        LEFT JOIN professional_details pd ON pd.user_id=u.id
        LEFT JOIN job_preferences jp ON jp.user_id=u.id
        LEFT JOIN categories c ON c.id=jp.category_id
        LEFT JOIN job_roles jr ON jr.id=jp.job_role_id
        LEFT JOIN candidate_payment_profiles cpp ON cpp.user_id=u.id
        WHERE u.id=? AND u.deleted_at IS NULL LIMIT 1");
    $stmt->execute([$viewId]);
    $viewCandidate = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($viewCandidate) {
        $docs = $pdo->prepare('SELECT * FROM candidate_documents WHERE user_id=? AND deleted_at IS NULL ORDER BY created_at DESC');
        $docs->execute([$viewId]);
        $viewDocuments = $docs->fetchAll(PDO::FETCH_ASSOC);
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'all');
$sql = "SELECT u.id, u.mobile, u.email, u.account_status, u.created_at, u.verified_at, u.rejection_reason, u.block_reason,
        cp.first_name, cp.last_name, cp.city, cp.country,
        jp.max_travel_distance_km, c.name AS category_name, jr.name AS job_role_name,
        (SELECT COUNT(*) FROM candidate_documents d WHERE d.user_id=u.id AND d.deleted_at IS NULL) AS document_count,
        (SELECT COUNT(*) FROM applications a WHERE a.user_id=u.id) AS application_count
        FROM users u
        LEFT JOIN candidate_profiles cp ON cp.user_id=u.id
        LEFT JOIN job_preferences jp ON jp.user_id=u.id
        LEFT JOIN categories c ON c.id=jp.category_id
        LEFT JOIN job_roles jr ON jr.id=jp.job_role_id
        WHERE u.deleted_at IS NULL";
$params = [];
if ($search !== '') {
    $sql .= ' AND (cp.first_name LIKE ? OR cp.last_name LIKE ? OR u.mobile LIKE ? OR u.email LIKE ? OR cp.city LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
}
if ($statusFilter !== 'all' && in_array($statusFilter, ['pending_verification','verified','rejected','blocked'], true)) {
    $sql .= ' AND u.account_status=?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY u.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<main class="content">
    <div class="topbar">
        <div><h1>Candidates</h1><p class="muted">All non-deleted user accounts, regardless of app role or access status.</p></div>
        <?php if ($viewCandidate): ?><a class="button-secondary" href="candidates.php">Back to Candidates</a><?php endif; ?>
    </div>
    <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>

    <?php if ($viewCandidate): ?>
    <section class="panel candidate-detail-panel">
        <div class="panel-header"><div><h2><?= e(trim(($viewCandidate['first_name'] ?? '') . ' ' . ($viewCandidate['last_name'] ?? ''))) ?: 'Candidate' ?></h2><p class="muted small"><?= e($viewCandidate['email'] ?? '') ?> · <?= e($viewCandidate['mobile'] ?? '') ?></p></div><span class="status-badge <?= e($viewCandidate['account_status']) ?>"><?= e(ucwords(str_replace('_',' ', $viewCandidate['account_status']))) ?></span></div>
        <div class="candidate-grid">
            <div class="detail-card"><h3>Personal Information</h3><p><strong>Name</strong><?= e(trim(($viewCandidate['first_name'] ?? '') . ' ' . ($viewCandidate['last_name'] ?? ''))) ?></p><p><strong>Date of Birth</strong><?= e($viewCandidate['date_of_birth'] ?? '—') ?></p><p><strong>Gender</strong><?= e($viewCandidate['gender'] ?? '—') ?></p><p><strong>City</strong><?= e($viewCandidate['city'] ?? '—') ?></p><p><strong>Country</strong><?= e($viewCandidate['country'] ?? '—') ?></p><p><strong>Postcode</strong><?= e($viewCandidate['postcode'] ?? '—') ?></p><p><strong>Address</strong><?= e($viewCandidate['full_address'] ?? '—') ?></p><p><strong>Referral Code</strong><?= e($viewCandidate['referral_code'] ?? '—') ?></p></div>
            <div class="detail-card"><h3>Professional & Preference</h3><p><strong>Experience</strong><?= e(($viewCandidate['experience_years'] ?? null) !== null ? $viewCandidate['experience_years'] . ' years' : '—') ?></p><p><strong>Previous Company</strong><?= e($viewCandidate['previous_company_name'] ?? '—') ?></p><p><strong>Category</strong><?= e($viewCandidate['category_name'] ?? '—') ?></p><p><strong>Job Role</strong><?= e($viewCandidate['job_role_name'] ?? '—') ?></p><p><strong>Travel Distance</strong><?= e(($viewCandidate['max_travel_distance_km'] ?? null) !== null ? $viewCandidate['max_travel_distance_km'] . ' KM' : '—') ?></p><p><strong>Working Hours</strong><?= e(($viewCandidate['preferred_working_hours'] ?? null) !== null ? $viewCandidate['preferred_working_hours'] . ' hrs' : '—') ?></p></div>
            <div class="detail-card"><h3>Bank / UPI</h3><p><strong>Payment Type</strong><?= e($viewCandidate['payment_type'] ?? '—') ?></p><p><strong>Account Holder</strong><?= e($viewCandidate['account_holder_name'] ?? '—') ?></p><p><strong>Bank</strong><?= e($viewCandidate['bank_name'] ?? '—') ?></p><p><strong>Account Number</strong><?= !empty($viewCandidate['account_number_encrypted']) ? '••••••' : '—' ?></p><p><strong>IFSC</strong><?= e($viewCandidate['ifsc_code'] ?? '—') ?></p><p><strong>Branch</strong><?= e($viewCandidate['branch_name'] ?? '—') ?></p><p><strong>UPI ID</strong><?= e($viewCandidate['upi_id'] ?? '—') ?></p></div>
        </div>

        <section class="detail-card candidate-documents" style="margin-top:20px">
            <h3>Documents <span class="muted small">(<?= count($viewDocuments) ?>)</span></h3>
            <?php if (!$viewDocuments): ?>
                <p class="muted">No documents uploaded.</p>
            <?php else: ?>
                <div class="table-wrap"><table class="data-table"><thead><tr><th>Document</th><th>Type</th><th>Status</th><th>Uploaded</th><th>File</th></tr></thead><tbody>
                <?php foreach ($viewDocuments as $doc):
                    $docType = $doc['document_type'] ?? $doc['document_name'] ?? $doc['type'] ?? 'Document';
                    // The upload API stores the document location in storage_path.
                    $filePath = $doc['storage_path'] ?? $doc['file_path'] ?? $doc['file_url'] ?? $doc['document_url'] ?? $doc['file'] ?? '';
                    $reviewStatus = $doc['review_status'] ?? $doc['status'] ?? 'pending';
                    $uploadedAt = $doc['created_at'] ?? $doc['uploaded_at'] ?? '—';
                    $safePath = trim((string)$filePath);
                    $isHttpUrl = preg_match('~^https?://~i', $safePath) === 1;
                    $isRelativePath = $safePath !== '' && !str_contains($safePath, '..') && !str_starts_with($safePath, '/') && !str_starts_with($safePath, '\\');

                    // Documents are stored by the app under /MWH/api/uploads/documents/...
                    // while this admin page is under /MWH/admin/. A plain "uploads/..."
                    // link therefore resolves to /MWH/admin/uploads/... and returns 404.
                    // Normalize the stored relative path to the actual API upload location.
                    $viewPath = $safePath;
                    if ($isRelativePath && !$isHttpUrl) {
                        $viewPath = '../api/' . ltrim(
                            preg_replace('~^api/~i', '', $safePath),
                            '/'
                        );
                    }
                ?>
                    <tr>
                        <td><?= e((string)($doc['title'] ?? $doc['document_name'] ?? $docType)) ?></td>
                        <td><?= e((string)$docType) ?></td>
                        <td><span class="status-badge <?= e((string)$reviewStatus) ?>"><?= e(ucwords(str_replace('_',' ', (string)$reviewStatus))) ?></span><?php if (!empty($doc['rejection_reason'])): ?><div class="muted small"><?= e((string)$doc['rejection_reason']) ?></div><?php endif; ?></td>
                        <td><?= e((string)$uploadedAt) ?></td>
                        <td><?php if ($isHttpUrl || $isRelativePath): ?><a class="button-small" href="<?= e($viewPath) ?>" target="_blank" rel="noopener noreferrer">View / Open</a><?php else: ?>—<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>

        <?php if (!empty($viewCandidate['rejection_reason']) || !empty($viewCandidate['block_reason'])): ?><div class="alert error"><strong>Reason:</strong> <?= e($viewCandidate['block_reason'] ?: $viewCandidate['rejection_reason']) ?></div><?php endif; ?>
        <div class="candidate-actions">
            <?php if ($viewCandidate['account_status'] !== 'verified'): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="verify"><input type="hidden" name="user_id" value="<?= (int)$viewId ?>"><button class="button-small success" type="submit">Verify Candidate</button></form><?php endif; ?>
            <?php if ($viewCandidate['account_status'] !== 'rejected'): ?><form method="post" class="inline-reason"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="user_id" value="<?= (int)$viewId ?>"><input name="reason" required maxlength="500" placeholder="Rejection reason"><button class="button-small danger" type="submit">Reject</button></form><?php endif; ?>
            <?php if ($viewCandidate['account_status'] !== 'blocked'): ?><form method="post" class="inline-reason"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="block"><input type="hidden" name="user_id" value="<?= (int)$viewId ?>"><input name="reason" required maxlength="500" placeholder="Block reason"><button class="button-small danger" type="submit">Block</button></form><?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="unblock"><input type="hidden" name="user_id" value="<?= (int)$viewId ?>"><button class="button-small success" type="submit">Unblock</button></form><?php endif; ?>
        </div>
    </section>
    <?php else: ?>
    <section class="panel"><div class="panel-header"><div><h2>All Users / Candidates</h2><p class="muted small">Includes active, inactive, blocked, rejected, and users without a candidate profile or app-access role.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Name</th><th>Contact</th><th>Role</th><th>City</th><th>Status</th><th>Registered</th><th>Action</th></tr></thead><tbody>
        <?php if (!$candidates): ?><tr><td colspan="7" class="text-center muted">No users found.</td></tr><?php else: foreach ($candidates as $row): ?>
            <tr><td><strong><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?: '—' ?></strong></td><td><?= e($row['mobile'] ?? '—') ?><div class="muted small"><?= e($row['email'] ?: '—') ?></div></td><td><?= e($row['job_role_name'] ?: $row['category_name'] ?: '—') ?></td><td><?= e($row['city'] ?: '—') ?></td><td><span class="status-badge <?= e($row['account_status']) ?>"><?= e(ucwords(str_replace('_',' ', $row['account_status']))) ?></span></td><td><?= e($row['created_at']) ?></td><td><a class="button-small" href="candidates.php?view=<?= (int)$row['id'] ?>">View</a></td></tr>
        <?php endforeach; endif; ?></tbody></table></div>
    </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>