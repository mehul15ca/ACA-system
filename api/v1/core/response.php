<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Response
{
  public static function json($data, int $status = 200): void
  {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
  }

  public static function ok($data = null, string $message = 'OK', array $meta = []): void
  {
    self::json([
      'success' => true,
      'message' => $message,
      'data' => $data,
      'meta' => (object)$meta
    ], 200);
  }

  public static function error(string $error, int $status = 400, ?string $code = null, $details = null): void
  {
    $payload = [
      'success' => false,
      'error' => $error,
      'code' => $status,
    ];
    if ($code !== null) $payload['error_code'] = $code;
    if ($details !== null) $payload['details'] = $details;

    self::json($payload, $status);
  }
}
