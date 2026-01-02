<?php

namespace ACA\Api\Controllers;

use ACA\Api\Core\Auth\RefreshTokenService;
use ACA\Api\Core\Auth\AuthMiddleware;
use ACA\Api\Core\Response;

class AuthLogoutController
{
    public function logout(): void
    {
        $user = AuthMiddleware::requireUser();

        RefreshTokenService::revokeAllForUser((int) $user['id']);

        Response::success([
            'message' => 'Logged out successfully'
        ]);
    }
}
