<?php
declare(strict_types=1);

// Minimal bootstrap for API layer (isolated from existing web app)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // tighten later for production
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Simple PSR-4-ish autoload for /api/v1 namespace
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

// Core helpers
require __DIR__ . '/response.php';
require __DIR__ . '/request.php';
require __DIR__ . '/db.php';
require __DIR__ . '/jwt.php';
require __DIR__ . '/router.php';
