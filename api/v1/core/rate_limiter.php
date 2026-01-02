<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class RateLimiter
{
    /**
     * Simple fixed-window rate limiter.
     * Key suggestion: "$ip|$method|$path" or "$ip|global"
     */
    public static function check(string $key, int $limit, int $windowSeconds): void
    {
        $now = time();
        $windowStart = (int)(floor($now / $windowSeconds) * $windowSeconds);

        $hash = hash('sha256', $key);

        $pdo = DB::conn();

        // Upsert counter for this window
        $sql = "
            INSERT INTO api_rate_limits (key_hash, window_start, count)
            VALUES (:h, :ws, 1)
            ON DUPLICATE KEY UPDATE
              count = IF(window_start = VALUES(window_start), count + 1, 1),
              window_start = IF(window_start = VALUES(window_start), window_start, VALUES(window_start))
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':h' => $hash,
            ':ws' => $windowStart,
        ]);

        // Read current count
        $stmt2 = $pdo->prepare("SELECT count, window_start FROM api_rate_limits WHERE key_hash = :h LIMIT 1");
        $stmt2->execute([':h' => $hash]);
        $row = $stmt2->fetch();

        $count = (int)($row['count'] ?? 0);
        if ($count > $limit) {
            // Optional: expose retry-after
            $retryAfter = ($windowStart + $windowSeconds) - $now;
            header('Retry-After: ' . max(1, $retryAfter));
            Response::error('Too many requests', 429, 'RATE_LIMITED');
        }
    }
}
