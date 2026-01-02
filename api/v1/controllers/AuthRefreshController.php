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

        $hash = hash('sha256', $refreshToken);

        $row = DB::selectOne(
            "SELECT * FROM auth_refresh_tokens
             WHERE token_hash = ?
             LIMIT 1",
            [$hash]
        );

        // Token never existed → invalid
        if (!$row) {
            Response::error('Invalid refresh token', 401, 'REFRESH_INVALID');
        }

        // 🔥 REUSE DETECTED
        if ($row['revoked_at'] !== null) {
            // Revoke entire family
            DB::execute(
                "UPDATE auth_refresh_tokens
                 SET revoked_at = NOW()
                 WHERE family_id = ?
                   AND revoked_at IS NULL",
                [$row['family_id']]
            );

            Response::error(
                'Refresh token reuse detected',
                401,
                'REFRESH_REUSE_DETECTED'
            );
        }

        // Expired
        if (strtotime($row['expires_at']) <= time()) {
            Response::error('Refresh token expired', 401, 'REFRESH_EXPIRED');
        }

        // Rotate current token
        DB::execute(
            "UPDATE auth_refresh_tokens
             SET revoked_at = NOW()
             WHERE id = ?",
            [(int)$row['id']]
        );

        $newRefresh = bin2hex(random_bytes(32));
        $newHash = hash('sha256', $newRefresh);

        DB::execute(
            "INSERT INTO auth_refresh_tokens
             (user_id, family_id, token_hash, issued_at, expires_at, ip_address, user_agent)
             VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ?)",
            [
                (int)$row['user_id'],
                $row['family_id'],
                $newHash,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]
        );

        $accessToken = JWT::issueAccessToken([
            'user_id' => (int)$row['user_id']
        ]);

        Response::success([
            'access_token'  => $accessToken,
            'refresh_token' => $newRefresh
        ], 'Token refreshed');
    }
}
