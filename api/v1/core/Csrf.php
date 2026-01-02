<?php
declare(strict_types=1);

namespace ACA\Core;

final class Csrf
{
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verify(?string $token): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $session = $_SESSION['csrf'] ?? '';
        if (!$token || !$session || !hash_equals($session, $token)) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }
    }
}
