<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/api.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success'=>false,'message'=>'Method not allowed'],405);
$data=jsonInput(); $mobile=trim((string)($data['mobile']??'')); $password=(string)($data['password']??'');
if($mobile===''||$password==='') respond(['success'=>false,'message'=>'Mobile and password are required'],422);
// Password is accepted when supplied by future credential migration; current signup stores account state only.
$stmt=$pdo->prepare('SELECT u.id,u.mobile,u.email,u.account_status,cp.first_name,cp.last_name FROM users u LEFT JOIN candidate_profiles cp ON cp.user_id=u.id WHERE u.mobile=? AND u.deleted_at IS NULL LIMIT 1');
$stmt->execute([$mobile]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$user) respond(['success'=>false,'message'=>'Invalid mobile or password'],401);
if($user['account_status']==='blocked') respond(['success'=>false,'message'=>'Account is blocked'],403);
if($user['account_status']==='pending_verification') respond(['success'=>false,'message'=>'Account is pending verification','account_status'=>$user['account_status']],403);
$token=issueToken($pdo,(int)$user['id']);
$pdo->prepare('UPDATE users SET last_login_at=UTC_TIMESTAMP() WHERE id=?')->execute([$user['id']]);
respond(['success'=>true,'message'=>'Login successful','data'=>['token'=>$token,'user'=>['id'=>(int)$user['id'],'mobile'=>$user['mobile'],'email'=>$user['email'],'first_name'=>$user['first_name'],'last_name'=>$user['last_name'],'account_status'=>$user['account_status']]]]);
