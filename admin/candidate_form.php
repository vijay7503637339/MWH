<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/config/security.php';

$pageTitle = 'Candidate';
$admin = currentAdmin();

if (empty($_SESSION['candidate_csrf'])) {
    $_SESSION['candidate_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['candidate_csrf'];
$errors = [];

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectCandidate(?int $id = null): never
{
    header('Location: candidates.php' . ($id ? '?view=' . $id : ''));
    exit;
}

function normalizeAdminDob(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        throw new InvalidArgumentException('Date of birth is required.');
    }

    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        $check = DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($check) && ($check['warning_count'] > 0 || $check['error_count'] > 0);

        if ($date instanceof DateTimeImmutable && !$hasErrors && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }

    throw new InvalidArgumentException('Invalid date of birth.');
}

function validateCandidateForm(PDO $pdo, array $data, bool $isEdit, int $editingId = 0): array
{
    $firstName = trim((string)($data['first_name'] ?? ''));
    $lastName = trim((string)($data['last_name'] ?? ''));
    $mobile = preg_replace('/\D+/', '', (string)($data['mobile'] ?? '')) ?? '';
    $email = trim((string)($data['email'] ?? ''));
    $dob = normalizeAdminDob((string)($data['date_of_birth'] ?? ''));
    $gender = trim((string)($data['gender'] ?? ''));
    $categoryId = (int)($data['category_id'] ?? 0);
    $jobRoleId = (int)($data['job_role_id'] ?? 0);
    $experience = trim((string)($data['experience_years'] ?? ''));
    $city = trim((string)($data['city'] ?? ''));
    $paymentType = trim((string)($data['payment_type'] ?? 'bank'));
    $accountHolder = trim((string)($data['account_holder_name'] ?? ''));
    $bankName = trim((string)($data['bank_name'] ?? ''));
    $accountNumber = preg_replace('/\D+/', '', (string)($data['account_number'] ?? '')) ?? '';
    $ifsc = strtoupper(trim((string)($data['ifsc_code'] ?? '')));
    $upiId = trim((string)($data['upi_id'] ?? ''));
    // Passwords must be at least 8 characters. Do not trim or otherwise alter
    // the password because spaces can legitimately be part of a password.
    $password = (string)($data['password'] ?? '');
    $passwordLength = mb_strlen($password, 'UTF-8');

    if ($firstName === '' || mb_strlen($firstName) > 100) throw new InvalidArgumentException('First name is required.');
    if ($lastName === '' || mb_strlen($lastName) > 100) throw new InvalidArgumentException('Last name is required.');
    if (!preg_match('/^[0-9]{10}$/', $mobile)) throw new InvalidArgumentException('Enter a valid 10-digit mobile number.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
    if ($gender === '') throw new InvalidArgumentException('Please select gender.');
    if ($categoryId <= 0 || $jobRoleId <= 0) throw new InvalidArgumentException('Category and job role are required.');
    $experienceValue = (float)$experience;
    if ($experience === '' || $experienceValue < 0 || $experienceValue > 50) throw new InvalidArgumentException('Experience must be between 0 and 50 years.');
    if ($city === '') throw new InvalidArgumentException('Preferred area is required.');
    if (!in_array($paymentType, ['bank', 'upi'], true)) throw new InvalidArgumentException('Invalid payment type.');

    if ($paymentType === 'bank') {
        if ($accountHolder === '') throw new InvalidArgumentException('Account holder name is required.');
        if ($bankName === '') throw new InvalidArgumentException('Bank name is required.');
        if ($accountNumber === '' || strlen($accountNumber) < 9 || strlen($accountNumber) > 18) throw new InvalidArgumentException('Enter a valid 9–18 digit account number.');
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) throw new InvalidArgumentException('Enter a valid IFSC code.');
    } elseif ($upiId === '') {
        throw new InvalidArgumentException('UPI ID is required.');
    }

    if (!$isEdit && $passwordLength < 8) {
        throw new InvalidArgumentException(
            'Password must be at least 8 characters. Current length: ' . $passwordLength . '.'
        );
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE mobile=? AND deleted_at IS NULL AND id<>? LIMIT 1');
    $stmt->execute([$mobile, $editingId]);
    if ($stmt->fetch()) throw new InvalidArgumentException('Mobile number is already registered.');

    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email=? AND deleted_at IS NULL AND id<>? LIMIT 1');
        $stmt->execute([$email, $editingId]);
        if ($stmt->fetch()) throw new InvalidArgumentException('Email address is already registered.');
    }

    $roleCheck = $pdo->prepare('SELECT id FROM job_roles WHERE id=? AND category_id=? AND is_active=1 AND deleted_at IS NULL LIMIT 1');
    $roleCheck->execute([$jobRoleId, $categoryId]);
    if (!$roleCheck->fetch()) throw new InvalidArgumentException('Selected job role is not valid for the selected category.');

    return [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'mobile' => $mobile,
        'email' => $email,
        'date_of_birth' => $dob,
        'gender' => $gender,
        'category_id' => $categoryId,
        'job_role_id' => $jobRoleId,
        'experience_years' => $experienceValue,
        'city' => $city,
        'payment_type' => $paymentType,
        'account_holder_name' => $accountHolder !== '' ? $accountHolder : null,
        'bank_name' => $bankName !== '' ? $bankName : null,
        'account_number' => $accountNumber !== '' ? $accountNumber : null,
        'ifsc_code' => $ifsc !== '' ? $ifsc : null,
        'upi_id' => $upiId !== '' ? $upiId : null,
        'password' => $password,
    ];
}

$editId = (int)($_GET['id'] ?? 0);
$isEdit = $editId > 0;
$form = [
    'first_name' => '',
    'last_name' => '',
    'mobile' => '',
    'email' => '',
    'date_of_birth' => '',
    'gender' => '',
    'category_id' => 0,
    'job_role_id' => 0,
    'experience_years' => '',
    'city' => '',
    'payment_type' => 'bank',
    'account_holder_name' => '',
    'bank_name' => '',
    'account_number' => '',
    'ifsc_code' => '',
    'upi_id' => '',
    'password' => '',
];

if ($isEdit) {
    $stmt = $pdo->prepare(
        "SELECT u.id,u.mobile,u.email,cp.first_name,cp.last_name,cp.date_of_birth,cp.gender,cp.city,
                pd.experience_years,jp.category_id,jp.job_role_id,
                cpp.payment_type,cpp.account_holder_name,cpp.bank_name,cpp.ifsc_code,cpp.upi_id
         FROM users u
         LEFT JOIN candidate_profiles cp ON cp.user_id=u.id
         LEFT JOIN professional_details pd ON pd.user_id=u.id
         LEFT JOIN job_preferences jp ON jp.user_id=u.id
         LEFT JOIN candidate_payment_profiles cpp ON cpp.user_id=u.id
         WHERE u.id=? AND u.deleted_at IS NULL LIMIT 1"
    );
    $stmt->execute([$editId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        redirectCandidate();
    }

    foreach ($form as $key => $_) {
        if (array_key_exists($key, $existing) && $existing[$key] !== null) {
            $form[$key] = $existing[$key];
        }
    }
    if (($form['date_of_birth'] ?? '') === '0000-00-00') {
        $form['date_of_birth'] = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['candidate_csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Invalid request token.');
    }

    $posted = $form;
    foreach (array_keys($form) as $key) {
        if (isset($_POST[$key])) {
            if ($key === 'password') {
                $posted[$key] = is_string($_POST[$key]) ? $_POST[$key] : '';
            } else {
                $posted[$key] = is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
            }
        }
    }
    $form = $posted;

    try {
        $data = validateCandidateForm($pdo, $form, $isEdit, $editId);
        $pdo->beginTransaction();

        if ($isEdit) {
            $stmt = $pdo->prepare('UPDATE users SET mobile=?,email=?' . ($data['password'] !== '' ? ',password_hash=?' : '') . ' WHERE id=?');
            $params = [$data['mobile'], $data['email'] !== '' ? $data['email'] : null];
            if ($data['password'] !== '') {
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            $params[] = $editId;
            $stmt->execute($params);

            $stmt = $pdo->prepare(
                'INSERT INTO candidate_profiles(user_id,first_name,last_name,date_of_birth,gender,full_address,city,country,postcode)
                 VALUES(?,?,?,?,?,NULL,?,\'India\',NULL)
                 ON DUPLICATE KEY UPDATE first_name=VALUES(first_name),last_name=VALUES(last_name),date_of_birth=VALUES(date_of_birth),gender=VALUES(gender),city=VALUES(city)'
            );
            $stmt->execute([$editId,$data['first_name'],$data['last_name'],$data['date_of_birth'],$data['gender'],$data['city']]);

            $stmt = $pdo->prepare(
                'INSERT INTO professional_details(user_id,experience_years,previous_company_name)
                 VALUES(?,?,NULL)
                 ON DUPLICATE KEY UPDATE experience_years=VALUES(experience_years)'
            );
            $stmt->execute([$editId,$data['experience_years']]);

            $stmt = $pdo->prepare(
                'INSERT INTO job_preferences(user_id,category_id,job_role_id,max_travel_distance_km,preferred_working_hours)
                 VALUES(?,?,?,NULL,NULL)
                 ON DUPLICATE KEY UPDATE category_id=VALUES(category_id),job_role_id=VALUES(job_role_id)'
            );
            $stmt->execute([$editId,$data['category_id'],$data['job_role_id']]);

            $encryptedAccount = $data['account_number'] !== null ? encryptSensitive((string)$data['account_number']) : null;
            $stmt = $pdo->prepare(
                'INSERT INTO candidate_payment_profiles(user_id,payment_type,account_holder_name,bank_name,account_number_encrypted,ifsc_code,branch_name,upi_id)
                 VALUES(?,?,?,?,?,?,NULL,?)
                 ON DUPLICATE KEY UPDATE payment_type=VALUES(payment_type),account_holder_name=VALUES(account_holder_name),bank_name=VALUES(bank_name),account_number_encrypted=VALUES(account_number_encrypted),ifsc_code=VALUES(ifsc_code),upi_id=VALUES(upi_id)'
            );
            $stmt->execute([$editId,$data['payment_type'],$data['account_holder_name'],$data['bank_name'],$encryptedAccount,$data['ifsc_code'],$data['upi_id']]);

            auditCandidate($pdo, $admin, 'candidate.updated', $editId, null, ['fields' => 'admin profile edit']);
            $pdo->commit();

            $_SESSION['candidate_success'] = 'Candidate updated successfully.';
            redirectCandidate($editId);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO users(mobile,email,password_hash,account_status,verified_at,verified_by_admin_id)
             VALUES(?,?,?,?,NOW(),?)"
        );
        $stmt->execute([
            $data['mobile'],
            $data['email'] !== '' ? $data['email'] : null,
            password_hash($data['password'], PASSWORD_DEFAULT),
            'verified',
            $admin['id'],
        ]);
        $newId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO candidate_profiles(user_id,first_name,last_name,date_of_birth,gender,full_address,city,country,postcode)
             VALUES(?,?,?,?,?,NULL,?,\'India\',NULL)'
        );
        $stmt->execute([$newId,$data['first_name'],$data['last_name'],$data['date_of_birth'],$data['gender'],$data['city']]);

        $stmt = $pdo->prepare(
            'INSERT INTO professional_details(user_id,experience_years,previous_company_name)
             VALUES(?,?,NULL)'
        );
        $stmt->execute([$newId,$data['experience_years']]);

        $stmt = $pdo->prepare(
            'INSERT INTO job_preferences(user_id,category_id,job_role_id,max_travel_distance_km,preferred_working_hours)
             VALUES(?,?,?,NULL,NULL)'
        );
        $stmt->execute([$newId,$data['category_id'],$data['job_role_id']]);

        $encryptedAccount = $data['account_number'] !== null ? encryptSensitive((string)$data['account_number']) : null;
        $stmt = $pdo->prepare(
            'INSERT INTO candidate_payment_profiles(user_id,payment_type,account_holder_name,bank_name,account_number_encrypted,ifsc_code,branch_name,upi_id)
             VALUES(?,?,?,?,?,?,NULL,?)'
        );
        $stmt->execute([$newId,$data['payment_type'],$data['account_holder_name'],$data['bank_name'],$encryptedAccount,$data['ifsc_code'],$data['upi_id']]);

        auditCandidate($pdo, $admin, 'candidate.created_by_admin', $newId, null, ['account_status'=>'verified']);
        $pdo->commit();

        $_SESSION['candidate_success'] = 'Candidate added successfully. They can log in immediately.';
        redirectCandidate($newId);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $exception instanceof InvalidArgumentException
            ? $exception->getMessage()
            : 'Unable to save candidate. Please check the details and try again.';
    }
}

$categories = $pdo->query(
    "SELECT id,name FROM categories WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order,name"
)->fetchAll(PDO::FETCH_ASSOC);

$roles = $pdo->query(
    "SELECT id,category_id,name FROM job_roles WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order,name"
)->fetchAll(PDO::FETCH_ASSOC);

$cities = ['Mumbai','Delhi','Bengaluru','Hyderabad','Chennai','Pune','Kolkata','Ahmedabad','Jaipur','Chandigarh','Lucknow','Kochi','Goa'];
?>
<?php require __DIR__ . '/includes/header.php'; ?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="content candidate-form-page">
    <div class="candidate-form-topbar">
        <div>
            <div class="page-kicker"><i class="bi bi-<?= $isEdit ? 'pencil-square' : 'person-plus' ?>"></i> Candidate Management</div>
            <h1><?= $isEdit ? 'Edit Candidate' : 'Add Candidate' ?></h1>
            <p class="muted"><?= $isEdit ? 'Update candidate information from the admin panel.' : 'Create a candidate account directly from the admin panel.' ?></p>
        </div>
        <a class="button-secondary" href="<?= $isEdit ? 'candidates.php?view=' . $editId : 'candidates.php' ?>"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <?php foreach ($errors as $error): ?><div class="alert error"><i class="bi bi-exclamation-circle"></i> <?= e($error) ?></div><?php endforeach; ?>

    <form class="candidate-form" method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

        <section class="panel form-panel">
            <div class="form-section-title"><div class="form-section-icon"><i class="bi bi-person-vcard"></i></div><div><h2>Personal Information</h2><p>Details used for the candidate profile.</p></div></div>
            <div class="form-grid">
                <label class="form-control"><span>First Name <b>*</b></span><input name="first_name" maxlength="100" value="<?= e((string)$form['first_name']) ?>" required></label>
                <label class="form-control"><span>Last Name <b>*</b></span><input name="last_name" maxlength="100" value="<?= e((string)$form['last_name']) ?>" required></label>
                <label class="form-control"><span>Mobile Number <b>*</b></span><input name="mobile" inputmode="numeric" maxlength="10" value="<?= e((string)$form['mobile']) ?>" required></label>
                <label class="form-control"><span>Email Address</span><input type="email" name="email" maxlength="190" value="<?= e((string)$form['email']) ?>"></label>
                <label class="form-control"><span>Date of Birth <b>*</b></span><input type="date" name="date_of_birth" value="<?= e((string)$form['date_of_birth']) ?>" required></label>
                <label class="form-control"><span>Gender <b>*</b></span><select name="gender" required><option value="">Select gender</option><option value="Female" <?= $form['gender']==='Female'?'selected':'' ?>>Female</option><option value="Male" <?= $form['gender']==='Male'?'selected':'' ?>>Male</option><option value="Other" <?= $form['gender']==='Other'?'selected':'' ?>>Other</option></select></label>
                <label class="form-control"><span>Preferred Area <b>*</b></span><select name="city" required><option value="">Select city</option><?php foreach ($cities as $city): ?><option value="<?= e($city) ?>" <?= $form['city']===$city?'selected':'' ?>><?= e($city) ?></option><?php endforeach; ?></select></label>
            </div>
        </section>

        <section class="panel form-panel">
            <div class="form-section-title"><div class="form-section-icon"><i class="bi bi-briefcase"></i></div><div><h2>Professional Details</h2><p>Select the same category, role and experience captured in the app.</p></div></div>
            <div class="form-grid">
                <label class="form-control"><span>Hospitality Category <b>*</b></span><select name="category_id" id="category_id" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)$form['category_id']===(int)$category['id']?'selected':'' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
                <label class="form-control"><span>Specific Role <b>*</b></span><select name="job_role_id" id="job_role_id" required><option value="">Select role</option></select></label>
                <label class="form-control"><span>Experience (Years) <b>*</b></span><input type="number" min="0" max="50" step="1" name="experience_years" value="<?= e((string)$form['experience_years']) ?>" required></label>
            </div>
        </section>

        <section class="panel form-panel">
            <div class="form-section-title"><div class="form-section-icon"><i class="bi bi-bank"></i></div><div><h2>Payment Details</h2><p>Payment information can be maintained by the admin.</p></div></div>
            <div class="form-grid">
                <label class="form-control"><span>Payment Type <b>*</b></span><select name="payment_type" id="payment_type" required><option value="bank" <?= $form['payment_type']==='bank'?'selected':'' ?>>Bank</option><option value="upi" <?= $form['payment_type']==='upi'?'selected':'' ?>>UPI</option></select></label>
                <label class="form-control bank-only"><span>Account Holder Name <b>*</b></span><input name="account_holder_name" value="<?= e((string)$form['account_holder_name']) ?>" maxlength="150"></label>
                <label class="form-control bank-only"><span>Bank Name <b>*</b></span><input name="bank_name" value="<?= e((string)$form['bank_name']) ?>" maxlength="150"></label>
                <label class="form-control bank-only"><span>Account Number <b>*</b></span><input name="account_number" inputmode="numeric" maxlength="18" value="<?= e((string)$form['account_number']) ?>"></label>
                <label class="form-control bank-only"><span>IFSC Code <b>*</b></span><input name="ifsc_code" maxlength="11" value="<?= e((string)$form['ifsc_code']) ?>"></label>
                <label class="form-control upi-only"><span>UPI ID <b>*</b></span><input name="upi_id" maxlength="190" value="<?= e((string)$form['upi_id']) ?>"></label>
            </div>
        </section>

        <section class="panel form-panel">
            <div class="form-section-title"><div class="form-section-icon"><i class="bi bi-shield-lock"></i></div><div><h2>Login Access</h2><p><?= $isEdit ? 'Leave password blank to keep the current password.' : 'Set the password the candidate will use to log in.' ?></p></div></div>
            <div class="form-grid">
                <label class="form-control"><span>Password <?= $isEdit ? '' : '<b>*</b>' ?></span><input id="candidate_password" type="password" name="password" minlength="8" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>><small class="password-hint" id="passwordHint"><?= $isEdit ? 'Leave blank to keep the current password.' : 'Use at least 8 characters.' ?></small></label>
                <?php if (!$isEdit): ?><div class="access-note"><i class="bi bi-check-circle-fill"></i><div><strong>Admin-created candidates are verified</strong><span>The account will be ready to log in immediately after creation.</span></div></div><?php endif; ?>
            </div>
        </section>

        <div class="form-actions">
            <a class="button-secondary" href="<?= $isEdit ? 'candidates.php?view=' . $editId : 'candidates.php' ?>">Cancel</a>
            <button class="button-small success form-submit" type="submit"><i class="bi bi-<?= $isEdit ? 'check2-circle' : 'person-plus' ?>"></i> <?= $isEdit ? 'Save Changes' : 'Add Candidate' ?></button>
        </div>
    </form>
</main>

<style>
.candidate-form-page{max-width:1280px;margin:0 auto}.candidate-form-topbar{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:22px}.candidate-form-topbar .page-kicker{display:flex;align-items:center;gap:8px;color:#4169e1;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}.candidate-form-topbar h1{margin:0 0 6px;font-size:32px}.candidate-form-topbar .button-secondary{display:inline-flex;align-items:center;gap:7px}
.form-panel{padding:22px 24px;margin-bottom:16px}.form-section-title{display:flex;align-items:center;gap:12px;padding-bottom:18px;border-bottom:1px solid #edf0f5;margin-bottom:18px}.form-section-icon{width:40px;height:40px;border-radius:11px;display:grid;place-items:center;background:#eef3ff;color:#4169e1;font-size:17px}.form-section-title h2{margin:0;color:#1b2940;font-size:18px}.form-section-title p{margin:4px 0 0;color:#8a94a6;font-size:11px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.form-control{display:flex;flex-direction:column;gap:7px}.form-control>span{font-size:11px;font-weight:800;color:#55637b}.form-control b{color:#d13b43}.form-control input,.form-control select{width:100%;height:44px;border:1px solid #dfe5ee;border-radius:10px;background:#f9fbfe;padding:0 12px;font-size:13px;color:#28364c;outline:none}.form-control input:focus,.form-control select:focus{border-color:#7c9bf4;box-shadow:0 0 0 3px rgba(65,105,225,.08)}.password-hint{color:#8a94a6;font-size:10px}.access-note{display:flex;align-items:center;gap:10px;padding:12px 14px;background:#effaf5;border:1px solid #caeddc;border-radius:10px;color:#087b50}.access-note i{font-size:18px}.access-note strong{display:block;font-size:11px}.access-note span{display:block;font-size:10px;margin-top:3px;color:#6b8477}.form-actions{display:flex;justify-content:flex-end;gap:9px;padding:4px 0 18px}.form-submit{height:42px!important;padding:0 18px!important;display:inline-flex!important;align-items:center;gap:7px}
@media(max-width:760px){.candidate-form-topbar{align-items:flex-start}.candidate-form-topbar h1{font-size:27px}.form-grid{grid-template-columns:1fr}.form-panel{padding:18px}.candidate-form-topbar .button-secondary{padding:9px 12px}.form-actions{flex-direction:column-reverse}.form-actions a,.form-submit{width:100%!important;justify-content:center}}
</style>

<script>
(function(){
    const roles = <?= json_encode($roles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const category = document.getElementById('category_id');
    const role = document.getElementById('job_role_id');
    const payment = document.getElementById('payment_type');
    const existingRole = <?= (int)$form['job_role_id'] ?>;
    const passwordInput = document.getElementById('candidate_password');
    const passwordHint = document.getElementById('passwordHint');

    function syncPasswordHint(){
        if (!passwordInput || !passwordHint) return;
        const length = [...passwordInput.value].length;
        if (length === 0) {
            passwordHint.textContent = <?= json_encode($isEdit ? 'Leave blank to keep the current password.' : 'Use at least 8 characters.') ?>;
        } else if (length < 8) {
            passwordHint.textContent = 'Password: ' + length + '/8 characters';
        } else {
            passwordHint.textContent = 'Password length OK (' + length + ' characters)';
        }
    }

    function syncRoles(){
        const categoryId = Number(category.value || 0);
        role.innerHTML = '<option value="">Select role</option>';
        roles.filter(item => Number(item.category_id) === categoryId).forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            if (Number(item.id) === existingRole) option.selected = true;
            role.appendChild(option);
        });
    }

    function syncPaymentFields(){
        const isBank = payment.value === 'bank';
        document.querySelectorAll('.bank-only').forEach(el => {
            el.style.display = isBank ? '' : 'none';
            const input = el.querySelector('input');
            if (input) input.required = isBank;
        });
        document.querySelectorAll('.upi-only').forEach(el => {
            el.style.display = isBank ? 'none' : '';
            const input = el.querySelector('input');
            if (input) input.required = !isBank;
        });
    }

    category.addEventListener('change', () => {
        role.innerHTML = '<option value="">Select role</option>';
        const categoryId = Number(category.value || 0);
        roles.filter(item => Number(item.category_id) === categoryId).forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            role.appendChild(option);
        });
    });

    payment.addEventListener('change', syncPaymentFields);
    if (passwordInput) passwordInput.addEventListener('input', syncPasswordHint);
    syncRoles();
    syncPaymentFields();
    syncPasswordHint();
})();
</script>

<?php
function auditCandidate(PDO $pdo, array $admin, string $action, int $userId, ?array $old = null, ?array $new = null): void
{
    $stmt = $pdo->prepare('INSERT INTO audit_logs (admin_user_id,user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $admin['id'], $userId, $action, 'candidate', $userId,
        $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
        $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}
?>
<?php require __DIR__ . '/includes/footer.php'; ?>
