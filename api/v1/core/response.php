<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Response
{
    private static int $statusCode = 200;
    private static ?string $lastErrorCode = null;

    public static function statusCode(): int
    {
        return self::$statusCode;
    }

    public static function lastErrorCode(): ?string
    {
        return self::$lastErrorCode;
    }

    private static function baseMeta(): array
    {
        return [
            'request_id' => $GLOBALS['ACA_REQUEST_ID'] ?? null,
            'api_version' => 'v1',
            'timestamp' => gmdate('c'),
        ];
    }

    public static function ok(array $data = [], string $message = 'OK', int $code = 200): void
    {
        self::$statusCode = $code;
        self::$lastErrorCode = null;

        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => self::baseMeta(),
        ], JSON_UNESCAPED_SLASHES);

        exit;
    }

    public static function error(
        string $message,
        int $code = 400,
        string $errorCode = 'ERROR',
        array $details = []
    ): void {
        self::$statusCode = $code;
        self::$lastErrorCode = $errorCode;

        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code,
            'error_code' => $errorCode,
            'details' => $details,
            'meta' => self::baseMeta(),
        ], JSON_UNESCAPED_SLASHES);

        exit;
    }
}
