<?php
declare(strict_types=1);

// Entry point: only bootstrap + routes
require __DIR__ . '/core/bootstrap.php';

use ACA\Api\Core\Router;

$router = new Router();

// Register route files
require __DIR__ . '/routes/health.php';
require __DIR__ . '/routes/auth.php';
require __DIR__ . '/routes/students.php';
require __DIR__ . '/routes/coaches.php';
require __DIR__ . '/routes/batches.php';
require __DIR__ . '/routes/attendance.php';
require __DIR__ . '/routes/fees.php';
require __DIR__ . '/routes/salaries.php';
require __DIR__ . '/routes/expenses.php';
require __DIR__ . '/routes/announcements.php';
require __DIR__ . '/routes/notifications.php';
require __DIR__ . '/routes/profile.php';

// Dispatch request
$router->dispatch();
