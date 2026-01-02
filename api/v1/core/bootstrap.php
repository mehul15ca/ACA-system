<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// CORS (tighten later using ACA_CORS_ORIGINS)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/*
|--------------------------------------------------------------------------
| Autoload FIRST (so all classes resolve consistently)
|--------------------------------------------------------------------------
*/
spl_autoload_register(function ($class) {
    $prefix = 'ACA\\Api\\';
    $baseDir = __DIR__ . '/../';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/*
|--------------------------------------------------------------------------
| Load Env ONCE (single source of truth)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/env.php';
\ACA\Api\Core\Env::load(__DIR__ . '/../.env');

/*
|--------------------------------------------------------------------------
| Core helpers
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/request.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/router.php';
