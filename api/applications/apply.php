<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'POST request required']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
$userId = (int)($input['user_id'] ?? 0);
$vacancyId = (int)($input['vacancy_id'] ?? 0);

if ($userId <= 0 || $vacancyId <= 0) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'user_id and vacancy_id are required']);
    exit;
}

try {
    $candidate = $pdo->prepare("SELECT id, account_status FROM users WHERE id=? AND deleted_at IS NULL LIMIT 1");
    $candidate->execute([$userId]);
    $user = $candidate->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new RuntimeException('Candidate not found');
    if ($user['account_status'] !== 'verified') throw new RuntimeException('Candidate account is not verified');

    $vacancy = $pdo->prepare("SELECT id, hotel_id, title, status, openings_count FROM vacancies WHERE id=? AND deleted_at IS NULL LIMIT 1");
    $vacancy->execute([$vacancyId]);
    $job = $vacancy->fetch(PDO::FETCH_ASSOC);
    if (!$job) throw new RuntimeException('Vacancy not found');
    if ($job['status'] !== 'published') throw new RuntimeException('This vacancy is not available for applications');

    $existing = $pdo->prepare('SELECT id,status FROM applications WHERE vacancy_id=? AND user_id=? LIMIT 1');
    $existing->execute([$vacancyId,$userId]);
    $application = $existing->fetch(PDO::FETCH_ASSOC);
    if ($application) {
        http_response_code(409);
        echo json_encode(['success'=>false,'message'=>'You have already applied for this vacancy','application_id'=>(int)$application['id'],'status'=>$application['status']]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO applications (vacancy_id,user_id,status,applied_at) VALUES (?,?,'applied',NOW())");
    $stmt->execute([$vacancyId,$userId]);
    $id = (int)$pdo->lastInsertId();

    echo json_encode(['success'=>true,'message'=>'Application submitted successfully','application_id'=>$id,'status'=>'applied']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e instanceof RuntimeException ? $e->getMessage() : 'Unable to submit application']);
}
