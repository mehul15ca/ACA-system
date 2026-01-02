<?php
declare(strict_types=1);

namespace ACA\Api\Core;

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

    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$h, $p, $s] = $parts;

        $expected = hash_hmac(
            'sha256',
            "$h.$p",
            self::secret(),
            true
        );

        if (!hash_equals($expected, self::base64UrlDecode($s))) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($p), true);

        if (!$payload || ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return $payload;
    }

    /* ---------------------------------------------
     | Internal helpers
     --------------------------------------------- */

    private static function encode(array $payload): string
    {
        $header = ['typ' => 'JWT', 'alg' => self::ALG];

        $payload['iat'] = time();
        $payload['exp'] = time() + self::TTL;

        $segments = [];
        $segments[] = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $segments[] = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signingInput = implode('.', $segments);
        $signature = hash_hmac(
            'sha256',
            $signingInput,
            self::secret(),
            true
        );

        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private static function secret(): string
    {
        $secret = Env::get('ACA_JWT_SECRET');

        if (!$secret || strlen($secret) < 32) {
            Response::error(
                'JWT secret not configured',
                500,
                'JWT_SECRET_MISSING'
            );
        }

        return $secret;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
