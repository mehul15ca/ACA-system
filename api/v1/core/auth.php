<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Auth
{
    public static function storeRefreshToken(int $userId, string $token): void
    {
        $hash = hash('sha256', $token);

        DB::query(
            "INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
             VALUES (?, ?, ?)",
            [$userId, $hash, JWT::refreshExpiry()]
        );
    }

    public static function rotateRefreshToken(string $token): ?int
    {
        $hash = hash('sha256', $token);

        $row = DB::fetch(
            "SELECT * FROM refresh_tokens
             WHERE token_hash = ? AND revoked = 0 AND expires_at > NOW()
             LIMIT 1",
            [$hash]
        );

        if (!$row) return null;

        DB::query(
            "UPDATE refresh_tokens SET revoked = 1, last_used_at = NOW()
             WHERE id = ?",
            [$row['id']]
        );

        return (int)$row['user_id'];
    }

    public static function revokeAll(int $userId): void
    {
        DB::query(
            "UPDATE refresh_tokens SET revoked = 1 WHERE user_id = ?",
            [$userId]
        );
    }
}
