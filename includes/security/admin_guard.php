<?php
// includes/security/admin_guard.php

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

final class AdminGuard
{
    /**
     * Enforces:
     * 1) logged-in
     * 2) correct role (admin/staff etc)
     * 3) optional permission key
     */
    public static function requireLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $uid  = $_SESSION['user_id'] ?? null;
        $role = $_SESSION['role'] ?? null;

        if (empty($uid) || !is_numeric($uid) || empty($role) || !is_string($role)) {
            self::redirectToLogin();
        }
    }

    public static function requireRole(array $allowedRoles): void
    {
        self::requireLogin();

        $role = (string)($_SESSION['role'] ?? '');
        if (!in_array($role, $allowedRoles, true)) {
            self::forbidden();
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireLogin();

        $role = (string)($_SESSION['role'] ?? '');
        if (!Permissions::has($role, $permission)) {
            self::forbidden();
        }
    }

    private static function redirectToLogin(): void
    {
        // Adjust login path if your admin login differs
        header('Location: /ACA-System/login.php');
        exit;
    }

    private static function forbidden(): void
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden.";
        exit;
    }
}
