<?php
// includes/security/csrf.php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const FIELD_NAME  = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        $t = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . $t . '">';
    }

    public static function validateRequest(): void
    {
        // Only enforce on state-changing requests
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST' && $method !== 'PUT' && $method !== 'PATCH' && $method !== 'DELETE') {
            return;
        }

        $posted = $_POST[self::FIELD_NAME] ?? '';
        if (!is_string($posted) || $posted === '') {
            self::reject();
        }

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '') {
            self::reject();
        }

        // constant-time comparison
        if (!hash_equals($sessionToken, $posted)) {
            self::reject();
        }
    }

    private static function reject(): void
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden (CSRF).";
        exit;
    }

    public static function name(): string
    {
        return self::FIELD_NAME;
    }
}
