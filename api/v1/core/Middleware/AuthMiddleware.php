<?php
declare(strict_types=1);

namespace ACA\Api\Core\Middleware;

use ACA\Api\Core\JWT;
use ACA\Api\Core\Response;

final class AuthMiddleware
{
    public static function handle(): array
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            Response::error('Unauthorized', 401, 'AUTH_REQUIRED');
        }

        $payload = JWT::decode($m[1]);

        if (!$payload) {
            Response::error('Invalid token', 401, 'AUTH_INVALID');
        }

        return $payload;
    }
}
