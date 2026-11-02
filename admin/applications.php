<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';
$pageTitle='Applications';
if(empty($_SESSION['applications_csrf'])) $_SESSION['applications_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['applications_csrf']; $errors=[]; $success=$_SESSION['applications_success']??''; unset($_SESSION['applications_success']);
function e(?string $v): string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function redirectApplications():never{header('Location: applications.php');exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['applications_csrf']??'',(string)($_POST['csrf']??''))){http_response_code(419);exit('Invalid request token.');}
 try{
  $id=(int)($_POST['application_id']??0); $action=(string)($_POST['action']??'');
  $find=$pdo->prepare('SELECT id,status,user_id,vacancy_id FROM applications WHERE id=? LIMIT 1'); $find->execute([$id]); $app=$find->fetch(PDO::FETCH_ASSOC);
  if(!$app)throw new RuntimeException('Application not found.');
  $admin=currentAdmin();

  // Delete only standalone applications. Preserve jobs and their attendance/payment history.
  if($action==='delete'){
   $pdo->beginTransaction();
   try{
    $jobCheck=$pdo->prepare('SELECT id FROM jobs WHERE application_id=? LIMIT 1 FOR UPDATE');
    $jobCheck->execute([$id]);
    if($jobCheck->fetchColumn()){
     throw new RuntimeException('This application cannot be deleted because a job is already assigned. Its job, attendance and payment history must be preserved.');
    }
    $locked=$pdo->prepare('SELECT id,status,user_id,vacancy_id,applied_at FROM applications WHERE id=? LIMIT 1 FOR UPDATE');
    $locked->execute([$id]); $app=$locked->fetch(PDO::FETCH_ASSOC);
    if(!$app)throw new RuntimeException('Application not found.');
    $audit=$pdo->prepare('INSERT INTO audit_logs(actor_type,actor_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?,?)');
    $audit->execute(['admin',$admin['id'],'application.deleted','application',$id,json_encode(['status'=>$app['status'],'user_id'=>(int)$app['user_id'],'vacancy_id'=>(int)$app['vacancy_id'],'applied_at'=>$app['applied_at']],JSON_UNESCAPED_UNICODE),json_encode(['deleted'=>true],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);
    $delete=$pdo->prepare('DELETE FROM applications WHERE id=?');
    $delete->execute([$id]);
    $pdo->commit();
   }catch(Throwable $deleteError){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $deleteError;
   }
   $_SESSION['applications_success']='Application deleted successfully.';
   redirectApplications();
  }

  $map=['contact'=>'contacted','select'=>'selected','reject'=>'rejected']; if(!isset($map[$action]))throw new RuntimeException('Invalid application action.');
  $new=$map[$action]; $reason=trim((string)($_POST['reason']??'')); if($new==='rejected'&&$reason==='')$reason='Application rejected by admin';
  $stmt=$pdo->prepare('UPDATE applications SET status=?,reviewed_at=NOW(),reviewed_by_admin_id=?,rejection_reason=?,notes=? WHERE id=?');
  $stmt->execute([$new,$admin['id'],$new==='rejected'?$reason:null,$reason!==''?$reason:null,$id]);
  $audit=$pdo->prepare('INSERT INTO audit_logs(admin_user_id,user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?,?)');
  $audit->execute([$admin['id'],$app['user_id'],'application.'.$new,'application',$id,json_encode(['status'=>$app['status']]),json_encode(['status'=>$new]),$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);
  $_SESSION['applications_success']='Application status updated successfully.'; redirectApplications();
 }catch(Throwable $ex){$errors[]=$ex->getMessage();}
}
$search=trim((string)($_GET['search']??'')); $status=(string)($_GET['status']??'all');
$sql="SELECT a.id,a.status,a.applied_at,a.rejection_reason,u.id AS user_id,u.mobile,u.email,cp.first_name,cp.last_name,cp.city,v.title AS vacancy_title,v.payout_amount,v.payout_period,h.name AS hotel_name,h.city AS hotel_city,c.name AS category_name,jr.name AS job_role_name FROM applications a INNER JOIN users u ON u.id=a.user_id LEFT JOIN candidate_profiles cp ON cp.user_id=u.id INNER JOIN vacancies v ON v.id=a.vacancy_id INNER JOIN hotels h ON h.id=v.hotel_id LEFT JOIN categories c ON c.id=v.category_id LEFT JOIN job_roles jr ON jr.id=v.job_role_id WHERE u.deleted_at IS NULL";
$params=[];
if(in_array($status,['applied','contacted','selected','rejected'],true)){$sql.=' AND a.status=?';$params[]=$status;}
if($search!==''){$sql.=' AND (cp.first_name LIKE ? OR cp.last_name LIKE ? OR u.mobile LIKE ? OR v.title LIKE ? OR h.name LIKE ?)';$term='%'.$search.'%';array_push($params,$term,$term,$term,$term,$term);}
$sql.=' ORDER BY a.applied_at DESC';$stmt=$pdo->prepare($sql);$stmt->execute($params);$applications=$stmt->fetchAll(PDO::FETCH_ASSOC);
$counts=['all'=>count($applications),'applied'=>0,'contacted'=>0,'selected'=>0,'rejected'=>0];
foreach($applications as $application){if(isset($counts[$application['status']]))$counts[$application['status']]++;}
require __DIR__.'/includes/header.php'; require __DIR__.'/includes/sidebar.php';
?>
<style>
.applications-page{max-width:1600px;margin:0 auto}
.applications-heading{align-items:center;margin-bottom:22px}
.applications-heading .page-kicker{display:flex;align-items:center;gap:8px;color:#4169e1;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}
.applications-heading h1{font-size:32px;margin-bottom:6px}
.applications-count{min-width:116px;text-align:right}.applications-count strong{display:block;font-size:30px;line-height:1;font-weight:800;color:#172033}.applications-count span{display:block;color:#7a8498;font-size:12px;margin-top:6px}
.application-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:18px}
.application-stat{position:relative;display:flex;align-items:center;gap:13px;background:#fff;border:1px solid #e7ebf2;border-radius:15px;padding:15px 16px;box-shadow:0 4px 14px rgba(20,35,60,.04);transition:.2s ease}
.application-stat:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(20,35,60,.07)}
.application-stat-icon{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:#eef3ff;color:#4169e1;font-size:17px;flex:0 0 40px}
.application-stat-copy{min-width:0}.application-stat-copy strong{display:block;font-size:21px;line-height:1.1;color:#172033}.application-stat-copy span{display:block;margin-top:4px;color:#7a8498;font-size:11px;font-weight:600}
.application-stat.applied .application-stat-icon{background:#fff7e8;color:#d97706}.application-stat.contacted .application-stat-icon{background:#eef7ff;color:#2878c8}.application-stat.selected .application-stat-icon{background:#effcf6;color:#12a66a}.application-stat.rejected .application-stat-icon{background:#fff1f2;color:#e5484d}
.applications-panel{padding:0;overflow:hidden}.applications-panel-header{padding:23px 23px 17px;margin:0}.applications-panel-header h2{font-size:18px}.applications-panel-header .muted{margin:0}
.application-filters{display:grid;grid-template-columns:minmax(280px,1fr) 220px auto auto;gap:10px;align-items:center;padding:0 23px 20px;border-bottom:1px solid #edf0f5}
.application-search,.application-status-filter{position:relative}.application-search i,.application-status-filter i{position:absolute;left:13px;top:50%;transform:translateY(-50%);z-index:1;color:#8b95a7;font-size:14px}.application-search input,.application-status-filter select{height:44px;padding:0 13px 0 38px;border-radius:10px;background:#f9fbfe;border-color:#e1e6ef}.application-status-filter select{padding-left:38px;cursor:pointer}
.application-search-button{width:auto;min-width:112px;height:44px;margin:0;padding:0 18px;border-radius:10px;box-shadow:none}.application-clear{height:44px;display:inline-flex;align-items:center;justify-content:center;padding:0 13px;border:1px solid #d8deea;border-radius:10px;color:#66738a;font-size:13px;font-weight:700;background:#fff}.application-clear:hover{background:#f8faff}
.application-table-wrap{overflow-x:auto}.application-table{width:100%;border-collapse:separate;border-spacing:0;min-width:980px}.application-table th{padding:13px 16px;background:#fafbfe;color:#7a8498;border-bottom:1px solid #e7ebf2;font-size:10px;text-transform:uppercase;letter-spacing:.07em;text-align:left;white-space:nowrap}.application-table td{padding:15px 16px;border-bottom:1px solid #eef1f5;vertical-align:middle;background:#fff}.application-table tbody tr:hover td{background:#fbfcff}.application-table tbody tr:last-child td{border-bottom:0}
.candidate-cell{display:flex;align-items:center;gap:11px;min-width:190px}.candidate-avatar{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;background:linear-gradient(145deg,#4169e1,#6385e9);color:#fff;font-size:13px;font-weight:800;flex:0 0 38px}.candidate-cell strong,.primary-cell{display:block;color:#1c2739;font-size:13px;font-weight:750}.candidate-cell span,.secondary-cell{display:block;margin-top:4px;color:#8a94a6;font-size:11px;line-height:1.3}.primary-cell{font-weight:700}.secondary-cell{max-width:180px}.date-cell strong{display:block;color:#303c52;font-size:12px;white-space:nowrap}.date-cell span{display:block;margin-top:4px;color:#929bad;font-size:11px}
.application-status{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:999px;font-size:11px;font-weight:750;white-space:nowrap}.application-status>span{width:6px;height:6px;border-radius:50%;background:currentColor}.status-applied{background:#fff7e8;color:#b76a00}.status-contacted{background:#eef7ff;color:#2878c8}.status-selected{background:#effcf6;color:#087b50}.status-rejected{background:#fff1f2;color:#c9363d}
.application-actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap;min-width:220px}.application-actions form{margin:0}.application-action{height:30px!important;width:auto!important;margin:0!important;padding:0 9px!important;border-radius:8px!important;border:1px solid #e0e5ee!important;background:#fff!important;color:#526078!important;box-shadow:none!important;font-size:10px!important;font-weight:750!important;display:inline-flex!important;align-items:center;justify-content:center;white-space:nowrap;transition:.18s ease!important}.application-action:hover{transform:none!important;background:#f8faff!important;border-color:#cbd4e2!important;filter:none!important;box-shadow:none!important}.application-action.view{color:#4169e1!important;border-color:#d9e2ff!important;background:#f5f8ff!important}.application-action.contact{color:#2878c8!important;border-color:#d7eaf9!important;background:#f5fbff!important}.application-action.select{color:#087b50!important;border-color:#bce8d4!important;background:#f4fff9!important}.application-action.reject{color:#c9363d!important;border-color:#f1c8cb!important;background:#fff8f8!important}
.application-empty{padding:55px 20px!important;text-align:center}.application-empty>div{display:flex;flex-direction:column;align-items:center;gap:8px}.application-empty i{font-size:30px;color:#a8b1c0}.application-empty strong{font-size:14px;color:#374258}.application-empty span{font-size:12px;color:#8b95a7}
@media(max-width:1100px){.application-summary{grid-template-columns:repeat(3,1fr)}.application-filters{grid-template-columns:1fr 190px auto}}
@media(max-width:760px){.applications-heading{align-items:flex-start}.applications-count{display:none}.application-summary{grid-template-columns:repeat(2,1fr)}.application-filters{grid-template-columns:1fr;padding-left:16px;padding-right:16px}.application-search-button,.application-clear{width:100%}.applications-panel-header{padding:18px 16px}.applications-page{max-width:none}}
@media(max-width:480px){.application-summary{grid-template-columns:1fr}.applications-heading h1{font-size:27px}}
</style>

<main class="content applications-page">
    <div class="topbar applications-heading">
        <div>
            <div class="page-kicker"><i class="bi bi-briefcase"></i> Workforce Management</div>
            <h1>Applications</h1>
            <p class="muted">Review, shortlist and manage candidate applications for hotel vacancies.</p>
        </div>
        <div class="applications-count"><strong><?=count($applications)?></strong><span>Applications</span></div>
    </div>

    <?php if($success):?><div class="alert success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif;?>
    <?php foreach($errors as $error):?><div class="alert error"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endforeach;?>

    <div class="application-summary">
        <div class="application-stat"><div class="application-stat-icon"><i class="bi bi-grid"></i></div><div class="application-stat-copy"><strong><?=$counts['all']?></strong><span>All Applications</span></div></div>
        <div class="application-stat applied"><div class="application-stat-icon"><i class="bi bi-hourglass-split"></i></div><div class="application-stat-copy"><strong><?=$counts['applied']?></strong><span>Applied</span></div></div>
        <div class="application-stat contacted"><div class="application-stat-icon"><i class="bi bi-telephone"></i></div><div class="application-stat-copy"><strong><?=$counts['contacted']?></strong><span>Contacted</span></div></div>
        <div class="application-stat selected"><div class="application-stat-icon"><i class="bi bi-check2-circle"></i></div><div class="application-stat-copy"><strong><?=$counts['selected']?></strong><span>Selected</span></div></div>
        <div class="application-stat rejected"><div class="application-stat-icon"><i class="bi bi-x-circle"></i></div><div class="application-stat-copy"><strong><?=$counts['rejected']?></strong><span>Rejected</span></div></div>
    </div>

    <section class="panel applications-panel">
        <div class="panel-header applications-panel-header">
            <div><h2>Application Pipeline</h2><p class="muted">Search applications and manage every candidate from one place.</p></div>
        </div>

        <form class="application-filters" method="get">
            <div class="application-search">
                <i class="bi bi-search"></i>
                <input type="search" name="search" value="<?=e($search)?>" placeholder="Search candidate, mobile, vacancy or hotel">
            </div>
            <div class="application-status-filter">
                <i class="bi bi-funnel"></i>
                <select name="status">
                    <option value="all">All Status</option>
                    <?php foreach(['applied'=>'Applied','contacted'=>'Contacted','selected'=>'Selected','rejected'=>'Rejected'] as $key=>$label):?>
                        <option value="<?=e($key)?>" <?=$status===$key?'selected':''?>><?=e($label)?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <button class="application-search-button" type="submit"><i class="bi bi-search"></i>&nbsp; Search</button>
            <?php if($search!==''||$status!=='all'):?><a class="application-clear" href="applications.php">Clear</a><?php endif;?>
        </form>

        <div class="application-table-wrap">
            <table class="application-table">
                <thead><tr><th>Candidate</th><th>Vacancy</th><th>Hotel</th><th>Applied</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if(!$applications):?><tr><td colspan="6" class="application-empty"><div><i class="bi bi-inbox"></i><strong>No applications found</strong><span>Try changing your search or status filter.</span></div></td></tr><?php endif;?>
                <?php foreach($applications as $row):?>
                    <tr>
                        <td><div class="candidate-cell"><div class="candidate-avatar"><?=e(strtoupper(substr(trim(($row['first_name']??'').' '.($row['last_name']??'')),0,1)))?></div><div><strong><?=e(trim(($row['first_name']??'').' '.($row['last_name']??'')))?></strong><span><?=e($row['mobile'])?><?=!empty($row['city'])?' · '.e($row['city']):''?></span></div></div></td>
                        <td><div class="primary-cell"><?=e($row['vacancy_title'])?></div><div class="secondary-cell"><?=e($row['category_name']??'')?><?=!empty($row['job_role_name'])?' · '.e($row['job_role_name']):''?></div></td>
                        <td><div class="primary-cell"><?=e($row['hotel_name'])?></div><div class="secondary-cell"><?=e($row['hotel_city']??'')?></div></td>
                        <td><div class="date-cell"><strong><?=e(date('d M Y',strtotime($row['applied_at'])))?></strong><span><?=e(date('h:i A',strtotime($row['applied_at'])))?></span></div></td>
                        <td><span class="application-status status-<?=e($row['status'])?>"><span></span><?=e(ucfirst($row['status']))?></span></td>
                        <td><div class="application-actions">
                            <a class="application-action view" href="candidates.php?view=<?=e((string)$row['user_id'])?>"><i class="bi bi-person"></i>&nbsp; Candidate</a>
                            <form method="post" onsubmit="return confirm('Are you sure you want to permanently delete this application? Candidate account will not be deleted. Applications linked to an assigned job cannot be deleted.');"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="application_id" value="<?=e((string)$row['id'])?>"><input type="hidden" name="action" value="delete"><button class="application-action reject" type="submit"><i class="bi bi-trash"></i>&nbsp; Delete</button></form>
                            <?php if($row['status']==='applied'):?>
                                <form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="application_id" value="<?=e((string)$row['id'])?>"><input type="hidden" name="action" value="contact"><button class="application-action contact" type="submit">Contact</button></form>
                                <form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="application_id" value="<?=e((string)$row['id'])?>"><input type="hidden" name="action" value="select"><button class="application-action select" type="submit"><i class="bi bi-check2"></i>&nbsp; Select</button></form>
                                <form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="application_id" value="<?=e((string)$row['id'])?>"><input type="hidden" name="action" value="reject"><button class="application-action reject" type="submit">Reject</button></form>
                            <?php elseif($row['status']==='contacted'):?>
                                <form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="application_id" value="<?=e((string)$row['id'])?>"><input type="hidden" name="action" value="select"><button class="application-action select" type="submit"><i class="bi bi-check2"></i>&nbsp; Select</button></form>
                            <?php endif;?>
                        </div></td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>