<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Auth
{
    public static function user(): array
{
    $authHeader = null;

    // 1) Standard PHP env
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    // 2) Apache-specific
    elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        }
    }

    if (!$authHeader || !preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        Response::error('Missing token', 401, 'AUTH_TOKEN_MISSING');
    }

    $payload = JWT::decode($matches[1]);
    if (!$payload) {
        Response::error('Invalid or expired token', 401, 'AUTH_TOKEN_INVALID');
    }

    return $payload;
}

}
