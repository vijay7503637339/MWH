<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$pageTitle = 'Notifications';
$admin = currentAdmin();
if (empty($_SESSION['notifications_csrf'])) $_SESSION['notifications_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['notifications_csrf'];
$errors = [];
$success = $_SESSION['notifications_success'] ?? '';
unset($_SESSION['notifications_success']);

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirectNotifications(): never { header('Location: notifications.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['notifications_csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Invalid request token.');
    }

    try {
        $recipient = trim((string)($_POST['recipient'] ?? ''));
        $type = trim((string)($_POST['type'] ?? 'general'));
        $title = trim((string)($_POST['title'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        $allowedTypes = ['job','payment','shift','document','referral','general'];
        if (!in_array($type, $allowedTypes, true)) throw new RuntimeException('Invalid notification type.');
        if ($title === '' || mb_strlen($title) > 190) throw new RuntimeException('Title is required and must be 190 characters or less.');
        if ($message === '') throw new RuntimeException('Message is required.');
        if (mb_strlen($message) > 5000) throw new RuntimeException('Message is too long.');

        if ($recipient === 'all') {
            $users = $pdo->query("SELECT id FROM users WHERE deleted_at IS NULL AND account_status='verified' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $userId = (int)$recipient;
            $find = $pdo->prepare("SELECT id FROM users WHERE id=? AND deleted_at IS NULL AND account_status='verified' LIMIT 1");
            $find->execute([$userId]);
            if (!$find->fetchColumn()) throw new RuntimeException('Selected candidate is not available or not verified.');
            $users = [$userId];
        }

        if (!$users) throw new RuntimeException('No verified candidates found.');

        $stmt = $pdo->prepare('INSERT INTO notifications(user_id,admin_user_id,type,title,message,data_json) VALUES(?,?,?,?,?,?)');
        $pdo->beginTransaction();
        foreach ($users as $userId) {
            $stmt->execute([(int)$userId, (int)$admin['id'], $type, $title, $message, null]);
        }
        $pdo->commit();

        $count = count($users);
        $audit = $pdo->prepare('INSERT INTO audit_logs(admin_user_id,action,entity_type,entity_id,new_values,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)');
        $audit->execute([
            (int)$admin['id'], 'notification.sent', 'notification', null,
            json_encode(['recipient'=>$recipient,'recipient_count'=>$count,'type'=>$type,'title'=>$title], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        $_SESSION['notifications_success'] = "Notification sent to {$count} candidate" . ($count === 1 ? '' : 's') . '.';
        redirectNotifications();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = $ex->getMessage();
    }
}

$candidates = $pdo->query("SELECT u.id, CONCAT(COALESCE(cp.first_name,''),' ',COALESCE(cp.last_name,'')) AS candidate_name, u.mobile FROM users u LEFT JOIN candidate_profiles cp ON cp.user_id=u.id WHERE u.deleted_at IS NULL AND u.account_status='verified' ORDER BY cp.first_name,cp.last_name,u.id")->fetchAll(PDO::FETCH_ASSOC);
$recent = $pdo->query("SELECT n.id,n.type,n.title,n.message,n.created_at,CONCAT(COALESCE(cp.first_name,''),' ',COALESCE(cp.last_name,'')) AS candidate_name,u.mobile FROM notifications n INNER JOIN users u ON u.id=n.user_id LEFT JOIN candidate_profiles cp ON cp.user_id=u.id WHERE n.admin_user_id IS NOT NULL ORDER BY n.created_at DESC,n.id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<style>
.notification-page{max-width:1450px;margin:0 auto}.notification-heading{margin-bottom:22px}.notification-heading .page-kicker{display:flex;align-items:center;gap:8px;color:#4169e1;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}.notification-heading h1{font-size:32px;margin-bottom:6px}.notification-layout{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(380px,.9fr);gap:18px}.notification-card{background:#fff;border:1px solid #e7ebf2;border-radius:16px;box-shadow:0 4px 14px rgba(20,35,60,.04);padding:22px}.notification-card h2{font-size:18px;margin:0 0 5px;color:#172033}.notification-card .sub{margin:0 0 18px;color:#7a8498;font-size:12px}.notification-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.notification-form-group.full{grid-column:1/-1}.notification-form-group label{display:block;margin-bottom:6px;font-size:11px;font-weight:750;color:#526078}.notification-form-group input,.notification-form-group select,.notification-form-group textarea{width:100%;box-sizing:border-box;border:1px solid #dfe5ee;border-radius:10px;padding:11px 12px;background:#fbfcfe;font:inherit;font-size:12px;color:#273247}.notification-form-group textarea{min-height:150px;resize:vertical}.notification-form-actions{display:flex;justify-content:flex-end;margin-top:18px}.notification-send{height:42px;border:0;border-radius:10px;background:#4169e1;color:#fff;padding:0 18px;font-weight:750;font-size:12px;cursor:pointer}.notification-alert{border-radius:10px;padding:11px 13px;margin-bottom:16px;font-size:12px}.notification-alert.success{background:#edf9f3;color:#087b50;border:1px solid #c7eddc}.notification-alert.error{background:#fff4f4;color:#b52d35;border:1px solid #f1d0d3}.notification-recent{padding:0;overflow:hidden}.notification-recent-head{padding:20px 22px;border-bottom:1px solid #edf0f5}.notification-list{max-height:650px;overflow:auto}.notification-row{padding:14px 22px;border-bottom:1px solid #eef1f5}.notification-row:last-child{border-bottom:0}.notification-row-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.notification-row strong{font-size:13px;color:#1c2739}.notification-type{display:inline-flex;padding:5px 8px;border-radius:999px;background:#eef3ff;color:#4169e1;font-size:9px;font-weight:800;text-transform:uppercase}.notification-row p{margin:5px 0 0;color:#66738a;font-size:11px;line-height:1.45}.notification-row-meta{margin-top:7px;color:#9aa3b2;font-size:10px}.notification-empty{padding:45px 20px;text-align:center;color:#8b95a7;font-size:12px}.notification-tip{margin-top:12px;padding:10px 12px;background:#f6f8fc;border-radius:10px;color:#7a8498;font-size:10px;line-height:1.5}@media(max-width:980px){.notification-layout{grid-template-columns:1fr}}@media(max-width:600px){.notification-form-grid{grid-template-columns:1fr}.notification-form-group.full{grid-column:auto}.notification-card{padding:17px}.notification-heading h1{font-size:27px}}
</style>

<main class="content notification-page">
    <div class="notification-heading">
        <div class="page-kicker"><i class="bi bi-bell-fill"></i> Candidate Communication</div>
        <h1>Notifications</h1>
        <p class="muted">Send important updates, job alerts and payment messages to candidates.</p>
    </div>

    <?php if ($success): ?><div class="notification-alert success"><i class="bi bi-check-circle-fill me-1"></i><?= e($success) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="notification-alert error"><i class="bi bi-exclamation-circle-fill me-1"></i><?= e($error) ?></div><?php endforeach; ?>

    <div class="notification-layout">
        <section class="notification-card">
            <h2>Send Notification</h2>
            <p class="sub">Choose one candidate or broadcast to all verified candidates.</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <div class="notification-form-grid">
                    <div class="notification-form-group">
                        <label for="recipient">Recipient</label>
                        <select id="recipient" name="recipient" required>
                            <option value="">Select recipient</option>
                            <option value="all">All Verified Candidates</option>
                            <?php foreach ($candidates as $candidate): ?>
                                <option value="<?= (int)$candidate['id'] ?>"><?= e(trim($candidate['candidate_name']) ?: 'Candidate #' . $candidate['id']) ?> — <?= e($candidate['mobile']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="notification-form-group">
                        <label for="type">Notification Type</label>
                        <select id="type" name="type" required>
                            <option value="general">General</option>
                            <option value="job">Job Alert</option>
                            <option value="payment">Payment Received</option>
                            <option value="shift">Shift Reminder</option>
                            <option value="document">Document Status</option>
                            <option value="referral">Referral Reward</option>
                        </select>
                    </div>
                    <div class="notification-form-group full">
                        <label for="title">Title</label>
                        <input id="title" name="title" maxlength="190" placeholder="e.g. New Job Alert" required>
                    </div>
                    <div class="notification-form-group full">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" maxlength="5000" placeholder="Write the notification message..." required></textarea>
                    </div>
                </div>
                <div class="notification-tip"><i class="bi bi-info-circle me-1"></i> Notifications are stored in the candidate's notification inbox. This screen does not send SMS or push notifications.</div>
                <div class="notification-form-actions"><button class="notification-send" type="submit"><i class="bi bi-send-fill me-1"></i> Send Notification</button></div>
            </form>
        </section>

        <section class="notification-card notification-recent">
            <div class="notification-recent-head"><h2>Recently Sent</h2><p class="sub mb-0">Latest admin-created notifications.</p></div>
            <div class="notification-list">
                <?php if (!$recent): ?><div class="notification-empty">No notifications sent yet.</div>
                <?php else: foreach ($recent as $row): ?>
                    <div class="notification-row">
                        <div class="notification-row-top"><strong><?= e($row['title']) ?></strong><span class="notification-type"><?= e($row['type']) ?></span></div>
                        <p><?= e($row['message']) ?></p>
                        <div class="notification-row-meta"><i class="bi bi-person me-1"></i><?= e(trim($row['candidate_name']) ?: 'Candidate') ?> · <?= e($row['mobile']) ?> · <?= e($row['created_at']) ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
