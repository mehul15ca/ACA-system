<?php
declare(strict_types=1);

// --------------------------------------------------
// Minimal bootstrap for API layer (isolated)
// --------------------------------------------------

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

// --------------------------------------------------
// Simple .env loader (API-local, no dependency)
// --------------------------------------------------
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
  foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;

    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    $k = trim($k);
    $v = trim($v);
    if ($k !== '' && !isset($_ENV[$k])) {
      $_ENV[$k] = $v;
    }
  }
}

// --------------------------------------------------
// CORS (restricted, env-based)
// --------------------------------------------------
$allowed = $_ENV['ACA_CORS_ORIGINS'] ?? 'http://localhost';
$allowedOrigins = array_map('trim', explode(',', $allowed));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin && in_array($origin, $allowedOrigins, true)) {
  header("Access-Control-Allow-Origin: $origin");
} else {
  // Fallback for non-browser clients
  header("Access-Control-Allow-Origin: " . $allowedOrigins[0]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// --------------------------------------------------
// PSR-4–ish autoloader for /api/v1
// --------------------------------------------------
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

// --------------------------------------------------
// Core helpers
// --------------------------------------------------
require __DIR__ . '/env.php';
require __DIR__ . '/response.php';
require __DIR__ . '/request.php';
require __DIR__ . '/db.php';
require __DIR__ . '/jwt.php';
require __DIR__ . '/router.php';
