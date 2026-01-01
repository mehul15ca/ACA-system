<?php
declare(strict_types=1);

namespace ACA\Api\Middleware;

use ACA\Api\Core\Response;

final class PermissionMiddleware
{
  public static function require(string $permission, array $payload): void
  {
    $perms = $payload['permissions'] ?? [];
    if (!in_array($permission, $perms, true)) {
      Response::error('Forbidden', 403, 'PERMISSION_DENIED', [
        'required' => $permission
      ]);
    }
  }
}
