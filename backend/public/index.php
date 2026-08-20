<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Development-friendly CORS foundation. Restrict Access-Control-Allow-Origin
// to the real admin/mobile domains before production launch.
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function jsonResponse(bool $success, array $data = [], string $message = '', int $status = 200): never
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Adjust this prefix when deploying the API under a different public path.
$prefix = '/api/v1';

if ($method === 'GET' && ($path === $prefix || $path === $prefix . '/')) {
    jsonResponse(true, ['api' => 'MWH API', 'version' => '1.0.0'], 'API is running.');
}

if ($method === 'GET' && $path === $prefix . '/health') {
    try {
        // Server deployment should provide the real config file outside the
        // repository. For local structure testing, report API health only if
        // the config file is available.
        $databaseFile = dirname(__DIR__) . '/config/database.php';

        if (!is_file($databaseFile)) {
            jsonResponse(false, ['api' => 'MWH API', 'database' => 'not_configured'], 'Database configuration is not installed on the server.', 503);
        }

        require $databaseFile;
        $pdo->query('SELECT 1');

        jsonResponse(true, [
            'api' => 'MWH API',
            'version' => '1.0.0',
            'database' => 'connected',
        ], 'Health check passed.');
    } catch (Throwable $e) {
        error_log('MWH health check failed: ' . $e->getMessage());
        jsonResponse(false, ['api' => 'MWH API', 'database' => 'unavailable'], 'Health check failed.', 503);
    }
}

jsonResponse(false, [], 'Endpoint not found.', 404);
