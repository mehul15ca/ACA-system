<?php
use ACA\Api\Core\Auth;
use ACA\Api\Core\Response;

$router->get('/students', function () {
    Auth::requirePermission('manage_students');

    // existing logic here
    Response::ok([
        'items' => []
    ], 'Students list');
});
