<?php
declare(strict_types=1); require_once __DIR__ . '/../config/api.php';
if($_SERVER['REQUEST_METHOD']!=='GET') respond(['success'=>false,'message'=>'Method not allowed'],405);
$page=max(1,(int)($_GET['page']??1)); $perPage=min(50,max(1,(int)($_GET['per_page']??20))); $offset=($page-1)*$perPage; $city=trim((string)($_GET['city']??''));
$where="status='active' AND deleted_at IS NULL"; $params=[]; if($city!==''){$where.=' AND city=?';$params[]=$city;}
$c=$pdo->prepare("SELECT COUNT(*) FROM hotels WHERE $where");$c->execute($params);$total=(int)$c->fetchColumn();
$s=$pdo->prepare("SELECT id,name,description,address_line,city,state,postcode,country,latitude,longitude,contact_name,contact_phone FROM hotels WHERE $where ORDER BY name LIMIT $perPage OFFSET $offset");$s->execute($params);respond(['success'=>true,'data'=>$s->fetchAll(PDO::FETCH_ASSOC),'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'last_page'=>$total?ceil($total/$perPage):0]]);
