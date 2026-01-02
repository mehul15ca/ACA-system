<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class AuthMiddleware
{
    /**
     * Require valid JWT.
     * Optionally require permissions.
     *
     * @param string[] $permissions
     */
    public static function require(array $permissions = []): void
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            Response::error('Unauthorized', 401, 'AUTH_MISSING_TOKEN');
        }

        try {
            $payload = JWT::decode($m[1]);
        } catch (\Throwable $e) {
            Response::error('Invalid token', 401, 'AUTH_INVALID_TOKEN');
        }

        if (!$payload || empty($payload['user_id'])) {
            Response::error('Invalid token payload', 401, 'AUTH_INVALID_PAYLOAD');
        }

        // Permission check (if required)
        if ($permissions) {
            $userPerms = $payload['permissions'] ?? [];
            foreach ($permissions as $perm) {
                if (!in_array($perm, $userPerms, true)) {
                    Response::error(
                        'Forbidden',
                        403,
                        'AUTH_FORBIDDEN',
                        ['missing_permission' => $perm]
                    );
                }
            }
        }

        // Store user globally for controllers
        $GLOBALS['AUTH_USER'] = $payload;
    }

    public static function user(): array
    {
        return $GLOBALS['AUTH_USER'] ?? [];
    }
}
