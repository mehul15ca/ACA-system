<?php
declare(strict_types=1);

header('X-API-Version: v1');
header('Content-Type: application/json; charset=utf-8');

/* ---------------------------------------------
 | Request metadata
 --------------------------------------------- */
$GLOBALS['ACA_REQUEST_ID'] = bin2hex(random_bytes(12));

/* ---------------------------------------------
 | CORS (ENV driven)
 --------------------------------------------- */
$allowed = array_filter(array_map('trim',
    explode(',', $_ENV['ACA_CORS_ORIGINS'] ?? '')
));

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin && in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Vary: Origin");
}

header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ---------------------------------------------
 | Autoload
 --------------------------------------------- */
spl_autoload_register(function ($class) {
    $prefix = 'ACA\\Api\\';
    $baseDir = __DIR__ . '/../';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;

    $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
});
