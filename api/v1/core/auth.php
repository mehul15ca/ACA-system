<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Auth
{
    public static function user(): array
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            Response::error(
                'Authorization token missing',
                401,
                'AUTH_TOKEN_MISSING'
            );
        }

        $payload = JWT::decode($m[1]);

        if (!$payload || !isset($payload['user_id'])) {
            Response::error(
                'Invalid token payload',
                401,
                'AUTH_INVALID_PAYLOAD'
            );
        }

        return $payload;
    }
}
