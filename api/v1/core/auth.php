<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Auth
{
    public static function user(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            Response::error('Missing token', 401, 'AUTH_TOKEN_MISSING');
        }

        $payload = JWT::decode($m[1]);

        if (!$payload) {
            Response::error('Invalid or expired token', 401, 'AUTH_TOKEN_INVALID');
        }

        return $payload;
    }

    public static function requirePermission(string $permission): array
    {
        $user = self::user();

        if (
            $user['role'] !== 'superadmin' &&
            !in_array($permission, $user['permissions'] ?? [], true)
        ) {
            Response::error('Permission denied', 403, 'PERMISSION_DENIED');
        }

        return $user;
    }
}
