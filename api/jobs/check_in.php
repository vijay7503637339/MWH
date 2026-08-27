<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = requireUser($pdo);
$input = jsonInput();
$jobId = isset($input['job_id']) ? (int)$input['job_id'] : 0;

if ($jobId <= 0) {
    respond(['success' => false, 'message' => 'job_id is required'], 422);
}

// Check-in must include a fresh hotel-front photo captured by the phone camera.
// Multipart field name: photo. Gallery is not accepted by the app UI; the backend
// validates the uploaded file as an image as an additional safety check.
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    respond(['success' => false, 'message' => 'A hotel front photo is required to check in'], 422);
}

$photo = $_FILES['photo'];
$maxPhotoSize = 8 * 1024 * 1024;
if ((int)$photo['size'] <= 0 || (int)$photo['size'] > $maxPhotoSize) {
    respond(['success' => false, 'message' => 'Hotel photo must be smaller than 8 MB'], 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($photo['tmp_name']);
$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
if (!isset($allowedMime[$mime])) {
    respond(['success' => false, 'message' => 'Only JPG, PNG or WEBP hotel photos are allowed'], 422);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT j.id, j.status, j.start_date, j.end_date, j.payout_amount,
                v.title, h.id AS hotel_id, h.name AS hotel_name
         FROM jobs j
         INNER JOIN vacancies v ON v.id = j.vacancy_id
         INNER JOIN hotels h ON h.id = j.hotel_id
         WHERE j.id = ? AND j.user_id = ?
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([$jobId, (int)$user['user_id']]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'Job not found'], 404);
    }

    $jobStatus = strtolower(trim((string)$job['status']));
    if (!in_array($jobStatus, ['assigned', 'upcoming', 'scheduled', 'confirmed'], true)) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'This job cannot be checked in now'], 409);
    }

    // Check-in remains available for the whole scheduled calendar day.
    // There is intentionally no start-time cutoff, so a candidate arriving late
    // can still check in after the scheduled start time.
    $today = gmdate('Y-m-d');
    if (!empty($job['start_date']) && $today < $job['start_date']) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'Check-in is not available before the job start date'], 409);
    }
    if (!empty($job['end_date']) && $today > $job['end_date']) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'This job has already ended'], 409);
    }

    $openStmt = $pdo->prepare(
        'SELECT id, check_in_at FROM attendance_records
         WHERE job_id = ? AND user_id = ? AND check_out_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );
    $openStmt->execute([$jobId, (int)$user['user_id']]);
    $open = $openStmt->fetch(PDO::FETCH_ASSOC);
    if ($open) {
        $pdo->rollBack();
        respond([
            'success' => false,
            'message' => 'You are already checked in for this job',
            'data' => [
                'job_id' => $jobId,
                'attendance_id' => (int)$open['id'],
                'status' => 'checked_in',
                'checked_in_at' => $open['check_in_at'],
            ],
        ], 409);
    }

    $uploadDir = dirname(__DIR__) . '/uploads/checkins/' . (int)$user['user_id'];
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create check-in upload directory');
    }

    $extension = $allowedMime[$mime];
    $storedName = 'job_' . $jobId . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
    $destination = $uploadDir . '/' . $storedName;
    if (!move_uploaded_file($photo['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to save hotel photo');
    }
    $photoPath = 'uploads/checkins/' . (int)$user['user_id'] . '/' . $storedName;

    $latitude = isset($input['latitude']) && $input['latitude'] !== '' ? (float)$input['latitude'] : null;
    $longitude = isset($input['longitude']) && $input['longitude'] !== '' ? (float)$input['longitude'] : null;

    $insert = $pdo->prepare(
        'INSERT INTO attendance_records
            (job_id, user_id, check_in_at, check_in_photo_path, check_in_capture_source,
             check_in_latitude, check_in_longitude, check_in_notes)
         VALUES (?, ?, UTC_TIMESTAMP(), ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $jobId,
        (int)$user['user_id'],
        $photoPath,
        'camera',
        $latitude,
        $longitude,
        isset($input['notes']) ? trim((string)$input['notes']) : null,
    ]);
    $attendanceId = (int)$pdo->lastInsertId();

    $update = $pdo->prepare("UPDATE jobs SET status = 'checked_in', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $update->execute([$jobId]);

    $pdo->commit();

    $timeStmt = $pdo->prepare('SELECT check_in_at, check_in_photo_path FROM attendance_records WHERE id = ? LIMIT 1');
    $timeStmt->execute([$attendanceId]);
    $attendance = $timeStmt->fetch(PDO::FETCH_ASSOC);

    respond([
        'success' => true,
        'message' => 'Checked in successfully',
        'data' => [
            'job_id' => $jobId,
            'attendance_id' => $attendanceId,
            'status' => 'checked_in',
            'checked_in_at' => $attendance['check_in_at'] ?? null,
            'check_in_photo_path' => $attendance['check_in_photo_path'] ?? $photoPath,
            'hotel' => [
                'id' => (int)$job['hotel_id'],
                'name' => $job['hotel_name'],
            ],
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (isset($destination) && is_file($destination)) @unlink($destination);
    respond(['success' => false, 'message' => 'Could not check in'], 500);
}
