<?php
declare(strict_types=1);

namespace ACA\Api\Core\Middleware;

use ACA\Api\Core\Response;

final class RbacMiddleware
{
    public static function require(array $required, array $userPerms): void
    {
        foreach ($required as $perm) {
            if (!in_array($perm, $userPerms, true)) {
                Response::error(
                    'Forbidden',
                    403,
                    'INSUFFICIENT_PERMISSION',
                    ['required' => $required]
                );
            }
        }
    }
}
