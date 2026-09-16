<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';

/**
 * Permanently removes candidate account data owned by $userId.
 *
 * The deletion is intentionally explicit because some legacy foreign keys in
 * the MWH schema do not cascade from users. Uploaded candidate files are also
 * removed after the database transaction succeeds.
 */
function deleteCandidateAccount(PDO $pdo, int $userId): array
{
    $fileStmt = $pdo->prepare(
        "SELECT storage_path FROM candidate_documents WHERE user_id=? AND storage_path IS NOT NULL"
    );
    $fileStmt->execute([$userId]);
    $paths = array_values(array_filter(array_map(
        static fn ($row) => trim((string)($row['storage_path'] ?? '')),
        $fileStmt->fetchAll(PDO::FETCH_ASSOC)
    )));

    $pdo->beginTransaction();
    try {
        $queries = [
            'DELETE FROM attendance_records WHERE user_id=?',
            'DELETE FROM payments WHERE user_id=?',
            'DELETE FROM jobs WHERE user_id=?',
            'DELETE FROM applications WHERE user_id=?',
            'DELETE FROM notifications WHERE user_id=?',
            'DELETE FROM support_tickets WHERE user_id=?',
            'DELETE FROM referrals WHERE referrer_user_id=? OR referred_user_id=?',
            'DELETE FROM candidate_documents WHERE user_id=?',
            'DELETE FROM candidate_payment_profiles WHERE user_id=?',
            'DELETE FROM job_preferences WHERE user_id=?',
            'DELETE FROM professional_details WHERE user_id=?',
            'DELETE FROM candidate_profiles WHERE user_id=?',
            'DELETE FROM api_tokens WHERE user_id=?',
            'DELETE FROM users WHERE id=?',
        ];

        foreach ($queries as $index => $sql) {
            $stmt = $pdo->prepare($sql);
            if ($index === 6) {
                $stmt->execute([$userId, $userId]);
            } else {
                $stmt->execute([$userId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $uploadsRoot = realpath(__DIR__ . '/../uploads');
    $filesDeleted = 0;
    if ($uploadsRoot !== false) {
        foreach ($paths as $relative) {
            $relative = ltrim(str_replace('\\', '/', $relative), '/');
            if (!str_starts_with($relative, 'uploads/')) continue;
            $file = realpath(__DIR__ . '/../' . $relative);
            if ($file !== false && str_starts_with($file, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($file)) {
                if (@unlink($file)) $filesDeleted++;
            }
        }
    }

    return ['files_deleted' => $filesDeleted];
}

// This endpoint is intentionally API-only. The public Google Play deletion
// request page is /MWH/delete-account.php and creates an admin-review request.
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'DELETE') {
    respond(['success'=>false,'message'=>'Use the authenticated in-app account deletion flow. Public web deletion requests must be submitted through /MWH/delete-account.php.'],405);
}

$user = requireUser($pdo);
$data = jsonInput();
$confirmed = $data['confirm'] ?? $data['confirmation'] ?? false;
if (!($confirmed === true || $confirmed === 1 || $confirmed === '1' || strtoupper((string)$confirmed) === 'DELETE')) {
    respond(['success'=>false,'message'=>'Account deletion confirmation is required'],422);
}

try {
    $result = deleteCandidateAccount($pdo, (int)$user['user_id']);
    respond(['success'=>true,'message'=>'Your Maan World Hospitality account and associated data have been permanently deleted.','data'=>$result]);
} catch (Throwable $e) {
    error_log('MWH in-app account deletion failed: ' . $e->getMessage());
    respond(['success'=>false,'message'=>'Unable to delete the account right now. Please try again or contact support.'],500);
}
