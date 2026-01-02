<?php
declare(strict_types=1);

namespace ACA\Api\Controllers;

use ACA\Api\Core\DB;
use ACA\Api\Core\Request;
use ACA\Api\Core\Response;
use ACA\Api\Core\JWT;
use ACA\Api\Core\Auth;
use ACA\Api\Core\Auth\RefreshTokenService;

final class AuthController
{
    public static function login(): void
    {
        $data = Request::json();

        $login    = trim((string)($data['email'] ?? $data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($login === '' || $password === '') {
            Response::error('Email/Username and password required', 422, 'LOGIN_MISSING_FIELDS');
        }

        $pdo = DB::conn();

        $result = $pdo->query("SHOW COLUMNS FROM users");
        $fields = [];
        while ($row = $result->fetch_assoc()) {
            $fields[] = strtolower($row['Field']);
        }

        $idCol    = self::pick($fields, ['id', 'user_id', 'userid']);
        $loginCol = self::pick($fields, ['email', 'username', 'user_name', 'login']);
        $passCol  = self::pick($fields, ['password', 'pass', 'passwd', 'password_hash']);
        $roleCol  = self::pick($fields, ['role', 'user_role', 'type']);

        if (!$idCol || !$loginCol || !$passCol || !$roleCol) {
            Response::error('Users table schema not compatible', 500, 'USERS_SCHEMA_MISMATCH');
        }

        $stmt = $pdo->prepare("
            SELECT `$idCol` AS id, `$loginCol` AS login, `$passCol` AS pass, `$roleCol` AS role
            FROM users
            WHERE `$loginCol` = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $login);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            Response::error('Invalid credentials', 401, 'LOGIN_INVALID');
        }

        $stored = (string)$user['pass'];
        $valid = str_starts_with($stored, '$')
            ? password_verify($password, $stored)
            : hash_equals($stored, $password);

        if (!$valid) {
            Response::error('Invalid credentials', 401, 'LOGIN_INVALID');
        }

        $permStmt = $pdo->prepare("
            SELECT p.code
            FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role = ?
        ");
        $permStmt->bind_param('s', $user['role']);
        $permStmt->execute();

        $permissions = [];
        $res = $permStmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $permissions[] = $r['code'];
        }

        $accessToken = JWT::issueAccessToken([
            'user_id'     => (int)$user['id'],
            'role'        => $user['role'],
            'permissions' => $permissions,
        ]);

        $refreshToken = RefreshTokenService::generateToken();
        RefreshTokenService::store(
            (int)$user['id'],
            $refreshToken,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        Response::ok([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token'         => $accessToken,
            'user' => [
                'id'    => (int)$user['id'],
                'login' => $user['login'],
                'role'  => $user['role'],
            ],
        ], 'Login successful');
    }

    // ✅ REQUIRED BY ROUTES
    public static function refresh(): void
    {
        Response::error('Refresh not implemented yet', 501, 'REFRESH_NOT_IMPLEMENTED');
    }

    // ✅ REQUIRED BY ROUTES
    public static function logout(): void
    {
        Response::ok(null, 'Logged out');
    }

    public static function me(): void
    {
        $payload = Auth::user();

        Response::ok([
            'user_id'     => $payload['user_id'],
            'role'        => $payload['role'],
            'permissions' => $payload['permissions'],
        ], 'Authenticated');
    }

    private static function pick(array $fields, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (in_array($c, $fields, true)) {
                return $c;
            }
        }
        return null;
    }
}
