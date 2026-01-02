<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Entry Point – ACA API (v1)
|--------------------------------------------------------------------------
| Bootstrap handles:
| - Autoload
| - Env
| - Headers
| - Core classes
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/core/bootstrap.php';

use ACA\Api\Core\Router;

$GLOBALS['router'] = new Router();
$router = $GLOBALS['router'];


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
