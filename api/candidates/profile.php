<?php
declare(strict_types=1);
require_once __DIR__.'/../config/api.php';
$u=requireUser($pdo);

if($_SERVER['REQUEST_METHOD']==='GET'){
 $s=$pdo->prepare("SELECT u.id,u.mobile,u.email,u.account_status,u.rejection_reason,u.verified_at,cp.first_name,cp.last_name,cp.date_of_birth,cp.gender,cp.full_address,cp.city,cp.country,cp.postcode,pd.experience_years,pd.previous_company_name,jp.category_id,c.name category_name,jp.job_role_id,jr.name job_role_name,jp.max_travel_distance_km,jp.preferred_working_hours,cpp.payment_type,cpp.account_holder_name,cpp.bank_name,cpp.ifsc_code,cpp.branch_name,cpp.upi_id FROM users u LEFT JOIN candidate_profiles cp ON cp.user_id=u.id LEFT JOIN professional_details pd ON pd.user_id=u.id LEFT JOIN job_preferences jp ON jp.user_id=u.id LEFT JOIN categories c ON c.id=jp.category_id LEFT JOIN job_roles jr ON jr.id=jp.job_role_id LEFT JOIN candidate_payment_profiles cpp ON cpp.user_id=u.id WHERE u.id=? LIMIT 1");
 $s->execute([$u['user_id']]);
 $row=$s->fetch(PDO::FETCH_ASSOC);
 if(!$row)respond(['success'=>false,'message'=>'Candidate not found'],404);
 $photo=$pdo->prepare("SELECT id,storage_path FROM candidate_documents WHERE user_id=? AND document_type='other' AND deleted_at IS NULL AND mime_type LIKE 'image/%' ORDER BY created_at ASC,id ASC LIMIT 1");
 $photo->execute([$u['user_id']]);
 $photoRow=$photo->fetch(PDO::FETCH_ASSOC);
 $row['profile_photo_url']=$photoRow ? '/candidates/profile_photo.php' : null;
 respond(['success'=>true,'data'=>$row]);
}

if($_SERVER['REQUEST_METHOD']==='PATCH'||$_SERVER['REQUEST_METHOD']==='POST'){
 $d=jsonInput();
 $pdo->beginTransaction();
 try{
  if(array_key_exists('email',$d)){
   $email=trim((string)$d['email']);
   if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid email');
   $pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute([$email,$u['user_id']]);
  }
  $sets=[];$vals=[];
  foreach(['first_name','last_name','date_of_birth','gender','full_address','city','postcode'] as $f){
   if(array_key_exists($f,$d)){$sets[]="$f=?";$vals[]=(string)$d[$f];}
  }
  if($sets){$vals[]=$u['user_id'];$pdo->prepare('UPDATE candidate_profiles SET '.implode(',',$sets).' WHERE user_id=?')->execute($vals);}
  $pdo->commit();
  respond(['success'=>true,'message'=>'Profile updated']);
 }catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  respond(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Unable to update profile'],500);
 }
}
respond(['success'=>false,'message'=>'Method not allowed'],405);
