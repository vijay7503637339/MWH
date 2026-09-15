<?php

declare(strict_types=1);

session_start();

function requireAdmin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function currentAdmin(): array
{
    return [
        'id' => (int)($_SESSION['admin_id'] ?? 0),
        'name' => (string)($_SESSION['admin_name'] ?? ''),
        'email' => (string)($_SESSION['admin_email'] ?? ''),
        'role' => (string)($_SESSION['admin_role'] ?? ''),
    ];
}

/**
 * Observe successful vacancy creation/publication after the main vacancies.php
 * request has completed, then create inbox notifications and send FCM.
 */
function registerAutomaticVacancyNotification(): void
{
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script !== 'vacancies.php' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    register_shutdown_function(static function (): void {
        try {
            $action = (string)($_POST['action'] ?? '');
            $vacancyId = (int)($_POST['vacancy_id'] ?? 0);
            $adminId = (int)($_SESSION['admin_id'] ?? 0);

            if ($adminId <= 0) {
                return;
            }

            $shouldNotify = false;
            if ($action === 'publish' && $vacancyId > 0) {
                $shouldNotify = true;
            } elseif ($action === 'save' && $vacancyId === 0 && (string)($_POST['status'] ?? 'draft') === 'published') {
                $shouldNotify = true;
            }

            if (!$shouldNotify) {
                return;
            }

            require_once __DIR__ . '/../../api/config/database.php';
            require_once __DIR__ . '/../../api/services/fcm.php';

            if ($vacancyId > 0) {
                $vacancyStmt = $pdo->prepare(
                    "SELECT v.id,v.title,v.description,v.openings_count,v.payout_amount,v.payout_period,
                            h.name AS hotel_name,h.city AS hotel_city
                     FROM vacancies v
                     INNER JOIN hotels h ON h.id=v.hotel_id
                     WHERE v.id=? AND v.status='published' AND v.deleted_at IS NULL
                       AND v.published_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                     LIMIT 1"
                );
                $vacancyStmt->execute([$vacancyId]);
            } else {
                $vacancyStmt = $pdo->prepare(
                    "SELECT v.id,v.title,v.description,v.openings_count,v.payout_amount,v.payout_period,
                            h.name AS hotel_name,h.city AS hotel_city
                     FROM vacancies v
                     INNER JOIN hotels h ON h.id=v.hotel_id
                     WHERE v.created_by_admin_id=? AND v.title=? AND v.status='published' AND v.deleted_at IS NULL
                       AND v.published_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                     ORDER BY v.id DESC
                     LIMIT 1"
                );
                $vacancyStmt->execute([$adminId, trim((string)($_POST['title'] ?? ''))]);
            }

            $vacancy = $vacancyStmt->fetch(PDO::FETCH_ASSOC);
            if (!$vacancy) {
                return;
            }
            $vacancyId = (int)$vacancy['id'];

            $duplicateStmt = $pdo->prepare(
                "SELECT id FROM notifications
                 WHERE type='job' AND title='New Vacancy Available'
                   AND data_json LIKE ?
                 LIMIT 1"
            );
            $duplicateStmt->execute(['%"vacancy_id":' . $vacancyId . '%']);
            if ($duplicateStmt->fetchColumn()) {
                return;
            }

            $users = $pdo->query(
                "SELECT id FROM users
                 WHERE deleted_at IS NULL AND account_status='verified'
                 ORDER BY id"
            )->fetchAll(PDO::FETCH_COLUMN);
            if (!$users) {
                return;
            }

            $title = 'New Vacancy Available';
            $message = $vacancy['title'] . ' is now available at ' . $vacancy['hotel_name'];
            if (!empty($vacancy['hotel_city'])) {
                $message .= ' in ' . $vacancy['hotel_city'];
            }
            $message .= '.';

            $notificationStmt = $pdo->prepare(
                'INSERT INTO notifications (user_id, admin_user_id, type, title, message, data_json) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $data = json_encode([
                'vacancy_id' => $vacancyId,
                'hotel_name' => (string)$vacancy['hotel_name'],
                'hotel_city' => (string)($vacancy['hotel_city'] ?? ''),
                'openings_count' => (int)$vacancy['openings_count'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($users as $userId) {
                $notificationStmt->execute([
                    (int)$userId,
                    $adminId,
                    'job',
                    $title,
                    $message,
                    $data,
                ]);
            }

            try {
                sendFcmToUsers(
                    $pdo,
                    array_map('intval', $users),
                    $title,
                    $message,
                    'job',
                    [
                        'vacancy_id' => $vacancyId,
                        'hotel_name' => (string)$vacancy['hotel_name'],
                        'hotel_city' => (string)($vacancy['hotel_city'] ?? ''),
                    ]
                );
            } catch (Throwable $pushError) {
                error_log('MWH vacancy push failed: ' . $pushError->getMessage());
            }
        } catch (Throwable $error) {
            error_log('MWH automatic vacancy notification failed: ' . $error->getMessage());
        }
    });
}

registerAutomaticVacancyNotification();
