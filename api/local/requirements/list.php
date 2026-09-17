<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    localJson(['success' => false, 'message' => 'GET or POST request required'], 405);
}

function localAuthUserIdForList(PDO $pdo): int
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        localJson(['success' => false, 'message' => 'Authorization token required'], 401);
    }
    $tokenHash = hash('sha256', trim($matches[1]));
    $stmt = $pdo->prepare(
        'SELECT user_id FROM api_tokens
         WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > CURRENT_TIMESTAMP
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        localJson(['success' => false, 'message' => 'Invalid or expired authorization token'], 401);
    }
    return (int)$row['user_id'];
}

try {
    $userId = localAuthUserIdForList($pdo);
    $access = $pdo->prepare(
        'SELECT 1 FROM user_app_access
         WHERE user_id = ? AND app_key = ? AND role_key = ? AND status = ?
         LIMIT 1'
    );
    $access->execute([$userId, 'mwh_local', 'contractor', 'active']);
    if (!$access->fetchColumn()) {
        localJson(['success' => false, 'message' => 'Contractor access is not enabled for this account'], 403);
    }

    $data = localInput();
    $status = trim((string)($data['status'] ?? $_GET['status'] ?? ''));
    $status = $status !== '' ? $status : null;
    $allowed = ['open', 'active', 'completed', 'cancelled', 'closed'];
    if ($status !== null && !in_array($status, $allowed, true)) {
        localJson(['success' => false, 'message' => 'Invalid status'], 422);
    }

    $limit = (int)($data['limit'] ?? $_GET['limit'] ?? 20);
    $limit = max(1, min(100, $limit));

    $sql = 'SELECT lr.id, lr.category_id, lr.job_role_id, lr.title, lr.description,
                   lr.openings_count, lr.work_location, lr.work_address,
                   lr.shift_date, lr.shift_start, lr.shift_end,
                   lr.minimum_experience, lr.payout_amount, lr.payout_period,
                   lr.status, lr.notes, lr.created_at, lr.updated_at,
                   c.name AS category_name, jr.name AS job_role_name
            FROM local_requirements lr
            LEFT JOIN categories c ON c.id = lr.category_id
            LEFT JOIN job_roles jr ON jr.id = lr.job_role_id
            WHERE lr.contractor_user_id = ?';
    $params = [$userId];
    if ($status !== null) {
        $sql .= ' AND lr.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY lr.created_at DESC LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summaryStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(status = "open") AS open_count,
            SUM(status = "active") AS active_count,
            SUM(status = "completed") AS completed_count
         FROM local_requirements
         WHERE contractor_user_id = ?'
    );
    $summaryStmt->execute([$userId]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    localJson([
        'success' => true,
        'data' => [
            'items' => $items,
            'summary' => [
                'total' => (int)($summary['total'] ?? 0),
                'open' => (int)($summary['open_count'] ?? 0),
                'active' => (int)($summary['active_count'] ?? 0),
                'completed' => (int)($summary['completed_count'] ?? 0),
            ],
        ],
    ]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    localJson(['success' => false, 'message' => 'Unable to load requirements'], 500);
}
