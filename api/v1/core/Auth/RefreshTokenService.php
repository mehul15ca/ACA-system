<?php

namespace ACA\Api\Core\Auth;

use ACA\Api\Core\Database\DB;
use DateTime;
use Exception;

class RefreshTokenService
{
    // 30 days
    private const REFRESH_TTL_DAYS = 30;

    /**
     * Generate a secure refresh token (plain)
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 chars
    }

    /**
     * Hash token before storing
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Store refresh token
     */
    public static function store(
        int $userId,
        string $plainToken,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        $hash = self::hashToken($plainToken);

        $issuedAt  = new DateTime();
        $expiresAt = (clone $issuedAt)->modify('+' . self::REFRESH_TTL_DAYS . ' days');

        DB::execute(
            "INSERT INTO auth_refresh_tokens
            (user_id, token_hash, issued_at, expires_at, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $hash,
                $issuedAt->format('Y-m-d H:i:s'),
                $expiresAt->format('Y-m-d H:i:s'),
                $ip,
                $userAgent
            ]
        );
    }

    /**
     * Find valid refresh token
     */
    public static function findValid(string $plainToken): ?array
    {
        $hash = self::hashToken($plainToken);

        return DB::selectOne(
            "SELECT *
             FROM auth_refresh_tokens
             WHERE token_hash = ?
               AND revoked_at IS NULL
               AND expires_at > NOW()",
            [$hash]
        );
    }

    /**
     * Rotate refresh token
     */
    public static function rotate(array $currentToken): string
    {
        $newPlain = self::generateToken();
        $newHash  = self::hashToken($newPlain);

        DB::execute(
            "UPDATE auth_refresh_tokens
             SET revoked_at = NOW(),
                 replaced_by_token_hash = ?
             WHERE id = ?",
            [$newHash, $currentToken['id']]
        );

        self::store(
            (int) $currentToken['user_id'],
            $newPlain,
            $currentToken['ip_address'] ?? null,
            $currentToken['user_agent'] ?? null
        );

        return $newPlain;
    }

    /**
     * Revoke all refresh tokens for user (logout)
     */
    public static function revokeAllForUser(int $userId): void
    {
        DB::execute(
            "UPDATE auth_refresh_tokens
             SET revoked_at = NOW()
             WHERE user_id = ?
               AND revoked_at IS NULL",
            [$userId]
        );
    }

    /**
     * Reuse detection — revoke entire chain
     */
    public static function revokeChain(string $plainToken): void
    {
        $hash = self::hashToken($plainToken);

        DB::execute(
            "UPDATE auth_refresh_tokens
             SET revoked_at = NOW()
             WHERE token_hash = ?
                OR replaced_by_token_hash = ?",
            [$hash, $hash]
        );
    }
}
