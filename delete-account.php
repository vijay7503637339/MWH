<?php
declare(strict_types=1);

require_once __DIR__ . '/api/config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['mwh_delete_csrf'])) {
    $_SESSION['mwh_delete_csrf'] = bin2hex(random_bytes(32));
}

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['mwh_delete_csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Your session expired. Please refresh the page and try again.');
        }

        // Honeypot field for simple bot protection.
        if (trim((string)($_POST['website'] ?? '')) !== '') {
            throw new RuntimeException('Unable to submit the request.');
        }

        $name = trim((string)($_POST['full_name'] ?? ''));
        $mobile = trim((string)($_POST['mobile'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        $confirm = (string)($_POST['confirm'] ?? '');

        if ($name === '' || mb_strlen($name) > 190) throw new RuntimeException('Please enter your full name.');
        if ($mobile === '' || !preg_match('/^[0-9+()\-\s]{7,25}$/', $mobile)) throw new RuntimeException('Please enter a valid registered mobile number.');
        if ($email !== '' && (mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) throw new RuntimeException('Please enter a valid email address.');
        if ($reason !== '' && mb_strlen($reason) > 500) throw new RuntimeException('The reason is too long.');
        if ($confirm !== '1') throw new RuntimeException('Please confirm that you want to request account deletion.');

        // Link the request to an existing account when possible, but do not reveal
        // whether a particular mobile/email belongs to an account on this public page.
        $lookup = $pdo->prepare('SELECT id, email FROM users WHERE mobile=? AND deleted_at IS NULL LIMIT 1');
        $lookup->execute([$mobile]);
        $user = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
        $userId = $user ? (int)$user['id'] : null;

        // Avoid duplicate pending requests for the same account/contact.
        if ($userId !== null) {
            $duplicate = $pdo->prepare("SELECT id FROM account_deletion_requests WHERE user_id=? AND status='pending' LIMIT 1");
            $duplicate->execute([$userId]);
        } else {
            $duplicate = $pdo->prepare("SELECT id FROM account_deletion_requests WHERE mobile=? AND status='pending' LIMIT 1");
            $duplicate->execute([$mobile]);
        }
        if ($duplicate->fetchColumn()) {
            $success = 'Your account deletion request is already pending. Our team will review it and process it after verification.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO account_deletion_requests (user_id, full_name, mobile, email, reason, status, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, \'pending\', ?, ?)');
            $stmt->execute([
                $userId,
                $name,
                $mobile,
                $email !== '' ? $email : null,
                $reason !== '' ? $reason : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ]);
            $success = 'Your account deletion request has been submitted. Our team will verify the request and process the deletion.';
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Delete Account | MWH – Hotel Staff & Jobs</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#172033;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px}.card{width:min(680px,100%);background:#fff;border:1px solid #e3e8f0;border-radius:24px;box-shadow:0 18px 55px rgba(20,35,60,.08);padding:34px}.brand{font-weight:800;color:#4169e1;font-size:13px;letter-spacing:.08em;text-transform:uppercase}h1{margin:8px 0 10px;font-size:32px;line-height:1.2;color:#172033}p,li{color:#647087;line-height:1.65;font-size:14px}.notice{padding:13px 15px;border-radius:12px;margin:20px 0;font-size:13px;line-height:1.55}.notice.success{background:#edf9f3;border:1px solid #c7eddc;color:#087b50}.notice.error{background:#fff3f3;border:1px solid #f1d0d3;color:#b42318}.section{margin-top:24px}.section h2{font-size:16px;margin:0 0 8px;color:#263249}.field{margin-top:16px}.field label{display:block;font-size:12px;font-weight:750;color:#526078;margin-bottom:7px}.field input,.field textarea{width:100%;border:1px solid #dce3ed;border-radius:11px;background:#fbfcfe;padding:12px 13px;font:inherit;font-size:14px;color:#273247;outline:none}.field input:focus,.field textarea:focus{border-color:#4169e1;box-shadow:0 0 0 3px rgba(65,105,225,.10)}.field textarea{min-height:90px;resize:vertical}.confirm{display:flex;gap:10px;align-items:flex-start;margin-top:18px;padding:13px;background:#f7f8fb;border-radius:11px;color:#526078;font-size:12px;line-height:1.5}.confirm input{margin-top:3px}.button{width:100%;margin-top:20px;border:0;border-radius:11px;background:#b42318;color:#fff;padding:14px 18px;font-weight:800;font-size:14px;cursor:pointer}.button:hover{filter:brightness(.96)}.small{font-size:12px;color:#8993a5;margin-top:15px}.privacy{margin-top:20px;font-size:12px}.privacy a{color:#4169e1;text-decoration:none}.honeypot{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}@media(max-width:560px){.card{padding:24px;border-radius:18px}h1{font-size:27px}}
</style>
</head>
<body>
<main class="page"><section class="card">
<div class="brand">Maan World Hospitality</div>
<h1>Delete Account</h1>
<p>Use this page to request deletion of your <strong>MWH – Hotel Staff &amp; Jobs</strong> account and associated personal data. This service is provided by <strong>WebStripe Technologies</strong>.</p>

<?php if ($success !== ''): ?><div class="notice success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<div class="section">
<h2>What will be deleted?</h2>
<p>After your request is verified and approved, we will delete the account data associated with your MWH candidate account, including profile/contact information, uploaded candidate documents and profile photo, job preferences, applications, jobs, attendance records, payment profile and payment records, notifications, support data and referrals, subject to any information that must be retained for legitimate legal, security or regulatory reasons.</p>
</div>

<form method="post" action="">
<input type="hidden" name="csrf" value="<?= e($_SESSION['mwh_delete_csrf']) ?>">
<div class="honeypot"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
<div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" maxlength="190" required autocomplete="name"></div>
<div class="field"><label for="mobile">Registered mobile number</label><input id="mobile" name="mobile" type="tel" maxlength="25" required autocomplete="tel"></div>
<div class="field"><label for="email">Registered email address <span style="font-weight:500">(optional)</span></label><input id="email" name="email" type="email" maxlength="190" autocomplete="email"></div>
<div class="field"><label for="reason">Reason for deletion <span style="font-weight:500">(optional)</span></label><textarea id="reason" name="reason" maxlength="500" placeholder="Optional"></textarea></div>
<label class="confirm"><input type="checkbox" name="confirm" value="1" required><span>I confirm that I am requesting deletion of my MWH account and understand that account deletion is permanent once approved and processed.</span></label>
<button class="button" type="submit">Submit Account Deletion Request</button>
</form>

<p class="small">For security, our team may contact you using the registered contact details to verify ownership before processing the request. Requests are normally reviewed by an MWH administrator.</p>
<p class="privacy">Read our <a href="/MWH/privacy-policy.php">Privacy Policy</a> or contact <a href="mailto:info@webstripetechnologies.com">info@webstripetechnologies.com</a>.</p>
</section></main>
</body>
</html>
