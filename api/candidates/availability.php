<?php
declare(strict_types=1);
require_once __DIR__.'/../config/api.php';
$u=requireUser($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $s = $pdo->prepare('SELECT availability_status FROM candidate_profiles WHERE user_id=? LIMIT 1');
    $s->execute([$u['user_id']]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!$row) respond(['success'=>false,'message'=>'Candidate profile not found'],404);
    respond(['success'=>true,'data'=>['availability_status'=>$row['availability_status'] ?? 'available','available'=>($row['availability_status'] ?? 'available') === 'available']]);
}

// Some production hosts/WAFs reject PATCH requests even though the API supports them.
// Accept POST as the canonical mobile update method as well.
if ($_SERVER['REQUEST_METHOD'] === 'PATCH' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = jsonInput();
    $value = strtolower(trim((string)($d['availability_status'] ?? ($d['available'] ?? ''))));
    if ($value === '1' || $value === 'true') $value = 'available';
    if ($value === '0' || $value === 'false') $value = 'unavailable';
    if (!in_array($value, ['available','unavailable'], true)) {
        respond(['success'=>false,'message'=>'availability_status must be available or unavailable'],422);
    }
    $s = $pdo->prepare('UPDATE candidate_profiles SET availability_status=? WHERE user_id=?');
    $s->execute([$value,$u['user_id']]);
    respond(['success'=>true,'message'=>'Availability updated','data'=>['availability_status'=>$value,'available'=>$value==='available']]);
}

respond(['success'=>false,'message'=>'Method not allowed'],405);
