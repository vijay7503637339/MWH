<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/services/fcm.php';

$pageTitle = 'Assign Job';
if (empty($_SESSION['assign_job_csrf'])) {
    $_SESSION['assign_job_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['assign_job_csrf'];
$errors = [];
$success = '';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$applicationId = (int)($_GET['application_id'] ?? $_POST['application_id'] ?? 0);
if ($applicationId <= 0) {
    http_response_code(400);
    exit('Application ID is required.');
}

$find = $pdo->prepare(
    'SELECT a.id, a.status AS application_status, a.user_id, a.vacancy_id,
            u.mobile, u.email,
            cp.first_name, cp.last_name,
            v.title AS vacancy_title, v.payout_amount AS vacancy_payout, v.payout_period,
            h.id AS hotel_id, h.name AS hotel_name, h.city AS hotel_city
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
     INNER JOIN vacancies v ON v.id = a.vacancy_id
     INNER JOIN hotels h ON h.id = v.hotel_id
     WHERE a.id = ?
     LIMIT 1'
);
$find->execute([$applicationId]);
$application = $find->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    http_response_code(404);
    exit('Application not found.');
}

$jobStmt = $pdo->prepare('SELECT * FROM jobs WHERE application_id = ? LIMIT 1');
$jobStmt->execute([$applicationId]);
$job = $jobStmt->fetch(PDO::FETCH_ASSOC) ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['assign_job_csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Invalid request token.');
    }

    try {
        if (!in_array((string)$application['application_status'], ['selected'], true)) {
            throw new RuntimeException('Only selected applications can be assigned as jobs.');
        }

        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $endDate = trim((string)($_POST['end_date'] ?? ''));
        $startTime = trim((string)($_POST['start_time'] ?? ''));
        $endTime = trim((string)($_POST['end_time'] ?? ''));
        $payout = trim((string)($_POST['payout_amount'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($startDate === '') {
            throw new RuntimeException('Start date is required.');
        }
        if ($endDate === '') {
            $endDate = $startDate;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            throw new RuntimeException('Please enter valid dates.');
        }
        if ($endDate < $startDate) {
            throw new RuntimeException('End date cannot be before start date.');
        }
        if ($startTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            throw new RuntimeException('Please enter a valid start time.');
        }
        if ($endTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            throw new RuntimeException('Please enter a valid end time.');
        }

        $payoutAmount = $payout !== '' ? (float)$payout : (float)$application['vacancy_payout'];
        if ($payoutAmount < 0) {
            throw new RuntimeException('Payout cannot be negative.');
        }

        $admin = currentAdmin();
        $isNewAssignment = $job === null;
        $pdo->beginTransaction();

        if ($job) {
            $update = $pdo->prepare(
                "UPDATE jobs
                 SET vacancy_id=?, user_id=?, hotel_id=?, status='upcoming', start_date=?, end_date=?,
                     start_time=?, end_time=?, payout_amount=?, assigned_by_admin_id=?, notes=?, updated_at=CURRENT_TIMESTAMP
                 WHERE id=?"
            );
            $update->execute([
                $application['vacancy_id'],
                $application['user_id'],
                $application['hotel_id'],
                $startDate,
                $endDate,
                $startTime !== '' ? $startTime : null,
                $endTime !== '' ? $endTime : null,
                $payoutAmount,
                $admin['id'],
                $notes !== '' ? $notes : null,
                $job['id'],
            ]);
            $jobId = (int)$job['id'];
            $action = 'job.rescheduled';
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO jobs
                    (application_id, vacancy_id, user_id, hotel_id, status, assigned_at, start_date, end_date,
                     start_time, end_time, payout_amount, assigned_by_admin_id, notes)
                 VALUES (?, ?, ?, ?, 'upcoming', CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insert->execute([
                $applicationId,
                $application['vacancy_id'],
                $application['user_id'],
                $application['hotel_id'],
                $startDate,
                $endDate,
                $startTime !== '' ? $startTime : null,
                $endTime !== '' ? $endTime : null,
                $payoutAmount,
                $admin['id'],
                $notes !== '' ? $notes : null,
            ]);
            $jobId = (int)$pdo->lastInsertId();
            $action = 'job.assigned';
        }

        $audit = $pdo->prepare(
            'INSERT INTO audit_logs
                (actor_type, actor_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $audit->execute([
            'admin',
            $admin['id'],
            $action,
            'job',
            $jobId,
            $job ? json_encode([
                'status' => $job['status'],
                'start_date' => $job['start_date'],
                'end_date' => $job['end_date'],
                'start_time' => $job['start_time'],
                'end_time' => $job['end_time'],
            ]) : null,
            json_encode([
                'status' => 'upcoming',
                'application_id' => $applicationId,
                'user_id' => (int)$application['user_id'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => $startTime !== '' ? $startTime : null,
                'end_time' => $endTime !== '' ? $endTime : null,
            ]),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        $notificationTitle = 'Job Assigned';
        $notificationMessage = 'You have been assigned to ' . $application['vacancy_title'] . ' at ' . $application['hotel_name'] . ' on ' . $startDate . '.';
        $notification = $pdo->prepare(
            'INSERT INTO notifications (user_id, admin_user_id, type, title, message, data_json) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $notification->execute([
            (int)$application['user_id'],
            (int)$admin['id'],
            'job',
            $notificationTitle,
            $notificationMessage,
            json_encode(['job_id' => $jobId, 'application_id' => $applicationId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $pdo->commit();

        if ($isNewAssignment) {
            try {
                sendFcmToUsers(
                    $pdo,
                    [(int)$application['user_id']],
                    $notificationTitle,
                    $notificationMessage,
                    'job',
                    ['job_id' => $jobId, 'application_id' => $applicationId]
                );
            } catch (Throwable $pushError) {
                error_log('MWH job assignment push failed: ' . $pushError->getMessage());
            }
        }

        $success = $job ? 'Job schedule updated successfully.' : 'Job assigned successfully.';

        $jobStmt->execute([$applicationId]);
        $job = $jobStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $ex->getMessage();
    }
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<style>
.assign-page{max-width:900px;margin:0 auto}.assign-card{padding:26px}.assign-candidate{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px;background:#f7f9fc;border:1px solid #e5e9f0;border-radius:14px;margin-bottom:22px}.assign-candidate h2{margin:0 0 5px;font-size:21px}.assign-candidate p{margin:0;color:#7a8498;font-size:13px}.assign-badge{padding:7px 11px;border-radius:999px;background:#effcf6;color:#087b50;font-size:11px;font-weight:800}.assign-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.assign-field label{display:block;font-size:12px;font-weight:750;color:#526078;margin-bottom:7px}.assign-field input,.assign-field textarea{width:100%;box-sizing:border-box;border:1px solid #dfe4ec;border-radius:10px;background:#fff;padding:11px 12px;font:inherit}.assign-field textarea{min-height:100px;resize:vertical}.assign-full{grid-column:1/-1}.assign-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px}.assign-actions a,.assign-actions button{height:42px;padding:0 18px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-weight:750;text-decoration:none}.assign-actions a{border:1px solid #d8deea;color:#66738a;background:#fff}.assign-actions button{border:0;background:#006b3c;color:#fff;cursor:pointer}@media(max-width:650px){.assign-grid{grid-template-columns:1fr}.assign-full{grid-column:auto}.assign-candidate{align-items:flex-start;flex-direction:column}}
</style>
<main class="content assign-page">
    <div class="topbar"><div><div class="page-kicker">WORKFORCE MANAGEMENT</div><h1>Assign Job</h1><p class="muted">Schedule the selected candidate so the job appears in the mobile app.</p></div></div>
    <?php if($success):?><div class="alert success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif;?>
    <?php foreach($errors as $error):?><div class="alert error"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endforeach;?>
    <section class="panel assign-card">
        <div class="assign-candidate">
            <div>
                <h2><?=e(trim(($application['first_name'] ?? '') . ' ' . ($application['last_name'] ?? '')) ?: $application['mobile'])?></h2>
                <p><?=e($application['vacancy_title'])?> · <?=e($application['hotel_name'])?><?=!empty($application['hotel_city']) ? ' · '.e($application['hotel_city']) : ''?></p>
            </div>
            <span class="assign-badge">Selected</span>
        </div>
        <form method="post">
            <input type="hidden" name="csrf" value="<?=e($csrf)?>">
            <input type="hidden" name="application_id" value="<?=e((string)$applicationId)?>">
            <div class="assign-grid">
                <div class="assign-field"><label>Start Date *</label><input type="date" name="start_date" required value="<?=e((string)($job['start_date'] ?? ''))?>"></div>
                <div class="assign-field"><label>End Date *</label><input type="date" name="end_date" required value="<?=e((string)($job['end_date'] ?? ($job['start_date'] ?? '')))?>"></div>
                <div class="assign-field"><label>Start Time</label><input type="time" name="start_time" value="<?=e((string)($job['start_time'] ?? ''))?>"></div>
                <div class="assign-field"><label>End Time</label><input type="time" name="end_time" value="<?=e((string)($job['end_time'] ?? ''))?>"></div>
                <div class="assign-field"><label>Payout Amount</label><input type="number" name="payout_amount" min="0" step="0.01" value="<?=e((string)($job['payout_amount'] ?? $application['vacancy_payout']))?>"></div>
                <div class="assign-field"><label>Payout Period</label><input type="text" value="<?=e((string)$application['payout_period'])?>" disabled></div>
                <div class="assign-field assign-full"><label>Admin Notes</label><textarea name="notes" placeholder="Optional instructions for this assignment"><?=e((string)($job['notes'] ?? ''))?></textarea></div>
            </div>
            <div class="assign-actions"><a href="applications.php">Back</a><button type="submit"><i class="bi bi-calendar-check"></i>&nbsp; <?= $job ? 'Update Assignment' : 'Assign Job' ?></button></div>
        </form>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
