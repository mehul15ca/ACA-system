<?php
declare(strict_types=1);

namespace ACA\Api\Controllers;

use ACA\Api\Core\Request;
use ACA\Api\Core\Response;
use ACA\Api\Core\DB;

final class AuthLogoutController
{
    public static function logout(): void
    {
        $data = Request::json();
        $refreshToken = (string)($data['refresh_token'] ?? '');

        if ($refreshToken === '') {
            Response::error('Refresh token required', 422, 'LOGOUT_MISSING_REFRESH');
        }

        $hash = hash('sha256', $refreshToken);

        // Revoke only this token (single-device logout)
        DB::execute(
            "UPDATE auth_refresh_tokens
             SET revoked_at = NOW()
             WHERE token_hash = ?
               AND revoked_at IS NULL",
            [$hash]
        );

        Response::success(null, 'Logged out');
    }

// inside AuthLogoutController
public static function logoutAll(): void
{
    $data = Request::json();
    $userId = (int)($data['user_id'] ?? 0);

    if ($userId <= 0) {
        Response::error('User ID required', 422, 'LOGOUT_ALL_MISSING_USER');
    }

    DB::execute(
        "UPDATE auth_refresh_tokens
         SET revoked_at = NOW()
         WHERE user_id = ?
           AND revoked_at IS NULL",
        [$userId]
    );

    Response::success(null, 'Logged out from all devices');
}


}
