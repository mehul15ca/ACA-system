<?php
use ACA\Api\Controllers\AnnouncementController;

/** @var ACA\Api\Core\Router $router */
$router->get('/announcements', [AnnouncementController::class, 'index']);
