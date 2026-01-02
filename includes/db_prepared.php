<?php
// includes/db_prepared.php

declare(strict_types=1);

/**
 * Requires a mysqli instance in $conn (typical XAMPP style).
 * Your config.php likely already sets $conn = new mysqli(...)
 */
final class DB
{
    public static function conn(): mysqli
    {
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) {
            throw new RuntimeException('DB connection $conn (mysqli) not available.');
        }
        return $conn;
    }

    /**
     * Run a prepared statement and return mysqli_stmt.
     * $types matches mysqli bind_param types string (e.g. "ssi")
     */
    public static function stmt(string $sql, string $types = '', array $params = []): mysqli_stmt
    {
        $c = self::conn();
        $stmt = $c->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $c->error);
        }

        if ($types !== '' || !empty($params)) {
            if ($types === '' || strlen($types) !== count($params)) {
                throw new InvalidArgumentException('Bind types length must match params count.');
            }
            $stmt->bind_param($types, ...self::refValues($params));
        }

        if (!$stmt->execute()) {
            $err = $stmt->error ?: 'Execute failed';
            $stmt->close();
            throw new RuntimeException($err);
        }

        return $stmt;
    }

    public static function fetchAll(mysqli_stmt $stmt): array
    {
        $res = $stmt->get_result();
        if (!$res) {
            return [];
        }
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public static function fetchOne(mysqli_stmt $stmt): ?array
    {
        $res = $stmt->get_result();
        if (!$res) {
            return null;
        }
        $row = $res->fetch_assoc();
        return $row ?: null;
    }

    public static function affected(mysqli_stmt $stmt): int
    {
        return $stmt->affected_rows;
    }

    private static function refValues(array $arr): array
    {
        // mysqli bind_param needs references
        $refs = [];
        foreach ($arr as $k => $v) {
            $refs[$k] = &$arr[$k];
        }
        return $refs;
    }
}
