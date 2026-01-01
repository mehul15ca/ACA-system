<?php
declare(strict_types=1);

use ACA\Api\Controllers\StudentController;

/** @var ACA\Api\Core\Router $router */
$router->get('/students', [StudentController::class, 'index']);
