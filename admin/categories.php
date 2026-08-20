<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$pageTitle = 'Categories & Job Roles';

if (empty($_SESSION['categories_csrf'])) {
    $_SESSION['categories_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['categories_csrf'];
$errors = [];
$success = $_SESSION['categories_success'] ?? '';
unset($_SESSION['categories_success']);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectCategories(): never
{
    header('Location: categories.php');
    exit;
}

function requireCategoryCsrf(string $token): void
{
    if (!hash_equals($_SESSION['categories_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid request token. Please go back and try again.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCategoryCsrf((string)($_POST['csrf'] ?? ''));
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'add_category') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') throw new RuntimeException('Category name is required.');
            if (mb_strlen($name) > 120) throw new RuntimeException('Category name is too long.');

            $check = $pdo->prepare('SELECT id FROM categories WHERE name=? AND deleted_at IS NULL LIMIT 1');
            $check->execute([$name]);
            if ($check->fetchColumn()) throw new RuntimeException('This category already exists.');

            $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
            $stmt->execute([$name]);
            $_SESSION['categories_success'] = 'Category added successfully.';
            redirectCategories();
        }

        if ($action === 'add_role') {
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            if ($categoryId <= 0) throw new RuntimeException('Invalid category.');
            if ($name === '') throw new RuntimeException('Job role name is required.');
            if (mb_strlen($name) > 150) throw new RuntimeException('Job role name is too long.');

            $categoryCheck = $pdo->prepare('SELECT id FROM categories WHERE id=? AND is_active=1 AND deleted_at IS NULL LIMIT 1');
            $categoryCheck->execute([$categoryId]);
            if (!$categoryCheck->fetchColumn()) throw new RuntimeException('Category not found or inactive.');

            $check = $pdo->prepare('SELECT id FROM job_roles WHERE category_id=? AND name=? AND deleted_at IS NULL LIMIT 1');
            $check->execute([$categoryId, $name]);
            if ($check->fetchColumn()) throw new RuntimeException('This job role already exists in this category.');

            $stmt = $pdo->prepare('INSERT INTO job_roles (category_id, name) VALUES (?, ?)');
            $stmt->execute([$categoryId, $name]);
            $_SESSION['categories_success'] = 'Job role added successfully.';
            redirectCategories();
        }

        if ($action === 'toggle_category') {
            $id = (int)($_POST['category_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE categories SET is_active=IF(is_active=1,0,1) WHERE id=? AND deleted_at IS NULL');
            $stmt->execute([$id]);
            $_SESSION['categories_success'] = 'Category status updated.';
            redirectCategories();
        }

        if ($action === 'toggle_role') {
            $id = (int)($_POST['job_role_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE job_roles SET is_active=IF(is_active=1,0,1) WHERE id=? AND deleted_at IS NULL');
            $stmt->execute([$id]);
            $_SESSION['categories_success'] = 'Job role status updated.';
            redirectCategories();
        }

        throw new RuntimeException('Invalid action.');
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$categoriesStmt = $pdo->query('SELECT id, name, is_active FROM categories WHERE deleted_at IS NULL ORDER BY name ASC');
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$rolesStmt = $pdo->query('SELECT id, category_id, name, is_active FROM job_roles WHERE deleted_at IS NULL ORDER BY category_id ASC, name ASC');
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
$rolesByCategory = [];
foreach ($roles as $role) {
    $rolesByCategory[(int)$role['category_id']][] = $role;
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<main class="content">
    <div class="topbar">
        <div>
            <h1>Categories & Job Roles</h1>
            <p class="muted">Create job categories and add services / job roles inside each category.</p>
        </div>
    </div>

    <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>

    <section class="panel" style="margin-bottom:24px;">
        <div class="panel-header">
            <div>
                <h2>Add Category</h2>
                <p class="muted small">Only the category name is required.</p>
            </div>
        </div>
        <form method="post" class="inline-create-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add_category">
            <input type="text" name="name" maxlength="120" required placeholder="e.g. Kitchen Staff">
            <button type="submit">+ Add Category</button>
        </form>
    </section>

    <section class="section">
        <div class="panel-header">
            <div>
                <h2>Categories</h2>
                <p class="muted small"><?= count($categories) ?> categor<?= count($categories) === 1 ? 'y' : 'ies' ?> found.</p>
            </div>
        </div>

        <div class="category-list">
            <?php if (!$categories): ?>
                <div class="empty-box">No categories found. Add your first category above.</div>
            <?php endif; ?>

            <?php foreach ($categories as $category): ?>
                <?php $categoryId = (int)$category['id']; ?>
                <div class="category-card">
                    <div class="category-header">
                        <div>
                            <strong><?= e($category['name']) ?></strong>
                            <span class="muted small">ID: <?= $categoryId ?></span>
                            <span class="status-badge <?= (int)$category['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int)$category['is_active'] === 1 ? 'Active' : 'Inactive' ?></span>
                        </div>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="toggle_category">
                            <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                            <button type="submit" class="button-small <?= (int)$category['is_active'] === 1 ? 'danger' : 'success' ?>"><?= (int)$category['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                    </div>

                    <div class="role-section">
                        <div class="role-section-header">
                            <strong>Job Roles</strong>
                            <span class="muted small"><?= count($rolesByCategory[$categoryId] ?? []) ?> role(s)</span>
                        </div>

                        <div class="role-list">
                            <?php foreach (($rolesByCategory[$categoryId] ?? []) as $role): ?>
                                <div class="role-row">
                                    <div>
                                        <span><?= e($role['name']) ?></span>
                                        <span class="muted small">ID: <?= (int)$role['id'] ?></span>
                                        <span class="status-badge <?= (int)$role['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int)$role['is_active'] === 1 ? 'Active' : 'Inactive' ?></span>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="toggle_role">
                                        <input type="hidden" name="job_role_id" value="<?= (int)$role['id'] ?>">
                                        <button type="submit" class="button-small <?= (int)$role['is_active'] === 1 ? 'danger' : 'success' ?>"><?= (int)$role['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($rolesByCategory[$categoryId])): ?><div class="empty-box small">No job roles added yet.</div><?php endif; ?>
                        </div>

                        <?php if ((int)$category['is_active'] === 1): ?>
                        <form method="post" class="inline-create-form role-create-form">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="add_role">
                            <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                            <input type="text" name="name" maxlength="150" required placeholder="Add job role, e.g. Tandoor Chef">
                            <button type="submit">+ Add Job Role</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
