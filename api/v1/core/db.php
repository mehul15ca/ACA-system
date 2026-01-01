<?php
declare(strict_types=1);

namespace ACA\Api\Core;

use PDO;
use PDOException;

final class DB
{
  private static ?PDO $pdo = null;

  public static function conn(): PDO
  {
    if (self::$pdo) return self::$pdo;

    $host = 'localhost';
    $db   = 'aca_system';
    $user = 'root';
    $pass = ''; // XAMPP default
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    try {
      self::$pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
      ]);
    } catch (PDOException $e) {
      Response::error('Database connection failed', 500, 'DB_CONNECTION_FAILED');
    }

    return self::$pdo;
  }
}
