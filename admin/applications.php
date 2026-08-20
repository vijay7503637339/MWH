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
  $map=['contact'=>'contacted','select'=>'selected','reject'=>'rejected']; if(!isset($map[$action]))throw new RuntimeException('Invalid application action.');
  $new=$map[$action]; $reason=trim((string)($_POST['reason']??'')); if($new==='rejected'&&$reason==='')$reason='Application rejected by admin';
  $admin=currentAdmin();
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
require __DIR__.'/includes/header.php'; require __DIR__.'/includes/sidebar.php';
?>
<main class="content applications-page">
    <div class="topbar applications-heading">
        <div>
            <div class="page-kicker"><i class="bi bi-briefcase"></i> Workforce Management</div>
            <h1>Applications</h1>
            <p class="muted">Review, shortlist and manage candidate applications for hotel vacancies.</p>
        </div>
        <div class="applications-count"><strong><?=count($applications)?></strong><span>Applications</span></div>
    </div>

    <?php if($success):?><div class="alert success"><i class="bi bi-check-circle"></i><?=e($success)?></div><?php endif;?>
    <?php foreach($errors as $error):?><div class="alert error"><i class="bi bi-exclamation-circle"></i><?=e($error)?></div><?php endforeach;?>

    <section class="panel applications-panel">
        <div class="panel-header applications-panel-header">
            <div><h2>Application Pipeline</h2><p class="muted">Search applications and filter candidates by their current status.</p></div>
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
            <button class="application-search-button" type="submit"><i class="bi bi-search"></i> Search</button>
            <?php if($search!==''||$status!=='all'):?><a class="application-clear" href="applications.php">Clear</a><?php endif;?>
        </form>

        <div class="application-table-wrap">
            <table class="application-table">
                <thead><tr><th>Candidate</th><th>Vacancy</th><th>Hotel</th><th>Applied</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if(!$applications):?><tr><td colspan="6" class="application-empty"><div><i class="bi bi-inbox"></i><strong>No applications found</strong><span>Try changing your search or status filter.</span></div></td></tr><?php endif;?>
                <?php foreach($applications as $row):?>
                    <tr>
                        <td>
                            <div class="candidate-cell"><div class="candidate-avatar"><?=e(strtoupper(substr(trim(($row['first_name']??'').' '.($row['last_name']??'')),0,1)))?></div><div><strong><?=e(trim(($row['first_name']??'').' '.($row['last_name']??'')))?></strong><span><?=e($row['mobile'])?><?=!empty($row['city'])?' · '.e($row['city']):''?></span></div></div>
                        </td>
                        <td><div class="primary-cell"><?=e($row['vacancy_title'])?></div><div class="secondary-cell"><?=e($row['category_name']??'')?><?=!empty($row['job_role_name'])?' · '.e($row['job_role_name']):''?></div></td>
                        <td><div class="primary-cell"><?=e($row['hotel_name'])?></div><div class="secondary-cell"><?=e($row['hotel_city']??'')?></div></td>
                        <td><div class="date-cell"><strong><?=e(date('d M Y',strtotime($row['applied_at'])))?></strong><span><?=e(date('h:i A',strtotime($row['applied_at'])))?></span></div></td>
                        <td><span class="application-status status-<?=e($row['status'])?>"><span></span><?=e(ucfirst($row['status']))?></span></td>
                        <td>
                            <div class="application-actions">
                                <a class="application-action view" href="candidates.php?view=<?=e((string)$row['user_id'])?>"><i class="bi bi-person"></i> Candidate</a>
                                <?php if($row['status']==='applied'):?>
                                    <form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="application_id" value="<?=e((string)$row['id'])?>"><input type="hidden" name="action" value="contact"><button class="application-action contact" type="submit">Contact</button></form>
                                    <form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="application_id" value="<?=e((string)$row['id'])?>"><input type="hidden" name="action" value="select"><button class="application-action select" type="submit"><i class="bi bi-check2"></i> Select</button></form>
                                    <form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="application_id" value="<?=e((string)$row['id'])?>"><input type="hidden" name="action" value="reject"><button class="application-action reject" type="submit">Reject</button></form>
                                <?php elseif($row['status']==='contacted'):?>
                                    <form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="application_id" value="<?=e((string)$row['id'])?>"><input type="hidden" name="action" value="select"><button class="application-action select" type="submit"><i class="bi bi-check2"></i> Select</button></form>
                                <?php endif;?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>