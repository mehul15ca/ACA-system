<?php
use ACA\Api\Controllers\ProfileController;

$router->get('/profile/me', [ProfileController::class, 'me']);
