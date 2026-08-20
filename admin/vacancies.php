<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$pageTitle = 'Vacancies';
$admin = currentAdmin();

if (empty($_SESSION['vacancy_csrf'])) {
    $_SESSION['vacancy_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['vacancy_csrf'];

$errors = [];
$success = $_SESSION['vacancy_success'] ?? '';
unset($_SESSION['vacancy_success']);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectVacancies(): never
{
    header('Location: vacancies.php');
    exit;
}

function requireVacancyCsrf(string $token): void
{
    if (!hash_equals($_SESSION['vacancy_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid request token. Please go back and try again.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireVacancyCsrf((string)($_POST['csrf'] ?? ''));
    $action = (string)($_POST['action'] ?? '');
    $vacancyId = (int)($_POST['vacancy_id'] ?? 0);

    try {
        if ($action === 'save') {
            $hotelId = (int)($_POST['hotel_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $jobRoleId = (int)($_POST['job_role_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $openings = (int)($_POST['openings_count'] ?? 1);
            $payoutAmount = trim((string)($_POST['payout_amount'] ?? ''));
            $payoutPeriod = trim((string)($_POST['payout_period'] ?? 'per_job'));
            $workingHours = trim((string)($_POST['working_hours'] ?? ''));
            $requirements = trim((string)($_POST['requirements'] ?? ''));
            $status = (string)($_POST['status'] ?? 'draft');

            if ($hotelId <= 0) throw new RuntimeException('Please select a hotel.');
            if ($categoryId <= 0) throw new RuntimeException('Please select a category.');
            if ($jobRoleId <= 0) throw new RuntimeException('Please select a service / job role.');
            if ($title === '') throw new RuntimeException('Vacancy title is required.');
            if ($openings < 1 || $openings > 100000) throw new RuntimeException('Openings must be at least 1.');
            if ($payoutAmount === '' || !is_numeric($payoutAmount) || (float)$payoutAmount < 0) throw new RuntimeException('Please enter a valid payout amount.');
            if ($workingHours !== '' && (!is_numeric($workingHours) || (float)$workingHours < 0 || (float)$workingHours > 24)) throw new RuntimeException('Working hours must be between 0 and 24.');
            if (!in_array($payoutPeriod, ['per_job', 'per_day', 'per_month'], true)) $payoutPeriod = 'per_job';
            if (!in_array($status, ['draft', 'published', 'closed'], true)) $status = 'draft';

            $hotelCheck = $pdo->prepare("SELECT id FROM hotels WHERE id=? AND status='active' AND deleted_at IS NULL LIMIT 1");
            $hotelCheck->execute([$hotelId]);
            if (!$hotelCheck->fetchColumn()) throw new RuntimeException('Selected hotel is not active.');

            $roleCheck = $pdo->prepare("SELECT id FROM job_roles WHERE id=? AND category_id=? AND is_active=1 AND deleted_at IS NULL LIMIT 1");
            $roleCheck->execute([$jobRoleId, $categoryId]);
            if (!$roleCheck->fetchColumn()) throw new RuntimeException('Selected service does not belong to the selected category.');

            $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

            if ($vacancyId > 0) {
                $check = $pdo->prepare('SELECT id, published_at FROM vacancies WHERE id=? AND deleted_at IS NULL LIMIT 1');
                $check->execute([$vacancyId]);
                $existing = $check->fetch(PDO::FETCH_ASSOC);
                if (!$existing) throw new RuntimeException('Vacancy not found.');

                if ($status === 'published' && empty($existing['published_at'])) {
                    $publishedAt = date('Y-m-d H:i:s');
                } elseif ($status !== 'published') {
                    $publishedAt = $existing['published_at'];
                }

                $stmt = $pdo->prepare('UPDATE vacancies SET hotel_id=?, category_id=?, job_role_id=?, title=?, description=?, openings_count=?, payout_amount=?, payout_period=?, working_hours=?, requirements=?, status=?, published_at=? WHERE id=?');
                $stmt->execute([$hotelId, $categoryId, $jobRoleId, $title, $description !== '' ? $description : null, $openings, number_format((float)$payoutAmount, 2, '.', ''), $payoutPeriod, $workingHours !== '' ? $workingHours : null, $requirements !== '' ? $requirements : null, $status, $publishedAt, $vacancyId]);
                $_SESSION['vacancy_success'] = 'Vacancy updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO vacancies (hotel_id, category_id, job_role_id, title, description, openings_count, payout_amount, payout_period, working_hours, requirements, status, published_at, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$hotelId, $categoryId, $jobRoleId, $title, $description !== '' ? $description : null, $openings, number_format((float)$payoutAmount, 2, '.', ''), $payoutPeriod, $workingHours !== '' ? $workingHours : null, $requirements !== '' ? $requirements : null, $status, $publishedAt, $admin['id']]);
                $_SESSION['vacancy_success'] = 'Vacancy added successfully.';
            }
            redirectVacancies();
        }

        if ($action === 'publish' && $vacancyId > 0) {
            $stmt = $pdo->prepare("UPDATE vacancies SET status='published', published_at=COALESCE(published_at, NOW()), closing_at=NULL WHERE id=? AND deleted_at IS NULL");
            $stmt->execute([$vacancyId]);
            $_SESSION['vacancy_success'] = 'Vacancy published successfully.';
            redirectVacancies();
        }

        if ($action === 'close' && $vacancyId > 0) {
            $stmt = $pdo->prepare("UPDATE vacancies SET status='closed', closing_at=NOW() WHERE id=? AND deleted_at IS NULL");
            $stmt->execute([$vacancyId]);
            $_SESSION['vacancy_success'] = 'Vacancy closed successfully.';
            redirectVacancies();
        }

        if ($action === 'delete' && $vacancyId > 0) {
            $stmt = $pdo->prepare("UPDATE vacancies SET deleted_at=NOW(), status='closed' WHERE id=? AND deleted_at IS NULL");
            $stmt->execute([$vacancyId]);
            $_SESSION['vacancy_success'] = 'Vacancy removed successfully.';
            redirectVacancies();
        }

        throw new RuntimeException('Invalid vacancy action.');
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$hotelsStmt = $pdo->query("SELECT id, name, city FROM hotels WHERE status='active' AND deleted_at IS NULL ORDER BY name ASC");
$hotels = $hotelsStmt->fetchAll(PDO::FETCH_ASSOC);

$rolesStmt = $pdo->query("SELECT jr.id, jr.category_id, jr.name, c.name AS category_name FROM job_roles jr INNER JOIN categories c ON c.id=jr.category_id WHERE jr.is_active=1 AND jr.deleted_at IS NULL AND c.is_active=1 AND c.deleted_at IS NULL ORDER BY c.sort_order, c.name, jr.sort_order, jr.name");
$jobRoles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

$editVacancy = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM vacancies WHERE id=? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$editId]);
        $editVacancy = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'all');
$showForm = isset($_GET['add']) || $editVacancy !== null || !empty($errors);

$sql = "SELECT v.*, h.name AS hotel_name, h.city AS hotel_city, c.name AS category_name, jr.name AS job_role_name
        FROM vacancies v
        INNER JOIN hotels h ON h.id=v.hotel_id
        INNER JOIN categories c ON c.id=v.category_id
        INNER JOIN job_roles jr ON jr.id=v.job_role_id
        WHERE v.deleted_at IS NULL";
$params = [];

if ($search !== '') {
    $sql .= ' AND (v.title LIKE ? OR h.name LIKE ? OR h.city LIKE ? OR c.name LIKE ? OR jr.name LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
}

if (in_array($statusFilter, ['draft', 'published', 'closed'], true)) {
    $sql .= ' AND v.status=?';
    $params[] = $statusFilter;
}

$sql .= ' ORDER BY v.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vacancies = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<main class="content">
    <div class="topbar">
        <div>
            <h1>Vacancies</h1>
            <p class="muted">Create and manage hotel job openings.</p>
        </div>
        <?php if (!$showForm): ?>
            <a class="button-link" href="vacancies.php?add=1#vacancy-form">+ Add Vacancy</a>
        <?php endif; ?>
    </div>

    <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>

    <?php if ($showForm): ?>
    <section class="panel vacancy-form-panel" id="vacancy-form">
        <div class="panel-header">
            <div>
                <h2><?= $editVacancy ? 'Edit Vacancy' : 'Add Vacancy' ?></h2>
                <p class="muted small">Select the hotel, category and service, then enter vacancy details.</p>
            </div>
            <a class="button-secondary" href="vacancies.php">Cancel</a>
        </div>

        <form method="post" class="form-grid" id="vacancyForm">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="vacancy_id" value="<?= (int)($editVacancy['id'] ?? 0) ?>">

            <div class="field"><label>Hotel *</label><select name="hotel_id" required><option value="">Select hotel</option><?php foreach ($hotels as $hotel): ?><option value="<?= (int)$hotel['id'] ?>" <?= ((int)($editVacancy['hotel_id'] ?? 0) === (int)$hotel['id']) ? 'selected' : '' ?>><?= e($hotel['name'] . ' — ' . $hotel['city']) ?></option><?php endforeach; ?></select></div>

            <div class="field"><label>Category *</label><select name="category_id" id="categorySelect" required><option value="">Select category</option><?php $seenCategories=[]; foreach ($jobRoles as $role): if (!isset($seenCategories[$role['category_id']])) { $seenCategories[$role['category_id']]=true; $selected=((int)($editVacancy['category_id'] ?? 0)===(int)$role['category_id'])?'selected':''; echo '<option value="'.(int)$role['category_id'].'" '.$selected.'>'.e($role['category_name']).'</option>'; } endforeach; ?></select></div>

            <div class="field"><label>Service / Job Role *</label><select name="job_role_id" id="jobRoleSelect" required><option value="">Select service</option><?php foreach ($jobRoles as $role): ?><option data-category="<?= (int)$role['category_id'] ?>" value="<?= (int)$role['id'] ?>" <?= ((int)($editVacancy['job_role_id'] ?? 0) === (int)$role['id']) ? 'selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?></select></div>

            <div class="field"><label>Vacancy Title *</label><input type="text" name="title" required maxlength="190" placeholder="e.g. Tandoor Chef" value="<?= e($editVacancy['title'] ?? '') ?>"></div>
            <div class="field full"><label>Description</label><textarea name="description" rows="4" maxlength="10000" placeholder="Describe the job and responsibilities..."><?= e($editVacancy['description'] ?? '') ?></textarea></div>
            <div class="field"><label>Number of Openings *</label><input type="number" name="openings_count" min="1" max="100000" required value="<?= e((string)($editVacancy['openings_count'] ?? 1)) ?>"></div>
            <div class="field"><label>Payout Amount *</label><input type="number" name="payout_amount" min="0" step="0.01" required value="<?= e((string)($editVacancy['payout_amount'] ?? '')) ?>"></div>
            <div class="field"><label>Payout Period *</label><select name="payout_period"><option value="per_job" <?= (($editVacancy['payout_period'] ?? 'per_job') === 'per_job') ? 'selected' : '' ?>>Per Job</option><option value="per_day" <?= (($editVacancy['payout_period'] ?? '') === 'per_day') ? 'selected' : '' ?>>Per Day</option><option value="per_month" <?= (($editVacancy['payout_period'] ?? '') === 'per_month') ? 'selected' : '' ?>>Per Month</option></select></div>
            <div class="field"><label>Working Hours</label><input type="number" name="working_hours" min="0" max="24" step="0.5" value="<?= e((string)($editVacancy['working_hours'] ?? '')) ?>" placeholder="e.g. 8"></div>
            <div class="field full"><label>Requirements</label><textarea name="requirements" rows="4" maxlength="10000" placeholder="Experience, skills, documents or other requirements..."><?= e($editVacancy['requirements'] ?? '') ?></textarea></div>
            <div class="field"><label>Status</label><select name="status"><option value="draft" <?= (($editVacancy['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft</option><option value="published" <?= (($editVacancy['status'] ?? '') === 'published') ? 'selected' : '' ?>>Published</option><option value="closed" <?= (($editVacancy['status'] ?? '') === 'closed') ? 'selected' : '' ?>>Closed</option></select></div>
            <div class="field full form-actions"><button type="submit"><?= $editVacancy ? 'Update Vacancy' : 'Save Vacancy' ?></button><a class="button-secondary" href="vacancies.php">Cancel</a></div>
        </form>
    </section>
    <?php endif; ?>

    <section class="section vacancies-list-section">
        <div class="panel-header">
            <div><h2>Vacancy List</h2><p class="muted small"><?= count($vacancies) ?> vacancy(s) found.</p></div>
            <form method="get" class="filter-form"><input type="search" name="search" placeholder="Search vacancy, hotel, category..." value="<?= e($search) ?>"><select name="status"><option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Vacancies</option><option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Published</option><option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option><option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option></select><button type="submit">Search</button></form>
        </div>

        <div class="table-wrap"><table class="data-table"><thead><tr><th>Vacancy</th><th>Hotel</th><th>Category / Service</th><th>Openings</th><th>Payout</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody>
        <?php if (!$vacancies): ?><tr><td colspan="8" class="table-empty">No vacancies found.</td></tr>
        <?php else: foreach ($vacancies as $vacancy): ?>
            <tr>
                <td><strong><?= e($vacancy['title']) ?></strong><br><span class="muted small"><?= e($vacancy['description'] ? mb_strimwidth($vacancy['description'], 0, 70, '...') : '') ?></span></td>
                <td><?= e($vacancy['hotel_name']) ?><br><span class="muted small"><?= e($vacancy['hotel_city']) ?></span></td>
                <td><?= e($vacancy['category_name']) ?><br><span class="muted small"><?= e($vacancy['job_role_name']) ?></span></td>
                <td><?= (int)$vacancy['openings_count'] ?></td>
                <td>₹<?= e(number_format((float)$vacancy['payout_amount'], 2)) ?><br><span class="muted small"><?= e(ucwords(str_replace('_', ' ', $vacancy['payout_period']))) ?></span></td>
                <td><span class="status-badge <?= e($vacancy['status']) ?>"><?= e(ucfirst($vacancy['status'])) ?></span></td>
                <td><?= e(date('d M Y', strtotime($vacancy['created_at']))) ?></td>
                <td><div class="actions">
                    <a class="button-small" href="vacancies.php?edit=<?= (int)$vacancy['id'] ?>#vacancy-form">Edit</a>
                    <?php if ($vacancy['status'] === 'draft' || $vacancy['status'] === 'closed'): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="vacancy_id" value="<?= (int)$vacancy['id'] ?>"><button class="button-small success" type="submit">Publish</button></form><?php endif; ?>
                    <?php if ($vacancy['status'] === 'published'): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="close"><input type="hidden" name="vacancy_id" value="<?= (int)$vacancy['id'] ?>"><button class="button-small danger" type="submit">Close</button></form><?php endif; ?>
                    <form method="post" onsubmit="return confirm('Remove this vacancy?');"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="vacancy_id" value="<?= (int)$vacancy['id'] ?>"><button class="button-small danger" type="submit">Remove</button></form>
                </div></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
    </section>
</main>

<script>
(function () {
    const category = document.getElementById('categorySelect');
    const role = document.getElementById('jobRoleSelect');
    if (!category || !role) return;
    const initialRole = role.value;
    function filterRoles() {
        const categoryId = category.value;
        let selectedVisible = false;
        Array.from(role.options).forEach(function (option, index) {
            if (index === 0) return;
            const visible = option.dataset.category === categoryId;
            option.hidden = !visible;
            if (visible && option.value === initialRole) selectedVisible = true;
        });
        if (!selectedVisible && role.value !== '') role.value = '';
    }
    category.addEventListener('change', function () { role.value = ''; filterRoles(); });
    filterRoles();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
