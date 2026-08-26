<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = requireUser($pdo);
$userId = (int) $user['user_id'];

try {
    $today = new DateTimeImmutable('today');
    $monthStart = $today->modify('first day of this month')->format('Y-m-d');
    $nextMonthStart = $today->modify('first day of next month')->format('Y-m-d');
    $lastMonthStart = $today->modify('first day of last month')->format('Y-m-d');
    $fourWeeksStart = $today->modify('-27 days')->format('Y-m-d');
    $currentYear = (int) $today->format('Y');

    $summaryStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(p.amount), 0) AS total_balance,
            COALESCE(SUM(CASE WHEN p.payment_date >= ? AND p.payment_date < ? THEN p.amount ELSE 0 END), 0) AS this_month,
            COALESCE(SUM(CASE WHEN p.payment_date >= ? AND p.payment_date < ? THEN p.amount ELSE 0 END), 0) AS last_month
         FROM payments p
         WHERE p.user_id = ?"
    );
    $summaryStmt->execute([$monthStart, $nextMonthStart, $lastMonthStart, $monthStart, $userId]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $pendingStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN j.payout_amount > COALESCE(x.paid_amount, 0)
                THEN j.payout_amount - COALESCE(x.paid_amount, 0) ELSE 0 END), 0) AS pending_amount,
            COALESCE(SUM(CASE WHEN j.payout_amount > COALESCE(x.paid_amount, 0) THEN 1 ELSE 0 END), 0) AS pending_jobs
         FROM jobs j
         LEFT JOIN (
            SELECT job_id, SUM(amount) AS paid_amount
            FROM payments
            WHERE user_id = ? AND job_id IS NOT NULL
            GROUP BY job_id
         ) x ON x.job_id = j.id
         WHERE j.user_id = ? AND j.status = 'completed'"
    );
    $pendingStmt->execute([$userId, $userId]);
    $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $completedStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM jobs
         WHERE user_id = ?
           AND status = 'completed'
           AND completed_at IS NOT NULL
           AND YEAR(completed_at) = ?"
    );
    $completedStmt->execute([$userId, $currentYear]);

    $transactionsStmt = $pdo->prepare(
        "SELECT
            p.id,
            p.job_id,
            p.amount,
            p.payment_date,
            p.payment_method,
            p.transaction_reference,
            p.notes,
            v.title AS job_title,
            h.name AS hotel_name,
            jr.name AS job_role_name,
            j.status AS job_status
         FROM payments p
         LEFT JOIN jobs j ON j.id = p.job_id
         LEFT JOIN vacancies v ON v.id = j.vacancy_id
         LEFT JOIN hotels h ON h.id = j.hotel_id
         LEFT JOIN job_roles jr ON jr.id = v.job_role_id
         WHERE p.user_id = ?
         ORDER BY p.payment_date DESC, p.id DESC
         LIMIT 50"
    );
    $transactionsStmt->execute([$userId]);
    $transactionRows = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);

    $weeklyStmt = $pdo->prepare(
        "SELECT p.payment_date, COALESCE(SUM(p.amount), 0) AS amount
         FROM payments p
         WHERE p.user_id = ? AND p.payment_date >= ?
         GROUP BY p.payment_date
         ORDER BY p.payment_date ASC"
    );
    $weeklyStmt->execute([$userId, $fourWeeksStart]);
    $dailyRows = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

    $weekly = [0.0, 0.0, 0.0, 0.0];
    $start = new DateTimeImmutable($fourWeeksStart);
    foreach ($dailyRows as $row) {
        $date = new DateTimeImmutable($row['payment_date']);
        $days = (int) $start->diff($date)->format('%r%a');
        $index = (int) floor($days / 7);
        if ($index >= 0 && $index < 4) {
            $weekly[$index] += (float) $row['amount'];
        }
    }

    $lastMonth = (float) ($summary['last_month'] ?? 0);
    $thisMonth = (float) ($summary['this_month'] ?? 0);
    $changePercent = $lastMonth > 0 ? (($thisMonth - $lastMonth) / $lastMonth) * 100 : null;

    $transactions = [];
    foreach ($transactionRows as $row) {
        $transactions[] = [
            'id' => (int) $row['id'],
            'job_id' => $row['job_id'] !== null ? (int) $row['job_id'] : null,
            'title' => $row['hotel_name'] ?: ($row['job_title'] ?: 'Payment'),
            'job_title' => $row['job_title'],
            'hotel_name' => $row['hotel_name'],
            'job_role' => $row['job_role_name'],
            'date' => $row['payment_date'],
            'amount' => (float) $row['amount'],
            'status' => 'paid',
            'payment_method' => $row['payment_method'],
            'transaction_reference' => $row['transaction_reference'],
            'notes' => $row['notes'],
        ];
    }

    respond([
        'success' => true,
        'message' => 'Earnings fetched successfully',
        'data' => [
            'summary' => [
                'total_balance' => (float) ($summary['total_balance'] ?? 0),
                'this_month' => $thisMonth,
                'last_month' => $lastMonth,
                'change_percent' => $changePercent !== null ? round($changePercent, 2) : null,
                'pending_amount' => (float) ($pending['pending_amount'] ?? 0),
                'pending_jobs' => (int) ($pending['pending_jobs'] ?? 0),
                'shifts_completed_this_year' => (int) $completedStmt->fetchColumn(),
            ],
            'weekly' => $weekly,
            'transactions' => $transactions,
        ],
    ]);
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'Could not fetch earnings'], 500);
}
