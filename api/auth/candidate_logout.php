<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/api.php';
if($_SERVER['REQUEST_METHOD']!=='POST') respond(['success'=>false,'message'=>'Method not allowed'],405);
$user=requireUser($pdo); $token=bearerToken(); $pdo->prepare('UPDATE api_tokens SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=?')->execute([hash('sha256',$token)]);
respond(['success'=>true,'message'=>'Logged out successfully']);
