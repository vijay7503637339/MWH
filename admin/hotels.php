<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$pageTitle = 'Hotels';
$admin = currentAdmin();

if (empty($_SESSION['hotel_csrf'])) {
    $_SESSION['hotel_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['hotel_csrf'];

$uploadDir = __DIR__ . '/../api/uploads/hotels';
$uploadUrl = '../api/uploads/hotels';
$allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$errors = [];
$success = $_SESSION['hotel_success'] ?? '';
unset($_SESSION['hotel_success']);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectHotels(): never
{
    header('Location: hotels.php');
    exit;
}

function requireCsrf(string $token): void
{
    if (!hash_equals($_SESSION['hotel_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid request token. Please go back and try again.');
    }
}

function uploadHotelImage(array $file, string $uploadDir, array $allowedMimes): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Hotel image upload failed.');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Hotel image must be 5 MB or smaller.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowedMimes[$mime])) {
        throw new RuntimeException('Only JPG, PNG and WebP images are allowed.');
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create hotel upload folder.');
    }

    $filename = 'hotel_' . bin2hex(random_bytes(12)) . '.' . $allowedMimes[$mime];
    $destination = rtrim($uploadDir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to save hotel image.');
    }

    return $filename;
}

function deleteHotelImageFile(string $path): void
{
    if ($path !== '' && is_file($path)) {
        @unlink($path);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf((string)($_POST['csrf'] ?? ''));

    $action = (string)($_POST['action'] ?? '');
    $hotelId = (int)($_POST['hotel_id'] ?? 0);

    try {
        if ($action === 'save') {
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $address = trim((string)($_POST['address_line'] ?? ''));
            $city = trim((string)($_POST['city'] ?? ''));
            $state = trim((string)($_POST['state'] ?? ''));
            $postcode = trim((string)($_POST['postcode'] ?? ''));
            $country = trim((string)($_POST['country'] ?? 'India')) ?: 'India';
            $latitude = trim((string)($_POST['latitude'] ?? ''));
            $longitude = trim((string)($_POST['longitude'] ?? ''));
            $contactName = trim((string)($_POST['contact_name'] ?? ''));
            $contactPhone = trim((string)($_POST['contact_phone'] ?? ''));
            $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

            if ($name === '') {
                throw new RuntimeException('Hotel name is required.');
            }
            if ($city === '') {
                throw new RuntimeException('City is required.');
            }
            if ($postcode !== '' && !preg_match('/^[0-9A-Za-z -]{3,12}$/', $postcode)) {
                throw new RuntimeException('Please enter a valid postcode.');
            }
            if ($contactPhone !== '' && !preg_match('/^[0-9+() -]{7,20}$/', $contactPhone)) {
                throw new RuntimeException('Please enter a valid contact number.');
            }
            if ($latitude !== '' && (!is_numeric($latitude) || $latitude < -90 || $latitude > 90)) {
                throw new RuntimeException('Latitude must be between -90 and 90.');
            }
            if ($longitude !== '' && (!is_numeric($longitude) || $longitude < -180 || $longitude > 180)) {
                throw new RuntimeException('Longitude must be between -180 and 180.');
            }

            $imageName = uploadHotelImage($_FILES['image'] ?? [], $uploadDir, $allowedMimes);

            if ($hotelId > 0) {
                $check = $pdo->prepare('SELECT id FROM hotels WHERE id = ? LIMIT 1');
                $check->execute([$hotelId]);
                if (!$check->fetchColumn()) {
                    throw new RuntimeException('Hotel not found.');
                }

                $stmt = $pdo->prepare('UPDATE hotels SET name = ?, description = ?, address_line = ?, city = ?, state = ?, postcode = ?, country = ?, latitude = ?, longitude = ?, contact_name = ?, contact_phone = ?, status = ?, deleted_at = NULL WHERE id = ?');
                $stmt->execute([
                    $name,
                    $description !== '' ? $description : null,
                    $address !== '' ? $address : null,
                    $city,
                    $state !== '' ? $state : null,
                    $postcode !== '' ? $postcode : null,
                    $country,
                    $latitude !== '' ? $latitude : null,
                    $longitude !== '' ? $longitude : null,
                    $contactName !== '' ? $contactName : null,
                    $contactPhone !== '' ? $contactPhone : null,
                    $status,
                    $hotelId,
                ]);

                if ($imageName !== null) {
                    $old = $pdo->prepare('SELECT id, storage_path FROM hotel_images WHERE hotel_id = ? AND is_primary = 1 LIMIT 1');
                    $old->execute([$hotelId]);
                    $oldImage = $old->fetch(PDO::FETCH_ASSOC);

                    $pdo->prepare('DELETE FROM hotel_images WHERE hotel_id = ?')->execute([$hotelId]);
                    if ($oldImage) {
                        deleteHotelImageFile(__DIR__ . '/../api/' . ltrim((string)$oldImage['storage_path'], '/'));
                    }

                    $image = $pdo->prepare('INSERT INTO hotel_images (hotel_id, storage_path, original_file_name, sort_order, is_primary) VALUES (?, ?, ?, 0, 1)');
                    $image->execute([$hotelId, 'uploads/hotels/' . $imageName, $_FILES['image']['name'] ?? null]);
                }

                $_SESSION['hotel_success'] = 'Hotel updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO hotels (name, description, address_line, city, state, postcode, country, latitude, longitude, contact_name, contact_phone, status, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $name,
                    $description !== '' ? $description : null,
                    $address !== '' ? $address : null,
                    $city,
                    $state !== '' ? $state : null,
                    $postcode !== '' ? $postcode : null,
                    $country,
                    $latitude !== '' ? $latitude : null,
                    $longitude !== '' ? $longitude : null,
                    $contactName !== '' ? $contactName : null,
                    $contactPhone !== '' ? $contactPhone : null,
                    $status,
                    $admin['id'],
                ]);

                $newHotelId = (int)$pdo->lastInsertId();
                if ($imageName !== null) {
                    $image = $pdo->prepare('INSERT INTO hotel_images (hotel_id, storage_path, original_file_name, sort_order, is_primary) VALUES (?, ?, ?, 0, 1)');
                    $image->execute([$newHotelId, 'uploads/hotels/' . $imageName, $_FILES['image']['name'] ?? null]);
                }

                $_SESSION['hotel_success'] = 'Hotel added successfully.';
            }

            redirectHotels();
        }

        if ($action === 'delete' && $hotelId > 0) {
            $stmt = $pdo->prepare("UPDATE hotels SET status = 'inactive', deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$hotelId]);
            $_SESSION['hotel_success'] = 'Hotel deactivated successfully.';
            redirectHotels();
        }

        throw new RuntimeException('Invalid hotel action.');
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$editHotel = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $stmt = $pdo->prepare("SELECT h.*, hi.storage_path AS image_path FROM hotels h LEFT JOIN hotel_images hi ON hi.hotel_id = h.id AND hi.is_primary = 1 WHERE h.id = ? LIMIT 1");
        $stmt->execute([$editId]);
        $editHotel = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'all');

$sql = "SELECT h.*, hi.storage_path AS image_path,
        (SELECT COUNT(*) FROM vacancies v WHERE v.hotel_id = h.id AND v.deleted_at IS NULL) AS vacancy_count
        FROM hotels h
        LEFT JOIN hotel_images hi ON hi.hotel_id = h.id AND hi.is_primary = 1
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= ' AND (h.name LIKE ? OR h.city LIKE ? OR h.contact_name LIKE ? OR h.contact_phone LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}

if ($statusFilter === 'active') {
    $sql .= " AND h.status = 'active' AND h.deleted_at IS NULL";
} elseif ($statusFilter === 'inactive') {
    $sql .= " AND (h.status = 'inactive' OR h.deleted_at IS NOT NULL)";
} else {
    $sql .= ' AND h.deleted_at IS NULL';
}

$sql .= ' ORDER BY h.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<main class="content">
    <div class="topbar">
        <div>
            <h1>Hotels</h1>
            <p class="muted">Add and manage registered MWH hotel partners.</p>
        </div>
        <a class="button-link" href="hotels.php#hotel-form">+ Add Hotel</a>
    </div>

    <?php if ($success): ?>
        <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <section class="panel" id="hotel-form">
        <div class="panel-header">
            <div>
                <h2><?= $editHotel ? 'Edit Hotel' : 'Add Hotel' ?></h2>
                <p class="muted small">Keep the hotel information accurate because vacancies will be linked to this hotel.</p>
            </div>
            <?php if ($editHotel): ?>
                <a class="button-secondary" href="hotels.php">Cancel Edit</a>
            <?php endif; ?>
        </div>

        <form method="post" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="hotel_id" value="<?= (int)($editHotel['id'] ?? 0) ?>">

            <div class="field full"><label>Hotel Name *</label><input type="text" name="name" required maxlength="190" value="<?= e($editHotel['name'] ?? '') ?>"></div>
            <div class="field full"><label>Description</label><textarea name="description" rows="3" maxlength="5000"><?= e($editHotel['description'] ?? '') ?></textarea></div>
            <div class="field full"><label>Address</label><textarea name="address_line" rows="2" maxlength="500"><?= e($editHotel['address_line'] ?? '') ?></textarea></div>
            <div class="field"><label>City *</label><input type="text" name="city" required maxlength="100" value="<?= e($editHotel['city'] ?? '') ?>"></div>
            <div class="field"><label>State</label><input type="text" name="state" maxlength="100" value="<?= e($editHotel['state'] ?? '') ?>"></div>
            <div class="field"><label>Postcode</label><input type="text" name="postcode" maxlength="20" value="<?= e($editHotel['postcode'] ?? '') ?>"></div>
            <div class="field"><label>Country</label><input type="text" name="country" maxlength="100" value="<?= e($editHotel['country'] ?? 'India') ?>"></div>
            <div class="field"><label>Contact Person</label><input type="text" name="contact_name" maxlength="150" value="<?= e($editHotel['contact_name'] ?? '') ?>"></div>
            <div class="field"><label>Contact Number</label><input type="text" name="contact_phone" maxlength="20" value="<?= e($editHotel['contact_phone'] ?? '') ?>"></div>
            <div class="field"><label>Latitude</label><input type="number" step="0.0000001" name="latitude" value="<?= e($editHotel['latitude'] ?? '') ?>"></div>
            <div class="field"><label>Longitude</label><input type="number" step="0.0000001" name="longitude" value="<?= e($editHotel['longitude'] ?? '') ?>"></div>
            <div class="field"><label>Status</label><select name="status"><option value="active" <?= (($editHotel['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option><option value="inactive" <?= (($editHotel['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option></select></div>
            <div class="field"><label>Hotel Image</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP, max 5 MB.</small></div>

            <?php if (!empty($editHotel['image_path'])): ?>
                <div class="field full"><img class="hotel-preview" src="<?= e($uploadUrl . '/' . basename((string)$editHotel['image_path'])) ?>" alt="Hotel image"></div>
            <?php endif; ?>

            <div class="field full"><button type="submit"><?= $editHotel ? 'Update Hotel' : 'Save Hotel' ?></button></div>
        </form>
    </section>

    <section class="section">
        <div class="panel-header">
            <div><h2>Hotel List</h2><p class="muted small"><?= count($hotels) ?> hotel(s) found.</p></div>
            <form method="get" class="filter-form">
                <input type="search" name="search" placeholder="Search hotel, city or contact..." value="<?= e($search) ?>">
                <select name="status"><option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Active Hotels</option><option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option></select>
                <button type="submit">Search</button>
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Hotel</th><th>Location</th><th>Contact</th><th>Vacancies</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (!$hotels): ?>
                    <tr><td colspan="7" class="table-empty">No hotels found.</td></tr>
                <?php else: ?>
                    <?php foreach ($hotels as $hotel): ?>
                        <tr>
                            <td><strong><?= e($hotel['name']) ?></strong></td>
                            <td><?= e(trim(($hotel['city'] ?? '') . (($hotel['state'] ?? '') ? ', ' . $hotel['state'] : ''))) ?></td>
                            <td><?= e($hotel['contact_name'] ?? '-') ?><br><span class="muted small"><?= e($hotel['contact_phone'] ?? '-') ?></span></td>
                            <td><?= (int)$hotel['vacancy_count'] ?></td>
                            <td><span class="status-badge <?= $hotel['status'] === 'active' ? 'active' : 'inactive' ?>"><?= e(ucfirst($hotel['status'])) ?></span></td>
                            <td><?= e(date('d M Y', strtotime((string)$hotel['created_at']))) ?></td>
                            <td><div class="actions"><a class="button-small" href="hotels.php?edit=<?= (int)$hotel['id'] ?>#hotel-form">Edit</a><form method="post" onsubmit="return confirm('Deactivate this hotel? Existing vacancy/application history will be kept.');"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="hotel_id" value="<?= (int)$hotel['id'] ?>"><button class="button-small danger" type="submit">Deactivate</button></form></div></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
