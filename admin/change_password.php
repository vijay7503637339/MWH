<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$pageTitle = 'Change Password';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $adminId = (int)$_SESSION['admin_id'];

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $message = 'Please fill in all password fields.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = 'New password must be at least 8 characters long.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New password and confirmation do not match.';
        $messageType = 'error';
    } elseif ($currentPassword === $newPassword) {
        $message = 'New password must be different from your current password.';
        $messageType = 'error';
    } else {
        $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $adminId]);
        $adminRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$adminRecord || !password_verify($currentPassword, (string)$adminRecord['password_hash'])) {
            $message = 'Current password is incorrect.';
            $messageType = 'error';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE admin_users SET password_hash = :password_hash WHERE id = :id AND deleted_at IS NULL');
            $update->execute([
                'password_hash' => $newHash,
                'id' => $adminId,
            ]);

            $message = 'Password changed successfully.';
            $messageType = 'success';
            $_POST = [];
        }
    }
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<main class="content">
    <div class="topbar">
        <div>
            <h1>Change Password</h1>
            <p class="muted">Update the password for your MWH admin account.</p>
        </div>
    </div>

    <section class="panel change-password-panel">
        <div class="panel-header">
            <div>
                <h2>Security Settings</h2>
                <p class="muted">Use your current password to set a new one.</p>
            </div>
            <div class="security-icon"><i class="bi bi-shield-lock-fill"></i></div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert <?= $messageType === 'success' ? 'success' : 'error' ?>" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off" novalidate>
            <div class="form-grid">
                <div class="field full">
                    <label for="current_password">Current Password</label>
                    <div class="password-field">
                        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" aria-label="Show current password" data-target="current_password"><i class="bi bi-eye"></i></button>
                    </div>
                </div>

                <div class="field">
                    <label for="new_password">New Password</label>
                    <div class="password-field">
                        <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
                        <button type="button" class="password-toggle" aria-label="Show new password" data-target="new_password"><i class="bi bi-eye"></i></button>
                    </div>
                    <small>Use at least 8 characters.</small>
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-field">
                        <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                        <button type="button" class="password-toggle" aria-label="Show confirmation password" data-target="confirm_password"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
            </div>

            <div class="password-actions">
                <a class="button-secondary" href="dashboard.php">Cancel</a>
                <button type="submit" class="button-link change-password-submit"><i class="bi bi-check2-circle"></i>&nbsp; Change Password</button>
            </div>
        </form>
    </section>
</main>

<style>
.change-password-panel{max-width:760px}
.security-icon{width:44px;height:44px;border-radius:13px;background:var(--primary-soft);color:var(--primary);display:grid;place-items:center;font-size:18px;flex:0 0 auto}
.password-field{position:relative}
.password-field input{padding-right:46px}
button.password-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:34px;height:34px;margin:0;padding:0;background:transparent;color:#7a8498;box-shadow:none;border:0;display:grid;place-items:center}
button.password-toggle:hover{transform:translateY(-50%);box-shadow:none;filter:none;background:#f5f7fb;color:var(--primary);border-radius:8px}
.password-actions{display:flex;justify-content:flex-end;align-items:center;gap:10px;margin-top:22px}
.password-actions .button-secondary,.password-actions .button-link{width:auto}
.password-actions .change-password-submit{border:0;color:#fff;margin:0;padding:11px 16px}
@media (max-width:700px){.password-actions{flex-direction:column-reverse;align-items:stretch}.password-actions .button-secondary,.password-actions .button-link{width:100%}}
</style>

<script>
document.querySelectorAll('.password-toggle').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const input = document.getElementById(toggle.dataset.target);
        const icon = toggle.querySelector('i');
        if (!input || !icon) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });
});

const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
if (newPassword && confirmPassword) {
    confirmPassword.addEventListener('input', () => {
        confirmPassword.setCustomValidity(confirmPassword.value && confirmPassword.value !== newPassword.value ? 'Passwords do not match.' : '');
    });
    newPassword.addEventListener('input', () => {
        confirmPassword.setCustomValidity(confirmPassword.value && confirmPassword.value !== newPassword.value ? 'Passwords do not match.' : '');
    });
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
