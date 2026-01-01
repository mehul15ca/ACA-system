<?php
declare(strict_types=1);

namespace ACA\Api\Middleware;

use ACA\Api\Core\Request;
use ACA\Api\Core\Response;
use ACA\Api\Core\JWT;

final class JwtMiddleware
{
  public static function requireAuth(): array
  {
    $token = Request::bearerToken();
    if (!$token) {
      Response::error('Missing token', 401, 'AUTH_TOKEN_MISSING');
    }

    $payload = JWT::decode($token);
    if (!$payload) {
      Response::error('Invalid or expired token', 401, 'AUTH_TOKEN_INVALID');
    }

    return $payload;
  }
}
