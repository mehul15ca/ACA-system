<?php
declare(strict_types=1);

namespace ACA\Api\Core;

use mysqli;

final class DB
{
    private static ?mysqli $conn = null;

    public static function conn(): mysqli
    {
        if (self::$conn) {
            return self::$conn;
        }

        $host = Env::get('DB_HOST', 'localhost');
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');
        $name = Env::get('DB_NAME', 'aca_system');

        $db = new mysqli($host, $user, $pass, $name);

        if ($db->connect_error) {
            Response::error(
                'Database connection failed',
                500,
                'DB_CONNECTION_FAILED',
                ['message' => $db->connect_error]
            );
        }

        $db->set_charset('utf8mb4');
        self::$conn = $db;

        return $db;
    }

    /* --------------------------------------------------
     | Convenience helpers
     -------------------------------------------------- */

    public static function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = self::prepare($sql, $params);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_assoc() : null;
    }

    public static function selectAll(string $sql, array $params = []): array
    {
        $stmt = self::prepare($sql, $params);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public static function execute(string $sql, array $params = []): bool
    {
        $stmt = self::prepare($sql, $params);
        return $stmt->execute();
    }

    public static function prepare(string $sql, array $params)
    {
        $db = self::conn();
        $stmt = $db->prepare($sql);

        if (!$stmt) {
            Response::error(
                'DB prepare failed',
                500,
                'DB_PREPARE_FAILED',
                ['sql' => $sql]
            );
        }

        if ($params) {
            $types = '';
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : 's';
            }
            $stmt->bind_param($types, ...$params);
        }

        return $stmt;
    }

    /* --------------------------------------------------
     | Compatibility layer (TEMP, SAFE)
     -------------------------------------------------- */

    // Used by legacy-style API code
    public static function fetch(string $sql, array $params = []): ?array
    {
        return self::selectOne($sql, $params);
    }

    // Raw query (used sparingly)
    public static function query(string $sql)
    {
        return self::conn()->query($sql);
    }
}
