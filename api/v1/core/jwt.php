<?php
declare(strict_types=1);

namespace ACA\Api\Core;

use RuntimeException;
use JsonException;

final class JWT
{
    private const ALG = 'HS256';
    private const TTL = 86400; // 24 hours

    /* ---------------------------------------------
     | Public API
     --------------------------------------------- */

    public static function issueAccessToken(array $payload): string
    {
        return self::encode($payload);
    }

    /**
     * Decode and validate JWT.
     * Returns payload array on success, null on failure.
     */
    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$h64, $p64, $s64] = $parts;

        $headerJson  = self::base64UrlDecode($h64);
        $payloadJson = self::base64UrlDecode($p64);
        $signature   = self::base64UrlDecode($s64);

        if ($headerJson === false || $payloadJson === false || $signature === false) {
            return null;
        }

        try {
            $header  = json_decode($headerJson, true, 512, JSON_THROW_ON_ERROR);
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($header) || ($header['alg'] ?? null) !== self::ALG) {
            return null;
        }

        $expectedSig = hash_hmac(
            'sha256',
            $h64 . '.' . $p64,
            self::secret(),
            true
        );

        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        // Expiry check
        if (!isset($payload['exp']) || !is_int($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /* ---------------------------------------------
     | Internal helpers
     --------------------------------------------- */

    private static function encode(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => self::ALG
        ];

        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + self::TTL;

        try {
            $h64 = self::base64UrlEncode(
                json_encode($header, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            );
            $p64 = self::base64UrlEncode(
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            );
        } catch (JsonException) {
            throw new RuntimeException('JWT encoding failed');
        }

        $signature = hash_hmac(
            'sha256',
            $h64 . '.' . $p64,
            self::secret(),
            true
        );

        return $h64 . '.' . $p64 . '.' . self::base64UrlEncode($signature);
    }

    /**
     * Load secret from environment only.
     * Must be >= 32 chars.
     */
    private static function secret(): string
    {
        $secret = Env::get('ACA_JWT_SECRET') ?? getenv('ACA_JWT_SECRET');

        if (!is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException('JWT secret missing or too weak');
        }

        return $secret;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Returns decoded string or false
     */
    private static function base64UrlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
