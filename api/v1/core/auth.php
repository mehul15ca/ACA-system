<?php
declare(strict_types=1);

namespace ACA\Api\Core;

use ACA\Api\Middleware\JwtMiddleware;

final class Auth
{
  public static function user(): array
  {
    return JwtMiddleware::requireAuth();
  }
}
