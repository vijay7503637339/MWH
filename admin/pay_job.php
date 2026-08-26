<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: jobs.php');
    exit;
}

if (empty($_SESSION['jobs_csrf']) || !hash_equals($_SESSION['jobs_csrf'], (string)($_POST['csrf'] ?? ''))) {
    http_response_code(419);
    exit('Invalid request token.');
}

$jobId = (int)($_POST['job_id'] ?? 0);
$amount = round((float)($_POST['amount'] ?? 0), 2);

try {
    if ($jobId <= 0 || $amount <= 0) {
        throw new RuntimeException('Invalid payment request.');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT j.id,j.user_id,j.status,j.payout_amount,j.completed_at,
                                  cp.first_name,cp.last_name,
                                  pprof.payment_type,pprof.account_holder_name,pprof.bank_name,
                                  pprof.account_number_encrypted,pprof.ifsc_code,pprof.branch_name,pprof.upi_id
                           FROM jobs j
                           LEFT JOIN candidate_profiles cp ON cp.user_id=j.user_id
                           LEFT JOIN candidate_payment_profiles pprof ON pprof.user_id=j.user_id
                           WHERE j.id=? FOR UPDATE");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        throw new RuntimeException('Job not found.');
    }
    if (!in_array((string)$job['status'], ['completed'], true)) {
        throw new RuntimeException('Only completed jobs can be paid.');
    }

    $paidStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE job_id=?');
    $paidStmt->execute([$jobId]);
    $alreadyPaid = (float)$paidStmt->fetchColumn();
    $remaining = round((float)$job['payout_amount'] - $alreadyPaid, 2);

    if ($remaining <= 0) {
        $pdo->rollBack();
        $_SESSION['jobs_error'] = 'This job has already been fully paid.';
        header('Location: jobs.php?status=completed');
        exit;
    }
    if (abs($amount - $remaining) > 0.01) {
        throw new RuntimeException('Payment must equal the remaining payout of ₹' . number_format($remaining, 2) . '.');
    }

    if (empty($job['payment_type'])) {
        throw new RuntimeException('Candidate bank/payment details are not available.');
    }

    $admin = currentAdmin();
    $paymentDate = date('Y-m-d');
    $method = in_array((string)$job['payment_type'], ['upi'], true) ? 'upi' : 'bank_transfer';

    $paymentStmt = $pdo->prepare('INSERT INTO payments(user_id,job_id,amount,payment_date,payment_method,transaction_reference,notes,entered_by_admin_id) VALUES(?,?,?,?,?,?,?,?)');
    $paymentStmt->execute([
        (int)$job['user_id'],
        $jobId,
        $amount,
        $paymentDate,
        $method,
        null,
        'Payment marked as paid from Jobs & Assignments.',
        (int)$admin['id'],
    ]);
    $paymentId = (int)$pdo->lastInsertId();
    $reference = 'MWH-PAY-' . date('Ymd') . '-' . str_pad((string)$paymentId, 6, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE payments SET transaction_reference=? WHERE id=?')->execute([$reference, $paymentId]);

    $pdo->prepare("UPDATE jobs SET status='paid', updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$jobId]);

    $audit = $pdo->prepare('INSERT INTO audit_logs(actor_type,actor_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?,?)');
    $audit->execute([
        'admin',
        (int)$admin['id'],
        'job.paid',
        'job',
        $jobId,
        json_encode(['status' => 'completed', 'paid_amount' => $alreadyPaid], JSON_UNESCAPED_UNICODE),
        json_encode(['status' => 'paid', 'payment_id' => $paymentId, 'amount' => $amount, 'transaction_reference' => $reference], JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    $pdo->commit();
    $_SESSION['jobs_success'] = 'Payment of ₹' . number_format($amount, 2) . ' recorded successfully. Job marked as Paid.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['jobs_error'] = $e->getMessage();
}

header('Location: jobs.php?status=' . (isset($_SESSION['jobs_error']) ? 'completed' : 'paid'));
exit;
