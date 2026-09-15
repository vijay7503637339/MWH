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
        // Child records first. These tables intentionally cover the candidate's
        // profile, documents, applications, jobs, attendance, payments and support data.
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

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    // Delete uploaded files after the DB transaction. Only paths under the API
    // uploads directory are eligible, preventing accidental arbitrary unlink().
    $uploadsRoot = realpath(__DIR__ . '/../uploads');
    $filesDeleted = 0;
    if ($uploadsRoot !== false) {
        foreach ($paths as $relative) {
            $relative = ltrim(str_replace('\\', '/', $relative), '/');
            if (!str_starts_with($relative, 'uploads/')) {
                continue;
            }
            $file = realpath(__DIR__ . '/../' . $relative);
            if ($file !== false && str_starts_with($file, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($file)) {
                if (@unlink($file)) {
                    $filesDeleted++;
                }
            }
        }
    }

    return ['files_deleted' => $filesDeleted];
}

function htmlResponse(string $title, string $message, bool $success = false): never
{
    http_response_code($success ? 200 : 400);
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $safeTitle . ' | Maan World Hospitality</title>'
        . '<style>body{margin:0;background:#f5f7f6;color:#17231c;font-family:Arial,sans-serif}.wrap{max-width:560px;margin:48px auto;padding:24px}.card{background:#fff;border:1px solid #d9ded9;border-radius:18px;padding:28px;box-shadow:0 8px 30px rgba(0,0,0,.06)}h1{margin:0 0 12px;color:#004d2b;font-size:26px}p{line-height:1.6;color:#53615a}.danger{color:#b42318}label{display:block;font-weight:700;margin:16px 0 7px}input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #cfd6d1;border-radius:10px;font-size:16px}button{width:100%;margin-top:20px;padding:13px;border:0;border-radius:10px;background:#b42318;color:#fff;font-weight:700;font-size:16px;cursor:pointer}.note{font-size:13px;color:#6a746e}</style></head><body><main class="wrap"><section class="card">'
        . '<h1>' . $safeTitle . '</h1><p>' . nl2br($safeMessage) . '</p>'
        . ($success ? '<p><strong>You can now uninstall the Maan World Hospitality app.</strong></p>' : '')
        . '</section></main></body></html>';
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Public Google Play account-deletion web resource.
if ($method === 'GET') {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Delete Account | Maan World Hospitality</title>'
        . '<style>body{margin:0;background:#f5f7f6;color:#17231c;font-family:Arial,sans-serif}.wrap{max-width:560px;margin:32px auto;padding:20px}.card{background:#fff;border:1px solid #d9ded9;border-radius:20px;padding:28px;box-shadow:0 8px 30px rgba(0,0,0,.06)}.brand{font-size:13px;font-weight:700;letter-spacing:.8px;color:#0b8f3e;text-transform:uppercase}h1{margin:8px 0 12px;color:#004d2b;font-size:28px}p,li{line-height:1.6;color:#53615a}label{display:block;font-weight:700;margin:16px 0 7px}input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #cfd6d1;border-radius:10px;font-size:16px}button{width:100%;margin-top:20px;padding:13px;border:0;border-radius:10px;background:#b42318;color:#fff;font-weight:700;font-size:16px;cursor:pointer}.danger{color:#b42318}.note{font-size:13px;color:#6a746e}</style></head><body><main class="wrap"><section class="card">'
        . '<div class="brand">Maan World Hospitality</div><h1>Delete your account</h1>'
        . '<p>Use this page to permanently delete your Maan World Hospitality candidate account and the personal data associated with it.</p>'
        . '<ul><li>Profile and contact information</li><li>Uploaded candidate documents and profile photo</li><li>Job preferences, applications, jobs and attendance records</li><li>Payment profile, payment records, notifications and support data</li></ul>'
        . '<p class="note">For security, enter the OTP associated with your registered mobile number. In the current development environment the OTP service uses the temporary test code 111111; replace this with the production SMS provider before launch.</p>'
        . '<form method="post" action=""><label for="mobile">Registered mobile number</label><input id="mobile" name="mobile" type="tel" required autocomplete="tel"><label for="otp">OTP</label><input id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required><label><input type="checkbox" name="confirm" value="1" required style="width:auto;margin-right:8px"> I understand that account deletion is permanent.</label><button type="submit">Permanently Delete Account</button></form>'
        . '<p class="note">If you cannot complete this form, contact WebStripe Technologies at info@webstripetechnologies.com.</p>'
        . '</section></main></body></html>';
    exit;
}

// In-app deletion uses the authenticated bearer token and DELETE method.
if ($method === 'DELETE') {
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
        respond(['success'=>false,'message'=>'Unable to delete the account right now. Please try again or contact support.'],500);
    }
}

// External web deletion request. The existing MWH OTP flow is used for identity verification.
if ($method === 'POST') {
    $data = jsonInput();
    $mobile = trim((string)($data['mobile'] ?? ''));
    $otp = trim((string)($data['otp'] ?? ''));
    $confirmed = (string)($data['confirm'] ?? '');

    if ($mobile === '' || $otp === '' || $confirmed !== '1') {
        htmlResponse('Verification required', 'Please provide your registered mobile number, OTP and deletion confirmation.');
    }
    if ($otp !== '111111') {
        htmlResponse('Invalid OTP', 'The OTP could not be verified. Please request a new OTP and try again.');
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE mobile=? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$mobile]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            htmlResponse('Account not found', 'No active Maan World Hospitality account was found for that mobile number.');
        }

        deleteCandidateAccount($pdo, (int)$user['id']);
        htmlResponse('Account deleted', 'Your account and associated Maan World Hospitality data have been permanently deleted.', true);
    } catch (Throwable $e) {
        htmlResponse('Deletion failed', 'We could not complete the deletion right now. Please try again or contact support.');
    }
}

respond(['success'=>false,'message'=>'Method not allowed'],405);
