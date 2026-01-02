<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class RateLimiter
{
    private static bool $initialized = false;

    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $db = DB::conn();

        $db->query("
            CREATE TABLE IF NOT EXISTS api_rate_limits (
                limiter_key VARCHAR(191) PRIMARY KEY,
                hits INT NOT NULL,
                reset_at INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        self::$initialized = true;
    }

    public static function check(string $key, int $limit, int $windowSeconds): void
    {
        self::init();

        $db  = DB::conn();
        $now = time();

        $stmt = $db->prepare("
            SELECT hits, reset_at
            FROM api_rate_limits
            WHERE limiter_key = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || $now > (int)$row['reset_at']) {
            $resetAt = $now + $windowSeconds;

            $stmt = $db->prepare("
                REPLACE INTO api_rate_limits (limiter_key, hits, reset_at)
                VALUES (?, 1, ?)
            ");
            $stmt->bind_param('si', $key, $resetAt);
            $stmt->execute();
            return;
        }

        if ((int)$row['hits'] >= $limit) {
            Response::error(
                'Too many requests',
                429,
                'RATE_LIMIT_EXCEEDED'
            );
        }

        $stmt = $db->prepare("
            UPDATE api_rate_limits
            SET hits = hits + 1
            WHERE limiter_key = ?
        ");
        $stmt->bind_param('s', $key);
        $stmt->execute();
    }
}
