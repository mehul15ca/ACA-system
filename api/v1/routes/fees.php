<?php
use ACA\Api\Controllers\FeeController;

/** @var ACA\Api\Core\Router $router */
$router->get('/fees', [FeeController::class, 'index']);
