<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$pageTitle = 'Payments';
if (empty($_SESSION['payments_csrf'])) {
    $_SESSION['payments_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['payments_csrf'];
$errors = [];
$success = $_SESSION['payments_success'] ?? '';
unset($_SESSION['payments_success']);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectPayments(): never
{
    header('Location: payments.php');
    exit;
}

function auditPayment(PDO $pdo, array $admin, int $userId, string $action, int $paymentId, ?array $old, ?array $new): void
{
    $stmt = $pdo->prepare('INSERT INTO audit_logs(admin_user_id,user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $admin['id'],
        $userId,
        $action,
        'payment',
        $paymentId,
        $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
        $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['payments_csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Invalid request token.');
    }

    try {
        $action = (string)($_POST['action'] ?? '');
        $admin = currentAdmin();

        if ($action === 'save') {
            $paymentId = (int)($_POST['payment_id'] ?? 0);
            $jobId = (int)($_POST['job_id'] ?? 0);
            $amount = round((float)($_POST['amount'] ?? 0), 2);
            $paymentDate = trim((string)($_POST['payment_date'] ?? ''));
            $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
            $reference = trim((string)($_POST['transaction_reference'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));

            if ($jobId <= 0) throw new RuntimeException('Please select a candidate job.');
            if ($amount <= 0) throw new RuntimeException('Payment amount must be greater than zero.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) throw new RuntimeException('Please enter a valid payment date.');
            if (!in_array($paymentMethod, ['bank_transfer', 'upi', 'cash', 'other'], true)) throw new RuntimeException('Please select a valid payment method.');

            $jobStmt = $pdo->prepare('SELECT j.id,j.user_id,j.payout_amount,cp.first_name,cp.last_name,v.title AS vacancy_title,h.name AS hotel_name FROM jobs j INNER JOIN users u ON u.id=j.user_id LEFT JOIN candidate_profiles cp ON cp.user_id=j.user_id INNER JOIN vacancies v ON v.id=j.vacancy_id INNER JOIN hotels h ON h.id=j.hotel_id WHERE j.id=? LIMIT 1');
            $jobStmt->execute([$jobId]);
            $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
            if (!$job) throw new RuntimeException('Selected job was not found.');

            $paidStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE job_id=?' . ($paymentId > 0 ? ' AND id<>?' : ''));
            $paidStmt->execute($paymentId > 0 ? [$jobId, $paymentId] : [$jobId]);
            $alreadyPaid = (float)$paidStmt->fetchColumn();
            $remaining = round((float)$job['payout_amount'] - $alreadyPaid, 2);
            if ($amount > $remaining) {
                throw new RuntimeException('Payment exceeds the remaining payout of ₹' . number_format(max(0, $remaining), 2) . '.');
            }

            $adminId = $admin['id'];
            $referenceValue = $reference !== '' ? $reference : null;
            $notesValue = $notes !== '' ? $notes : null;

            if ($paymentId > 0) {
                $find = $pdo->prepare('SELECT id,user_id,job_id,amount,payment_date,payment_method,transaction_reference,notes FROM payments WHERE id=? LIMIT 1');
                $find->execute([$paymentId]);
                $old = $find->fetch(PDO::FETCH_ASSOC);
                if (!$old) throw new RuntimeException('Payment not found.');

                $stmt = $pdo->prepare('UPDATE payments SET job_id=?,amount=?,payment_date=?,payment_method=?,transaction_reference=?,notes=? WHERE id=?');
                $stmt->execute([$jobId, $amount, $paymentDate, $paymentMethod, $referenceValue, $notesValue, $paymentId]);
                auditPayment($pdo, $admin, (int)$job['user_id'], 'payment.updated', $paymentId, $old, [
                    'job_id' => $jobId,
                    'amount' => $amount,
                    'payment_date' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'transaction_reference' => $referenceValue,
                    'notes' => $notesValue,
                ]);
                $_SESSION['payments_success'] = 'Payment updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO payments(user_id,job_id,amount,payment_date,payment_method,transaction_reference,notes,entered_by_admin_id) VALUES(?,?,?,?,?,?,?,?)');
                $stmt->execute([(int)$job['user_id'], $jobId, $amount, $paymentDate, $paymentMethod, $referenceValue, $notesValue, $adminId]);
                $newId = (int)$pdo->lastInsertId();
                auditPayment($pdo, $admin, (int)$job['user_id'], 'payment.created', $newId, null, [
                    'job_id' => $jobId,
                    'amount' => $amount,
                    'payment_date' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'transaction_reference' => $referenceValue,
                    'notes' => $notesValue,
                ]);
                $_SESSION['payments_success'] = 'Payment recorded successfully.';
            }
            redirectPayments();
        }

        if ($action === 'delete') {
            $paymentId = (int)($_POST['payment_id'] ?? 0);
            if ($paymentId <= 0) throw new RuntimeException('Invalid payment.');
            $find = $pdo->prepare('SELECT id,user_id,job_id,amount,payment_date,payment_method,transaction_reference,notes FROM payments WHERE id=? LIMIT 1');
            $find->execute([$paymentId]);
            $old = $find->fetch(PDO::FETCH_ASSOC);
            if (!$old) throw new RuntimeException('Payment not found.');

            $stmt = $pdo->prepare('DELETE FROM payments WHERE id=?');
            $stmt->execute([$paymentId]);
            auditPayment($pdo, $admin, (int)$old['user_id'], 'payment.deleted', $paymentId, $old, null);
            $_SESSION['payments_success'] = 'Payment deleted successfully.';
            redirectPayments();
        }

        throw new RuntimeException('Invalid payment action.');
    } catch (Throwable $ex) {
        $errors[] = $ex->getMessage();
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$method = (string)($_GET['method'] ?? 'all');
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$where = ['u.deleted_at IS NULL'];
$params = [];
if ($search !== '') {
    $where[] = '(cp.first_name LIKE ? OR cp.last_name LIKE ? OR u.mobile LIKE ? OR v.title LIKE ? OR h.name LIKE ? OR p.transaction_reference LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term, $term);
}
if (in_array($method, ['bank_transfer', 'upi', 'cash', 'other'], true)) {
    $where[] = 'p.payment_method=?';
    $params[] = $method;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 'p.payment_date>=?';
    $params[] = $from;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 'p.payment_date<=?';
    $params[] = $to;
}

$sql = 'SELECT p.id,p.user_id,p.job_id,p.amount,p.payment_date,p.payment_method,p.transaction_reference,p.notes,cp.first_name,cp.last_name,u.mobile,v.title AS vacancy_title,h.name AS hotel_name,h.city AS hotel_city FROM payments p INNER JOIN users u ON u.id=p.user_id LEFT JOIN candidate_profiles cp ON cp.user_id=p.user_id LEFT JOIN jobs j ON j.id=p.job_id LEFT JOIN vacancies v ON v.id=j.vacancy_id LEFT JOIN hotels h ON h.id=j.hotel_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.payment_date DESC,p.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPaid = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments')->fetchColumn();
$thisMonth = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND payment_date < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)")->fetchColumn();
$pendingPayout = (float)$pdo->query('SELECT COALESCE(SUM(GREATEST(j.payout_amount-COALESCE(x.paid,0),0)),0) FROM jobs j LEFT JOIN (SELECT job_id,SUM(amount) AS paid FROM payments WHERE job_id IS NOT NULL GROUP BY job_id) x ON x.job_id=j.id WHERE j.status NOT IN (\'cancelled\',\'rejected\')')->fetchColumn();
$paymentCount = (int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();

$jobStmt = $pdo->query('SELECT j.id,j.payout_amount,cp.first_name,cp.last_name,u.mobile,v.title AS vacancy_title,h.name AS hotel_name,COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.job_id=j.id),0) AS paid_amount FROM jobs j INNER JOIN users u ON u.id=j.user_id LEFT JOIN candidate_profiles cp ON cp.user_id=j.user_id INNER JOIN vacancies v ON v.id=j.vacancy_id INNER JOIN hotels h ON h.id=j.hotel_id WHERE j.status NOT IN (\'cancelled\',\'rejected\') ORDER BY j.assigned_at DESC');
$jobs = $jobStmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<style>
.payments-page{max-width:1600px;margin:0 auto}
.payments-heading{align-items:center;margin-bottom:22px}.payments-heading .page-kicker{display:flex;align-items:center;gap:8px;color:#4169e1;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}.payments-heading h1{font-size:32px;margin-bottom:6px}.payments-count{min-width:116px;text-align:right}.payments-count strong{display:block;font-size:30px;line-height:1;font-weight:800;color:#172033}.payments-count span{display:block;color:#7a8498;font-size:12px;margin-top:6px}
.payment-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.payment-stat{display:flex;align-items:center;gap:13px;background:#fff;border:1px solid #e7ebf2;border-radius:15px;padding:15px 16px;box-shadow:0 4px 14px rgba(20,35,60,.04)}.payment-stat-icon{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:#eef3ff;color:#4169e1;font-size:17px;flex:0 0 40px}.payment-stat-copy strong{display:block;font-size:20px;line-height:1.1;color:#172033}.payment-stat-copy span{display:block;margin-top:4px;color:#7a8498;font-size:11px;font-weight:600}.payment-stat.month .payment-stat-icon{background:#eef7ff;color:#2878c8}.payment-stat.pending .payment-stat-icon{background:#fff7e8;color:#c47b0b}.payment-stat.count .payment-stat-icon{background:#effcf6;color:#087b50}
.payments-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:18px}.payments-toolbar h2{font-size:18px;margin:0}.payments-toolbar p{margin:4px 0 0}.payment-add{height:42px!important;width:auto!important;margin:0!important;padding:0 16px!important;border-radius:10px!important;box-shadow:none!important;white-space:nowrap}
.payment-filters{display:grid;grid-template-columns:minmax(250px,1fr) 180px 150px 150px auto auto;gap:9px;align-items:center;padding:0 22px 18px;border-bottom:1px solid #edf0f5}.payment-field{position:relative}.payment-field i{position:absolute;left:12px;top:50%;transform:translateY(-50%);z-index:1;color:#8b95a7;font-size:13px}.payment-field input,.payment-field select{height:42px;padding:0 12px 0 36px;border-radius:9px;background:#f9fbfe;border-color:#e1e6ef}.payment-filter-button,.payment-clear{height:42px;display:inline-flex;align-items:center;justify-content:center;padding:0 13px;border-radius:9px;font-size:12px;font-weight:750}.payment-filter-button{border:0;background:#4169e1;color:#fff}.payment-clear{border:1px solid #d8deea;color:#66738a;background:#fff}
.payments-table-wrap{overflow-x:auto}.payments-table{width:100%;border-collapse:separate;border-spacing:0;min-width:1050px}.payments-table th{padding:13px 16px;background:#fafbfe;color:#7a8498;border-bottom:1px solid #e7ebf2;font-size:10px;text-transform:uppercase;letter-spacing:.07em;text-align:left;white-space:nowrap}.payments-table td{padding:14px 16px;border-bottom:1px solid #eef1f5;vertical-align:middle;background:#fff}.payments-table tbody tr:hover td{background:#fbfcff}.payments-table tbody tr:last-child td{border-bottom:0}.candidate-cell{display:flex;align-items:center;gap:10px;min-width:180px}.candidate-avatar{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;background:linear-gradient(145deg,#4169e1,#6385e9);color:#fff;font-size:12px;font-weight:800;flex:0 0 36px}.candidate-cell strong,.primary-cell{display:block;color:#1c2739;font-size:13px;font-weight:750}.candidate-cell span,.secondary-cell{display:block;margin-top:4px;color:#8a94a6;font-size:11px;line-height:1.3}.secondary-cell{max-width:190px}.amount-cell{font-size:14px;font-weight:800;color:#172033;white-space:nowrap}.date-cell strong{display:block;font-size:12px;color:#303c52;white-space:nowrap}.date-cell span{display:block;margin-top:3px;color:#929bad;font-size:10px}.method-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:999px;background:#f3f6fb;color:#526078;font-size:10px;font-weight:750;white-space:nowrap}.reference-cell{font-size:11px;color:#66738a;max-width:160px;word-break:break-word}.payment-actions{display:flex;gap:6px;align-items:center}.payment-action{height:30px!important;width:auto!important;margin:0!important;padding:0 9px!important;border-radius:8px!important;border:1px solid #dce3ee!important;background:#fff!important;color:#526078!important;box-shadow:none!important;font-size:10px!important;font-weight:750!important;cursor:pointer}.payment-action.edit{color:#4169e1!important;border-color:#d9e2ff!important;background:#f5f8ff!important}.payment-action.delete{color:#c9363d!important;border-color:#f1c8cb!important;background:#fff8f8!important}.payment-empty{padding:55px 20px!important;text-align:center}.payment-empty i{font-size:30px;color:#a8b1c0}.payment-empty strong{display:block;margin-top:8px;font-size:14px;color:#374258}.payment-empty span{display:block;margin-top:5px;font-size:12px;color:#8b95a7}
.payment-modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,25,45,.48);z-index:1000;padding:24px;align-items:center;justify-content:center}.payment-modal-backdrop.open{display:flex}.payment-modal{width:min(620px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:18px;box-shadow:0 24px 70px rgba(15,25,45,.22)}.payment-modal-head{display:flex;align-items:center;justify-content:space-between;padding:20px 22px;border-bottom:1px solid #edf0f5}.payment-modal-head h3{margin:0;font-size:18px;color:#172033}.payment-close{border:0;background:#f3f5f8;width:34px;height:34px;border-radius:9px;color:#66738a;cursor:pointer}.payment-form{padding:20px 22px}.payment-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.payment-form-group.full{grid-column:1/-1}.payment-form-group label{display:block;margin-bottom:6px;font-size:11px;font-weight:750;color:#526078}.payment-form-group input,.payment-form-group select,.payment-form-group textarea{width:100%;border:1px solid #dfe5ee;border-radius:9px;padding:10px 11px;background:#fbfcfe;font:inherit;font-size:12px;box-sizing:border-box}.payment-form-group textarea{min-height:82px;resize:vertical}.payment-job-meta{margin-top:7px;padding:9px 10px;border-radius:9px;background:#f6f8fc;color:#778298;font-size:10px;line-height:1.5}.payment-form-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:18px}.payment-form-actions button{height:40px;border-radius:9px;padding:0 15px;font-weight:750;font-size:12px;border:1px solid #d8deea;background:#fff;color:#66738a;cursor:pointer}.payment-form-actions .primary{border:0;background:#4169e1;color:#fff}.payment-delete-form{display:inline}.payment-note{margin-top:8px;color:#8b95a7;font-size:10px}
@media(max-width:1100px){.payment-summary{grid-template-columns:repeat(2,1fr)}.payment-filters{grid-template-columns:1fr 1fr 1fr auto}.payment-filters .payment-search{grid-column:1/-1}}
@media(max-width:760px){.payments-heading{align-items:flex-start}.payments-count{display:none}.payment-summary{grid-template-columns:1fr 1fr}.payment-filters{grid-template-columns:1fr 1fr;padding:0 16px 16px}.payment-filters .payment-search{grid-column:1/-1}.payment-toolbar{align-items:flex-start}.payment-add{padding:0 12px!important}.payment-form-grid{grid-template-columns:1fr}.payment-form-group.full{grid-column:auto}.payments-panel{overflow:hidden}}
@media(max-width:480px){.payment-summary{grid-template-columns:1fr}.payments-heading h1{font-size:27px}.payments-toolbar{flex-direction:column}.payment-add{width:100%!important}}
</style>

<main class="content payments-page">
    <div class="topbar payments-heading">
        <div>
            <div class="page-kicker"><i class="bi bi-wallet2"></i> Finance & Workforce</div>
            <h1>Payments</h1>
            <p class="muted">Record, track and review candidate payouts from one place.</p>
        </div>
        <div class="payments-count"><strong><?=number_format($paymentCount)?></strong><span>Payments</span></div>
    </div>

    <?php if ($success): ?><div class="alert success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="alert error"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endforeach; ?>

    <div class="payment-summary">
        <div class="payment-stat"><div class="payment-stat-icon"><i class="bi bi-currency-rupee"></i></div><div class="payment-stat-copy"><strong>₹<?=number_format($totalPaid, 2)?></strong><span>Total Paid</span></div></div>
        <div class="payment-stat month"><div class="payment-stat-icon"><i class="bi bi-calendar3"></i></div><div class="payment-stat-copy"><strong>₹<?=number_format($thisMonth, 2)?></strong><span>This Month</span></div></div>
        <div class="payment-stat pending"><div class="payment-stat-icon"><i class="bi bi-hourglass-split"></i></div><div class="payment-stat-copy"><strong>₹<?=number_format($pendingPayout, 2)?></strong><span>Pending Payout</span></div></div>
        <div class="payment-stat count"><div class="payment-stat-icon"><i class="bi bi-receipt"></i></div><div class="payment-stat-copy"><strong><?=number_format($paymentCount)?></strong><span>Transactions</span></div></div>
    </div>

    <section class="panel payments-panel">
        <div class="panel-header payments-toolbar">
            <div><h2>Payment Records</h2><p class="muted">Search transactions, review payout history and record new payments.</p></div>
            <button type="button" class="payment-add" onclick="openPaymentModal()"><i class="bi bi-plus-lg"></i>&nbsp; Record Payment</button>
        </div>

        <form class="payment-filters" method="get">
            <div class="payment-field payment-search"><i class="bi bi-search"></i><input type="search" name="search" value="<?=e($search)?>" placeholder="Search candidate, mobile, vacancy, hotel or reference"></div>
            <div class="payment-field"><i class="bi bi-wallet2"></i><select name="method"><option value="all">All Methods</option><?php foreach(['bank_transfer'=>'Bank Transfer','upi'=>'UPI','cash'=>'Cash','other'=>'Other'] as $key=>$label): ?><option value="<?=e($key)?>" <?=$method===$key?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select></div>
            <div class="payment-field"><i class="bi bi-calendar3"></i><input type="date" name="from" value="<?=e($from)?>" title="From date"></div>
            <div class="payment-field"><i class="bi bi-calendar3"></i><input type="date" name="to" value="<?=e($to)?>" title="To date"></div>
            <button class="payment-filter-button" type="submit"><i class="bi bi-search"></i>&nbsp; Search</button>
            <?php if ($search!=='' || $method!=='all' || $from!=='' || $to!==''): ?><a class="payment-clear" href="payments.php">Clear</a><?php endif; ?>
        </form>

        <div class="payments-table-wrap">
            <table class="payments-table">
                <thead><tr><th>Candidate</th><th>Job / Hotel</th><th>Amount</th><th>Payment Date</th><th>Method</th><th>Reference</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (!$payments): ?><tr><td colspan="7" class="payment-empty"><i class="bi bi-receipt"></i><strong>No payment records found</strong><span>Record a payment or change your search filters.</span></td></tr><?php endif; ?>
                <?php foreach ($payments as $row): $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); $initial = strtoupper(substr($name !== '' ? $name : 'C', 0, 1)); ?>
                    <tr>
                        <td><div class="candidate-cell"><div class="candidate-avatar"><?=e($initial)?></div><div><strong><?=e($name !== '' ? $name : 'Candidate #'.$row['user_id'])?></strong><span><?=e($row['mobile'] ?? '')?></span></div></div></td>
                        <td><div class="primary-cell"><?=e($row['vacancy_title'] ?? 'Job not linked')?></div><div class="secondary-cell"><?=e($row['hotel_name'] ?? '—')?><?=!empty($row['hotel_city'])?' · '.e($row['hotel_city']):''?></div></td>
                        <td><div class="amount-cell">₹<?=number_format((float)$row['amount'], 2)?></div></td>
                        <td><div class="date-cell"><strong><?=e(date('d M Y', strtotime($row['payment_date'])))?></strong><span><?=e(date('D', strtotime($row['payment_date'])))?></span></div></td>
                        <td><span class="method-badge"><i class="bi <?= $row['payment_method']==='upi'?'bi-phone':($row['payment_method']==='cash'?'bi-cash':'bi-bank') ?>"></i><?=e(ucwords(str_replace('_',' ',$row['payment_method'])))?></span></td>
                        <td><div class="reference-cell"><?=e($row['transaction_reference'] ?: '—')?></div></td>
                        <td><div class="payment-actions"><button type="button" class="payment-action edit" onclick='editPayment(<?=json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>)'><i class="bi bi-pencil"></i> Edit</button><form class="payment-delete-form" method="post" onsubmit="return confirm('Delete this payment record? This action cannot be undone.');"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="payment_id" value="<?=e((string)$row['id'])?>"><button class="payment-action delete" type="submit"><i class="bi bi-trash"></i></button></form></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<div class="payment-modal-backdrop" id="paymentModal" onclick="if(event.target===this) closePaymentModal()">
    <div class="payment-modal">
        <div class="payment-modal-head"><h3 id="paymentModalTitle">Record Payment</h3><button type="button" class="payment-close" onclick="closePaymentModal()"><i class="bi bi-x-lg"></i></button></div>
        <form class="payment-form" method="post">
            <input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="save"><input type="hidden" name="payment_id" id="payment_id" value="0">
            <div class="payment-form-grid">
                <div class="payment-form-group full"><label for="job_id">Candidate / Job *</label><select name="job_id" id="job_id" required onchange="updateJobMeta()"><option value="">Select candidate job</option><?php foreach ($jobs as $job): $jobName=trim(($job['first_name']??'').' '.($job['last_name']??'')); $remaining=max(0,(float)$job['payout_amount']-(float)$job['paid_amount']); ?><option value="<?=e((string)$job['id'])?>" data-payout="<?=e((string)$job['payout_amount'])?>" data-paid="<?=e((string)$job['paid_amount'])?>" <?=$remaining<=0?'disabled':''?>><?=e($jobName !== '' ? $jobName : 'Candidate #'.$job['id'])?> · <?=e($job['vacancy_title'])?> · <?=e($job['hotel_name'])?> · Remaining ₹<?=number_format($remaining,2)?></option><?php endforeach; ?></select><div class="payment-job-meta" id="jobMeta">Select a job to see payout and remaining balance.</div></div>
                <div class="payment-form-group"><label for="amount">Amount *</label><input type="number" name="amount" id="amount" min="0.01" step="0.01" placeholder="0.00" required></div>
                <div class="payment-form-group"><label for="payment_date">Payment Date *</label><input type="date" name="payment_date" id="payment_date" value="<?=e(date('Y-m-d'))?>" required></div>
                <div class="payment-form-group"><label for="payment_method">Payment Method *</label><select name="payment_method" id="payment_method" required><option value="bank_transfer">Bank Transfer</option><option value="upi">UPI</option><option value="cash">Cash</option><option value="other">Other</option></select></div>
                <div class="payment-form-group"><label for="transaction_reference">Transaction Reference</label><input type="text" name="transaction_reference" id="transaction_reference" maxlength="190" placeholder="UTR / transaction ID"></div>
                <div class="payment-form-group full"><label for="notes">Notes</label><textarea name="notes" id="notes" maxlength="500" placeholder="Optional payment note"></textarea></div>
            </div>
            <div class="payment-note">Payment cannot exceed the remaining payout for the selected job.</div>
            <div class="payment-form-actions"><button type="button" onclick="closePaymentModal()">Cancel</button><button class="primary" type="submit" id="paymentSubmit">Save Payment</button></div>
        </form>
    </div>
</div>

<script>
const paymentModal = document.getElementById('paymentModal');
const jobSelect = document.getElementById('job_id');
function openPaymentModal(){
    document.getElementById('paymentModalTitle').textContent='Record Payment';
    document.getElementById('payment_id').value='0';
    document.getElementById('amount').value='';
    document.getElementById('payment_date').value='<?=e(date('Y-m-d'))?>';
    document.getElementById('payment_method').value='bank_transfer';
    document.getElementById('transaction_reference').value='';
    document.getElementById('notes').value='';
    jobSelect.value='';
    document.getElementById('paymentSubmit').textContent='Save Payment';
    updateJobMeta(); paymentModal.classList.add('open');
}
function closePaymentModal(){paymentModal.classList.remove('open');}
function updateJobMeta(){
    const option=jobSelect.options[jobSelect.selectedIndex];
    if(!option || !option.value){document.getElementById('jobMeta').textContent='Select a job to see payout and remaining balance.';return;}
    const payout=parseFloat(option.dataset.payout||0), paid=parseFloat(option.dataset.paid||0), remaining=Math.max(0,payout-paid);
    document.getElementById('jobMeta').textContent='Payout: ₹'+payout.toLocaleString('en-IN',{minimumFractionDigits:2})+'  ·  Already paid: ₹'+paid.toLocaleString('en-IN',{minimumFractionDigits:2})+'  ·  Remaining: ₹'+remaining.toLocaleString('en-IN',{minimumFractionDigits:2});
    document.getElementById('amount').max=remaining.toFixed(2);
}
function editPayment(row){
    document.getElementById('paymentModalTitle').textContent='Edit Payment';
    document.getElementById('payment_id').value=row.id;
    jobSelect.value=row.job_id || '';
    document.getElementById('amount').value=row.amount;
    document.getElementById('payment_date').value=row.payment_date;
    document.getElementById('payment_method').value=row.payment_method;
    document.getElementById('transaction_reference').value=row.transaction_reference || '';
    document.getElementById('notes').value=row.notes || '';
    document.getElementById('paymentSubmit').textContent='Update Payment';
    updateJobMeta(); paymentModal.classList.add('open');
}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closePaymentModal();});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>