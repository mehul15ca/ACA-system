<?php
use ACA\Api\Controllers\CoachController;

/** @var ACA\Api\Core\Router $router */
$router->get('/coaches', [CoachController::class, 'index']);
