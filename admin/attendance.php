<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$pageTitle = 'Attendance';

if (empty($_SESSION['attendance_csrf'])) {
    $_SESSION['attendance_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['attendance_csrf'];
$success = $_SESSION['attendance_success'] ?? '';
unset($_SESSION['attendance_success']);
$error = '';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectAttendance(): never
{
    header('Location: attendance.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['attendance_csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Invalid request token.');
    }

    try {
        $action = (string)($_POST['action'] ?? '');
        $attendanceId = (int)($_POST['attendance_id'] ?? 0);

        if ($action === 'checkout') {
            if ($attendanceId <= 0) {
                throw new RuntimeException('Invalid attendance record.');
            }

            $find = $pdo->prepare('SELECT id,check_out_at FROM attendance_records WHERE id=? LIMIT 1');
            $find->execute([$attendanceId]);
            $record = $find->fetch(PDO::FETCH_ASSOC);
            if (!$record) {
                throw new RuntimeException('Attendance record not found.');
            }
            if (!empty($record['check_out_at'])) {
                throw new RuntimeException('This candidate is already checked out.');
            }

            $notes = trim((string)($_POST['check_out_notes'] ?? ''));
            $notes = $notes !== '' ? $notes : null;
            // Attendance API timestamps are stored in UTC. Keep admin check-out
            // in the same timezone so duration calculations remain consistent.
            $stmt = $pdo->prepare('UPDATE attendance_records SET check_out_at=UTC_TIMESTAMP(), check_out_notes=? WHERE id=?');
            $stmt->execute([$notes, $attendanceId]);

            $_SESSION['attendance_success'] = 'Candidate checked out successfully.';
            redirectAttendance();
        }

        throw new RuntimeException('Invalid attendance action.');
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$dateFrom = trim((string)($_GET['from_date'] ?? ''));
$dateTo = trim((string)($_GET['to_date'] ?? ''));
$status = (string)($_GET['status'] ?? 'all');
$hotelId = (int)($_GET['hotel_id'] ?? 0);

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}
if (!in_array($status, ['all', 'checked_in', 'checked_out'], true)) {
    $status = 'all';
}

$where = ['u.deleted_at IS NULL'];
$params = [];

if ($dateFrom !== '' && $dateTo !== '') {
    $where[] = 'DATE(ar.check_in_at) BETWEEN ? AND ?';
    $params[] = $dateFrom;
    $params[] = $dateTo;
} elseif ($dateFrom !== '') {
    $where[] = 'DATE(ar.check_in_at)=?';
    $params[] = $dateFrom;
} elseif ($dateTo !== '') {
    $where[] = 'DATE(ar.check_in_at)=?';
    $params[] = $dateTo;
}

if ($search !== '') {
    $where[] = '(cp.first_name LIKE ? OR cp.last_name LIKE ? OR u.mobile LIKE ? OR v.title LIKE ? OR h.name LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
}
if ($status === 'checked_in') {
    $where[] = 'ar.check_out_at IS NULL';
} elseif ($status === 'checked_out') {
    $where[] = 'ar.check_out_at IS NOT NULL';
}
if ($hotelId > 0) {
    $where[] = 'h.id=?';
    $params[] = $hotelId;
}

$sql = 'SELECT ar.id,ar.job_id,ar.user_id,ar.check_in_at,ar.check_in_photo_path,ar.check_in_capture_source,ar.check_in_latitude,ar.check_in_longitude,ar.check_in_notes,ar.check_out_at,ar.check_out_notes,
        CASE
            WHEN ar.check_out_at IS NOT NULL THEN
                CASE
                    WHEN TIMESTAMPDIFF(MINUTE, ar.check_in_at, ar.check_out_at) >= 0
                        THEN TIMESTAMPDIFF(MINUTE, ar.check_in_at, ar.check_out_at)
                    -- Older records can contain check-in in IST and check-out in UTC.
                    -- The exact India offset is 330 minutes; normalize those legacy rows.
                    WHEN TIMESTAMPDIFF(MINUTE, ar.check_in_at, ar.check_out_at) + 330 >= 0
                        THEN TIMESTAMPDIFF(MINUTE, ar.check_in_at, ar.check_out_at) + 330
                    ELSE 0
                END
            ELSE GREATEST(0, TIMESTAMPDIFF(MINUTE, ar.check_in_at, UTC_TIMESTAMP()))
        END AS worked_minutes,
        cp.first_name,cp.last_name,u.mobile,v.title AS vacancy_title,h.id AS hotel_id,h.name AS hotel_name,h.city AS hotel_city,
        j.status AS job_status,j.payout_amount
        FROM attendance_records ar
        INNER JOIN users u ON u.id=ar.user_id
        LEFT JOIN candidate_profiles cp ON cp.user_id=ar.user_id
        INNER JOIN jobs j ON j.id=ar.job_id
        INNER JOIN vacancies v ON v.id=j.vacancy_id
        INNER JOIN hotels h ON h.id=j.hotel_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ar.check_in_at DESC, ar.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hotels = $pdo->query("SELECT id,name,city FROM hotels WHERE status='active' AND deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$statsWhere = [];
$statsParams = [];
if ($dateFrom !== '' && $dateTo !== '') {
    $statsWhere[] = 'DATE(check_in_at) BETWEEN ? AND ?';
    $statsParams[] = $dateFrom;
    $statsParams[] = $dateTo;
} elseif ($dateFrom !== '') {
    $statsWhere[] = 'DATE(check_in_at)=?';
    $statsParams[] = $dateFrom;
} elseif ($dateTo !== '') {
    $statsWhere[] = 'DATE(check_in_at)=?';
    $statsParams[] = $dateTo;
}
$statsSql = "SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN check_out_at IS NULL THEN 1 ELSE 0 END) AS checked_in,
    SUM(CASE WHEN check_out_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_out,
    COALESCE(SUM(
        CASE
            WHEN check_out_at IS NULL THEN 0
            WHEN TIMESTAMPDIFF(MINUTE,check_in_at,check_out_at) >= 0
                THEN TIMESTAMPDIFF(MINUTE,check_in_at,check_out_at)
            WHEN TIMESTAMPDIFF(MINUTE,check_in_at,check_out_at) + 330 >= 0
                THEN TIMESTAMPDIFF(MINUTE,check_in_at,check_out_at) + 330
            ELSE 0
        END
    ),0) AS minutes_worked
    FROM attendance_records";
if ($statsWhere) {
    $statsSql .= ' WHERE ' . implode(' AND ', $statsWhere);
}
$statStmt = $pdo->prepare($statsSql);
$statStmt->execute($statsParams);
$stats = $statStmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'checked_in'=>0,'checked_out'=>0,'minutes_worked'=>0];

$totalAttendance = (int)$pdo->query('SELECT COUNT(*) FROM attendance_records')->fetchColumn();
$totalMinutes = (int)($stats['minutes_worked'] ?? 0);
$hours = intdiv($totalMinutes, 60);
$minutes = $totalMinutes % 60;
$workedLabel = $hours . 'h ' . $minutes . 'm';

function initials(string $first, string $last): string
{
    $value = trim($first . ' ' . $last);
    if ($value === '') return '?';
    $parts = preg_split('/\s+/', $value) ?: [];
    $out = strtoupper(substr((string)($parts[0] ?? ''), 0, 1));
    if (count($parts) > 1) $out .= strtoupper(substr((string)$parts[count($parts)-1], 0, 1));
    return $out ?: '?';
}

function formatDateTime(?string $value): array
{
    if (!$value) return ['—', ''];
    $ts = strtotime($value);
    return [$ts ? date('d M Y', $ts) : $value, $ts ? date('h:i A', $ts) : ''];
}

function durationLabel(?int $workedMinutes): string
{
    if ($workedMinutes === null) {
        return '—';
    }

    $workedMinutes = max(0, $workedMinutes);
    $hours = intdiv($workedMinutes, 60);
    $minutes = $workedMinutes % 60;

    return $hours . 'h ' . $minutes . 'm';
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<main class="content attendance-page">
    <div class="topbar attendance-heading">
        <div>
            <div class="page-kicker"><i class="bi bi-clock-history"></i> Workforce Management</div>
            <h1>Attendance</h1>
            <p class="muted">Track candidate check-ins, check-outs and working hours across hotels.</p>
        </div>
        <div class="attendance-count">
            <strong><?= number_format($totalAttendance) ?></strong>
            <span>Total records</span>
        </div>
    </div>

    <?php if ($success): ?><div class="attendance-alert success"><i class="bi bi-check-circle-fill"></i><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="attendance-alert error"><i class="bi bi-exclamation-circle-fill"></i><?= e($error) ?></div><?php endif; ?>

    <section class="attendance-summary">
        <div class="attendance-stat"><div class="attendance-stat-icon blue"><i class="bi bi-people"></i></div><div><strong><?= number_format((int)$stats['total']) ?></strong><span><?= $dateFrom === '' && $dateTo === '' ? 'All attendance' : 'Attendance for selected period' ?></span></div></div>
        <div class="attendance-stat"><div class="attendance-stat-icon amber"><i class="bi bi-person-walking"></i></div><div><strong><?= number_format((int)$stats['checked_in']) ?></strong><span>Currently checked in</span></div></div>
        <div class="attendance-stat"><div class="attendance-stat-icon green"><i class="bi bi-check2-circle"></i></div><div><strong><?= number_format((int)$stats['checked_out']) ?></strong><span>Checked out</span></div></div>
        <div class="attendance-stat"><div class="attendance-stat-icon purple"><i class="bi bi-hourglass-split"></i></div><div><strong><?= e($workedLabel) ?></strong><span>Completed hours today</span></div></div>
    </section>

    <section class="panel attendance-panel">
        <div class="attendance-panel-header">
            <div>
                <h2>Attendance Records</h2>
                <p class="muted">Search, filter and manage candidate attendance records.</p>
            </div>
        </div>

        <form class="attendance-filters" method="get">
            <div class="attendance-field search-field"><i class="bi bi-search"></i><input type="text" name="search" value="<?= e($search) ?>" placeholder="Search candidate, mobile, vacancy or hotel"></div>
            <div class="attendance-field"><i class="bi bi-calendar3"></i><input type="date" name="from_date" value="<?= e($dateFrom) ?>" aria-label="From date"></div>
            <div class="attendance-field"><i class="bi bi-calendar3"></i><input type="date" name="to_date" value="<?= e($dateTo) ?>" aria-label="To date"></div>
            <div class="attendance-field"><i class="bi bi-funnel"></i><select name="status"><option value="all" <?= $status==='all'?'selected':'' ?>>All Status</option><option value="checked_in" <?= $status==='checked_in'?'selected':'' ?>>Checked In</option><option value="checked_out" <?= $status==='checked_out'?'selected':'' ?>>Checked Out</option></select></div>
            <div class="attendance-field"><i class="bi bi-building"></i><select name="hotel_id"><option value="0">All Hotels</option><?php foreach ($hotels as $hotel): ?><option value="<?= (int)$hotel['id'] ?>" <?= $hotelId===(int)$hotel['id']?'selected':'' ?>><?= e($hotel['name']) ?><?= !empty($hotel['city']) ? ' · '.e($hotel['city']) : '' ?></option><?php endforeach; ?></select></div>
            <button class="attendance-search" type="submit"><i class="bi bi-search"></i> Search</button>
            <?php if ($search !== '' || $status !== 'all' || $hotelId > 0 || $dateFrom !== '' || $dateTo !== ''): ?><a class="attendance-clear" href="attendance.php">Reset</a><?php endif; ?>
        </form>

        <div class="attendance-table-wrap">
            <table class="attendance-table">
                <thead><tr><th>Candidate</th><th>Job / Hotel</th><th>Check In</th><th>Check Out</th><th>Duration</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (!$records): ?>
                    <tr><td colspan="7" class="attendance-empty"><i class="bi bi-calendar2-x"></i><strong>No attendance records found</strong><span>Try another date or change your filters.</span></td></tr>
                <?php else: foreach ($records as $record):
                    [$inDate,$inTime] = formatDateTime($record['check_in_at']);
                    [$outDate,$outTime] = formatDateTime($record['check_out_at']);
                    $name = trim(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? '')) ?: 'Unknown candidate';
                    $isIn = empty($record['check_out_at']);
                    $workedMinutes = isset($record['worked_minutes']) ? (int)$record['worked_minutes'] : null;
                    $workedDuration = durationLabel($workedMinutes);
                ?>
                    <tr>
                        <td><div class="candidate-cell"><div class="candidate-avatar"><?= e(initials((string)$record['first_name'], (string)$record['last_name'])) ?></div><div><strong><?= e($name) ?></strong><span><?= e((string)$record['mobile']) ?></span></div></div></td>
                        <td><strong class="primary-cell"><?= e((string)$record['vacancy_title']) ?></strong><span class="secondary-cell"><?= e((string)$record['hotel_name']) ?><?= !empty($record['hotel_city']) ? ' · '.e((string)$record['hotel_city']) : '' ?></span></td>
                        <td><div class="datetime-cell"><strong><?= e($inDate) ?></strong><span><?= e($inTime) ?></span></div></td>
                        <td><div class="datetime-cell"><?php if ($record['check_out_at']): ?><strong><?= e($outDate) ?></strong><span><?= e($outTime) ?></span><?php else: ?><span class="dash">Not checked out</span><?php endif; ?></div></td>
                        <td><span class="duration-badge"><i class="bi bi-stopwatch"></i><?= e($workedDuration) ?></span></td>
                        <td><span class="attendance-status <?= $isIn ? 'status-in' : 'status-out' ?>"><span></span><?= $isIn ? 'Checked In' : 'Checked Out' ?></span></td>
                        <td><div class="attendance-actions"><button type="button" class="attendance-action view" data-id="<?= (int)$record['id'] ?>" data-name="<?= e($name) ?>" data-mobile="<?= e((string)$record['mobile']) ?>" data-job="<?= e((string)$record['vacancy_title']) ?>" data-hotel="<?= e((string)$record['hotel_name']) ?>" data-checkin="<?= e($inDate . ' ' . $inTime) ?>" data-checkout="<?= e($record['check_out_at'] ? $outDate . ' ' . $outTime : 'Not checked out') ?>" data-duration="<?= e($workedDuration) ?>" data-source="<?= e((string)$record['check_in_capture_source']) ?>" data-notes="<?= e((string)($record['check_in_notes'] ?? '')) ?>"><i class="bi bi-eye"></i> View</button><?php if ($isIn): ?><button type="button" class="attendance-action checkout" data-checkout-id="<?= (int)$record['id'] ?>" data-checkout-name="<?= e($name) ?>"><i class="bi bi-box-arrow-right"></i> Check Out</button><?php endif; ?></div></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<div class="attendance-modal" id="attendanceViewModal" aria-hidden="true">
    <div class="attendance-modal-backdrop"></div>
    <div class="attendance-modal-card">
        <button type="button" class="modal-close" data-close-modal><i class="bi bi-x-lg"></i></button>
        <div class="modal-title-row"><div class="modal-avatar" id="modalAvatar">C</div><div><div class="page-kicker">Attendance Details</div><h2 id="modalName">Candidate</h2><span id="modalMobile"></span></div></div>
        <div class="detail-grid"><div><label>Job</label><strong id="modalJob">—</strong></div><div><label>Hotel</label><strong id="modalHotel">—</strong></div><div><label>Check In</label><strong id="modalCheckIn">—</strong></div><div><label>Check Out</label><strong id="modalCheckOut">—</strong></div><div><label>Duration</label><strong id="modalDuration">—</strong></div><div><label>Capture Source</label><strong id="modalSource">—</strong></div></div>
        <div class="modal-notes"><label>Check-in Notes</label><p id="modalNotes">No notes added.</p></div>
    </div>
</div>

<div class="attendance-modal" id="attendanceCheckoutModal" aria-hidden="true">
    <div class="attendance-modal-backdrop"></div>
    <div class="attendance-modal-card small">
        <button type="button" class="modal-close" data-close-modal><i class="bi bi-x-lg"></i></button>
        <div class="modal-icon warning"><i class="bi bi-box-arrow-right"></i></div><h2>Check out candidate?</h2><p class="muted">You are about to mark <strong id="checkoutName">this candidate</strong> as checked out using the current server time.</p>
        <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="checkout"><input type="hidden" name="attendance_id" id="checkoutId"><label for="checkoutNotes">Optional note</label><textarea id="checkoutNotes" name="check_out_notes" rows="3" placeholder="Add a note if needed..."></textarea><div class="modal-actions"><button type="button" class="attendance-clear" data-close-modal>Cancel</button><button type="submit" class="attendance-search"><i class="bi bi-check2"></i> Confirm Check Out</button></div></form>
    </div>
</div>

<style>
.attendance-page{max-width:1600px;margin-right:0!important}.attendance-heading{align-items:center;margin-bottom:22px}.attendance-heading .page-kicker{display:flex;align-items:center;gap:8px;color:#4169e1;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}.attendance-heading h1{font-size:32px;margin-bottom:6px}.attendance-count{text-align:right;min-width:120px}.attendance-count strong{display:block;font-size:30px;line-height:1;color:#172033}.attendance-count span{display:block;color:#7a8498;font-size:12px;margin-top:6px}.attendance-alert{display:flex;align-items:center;gap:9px;padding:12px 15px;border-radius:10px;margin-bottom:16px;font-size:12px;font-weight:650}.attendance-alert.success{background:#ecfbf4;color:#087b50;border:1px solid #c9efdc}.attendance-alert.error{background:#fff1f2;color:#bd3139;border:1px solid #f1ccd0}
.attendance-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.attendance-stat{display:flex;align-items:center;gap:13px;background:#fff;border:1px solid #e7ebf2;border-radius:15px;padding:16px;box-shadow:0 4px 14px rgba(20,35,60,.04)}.attendance-stat-icon{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;font-size:18px;flex:0 0 42px}.attendance-stat-icon.blue{background:#eef3ff;color:#4169e1}.attendance-stat-icon.amber{background:#fff7e8;color:#bd7a09}.attendance-stat-icon.green{background:#ecfbf4;color:#087b50}.attendance-stat-icon.purple{background:#f3efff;color:#7653c6}.attendance-stat strong{display:block;color:#172033;font-size:21px;line-height:1.1}.attendance-stat span{display:block;color:#7d879a;font-size:11px;margin-top:5px;font-weight:600}
.attendance-panel{padding:0;overflow:hidden}.attendance-panel-header{padding:22px 24px 16px}.attendance-panel-header h2{margin:0;font-size:18px}.attendance-panel-header p{margin:4px 0 0;font-size:11px}.attendance-filters{display:grid;grid-template-columns:minmax(250px,1fr) 160px 160px 175px minmax(180px,210px) auto auto;gap:9px;align-items:center;padding:0 24px 18px;border-bottom:1px solid #edf0f5}.attendance-field{position:relative}.attendance-field i{position:absolute;left:12px;top:50%;transform:translateY(-50%);z-index:1;color:#8b95a7;font-size:13px}.attendance-field input,.attendance-field select{width:100%;height:42px;min-height:42px;margin:0;padding:0 12px 0 35px;border-radius:9px;background:#f9fbfe;border-color:#dfe5ee;font-size:12px}.attendance-search,.attendance-clear{height:42px!important;display:inline-flex!important;align-items:center;justify-content:center;gap:7px;padding:0 14px!important;border-radius:9px!important;font-size:11px!important;font-weight:800!important;white-space:nowrap;text-decoration:none}.attendance-search{border:0!important;color:#fff!important;background:linear-gradient(135deg,#3f6ff0,#5d80ed)!important;box-shadow:0 6px 15px rgba(63,111,240,.14)!important}.attendance-clear{border:1px solid #d9e0eb!important;background:#fff!important;color:#65738a!important}
.attendance-table-wrap{overflow-x:auto}.attendance-table{width:100%;border-collapse:separate;border-spacing:0;min-width:1050px}.attendance-table th{padding:13px 16px;background:#fafbfe;color:#7a8498;border-bottom:1px solid #e7ebf2;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;text-align:left;white-space:nowrap}.attendance-table td{padding:14px 16px;border-bottom:1px solid #eef1f5;vertical-align:middle;background:#fff}.attendance-table tbody tr:hover td{background:#fbfcff}.attendance-table tbody tr:last-child td{border-bottom:0}.candidate-cell{display:flex;align-items:center;gap:10px;min-width:180px}.candidate-avatar{width:36px;height:36px;flex:0 0 36px;border-radius:10px;display:grid;place-items:center;background:linear-gradient(145deg,#4169e1,#6c8ff4);color:#fff;font-size:11px;font-weight:900}.candidate-cell strong,.primary-cell{display:block;color:#24324a;font-size:12px;font-weight:800}.candidate-cell span,.secondary-cell{display:block;margin-top:4px;color:#8b95a7;font-size:10px;line-height:1.3}.secondary-cell{max-width:200px}.datetime-cell strong{display:block;color:#34425a;font-size:11px;white-space:nowrap}.datetime-cell span{display:block;margin-top:3px;color:#8f99a9;font-size:10px;white-space:nowrap}.datetime-cell .dash{color:#a0a9b7;font-style:italic}.duration-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:999px;background:#f4f7fb;color:#52617a;font-size:10px;font-weight:750;white-space:nowrap}.attendance-status{display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap}.attendance-status>span{width:6px;height:6px;border-radius:50%;background:currentColor}.status-in{color:#b26c00;background:#fff7e7}.status-out{color:#087b50;background:#ebfbf4}.attendance-actions{display:flex;gap:6px;align-items:center;white-space:nowrap}.attendance-action{height:31px!important;width:auto!important;margin:0!important;display:inline-flex!important;align-items:center;justify-content:center;gap:5px;padding:0 10px!important;border-radius:8px!important;font-size:10px!important;font-weight:800!important;cursor:pointer;box-shadow:none!important}.attendance-action.view{border:1px solid #dce3ed!important;background:#fff!important;color:#50617b!important}.attendance-action.checkout{border:1px solid #c9d9fa!important;background:#f4f7ff!important;color:#315fc8!important}.attendance-empty{text-align:center!important;padding:58px 20px!important}.attendance-empty i{display:block;color:#aeb8c8;font-size:30px;margin-bottom:8px}.attendance-empty strong{display:block;color:#4b5870;font-size:13px}.attendance-empty span{display:block;color:#909aab;font-size:11px;margin-top:5px}
.attendance-modal{position:fixed;inset:0;z-index:100;display:none;align-items:center;justify-content:center;padding:20px}.attendance-modal.open{display:flex}.attendance-modal-backdrop{position:absolute;inset:0;background:rgba(11,20,36,.48);backdrop-filter:blur(4px)}.attendance-modal-card{position:relative;width:min(650px,100%);background:#fff;border:1px solid #e5e9f0;border-radius:18px;padding:25px;box-shadow:0 25px 70px rgba(10,22,45,.22);z-index:1}.attendance-modal-card.small{width:min(460px,100%)}.modal-close{position:absolute;right:15px;top:15px;width:32px;height:32px;border:1px solid #e4e8ef;border-radius:9px;background:#fff;color:#7b879a;display:grid;place-items:center}.modal-title-row{display:flex;align-items:center;gap:13px;padding-right:35px}.modal-avatar{width:48px;height:48px;border-radius:13px;background:linear-gradient(145deg,#4169e1,#6c8ff4);color:#fff;display:grid;place-items:center;font-weight:900}.modal-title-row .page-kicker{margin-bottom:3px;color:#4169e1;font-size:10px}.modal-title-row h2{margin:0;font-size:20px;color:#172033}.modal-title-row span{font-size:11px;color:#8a95a8}.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:22px}.detail-grid>div,.modal-notes{padding:13px;border:1px solid #edf0f4;background:#fafbfe;border-radius:11px}.detail-grid label,.modal-notes label{display:block;color:#8b95a7;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;margin:0 0 5px}.detail-grid strong{display:block;color:#34425a;font-size:12px}.modal-notes{margin-top:12px}.modal-notes p{margin:0;color:#66738a;font-size:11px;line-height:1.5}.modal-icon{width:46px;height:46px;border-radius:13px;display:grid;place-items:center;font-size:19px;margin-bottom:13px}.modal-icon.warning{background:#fff7e8;color:#bd7a09}.attendance-modal-card.small h2{margin:0 0 7px;font-size:19px}.attendance-modal-card.small>.muted{font-size:11px;line-height:1.5;margin-bottom:17px}.attendance-modal-card textarea{width:100%;margin-top:7px;resize:vertical;min-height:80px}.modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}.modal-actions button{width:auto!important;margin:0!important}
@media(max-width:1100px){.attendance-filters{grid-template-columns:1fr 1fr 1fr}.search-field{grid-column:1/-1}.attendance-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.attendance-search,.attendance-clear{width:100%}}@media(max-width:600px){.attendance-heading{align-items:flex-start}.attendance-count{display:none}.attendance-summary{grid-template-columns:1fr}.attendance-filters{grid-template-columns:1fr;padding:0 16px 16px}.search-field{grid-column:auto}.attendance-panel-header{padding:18px}.detail-grid{grid-template-columns:1fr}.attendance-modal-card{padding:20px}.attendance-page{padding-left:14px!important;padding-right:14px!important}}
</style>

<script>
(function(){
    const viewModal=document.getElementById('attendanceViewModal');
    const checkoutModal=document.getElementById('attendanceCheckoutModal');
    const closeAll=()=>document.querySelectorAll('.attendance-modal.open').forEach(m=>{m.classList.remove('open');m.setAttribute('aria-hidden','true');});
    document.querySelectorAll('[data-close-modal],.attendance-modal-backdrop').forEach(el=>el.addEventListener('click',closeAll));
    document.querySelectorAll('.attendance-action.view').forEach(btn=>btn.addEventListener('click',()=>{
        const d=btn.dataset;
        const name=d.name||'Candidate';
        document.getElementById('modalName').textContent=name;
        document.getElementById('modalMobile').textContent=d.mobile||'';
        document.getElementById('modalAvatar').textContent=name.split(/\s+/).map(x=>x[0]).join('').slice(0,2).toUpperCase();
        document.getElementById('modalJob').textContent=d.job||'—';
        document.getElementById('modalHotel').textContent=d.hotel||'—';
        document.getElementById('modalCheckIn').textContent=d.checkin||'—';
        document.getElementById('modalCheckOut').textContent=d.checkout||'—';
        document.getElementById('modalDuration').textContent=d.duration||'—';
        document.getElementById('modalSource').textContent=d.source||'—';
        document.getElementById('modalNotes').textContent=d.notes||'No notes added.';
        viewModal.classList.add('open');viewModal.setAttribute('aria-hidden','false');
    }));
    document.querySelectorAll('.attendance-action.checkout').forEach(btn=>btn.addEventListener('click',()=>{
        document.getElementById('checkoutId').value=btn.dataset.checkoutId||'';
        document.getElementById('checkoutName').textContent=btn.dataset.checkoutName||'this candidate';
        document.getElementById('checkoutNotes').value='';
        checkoutModal.classList.add('open');checkoutModal.setAttribute('aria-hidden','false');
    }));
    document.addEventListener('keydown',e=>{if(e.key==='Escape')closeAll();});
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
