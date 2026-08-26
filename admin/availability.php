<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';
$pageTitle = 'Candidate Availability';
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));
if (!in_array($status, ['all','available','unavailable'], true)) $status = 'all';
$sql = "SELECT u.id,u.mobile,cp.first_name,cp.last_name,cp.city,cp.availability_status,c.name AS category_name,jr.name AS job_role_name FROM users u LEFT JOIN candidate_profiles cp ON cp.user_id=u.id LEFT JOIN job_preferences jp ON jp.user_id=u.id LEFT JOIN categories c ON c.id=jp.category_id LEFT JOIN job_roles jr ON jr.id=jp.job_role_id WHERE u.deleted_at IS NULL";
$params = [];
if ($search !== '') { $sql .= " AND (u.mobile LIKE ? OR CONCAT(COALESCE(cp.first_name,''),' ',COALESCE(cp.last_name,'')) LIKE ? OR cp.city LIKE ? OR c.name LIKE ? OR jr.name LIKE ?)"; $term='%'.$search.'%'; $params=[$term,$term,$term,$term,$term]; }
if ($status !== 'all') { $sql .= ' AND cp.availability_status=?'; $params[]=$status; }
$sql .= ' ORDER BY cp.availability_status ASC, cp.first_name ASC, cp.last_name ASC';
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<style>
.availability-page{max-width:1500px;margin:0 auto}.availability-tabs{display:flex;gap:8px;margin-bottom:16px}.availability-tabs a{padding:9px 13px;border:1px solid #dfe4ec;border-radius:9px;background:#fff;color:#66738a;text-decoration:none;font-size:12px;font-weight:750}.availability-tabs a.active{background:#006b3c;color:#fff;border-color:#006b3c}.availability-filter{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;padding:15px;background:#fff;border:1px solid #e7ebf2;border-radius:12px;margin-bottom:18px}.availability-filter label{display:block;font-size:10px;font-weight:800;color:#7a8498;text-transform:uppercase;margin-bottom:5px}.availability-filter input,.availability-filter select{height:38px;border:1px solid #dfe4ec;border-radius:8px;padding:0 11px;font-size:12px;min-width:220px}.availability-filter button{height:38px;border:0;border-radius:8px;background:#4b72ee;color:#fff;padding:0 16px;font-size:12px;font-weight:800}.availability-filter a{height:38px;display:inline-flex;align-items:center;border:1px solid #dfe4ec;border-radius:8px;padding:0 14px;color:#66738a;text-decoration:none;font-size:12px;font-weight:800}.availability-table{width:100%;border-collapse:separate;border-spacing:0;min-width:800px}.availability-table th{padding:13px 15px;background:#fafbfe;color:#7a8498;border-bottom:1px solid #e7ebf2;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.07em}.availability-table td{padding:14px 15px;border-bottom:1px solid #eef1f5;background:#fff;vertical-align:middle}.availability-table-wrap{overflow:auto}.candidate-name{font-size:13px;font-weight:750;color:#1c2739}.muted-small{font-size:11px;color:#8a94a6;margin-top:3px}.availability-badge{display:inline-flex;padding:6px 10px;border-radius:999px;font-size:10px;font-weight:800}.availability-badge.available{background:#effcf6;color:#087b50}.availability-badge.unavailable{background:#fff1f2;color:#c9363d}.availability-empty{text-align:center;padding:45px!important;color:#7a8498}
</style>
<main class="content availability-page">
  <div class="topbar"><div><div class="page-kicker">WORKFORCE MANAGEMENT</div><h1>Candidate Availability</h1><p class="muted">Live availability reported by candidates from the mobile app.</p></div></div>
  <div class="availability-tabs">
    <?php foreach(['all'=>'All','available'=>'Available','unavailable'=>'Unavailable'] as $key=>$label): ?><a class="<?= $status===$key?'active':'' ?>" href="availability.php?status=<?=e($key)?><?= $search!==''?'&search='.urlencode($search):'' ?>"><?=e($label)?></a><?php endforeach; ?>
  </div>
  <form method="get" class="availability-filter">
    <div><label>Search</label><input type="search" name="search" value="<?=e($search)?>" placeholder="Candidate, mobile, city, category..." /></div>
    <div><label>Status</label><select name="status"><option value="all" <?= $status==='all'?'selected':'' ?>>All</option><option value="available" <?= $status==='available'?'selected':'' ?>>Available</option><option value="unavailable" <?= $status==='unavailable'?'selected':'' ?>>Unavailable</option></select></div>
    <button type="submit"><i class="bi bi-search"></i>&nbsp; Search / Filter</button>
    <a href="availability.php">Clear</a>
  </form>
  <section class="panel"><div class="panel-header"><div><h2>Candidates</h2><p class="muted"><?=count($rows)?> result<?=count($rows)===1?'':'s'?></p></div></div><div class="availability-table-wrap"><table class="availability-table"><thead><tr><th>Candidate</th><th>Mobile</th><th>Category / Role</th><th>Preferred Area</th><th>Availability</th></tr></thead><tbody>
  <?php if(!$rows): ?><tr><td colspan="5" class="availability-empty">No candidates found.</td></tr><?php endif; ?>
  <?php foreach($rows as $row): $name=trim(($row['first_name']??'').' '.($row['last_name']??'')); $availability=$row['availability_status']??'available'; ?><tr><td><div class="candidate-name"><?=e($name?:$row['mobile'])?></div><div class="muted-small">Candidate #<?=e((string)$row['id'])?></div></td><td><?=e($row['mobile'])?></td><td><div class="candidate-name"><?=e($row['category_name']??'—')?></div><div class="muted-small"><?=e($row['job_role_name']??'')?></div></td><td><?=e($row['city']??'—')?></td><td><span class="availability-badge <?=e($availability)?>"><?=e(ucfirst($availability))?></span></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
