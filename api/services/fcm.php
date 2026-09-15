<?php
declare(strict_types=1);

/**
 * Firebase Cloud Messaging HTTP v1 sender.
 *
 * The service-account JSON must be provisioned on the server and MUST NOT be
 * committed to Git. Default location: api/config/firebase-service-account.json
 */

function fcmServiceAccountPath(): string {
    $configured = getenv('MWH_FIREBASE_SERVICE_ACCOUNT');
    if (is_string($configured) && trim($configured) !== '') return trim($configured);
    return __DIR__ . '/../config/firebase-service-account.json';
}

function fcmLoadServiceAccount(): array {
    $path = fcmServiceAccountPath();
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Firebase service account is not configured on the server.');
    }
    $json = file_get_contents($path);
    $config = json_decode($json ?: '', true);
    if (!is_array($config) || empty($config['project_id']) || empty($config['client_email']) || empty($config['private_key']) || empty($config['token_uri'])) {
        throw new RuntimeException('Firebase service account configuration is invalid.');
    }
    return $config;
}

function fcmBase64Url(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function fcmAccessToken(array $serviceAccount): string {
    $now = time();
    $header = fcmBase64Url(json_encode(['alg'=>'RS256','typ'=>'JWT'], JSON_UNESCAPED_SLASHES));
    $claims = fcmBase64Url(json_encode([
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $serviceAccount['token_uri'],
        'iat' => $now,
        'exp' => $now + 3600,
    ], JSON_UNESCAPED_SLASHES));
    $unsigned = $header . '.' . $claims;
    $signature = '';
    $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
    if ($privateKey === false || !openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Could not sign Firebase access-token request.');
    }

    $jwt = $unsigned . '.' . fcmBase64Url($signature);
    $ch = curl_init($serviceAccount['token_uri']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Firebase authentication failed.' . ($error !== '' ? ' ' . $error : ''));
    }
    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded) || empty($decoded['access_token'])) {
        throw new RuntimeException('Firebase authentication returned no access token.');
    }
    return (string)$decoded['access_token'];
}

/**
 * Sends one notification to each registered FCM device for the supplied users.
 * Returns sent/failed/removed-token counts.
 */
function sendFcmToUsers(PDO $pdo, array $userIds, string $title, string $message, string $type, ?array $data = null): array {
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
    if (!$userIds) return ['sent'=>0,'failed'=>0,'removed'=>0,'devices'=>0];

    $serviceAccount = fcmLoadServiceAccount();
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare("SELECT id,user_id,fcm_token FROM notification_devices WHERE user_id IN ($placeholders)");
    $stmt->execute($userIds);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$devices) return ['sent'=>0,'failed'=>0,'removed'=>0,'devices'=>0];

    $accessToken = fcmAccessToken($serviceAccount);
    $endpoint = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode((string)$serviceAccount['project_id']) . '/messages:send';
    $sent = 0; $failed = 0; $removed = 0;

    foreach ($devices as $device) {
        $payloadData = [
            'type' => $type,
            'notification_id' => isset($data['notification_id']) ? (string)$data['notification_id'] : '',
        ];
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($key === 'notification_id') continue;
                $payloadData[(string)$key] = is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        $payload = [
            'message' => [
                'token' => (string)$device['fcm_token'],
                'notification' => ['title'=>$title,'body'=>$message],
                'data' => $payloadData,
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => ['channel_id'=>'mwh_general'],
                ],
            ],
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json; charset=utf-8',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $status >= 200 && $status < 300) {
            $sent++;
            continue;
        }

        $failed++;
        $decoded = json_decode((string)$response, true);
        $errorStatus = is_array($decoded) ? (string)($decoded['error']['status'] ?? '') : '';
        $errorCode = is_array($decoded) ? (string)($decoded['error']['details'][0]['errorCode'] ?? '') : '';
        if ($status === 404 || $errorStatus === 'NOT_FOUND' || $errorCode === 'UNREGISTERED') {
            $delete = $pdo->prepare('DELETE FROM notification_devices WHERE id=?');
            $delete->execute([(int)$device['id']]);
            $removed++;
        }
    }

    return ['sent'=>$sent,'failed'=>$failed,'removed'=>$removed,'devices'=>count($devices)];
}
