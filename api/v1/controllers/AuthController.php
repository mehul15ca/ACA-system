<?php
declare(strict_types=1);

namespace ACA\Api\Controllers;

use ACA\Api\Core\DB;
use ACA\Api\Core\Request;
use ACA\Api\Core\Response;
use ACA\Api\Core\JWT;
use ACA\Api\Core\Auth;

final class AuthController
{
  public static function login(): void
  {
    $data = Request::json();

    // Accept email OR username for login
    $login = trim((string)($data['email'] ?? $data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if ($login === '' || $password === '') {
      Response::error('Email/Username and password required', 422);
    }

    $pdo = DB::conn();

    /* -------------------------------------------------
       Detect USERS table schema dynamically
    ------------------------------------------------- */
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll();
    $fields = array_map(fn($c) => strtolower($c['Field']), $columns);

    $idCol = self::pick($fields, ['id', 'user_id', 'userid']);
    $loginCol = self::pick($fields, ['email', 'username', 'user_name', 'login']);
    $passCol = self::pick($fields, ['password', 'pass', 'passwd', 'password_hash']);
    $roleCol = self::pick($fields, ['role', 'user_role', 'type']);

    if (!$idCol || !$loginCol || !$passCol || !$roleCol) {
      Response::error(
        'Users table schema not compatible',
        500,
        'USERS_SCHEMA_MISMATCH',
        $fields
      );
    }

    /* -------------------------------------------------
       Fetch user
    ------------------------------------------------- */
    $sql = "
      SELECT
        `$idCol`   AS id,
        `$loginCol` AS login,
        `$passCol` AS pass,
        `$roleCol` AS role
      FROM users
      WHERE `$loginCol` = ?
      LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if (!$user) {
      Response::error('Invalid credentials', 401);
    }

    /* -------------------------------------------------
       Verify password (hashed OR legacy plain)
    ------------------------------------------------- */
    $stored = (string)$user['pass'];
    $valid = false;

    if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon')) {
      $valid = password_verify($password, $stored);
    } else {
      // legacy plain-text fallback (temporary)
      $valid = hash_equals($stored, $password);
    }

    if (!$valid) {
      Response::error('Invalid credentials', 401);
    }

    /* -------------------------------------------------
       Permissions
    ------------------------------------------------- */
    $permStmt = $pdo->prepare("
      SELECT p.code
      FROM permissions p
      JOIN role_permissions rp ON rp.permission_id = p.id
      WHERE rp.role = ?
    ");
    $permStmt->execute([$user['role']]);
    $permissions = array_column($permStmt->fetchAll(), 'code');

    /* -------------------------------------------------
       JWT
    ------------------------------------------------- */
    $token = JWT::encode([
      'user_id' => (int)$user['id'],
      'role' => $user['role'],
      'permissions' => $permissions,
    ]);

    Response::ok([
      'token' => $token,
      'user' => [
        'id' => (int)$user['id'],
        'login' => $user['login'],
        'role' => $user['role'],
      ],
    ], 'Login successful');
  }

  public static function me(): void
  {
    $payload = Auth::user();

    Response::ok([
      'user_id' => $payload['user_id'],
      'role' => $payload['role'],
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
