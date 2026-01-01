<?php
use ACA\Api\Controllers\NotificationController;

/** @var ACA\Api\Core\Router $router */
$router->get('/notifications', [NotificationController::class, 'index']);
