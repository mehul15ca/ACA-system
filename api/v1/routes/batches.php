<?php
use ACA\Api\Controllers\BatchController;

/** @var ACA\Api\Core\Router $router */
$router->get('/batches', [BatchController::class, 'index']);
