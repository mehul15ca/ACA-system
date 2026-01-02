<?php
declare(strict_types=1);
header('X-API-Version: v1');
header('X-API-Status: stable');
header('Content-Type: application/json; charset=utf-8');

// Request timing + request id (for logging)
$GLOBALS['ACA_START_MS'] = (int) floor(microtime(true) * 1000);
$GLOBALS['ACA_REQUEST_ID'] = bin2hex(random_bytes(12)); // 24 chars

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
| Autoload FIRST
|--------------------------------------------------------------------------
*/
spl_autoload_register(function ($class) {
    $prefix = 'ACA\\Api\\';
    $baseDir = __DIR__ . '/../';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;

    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) require $file;
});

/*
|--------------------------------------------------------------------------
| Load env once
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/env.php';
\ACA\Api\Core\Env::load(__DIR__ . '/../.env');

/*
|--------------------------------------------------------------------------
| Core helpers
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/Router.php';

//require_once __DIR__ . '/RateLimiter.php';
//require_once __DIR__ . '/ApiLogger.php';

