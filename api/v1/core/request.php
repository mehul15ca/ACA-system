<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Request
{
  public static function method(): string
  {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
  }

  public static function headers(): array
  {
    if (function_exists('getallheaders')) {
      return getallheaders();
    }

    $headers = [];
    foreach ($_SERVER as $key => $value) {
      if (str_starts_with($key, 'HTTP_')) {
        $name = str_replace('_', '-', substr($key, 5));
        $headers[$name] = $value;
      }
    }
    return $headers;
  }

  public static function bearerToken(): ?string
  {
    $headers = self::headers();
    $auth = $headers['Authorization'] ?? $headers['AUTHORIZATION'] ?? null;

    if (!$auth) return null;
    if (!preg_match('/Bearer\s(\S+)/', $auth, $matches)) return null;

    return $matches[1];
  }

  public static function json(): array
  {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
  }

  public static function query(string $key = null, $default = null)
  {
    if ($key === null) return $_GET;
    return $_GET[$key] ?? $default;
  }
}
