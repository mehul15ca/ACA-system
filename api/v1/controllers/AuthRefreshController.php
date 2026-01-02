<?php
declare(strict_types=1);

namespace ACA\Api\Controllers;

use ACA\Api\Core\Request;
use ACA\Api\Core\Response;
use ACA\Api\Core\JWT;
use ACA\Api\Core\DB;

final class AuthRefreshController
{
    public static function refresh(): void
    {
        $data = Request::json();
        $refreshToken = (string)($data['refresh_token'] ?? '');

        if ($refreshToken === '') {
            Response::error('Refresh token required', 422, 'REFRESH_MISSING');
        }

        $tokenHash = hash('sha256', $refreshToken);

        $row = DB::selectOne(
            "SELECT * FROM auth_refresh_tokens
             WHERE token_hash = ?
               AND revoked_at IS NULL
               AND expires_at > NOW()
             LIMIT 1",
            [$tokenHash]
        );

        if (!$row) {
            Response::error('Invalid refresh token', 401, 'REFRESH_INVALID');
        }

        // Rotate: revoke old token
        DB::execute(
            "UPDATE auth_refresh_tokens
             SET revoked_at = NOW()
             WHERE id = ?",
            [(int)$row['id']]
        );

        // Issue new refresh token
        $newRefresh = bin2hex(random_bytes(32));
        $newHash    = hash('sha256', $newRefresh);

        DB::execute(
            "INSERT INTO auth_refresh_tokens
             (user_id, token_hash, issued_at, expires_at, ip_address, user_agent)
             VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ?)",
            [
                (int)$row['user_id'],
                $newHash,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]
        );

        // Issue new access token
        $accessToken = JWT::issueAccessToken([
            'user_id' => (int)$row['user_id']
        ]);

        Response::success([
            'access_token'  => $accessToken,
            'refresh_token' => $newRefresh
        ], 'Token refreshed');
    }
}
