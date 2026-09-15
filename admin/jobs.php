<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';
$pageTitle = 'Jobs';
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['jobs_csrf'])) $_SESSION['jobs_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['jobs_csrf'];
$success = $_SESSION['jobs_success'] ?? ''; unset($_SESSION['jobs_success']);
$error = $_SESSION['jobs_error'] ?? ''; unset($_SESSION['jobs_error']);
$status = (string)($_GET['status'] ?? 'all');
$allowed = ['all','selected','upcoming','checked_in','completed','paid','cancelled'];
if (!in_array($status, $allowed, true)) $status = 'all';
$search = trim((string)($_GET['search'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$sql = "SELECT a.id AS application_id, a.status AS application_status,
               u.id AS user_id, u.mobile, cp.first_name, cp.last_name,
               v.title AS vacancy_title, v.payout_period,
               h.name AS hotel_name, h.city AS hotel_city,
               j.id AS job_id, j.status AS job_status, j.start_date, j.end_date,
               j.start_time, j.end_time, j.payout_amount, j.assigned_at, j.completed_at,
               COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.job_id=j.id),0) AS paid_amount,
               pprof.payment_type, pprof.account_holder_name, pprof.bank_name,
               pprof.account_number_encrypted, pprof.ifsc_code, pprof.branch_name, pprof.upi_id
        FROM applications a
        INNER JOIN users u ON u.id=a.user_id
        LEFT JOIN candidate_profiles cp ON cp.user_id=u.id
        INNER JOIN vacancies v ON v.id=a.vacancy_id
        INNER JOIN hotels h ON h.id=v.hotel_id
        LEFT JOIN jobs j ON j.application_id=a.id
        LEFT JOIN candidate_payment_profiles pprof ON pprof.user_id=a.user_id
        WHERE a.status='selected' AND u.deleted_at IS NULL";
$params=[];
if ($status === 'selected') {
    $sql .= ' AND j.id IS NULL';
} elseif ($status === 'paid') {
    $sql .= " AND j.status='completed' AND COALESCE((SELECT SUM(p2.amount) FROM payments p2 WHERE p2.job_id=j.id),0) >= COALESCE(j.payout_amount,0)";
} elseif ($status !== 'all') {
    $sql .= ' AND j.status=?';
    $params[]=$status;
}
if ($search !== '') {
    $sql .= " AND (u.mobile LIKE ? OR CONCAT(COALESCE(cp.first_name,''),' ',COALESCE(cp.last_name,'')) LIKE ? OR v.title LIKE ? OR h.name LIKE ? OR h.city LIKE ?)";
    $term='%'.$search.'%'; array_push($params,$term,$term,$term,$term,$term);
}
if ($dateFrom !== '') { $sql .= ' AND COALESCE(j.start_date,j.end_date) >= ?'; $params[]=$dateFrom; }
if ($dateTo !== '') { $sql .= ' AND COALESCE(j.start_date,j.end_date) <= ?'; $params[]=$dateTo; }
$sql .= ' ORDER BY COALESCE(j.start_date, "9999-12-31"), a.reviewed_at DESC';
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<style>
.jobs-page{max-width:1600px;margin:0 auto}.jobs-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}.jobs-tabs a{padding:9px 13px;border:1px solid #dfe4ec;border-radius:9px;background:#fff;color:#66738a;text-decoration:none;font-size:12px;font-weight:750}.jobs-tabs a.active{background:#006b3c;color:#fff;border-color:#006b3c}
.jobs-filters{display:flex;gap:10px;align-items:flex-end;flex-wrap:nowrap;padding:15px;background:#fff;border:1px solid #e7ebf2;border-radius:12px;margin-bottom:18px;overflow-x:auto}.jobs-filter{display:flex;flex-direction:column;gap:5px;flex:0 0 auto}.jobs-filter label{font-size:10px;font-weight:800;color:#7a8498;text-transform:uppercase;letter-spacing:.05em}.jobs-filter input{height:38px;border:1px solid #dfe4ec;border-radius:8px;padding:0 11px;font-size:12px;min-width:180px;box-sizing:border-box}.jobs-filter input[type=date]{min-width:145px}
.filter-btn,.clear-btn{height:38px!important;padding:0 14px!important;border-radius:8px;font-size:12px;font-weight:800;text-decoration:none;display:inline-flex!important;align-items:center;justify-content:center;cursor:pointer;white-space:nowrap;box-sizing:border-box;width:auto!important;min-width:0!important;max-width:max-content!important;flex:0 0 auto!important}.filter-btn{border:1px solid #006b3c;background:#006b3c;color:#fff}.clear-btn{border:1px solid #dfe4ec;background:#fff;color:#66738a}.jobs-result-count{font-size:11px;color:#8a94a6;margin-left:auto;padding-bottom:10px;white-space:nowrap;flex:0 0 auto}
@media (max-width:900px){.jobs-filters{flex-wrap:wrap;overflow-x:visible}.jobs-result-count{margin-left:0;width:100%}}
.jobs-table-wrap{overflow:auto}.jobs-table{width:100%;border-collapse:separate;border-spacing:0;min-width:1180px}.jobs-table th{padding:13px 15px;background:#fafbfe;color:#7a8498;border-bottom:1px solid #e7ebf2;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.07em}.jobs-table td{padding:14px 15px;border-bottom:1px solid #eef1f5;background:#fff;vertical-align:middle}.jobs-primary{font-size:13px;font-weight:750;color:#1c2739}.jobs-secondary{font-size:11px;color:#8a94a6;margin-top:4px}.jobs-status{display:inline-flex;padding:6px 9px;border-radius:999px;font-size:10px;font-weight:800;text-transform:capitalize}.jobs-status.upcoming{background:#effcf6;color:#087b50}.jobs-status.checked_in{background:#eef7ff;color:#2878c8}.jobs-status.completed{background:#f0f2f5;color:#596579}.jobs-status.cancelled{background:#fff1f2;color:#c9363d}.jobs-status.selected{background:#fff7e8;color:#b76a00}.jobs-assign,.jobs-pay,.jobs-edit{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border-radius:8px;text-decoration:none;font-size:11px;font-weight:800;border:0;cursor:pointer}.jobs-pay-form{display:inline-block;margin:0}.jobs-assign{background:#006b3c;color:#fff}.jobs-edit{background:#f5f8ff;border:1px solid #d9e2ff;color:#4169e1}.jobs-pay{background:#087b50;color:#fff}.jobs-paid{font-size:11px;font-weight:800;color:#087b50}.jobs-locked{font-size:11px;font-weight:800;color:#8a94a6}.jobs-empty{text-align:center;padding:55px!important;color:#7a8498}.flash{padding:12px 15px;border-radius:10px;margin-bottom:15px;font-size:13px;font-weight:700}.flash.success{background:#effcf6;color:#087b50;border:1px solid #cceedd}.flash.error{background:#fff1f2;color:#c9363d;border:1px solid #ffd2d6}
</style>
<main class="content jobs-page">
    <div class="topbar"><div><div class="page-kicker">WORKFORCE MANAGEMENT</div><h1>Jobs</h1><p class="muted">Assign selected candidates, schedule shifts and manage their mobile job lifecycle.</p></div></div>
    <?php if($success):?><div class="flash success"><i class="bi bi-check-circle"></i>&nbsp;<?=e($success)?></div><?php endif;?>
    <?php if($error):?><div class="flash error"><i class="bi bi-exclamation-circle"></i>&nbsp;<?=e($error)?></div><?php endif;?>
    <div class="jobs-tabs">
        <?php foreach(['all'=>'All','selected'=>'Needs Assignment','upcoming'=>'Upcoming','checked_in'=>'Checked In','completed'=>'Completed','paid'=>'Paid','cancelled'=>'Cancelled'] as $key=>$label):?>
            <a class="<?= $status===$key?'active':'' ?>" href="jobs.php?status=<?=e($key)?><?= $search!==''?'&search='.urlencode($search):'' ?><?= $dateFrom!==''?'&date_from='.urlencode($dateFrom):'' ?><?= $dateTo!==''?'&date_to='.urlencode($dateTo):'' ?>"><?=e($label)?></a>
        <?php endforeach;?>
    </div>
    <form method="get" class="jobs-filters">
        <input type="hidden" name="status" value="<?=e($status)?>">
        <div class="jobs-filter"><label>Search</label><input type="search" name="search" value="<?=e($search)?>" placeholder="Candidate, mobile, job, hotel..." /></div>
        <div class="jobs-filter"><label>Start Date From</label><input type="date" name="date_from" value="<?=e($dateFrom)?>" /></div>
        <div class="jobs-filter"><label>Start Date To</label><input type="date" name="date_to" value="<?=e($dateTo)?>" /></div>
        <button class="filter-btn" type="submit"><i class="bi bi-search"></i>&nbsp; Search / Filter</button>
        <a class="clear-btn" href="jobs.php?status=<?=e($status)?>"><i class="bi bi-x-circle"></i>&nbsp; Clear</a>
        <div class="jobs-result-count"><?=count($rows)?> result<?=count($rows)===1?'':'s'?></div>
    </form>
    <section class="panel">
        <div class="panel-header"><div><h2><?= $status==='selected' ? 'Candidates Awaiting Assignment' : 'Job Assignments' ?></h2><p class="muted">Only selected applications can be assigned.</p></div></div>
        <div class="jobs-table-wrap">
            <table class="jobs-table"><thead><tr><th>Candidate</th><th>Vacancy</th><th>Hotel</th><th>Schedule</th><th>Pay</th><th>Status</th><th>Action</th></tr></thead><tbody>
            <?php if(!$rows):?><tr><td colspan="7" class="jobs-empty">No candidates/jobs found for this filter.</td></tr><?php endif;?>
            <?php foreach($rows as $row): $name=trim(($row['first_name']??'').' '.($row['last_name']??'')); $scheduled=$row['start_date']?date('d M Y',strtotime($row['start_date'])):'Not scheduled'; if($row['end_date']&&$row['end_date']!==$row['start_date'])$scheduled.=' – '.date('d M Y',strtotime($row['end_date'])); $times=''; if($row['start_time'])$times=date('h:i A',strtotime($row['start_time'])); if($row['end_time'])$times.=' – '.date('h:i A',strtotime($row['end_time'])); $payout=(float)$row['payout_amount'];$paid=(float)$row['paid_amount'];$remaining=max(0,$payout-$paid); ?>
            <tr><td><div class="jobs-primary"><?=e($name?:$row['mobile'])?></div><div class="jobs-secondary"><?=e($row['mobile'])?></div></td><td><div class="jobs-primary"><?=e($row['vacancy_title'])?></div></td><td><div class="jobs-primary"><?=e($row['hotel_name'])?></div><div class="jobs-secondary"><?=e($row['hotel_city']??'')?></div></td><td><div class="jobs-primary"><?=e($scheduled)?></div><div class="jobs-secondary"><?=e($times?:'Time not set')?></div></td><td><div class="jobs-primary">₹<?=e(number_format($payout,2))?></div><div class="jobs-secondary">Paid ₹<?=e(number_format($paid,2))?> · Remaining ₹<?=e(number_format($remaining,2))?></div></td><td><span class="jobs-status <?=e((string)($row['job_status']?:'selected'))?>"><?=e(str_replace('_',' ',(string)($row['job_status']?:'Selected')))?></span></td><td><?php if(empty($row['job_id'])):?><a class="jobs-assign" href="assign_job.php?application_id=<?=e((string)$row['application_id'])?>"><i class="bi bi-calendar-check"></i>&nbsp; Assign</a><?php elseif((string)$row['job_status']==='completed'&&$remaining>0):?><form method="post" action="pay_job.php" class="jobs-pay-form"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="job_id" value="<?=e((string)$row['job_id'])?>"><input type="hidden" name="amount" value="<?=e(number_format($remaining,2,'.',''))?>"><button type="submit" class="jobs-pay"><i class="bi bi-wallet2"></i>&nbsp; Pay</button></form><?php elseif((string)$row['job_status']==='completed'&&$remaining<=0):?><span class="jobs-paid"><i class="bi bi-check-circle"></i>&nbsp; Paid</span><?php elseif((string)$row['job_status']==='checked_in'):?><span class="jobs-locked"><i class="bi bi-lock"></i>&nbsp; Locked</span><?php else:?><a class="jobs-edit" href="assign_job.php?application_id=<?=e((string)$row['application_id'])?>"><i class="bi bi-pencil"></i>&nbsp; Edit</a><?php endif;?></td></tr>
            <?php endforeach;?></tbody></table>
        </div>
    </section>
</main>