<?php
declare(strict_types=1);
require_once __DIR__.'/../config/api.php';
$u=requireUser($pdo);

$s=$pdo->prepare("SELECT storage_path,mime_type FROM candidate_documents WHERE user_id=? AND document_type='other' AND deleted_at IS NULL AND mime_type LIKE 'image/%' ORDER BY created_at ASC,id ASC LIMIT 1");
$s->execute([$u['user_id']]);
$row=$s->fetch(PDO::FETCH_ASSOC);
if(!$row){http_response_code(404);exit;}

$relative=ltrim((string)$row['storage_path'],'/');
$file=__DIR__.'/../'.$relative;
if(!is_file($file)){http_response_code(404);exit;}

header('Content-Type: '.($row['mime_type'] ?: 'image/jpeg'));
header('Cache-Control: private, max-age=3600');
header('Content-Length: '.filesize($file));
readfile($file);
