<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Response
{
    private static int $statusCode = 200;
    private static ?string $lastErrorCode = null;

    /* --------------------------------------------------
     | SUCCESS RESPONSES
     -------------------------------------------------- */

    public static function success($data = null, string $message = 'OK', int $status = 200): void
    {
        self::$statusCode = $status;
        self::$lastErrorCode = null;

        http_response_code($status);

        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'meta'    => self::meta(),
        ], JSON_UNESCAPED_SLASHES);

        exit;
    }

    // ✅ ALIAS — keeps system stable
    public static function ok($data = null, string $message = 'OK', int $status = 200): void
    {
        self::success($data, $message, $status);
    }

    /* --------------------------------------------------
     | ERROR RESPONSES
     -------------------------------------------------- */

    public static function error(
        string $message,
        int $status,
        string $errorCode,
        $details = null
    ): void {
        self::$statusCode = $status;
        self::$lastErrorCode = $errorCode;

        http_response_code($status);

        echo json_encode([
            'success' => false,
            'error'   => $message,
            'code'    => $status,
            'error_code' => $errorCode,
            'details' => $details,
            'meta'    => self::meta(),
        ], JSON_UNESCAPED_SLASHES);

        exit;
    }

    /* --------------------------------------------------
     | META / HELPERS
     -------------------------------------------------- */

    public static function statusCode(): int
    {
        return self::$statusCode;
    }

    public static function lastErrorCode(): ?string
    {
        return self::$lastErrorCode;
    }

    private static function meta(): array
    {
        return [
            'request_id'  => $GLOBALS['ACA_REQUEST_ID'] ?? null,
            'api_version' => 'v1',
            'timestamp'   => gmdate('c'),
        ];
    }
}
