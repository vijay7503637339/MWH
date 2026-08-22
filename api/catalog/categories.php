<?php
declare(strict_types=1); require_once __DIR__ . '/../config/api.php';
if($_SERVER['REQUEST_METHOD']!=='GET') respond(['success'=>false,'message'=>'Method not allowed'],405);
$categoryId=(int)($_GET['category_id']??0);
if($categoryId>0){$s=$pdo->prepare('SELECT id,name,description,sort_order FROM job_roles WHERE category_id=? AND is_active=1 AND deleted_at IS NULL ORDER BY sort_order,name');$s->execute([$categoryId]);respond(['success'=>true,'data'=>$s->fetchAll(PDO::FETCH_ASSOC)]);}
$s=$pdo->query('SELECT id,name,description,sort_order FROM categories WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order,name');
respond(['success'=>true,'data'=>$s->fetchAll(PDO::FETCH_ASSOC)]);
