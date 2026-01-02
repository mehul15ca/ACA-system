<?php
declare(strict_types=1);

use ACA\Api\Core\{Request, Response, JWT, DB};

/*
|--------------------------------------------------------------------------
| Login (Phase K – Step 1: Access token only)
|--------------------------------------------------------------------------
*/
$router->post('/auth/login', function () {
    $data = Request::json();

    $email    = strtolower(trim($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if ($email === '' || $password === '') {
        Response::error(
            'Email and password required',
            400,
            'LOGIN_MISSING_FIELDS'
        );
    }

    $user = DB::selectOne(
        "SELECT id, username, password_hash, role, status
         FROM users
         WHERE username = ?
           AND status = 'active'
         LIMIT 1",
        [$email]
    );

    if (!$user || !password_verify($password, $user['password_hash'])) {
        Response::error(
            'Invalid credentials',
            401,
            'LOGIN_INVALID'
        );
    }

   $token = JWT::issueAccessToken([
    'user_id' => (int)$user['id'],
    'role'    => $user['role'],
]);


    Response::success([
        'token' => $token,
        'user' => [
            'id'    => (int)$user['id'],
            'login' => $user['username'],
            'role'  => $user['role'],
        ],
    ], 'Login successful');
});
