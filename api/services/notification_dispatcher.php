<?php
declare(strict_types=1);

require_once __DIR__ . '/fcm.php';

/**
 * Creates one persistent vacancy notification per verified candidate and sends
 * the corresponding FCM push to that candidate's registered devices.
 *
 * The deterministic vacancy/user check plus a MySQL named lock makes repeated
 * publish requests idempotent and prevents duplicate notifications.
 */
function notifyVerifiedCandidatesForVacancy(PDO $pdo, int $vacancyId, int $adminUserId): array
{
    if ($vacancyId <= 0) {
        return ['candidates' => 0, 'created' => 0, 'push_sent' => 0, 'push_failed' => 0, 'devices' => 0];
    }

    $vacancyStmt = $pdo->prepare(
        'SELECT v.id, v.title, h.name AS hotel_name
         FROM vacancies v
         INNER JOIN hotels h ON h.id = v.hotel_id
         WHERE v.id = ? AND v.deleted_at IS NULL
         LIMIT 1'
    );
    $vacancyStmt->execute([$vacancyId]);
    $vacancy = $vacancyStmt->fetch(PDO::FETCH_ASSOC);
    if (!$vacancy) {
        throw new RuntimeException('Vacancy not found for notification dispatch.');
    }

    $lockName = 'mwh-vacancy-notify-' . $vacancyId;
    $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lockStmt->execute([$lockName]);
    if ((int)$lockStmt->fetchColumn() !== 1) {
        throw new RuntimeException('Could not acquire vacancy notification lock.');
    }

    try {
        $candidatesStmt = $pdo->query(
            "SELECT id
             FROM users
             WHERE account_status='verified'
               AND deleted_at IS NULL
             ORDER BY id ASC"
        );
        $candidates = $candidatesStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!$candidates) {
            return ['candidates' => 0, 'created' => 0, 'push_sent' => 0, 'push_failed' => 0, 'devices' => 0];
        }

        $message = 'New hospitality job available: ' . $vacancy['title'] . ' at ' . $vacancy['hotel_name'] . '.';
        $exists = $pdo->prepare(
            "SELECT id FROM notifications
             WHERE user_id = ?
               AND type = 'vacancy'
               AND JSON_UNQUOTE(JSON_EXTRACT(data_json, '$.vacancy_id')) = ?
             LIMIT 1"
        );
        $insert = $pdo->prepare(
            'INSERT INTO notifications (user_id, admin_user_id, type, title, message, data_json)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $newUserIds = [];
        $dataJson = json_encode(
            ['vacancy_id' => (int)$vacancy['id']],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $pdo->beginTransaction();
        foreach ($candidates as $candidateId) {
            $candidateId = (int)$candidateId;
            $exists->execute([$candidateId, (string)$vacancy['id']]);
            if ($exists->fetchColumn()) {
                continue;
            }

            $insert->execute([
                $candidateId,
                $adminUserId,
                'vacancy',
                'New Job Available',
                $message,
                $dataJson,
            ]);
            if ($insert->rowCount() === 1) {
                $newUserIds[] = $candidateId;
            }
        }
        $pdo->commit();

        if (!$newUserIds) {
            return [
                'candidates' => count($candidates),
                'created' => 0,
                'push_sent' => 0,
                'push_failed' => 0,
                'devices' => 0,
            ];
        }

        $push = ['sent' => 0, 'failed' => 0, 'removed' => 0, 'devices' => 0];
        try {
            $push = sendFcmToUsers(
                $pdo,
                $newUserIds,
                'New Job Available',
                $message,
                'vacancy',
                ['vacancy_id' => (int)$vacancy['id']]
            );
        } catch (Throwable $pushError) {
            error_log('MWH vacancy push failed: ' . $pushError->getMessage());
        }

        return [
            'candidates' => count($candidates),
            'created' => count($newUserIds),
            'push_sent' => (int)($push['sent'] ?? 0),
            'push_failed' => (int)($push['failed'] ?? 0),
            'devices' => (int)($push['devices'] ?? 0),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        } catch (Throwable $releaseError) {
            error_log('MWH vacancy notification lock release failed: ' . $releaseError->getMessage());
        }
    }
}
