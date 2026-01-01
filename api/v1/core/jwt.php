<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class JWT
{
  private const ALG = 'HS256';
  private const TTL = 86400; // 24 hours

  private static function secret(): string
  {
    $s = Env::get('ACA_JWT_SECRET');
    if (!$s || strlen($s) < 32) {
      Response::error(
        'JWT secret not configured',
        500,
        'JWT_SECRET_MISSING'
      );
    }
    return $s;
  }

  public static function encode(array $payload): string
  {
    $header = ['typ' => 'JWT', 'alg' => self::ALG];
    $payload['iat'] = time();
    $payload['exp'] = time() + self::TTL;

    $segments = [];
    $segments[] = self::base64UrlEncode(json_encode($header));
    $segments[] = self::base64UrlEncode(json_encode($payload));

    $signingInput = implode('.', $segments);
    $signature = hash_hmac('sha256', $signingInput, self::secret(), true);
    $segments[] = self::base64UrlEncode($signature);

    return implode('.', $segments);
  }

  public static function decode(string $token): ?array
  {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$h, $p, $s] = $parts;

    $validSig = hash_hmac(
      'sha256',
      "$h.$p",
      self::secret(),
      true
    );

    if (!hash_equals($validSig, self::base64UrlDecode($s))) {
      return null;
    }

    $payload = json_decode(self::base64UrlDecode($p), true);
    if (!$payload || ($payload['exp'] ?? 0) < time()) {
      return null;
    }

    return $payload;
  }

  private static function base64UrlEncode(string $data): string
  {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  private static function base64UrlDecode(string $data): string
  {
    return base64_decode(strtr($data, '-_', '+/'));
  }
}
