<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';
$pageTitle = 'Jobs';
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$status = (string)($_GET['status'] ?? 'all');
$allowed = ['all','selected','upcoming','checked_in','completed','cancelled'];
if (!in_array($status, $allowed, true)) $status = 'all';
$sql = "SELECT a.id AS application_id, a.status AS application_status,
               u.id AS user_id, u.mobile, cp.first_name, cp.last_name,
               v.title AS vacancy_title, v.payout_period,
               h.name AS hotel_name, h.city AS hotel_city,
               j.id AS job_id, j.status AS job_status, j.start_date, j.end_date,
               j.start_time, j.end_time, j.payout_amount, j.assigned_at
        FROM applications a
        INNER JOIN users u ON u.id=a.user_id
        LEFT JOIN candidate_profiles cp ON cp.user_id=u.id
        INNER JOIN vacancies v ON v.id=a.vacancy_id
        INNER JOIN hotels h ON h.id=v.hotel_id
        LEFT JOIN jobs j ON j.application_id=a.id
        WHERE a.status='selected' AND u.deleted_at IS NULL";
$params=[];
if ($status === 'selected') $sql .= ' AND j.id IS NULL';
elseif ($status !== 'all') { $sql .= ' AND j.status=?'; $params[]=$status; }
$sql .= ' ORDER BY COALESCE(j.start_date, "9999-12-31"), a.reviewed_at DESC';
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<style>
.jobs-page{max-width:1600px;margin:0 auto}.jobs-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}.jobs-tabs a{padding:9px 13px;border:1px solid #dfe4ec;border-radius:9px;background:#fff;color:#66738a;text-decoration:none;font-size:12px;font-weight:750}.jobs-tabs a.active{background:#006b3c;color:#fff;border-color:#006b3c}.jobs-table-wrap{overflow:auto}.jobs-table{width:100%;border-collapse:separate;border-spacing:0;min-width:1050px}.jobs-table th{padding:13px 15px;background:#fafbfe;color:#7a8498;border-bottom:1px solid #e7ebf2;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.07em}.jobs-table td{padding:14px 15px;border-bottom:1px solid #eef1f5;background:#fff;vertical-align:middle}.jobs-primary{font-size:13px;font-weight:750;color:#1c2739}.jobs-secondary{font-size:11px;color:#8a94a6;margin-top:4px}.jobs-status{display:inline-flex;padding:6px 9px;border-radius:999px;font-size:10px;font-weight:800;text-transform:capitalize}.jobs-status.upcoming{background:#effcf6;color:#087b50}.jobs-status.checked_in{background:#eef7ff;color:#2878c8}.jobs-status.completed{background:#f0f2f5;color:#596579}.jobs-status.cancelled{background:#fff1f2;color:#c9363d}.jobs-status.selected{background:#fff7e8;color:#b76a00}.jobs-assign{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border-radius:8px;background:#006b3c;color:#fff;text-decoration:none;font-size:11px;font-weight:800}.jobs-edit{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border-radius:8px;background:#f5f8ff;border:1px solid #d9e2ff;color:#4169e1;text-decoration:none;font-size:11px;font-weight:800}.jobs-empty{text-align:center;padding:55px!important;color:#7a8498}
</style>
<main class="content jobs-page">
    <div class="topbar"><div><div class="page-kicker">WORKFORCE MANAGEMENT</div><h1>Jobs</h1><p class="muted">Assign selected candidates, schedule shifts and manage their mobile job lifecycle.</p></div></div>
    <div class="jobs-tabs">
        <?php foreach(['all'=>'All','selected'=>'Needs Assignment','upcoming'=>'Upcoming','checked_in'=>'Checked In','completed'=>'Completed','cancelled'=>'Cancelled'] as $key=>$label):?>
            <a class="<?= $status===$key?'active':'' ?>" href="jobs.php?status=<?=e($key)?>"><?=e($label)?></a>
        <?php endforeach;?>
    </div>
    <section class="panel">
        <div class="panel-header"><div><h2><?= $status==='selected' ? 'Candidates Awaiting Assignment' : 'Job Assignments' ?></h2><p class="muted">Only selected applications can be assigned.</p></div></div>
        <div class="jobs-table-wrap">
            <table class="jobs-table">
                <thead><tr><th>Candidate</th><th>Vacancy</th><th>Hotel</th><th>Schedule</th><th>Pay</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php if(!$rows):?><tr><td colspan="7" class="jobs-empty">No candidates/jobs found for this filter.</td></tr><?php endif;?>
                <?php foreach($rows as $row):
                    $name=trim(($row['first_name']??'').' '.($row['last_name']??''));
                    $scheduled=$row['start_date'] ? date('d M Y',strtotime($row['start_date'])) : 'Not scheduled';
                    if($row['end_date'] && $row['end_date']!==$row['start_date']) $scheduled .= ' – '.date('d M Y',strtotime($row['end_date']));
                    $times='';
                    if($row['start_time']) $times=date('h:i A',strtotime($row['start_time']));
                    if($row['end_time']) $times.=' – '.date('h:i A',strtotime($row['end_time']));
                ?>
                <tr>
                    <td><div class="jobs-primary"><?=e($name ?: $row['mobile'])?></div><div class="jobs-secondary"><?=e($row['mobile'])?></div></td>
                    <td><div class="jobs-primary"><?=e($row['vacancy_title'])?></div></td>
                    <td><div class="jobs-primary"><?=e($row['hotel_name'])?></div><div class="jobs-secondary"><?=e($row['hotel_city']??'')?></div></td>
                    <td><div class="jobs-primary"><?=e($scheduled)?></div><div class="jobs-secondary"><?=e($times ?: 'Time not set')?></div></td>
                    <td><div class="jobs-primary">₹<?=e(number_format((float)$row['payout_amount'],2))?></div><div class="jobs-secondary"><?=e($row['payout_period'])?></div></td>
                    <td><span class="jobs-status <?=e((string)($row['job_status'] ?: 'selected'))?>"><?=e(str_replace('_',' ',(string)($row['job_status'] ?: 'Selected')))?></span></td>
                    <td>
                        <?php if(empty($row['job_id'])):?>
                            <a class="jobs-assign" href="assign_job.php?application_id=<?=e((string)$row['application_id'])?>"><i class="bi bi-calendar-check"></i>&nbsp; Assign</a>
                        <?php else:?>
                            <a class="jobs-edit" href="assign_job.php?application_id=<?=e((string)$row['application_id'])?>"><i class="bi bi-pencil"></i>&nbsp; Edit</a>
                        <?php endif;?>
                    </td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
