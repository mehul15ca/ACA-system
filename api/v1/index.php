<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Entry Point – ACA API
|--------------------------------------------------------------------------
| Correct bootstrap order (NO autoload guessing)
|--------------------------------------------------------------------------
*/

// Core primitives (order matters)
require_once __DIR__ . '/core/Env.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/DB.php';
require_once __DIR__ . '/core/Router.php';

// Optional shared bootstrap (middleware, headers, etc.)
require_once __DIR__ . '/core/bootstrap.php';

use ACA\Api\Core\Router;

$router = new Router();

/*
|--------------------------------------------------------------------------
| Routes
|--------------------------------------------------------------------------
*/
require __DIR__ . '/routes/health.php';
require __DIR__ . '/routes/auth.php';
require __DIR__ . '/routes/profile.php';

require __DIR__ . '/routes/students.php';
require __DIR__ . '/routes/coaches.php';
require __DIR__ . '/routes/batches.php';
require __DIR__ . '/routes/attendance.php';
require __DIR__ . '/routes/fees.php';
require __DIR__ . '/routes/salaries.php';
require __DIR__ . '/routes/expenses.php';
require __DIR__ . '/routes/announcements.php';
require __DIR__ . '/routes/notifications.php';

/*
|--------------------------------------------------------------------------
| Dispatch
|--------------------------------------------------------------------------
*/
$router->dispatch();
