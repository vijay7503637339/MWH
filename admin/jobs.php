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
if ($status === 'selected') $sql .= ' AND j.id IS NULL';
elseif ($status !== 'all') { $sql .= ' AND j.status=?'; $params[]=$status; }
$sql .= ' ORDER BY COALESCE(j.start_date, "9999-12-31"), a.reviewed_at DESC';
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<style>
.jobs-page{max-width:1600px;margin:0 auto}.jobs-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}.jobs-tabs a{padding:9px 13px;border:1px solid #dfe4ec;border-radius:9px;background:#fff;color:#66738a;text-decoration:none;font-size:12px;font-weight:750}.jobs-tabs a.active{background:#006b3c;color:#fff;border-color:#006b3c}.jobs-table-wrap{overflow:auto}.jobs-table{width:100%;border-collapse:separate;border-spacing:0;min-width:1180px}.jobs-table th{padding:13px 15px;background:#fafbfe;color:#7a8498;border-bottom:1px solid #e7ebf2;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.07em}.jobs-table td{padding:14px 15px;border-bottom:1px solid #eef1f5;background:#fff;vertical-align:middle}.jobs-primary{font-size:13px;font-weight:750;color:#1c2739}.jobs-secondary{font-size:11px;color:#8a94a6;margin-top:4px}.jobs-status{display:inline-flex;padding:6px 9px;border-radius:999px;font-size:10px;font-weight:800;text-transform:capitalize}.jobs-status.upcoming{background:#effcf6;color:#087b50}.jobs-status.checked_in{background:#eef7ff;color:#2878c8}.jobs-status.completed{background:#f0f2f5;color:#596579}.jobs-status.paid{background:#e9f8ef;color:#087b50}.jobs-status.cancelled{background:#fff1f2;color:#c9363d}.jobs-status.selected{background:#fff7e8;color:#b76a00}.jobs-assign,.jobs-pay,.jobs-edit{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border-radius:8px;text-decoration:none;font-size:11px;font-weight:800;border:0;cursor:pointer}.jobs-assign{background:#006b3c;color:#fff}.jobs-edit{background:#f5f8ff;border:1px solid #d9e2ff;color:#4169e1}.jobs-pay{background:#087b50;color:#fff}.jobs-paid{font-size:11px;font-weight:800;color:#087b50}.jobs-empty{text-align:center;padding:55px!important;color:#7a8498}.flash{padding:12px 15px;border-radius:10px;margin-bottom:15px;font-size:13px;font-weight:700}.flash.success{background:#effcf6;color:#087b50;border:1px solid #cceedd}.flash.error{background:#fff1f2;color:#c9363d;border:1px solid #ffd2d6}
.pay-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;padding:20px;z-index:9999}.pay-modal-backdrop.open{display:flex}.pay-modal{width:min(560px,100%);background:#fff;border-radius:18px;box-shadow:0 24px 70px rgba(0,0,0,.22);overflow:hidden}.pay-head{display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid #edf0f5}.pay-head h3{margin:0;font-size:18px;color:#172033}.pay-close{border:0;background:#f3f5f8;border-radius:8px;width:34px;height:34px;cursor:pointer}.pay-body{padding:20px}.pay-candidate{display:flex;justify-content:space-between;gap:15px;margin-bottom:16px}.pay-candidate strong{font-size:16px}.pay-candidate span{display:block;color:#7a8498;font-size:12px;margin-top:4px}.pay-amount{font-size:22px;font-weight:850;color:#006b3c}.bank-card{background:#f8fafc;border:1px solid #e5e9f0;border-radius:12px;padding:14px;margin-bottom:16px}.bank-card h4{margin:0 0 10px;font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#6b7689}.bank-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.bank-item span{display:block;font-size:10px;color:#8a94a6}.bank-item strong{display:block;margin-top:3px;font-size:12px;color:#1c2739;word-break:break-word}.pay-warning{font-size:11px;color:#7a8498;margin-bottom:15px}.pay-actions{display:flex;justify-content:flex-end;gap:9px}.pay-actions button{height:40px;padding:0 15px;border-radius:9px;border:1px solid #d8deea;background:#fff;cursor:pointer;font-weight:750}.pay-actions .confirm{background:#006b3c;color:#fff;border-color:#006b3c}
@media(max-width:800px){.bank-grid{grid-template-columns:1fr}.jobs-table{min-width:1050px}}
</style>
<main class="content jobs-page">
    <div class="topbar"><div><div class="page-kicker">WORKFORCE MANAGEMENT</div><h1>Jobs</h1><p class="muted">Assign selected candidates, schedule shifts and manage their mobile job lifecycle.</p></div></div>
    <?php if($success):?><div class="flash success"><i class="bi bi-check-circle"></i>&nbsp;<?=e($success)?></div><?php endif;?>
    <?php if($error):?><div class="flash error"><i class="bi bi-exclamation-circle"></i>&nbsp;<?=e($error)?></div><?php endif;?>
    <div class="jobs-tabs">
        <?php foreach(['all'=>'All','selected'=>'Needs Assignment','upcoming'=>'Upcoming','checked_in'=>'Checked In','completed'=>'Completed','paid'=>'Paid','cancelled'=>'Cancelled'] as $key=>$label):?>
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
                    $payout=(float)$row['payout_amount']; $paid=(float)$row['paid_amount']; $remaining=max(0,$payout-$paid);
                    $account='';
                    if(!empty($row['account_number_encrypted'])) {
                        $account='Encrypted account number stored';
                        $securityFile=__DIR__.'/../api/config/security.php';
                        if(is_file($securityFile)) { try { require_once $securityFile; if(function_exists('decryptSensitive')) $account=decryptSensitive((string)$row['account_number_encrypted']); } catch(Throwable $ignore) {} }
                    }
                    $bank=[
                        'payment_type'=>(string)($row['payment_type']??''),
                        'account_holder_name'=>(string)($row['account_holder_name']??''),
                        'bank_name'=>(string)($row['bank_name']??''),
                        'account_number'=>$account,
                        'ifsc_code'=>(string)($row['ifsc_code']??''),
                        'branch_name'=>(string)($row['branch_name']??''),
                        'upi_id'=>(string)($row['upi_id']??''),
                    ];
                    $payData=['job_id'=>(int)$row['job_id'],'name'=>$name ?: 'Candidate #'.$row['user_id'],'vacancy'=>(string)$row['vacancy_title'],'amount'=>$remaining,'bank'=>$bank];
                ?>
                <tr>
                    <td><div class="jobs-primary"><?=e($name ?: $row['mobile'])?></div><div class="jobs-secondary"><?=e($row['mobile'])?></div></td>
                    <td><div class="jobs-primary"><?=e($row['vacancy_title'])?></div></td>
                    <td><div class="jobs-primary"><?=e($row['hotel_name'])?></div><div class="jobs-secondary"><?=e($row['hotel_city']??'')?></div></td>
                    <td><div class="jobs-primary"><?=e($scheduled)?></div><div class="jobs-secondary"><?=e($times ?: 'Time not set')?></div></td>
                    <td><div class="jobs-primary">₹<?=e(number_format($payout,2))?></div><div class="jobs-secondary">Paid ₹<?=e(number_format($paid,2))?> · Remaining ₹<?=e(number_format($remaining,2))?></div></td>
                    <td><span class="jobs-status <?=e((string)($row['job_status'] ?: 'selected'))?>"><?=e(str_replace('_',' ',(string)($row['job_status'] ?: 'Selected')))?></span></td>
                    <td>
                        <?php if(empty($row['job_id'])):?>
                            <a class="jobs-assign" href="assign_job.php?application_id=<?=e((string)$row['application_id'])?>"><i class="bi bi-calendar-check"></i>&nbsp; Assign</a>
                        <?php elseif((string)$row['job_status']==='completed' && $remaining>0):?>
                            <button type="button" class="jobs-pay" onclick='openPayModal(<?=json_encode($payData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>)'><i class="bi bi-wallet2"></i>&nbsp; Pay</button>
                        <?php elseif((string)$row['job_status']==='paid' || $remaining<=0):?>
                            <span class="jobs-paid"><i class="bi bi-check-circle"></i>&nbsp; Paid</span>
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

<div class="pay-modal-backdrop" id="payModal" onclick="if(event.target===this)closePayModal()">
  <div class="pay-modal">
    <div class="pay-head"><h3>Pay Candidate</h3><button type="button" class="pay-close" onclick="closePayModal()"><i class="bi bi-x-lg"></i></button></div>
    <form method="post" action="pay_job.php" class="pay-body">
      <input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="job_id" id="pay_job_id"><input type="hidden" name="amount" id="pay_amount">
      <div class="pay-candidate"><div><strong id="pay_name">Candidate</strong><span id="pay_vacancy">Job</span></div><div class="pay-amount" id="pay_amount_label">₹0.00</div></div>
      <div class="bank-card"><h4>Candidate Payment Details</h4><div class="bank-grid" id="bankDetails"></div></div>
      <div class="pay-warning">This action records the payment in MWH and changes the job status to Paid. It does not initiate an external bank/UPI transfer.</div>
      <div class="pay-actions"><button type="button" onclick="closePayModal()">Cancel</button><button type="submit" class="confirm" onclick="return confirm('Confirm payment and mark this job as Paid?')"><i class="bi bi-check2-circle"></i>&nbsp; Pay</button></div>
    </form>
  </div>
</div>
<script>
function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function openPayModal(data){
 document.getElementById('pay_job_id').value=data.job_id;
 document.getElementById('pay_amount').value=Number(data.amount).toFixed(2);
 document.getElementById('pay_name').textContent=data.name||'Candidate';
 document.getElementById('pay_vacancy').textContent=data.vacancy||'Job';
 document.getElementById('pay_amount_label').textContent='₹'+Number(data.amount).toLocaleString('en-IN',{minimumFractionDigits:2});
 const b=data.bank||{};
 const items=[['Account Holder',b.account_holder_name],['Payment Type',b.payment_type],['Bank',b.bank_name],['Account Number',b.account_number],['IFSC',b.ifsc_code],['Branch',b.branch_name],['UPI ID',b.upi_id]];
 document.getElementById('bankDetails').innerHTML=items.filter(x=>x[1]).map(x=>'<div class="bank-item"><span>'+esc(x[0])+'</span><strong>'+esc(x[1])+'</strong></div>').join('')||'<div class="bank-item"><strong>Payment details not available</strong></div>';
 document.getElementById('payModal').classList.add('open');
}
function closePayModal(){document.getElementById('payModal').classList.remove('open');}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closePayModal();});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
