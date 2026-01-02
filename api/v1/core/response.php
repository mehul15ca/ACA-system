<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Response
{
    private static int $statusCode = 200;
    private static ?string $lastErrorCode = null;

    public static function success(
        array $data = [],
        string $message = 'OK',
        int $status = 200,
        array $meta = []
    ): void {
        self::$statusCode = $status;

        http_response_code($status);

        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'meta'    => array_merge(self::meta(), $meta),
        ], JSON_UNESCAPED_SLASHES);

        exit;
    }

    public static function error(
        string $message,
        int $status,
        string $errorCode,
        array $details = []
    ): void {
        self::$statusCode   = $status;
        self::$lastErrorCode = $errorCode;

        http_response_code($status);

        echo json_encode([
            'success'    => false,
            'error'      => $message,
            'code'       => $status,
            'error_code' => $errorCode,
            'details'    => $details,
            'meta'       => self::meta(),
        ], JSON_UNESCAPED_SLASHES);

        exit;
    }

    /* ---------------------------------------------
     | Metadata helpers
     --------------------------------------------- */

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
            'request_id'  => $GLOBALS['ACA_REQUEST_ID'] ?? '',
            'api_version' => 'v1',
            'timestamp'   => gmdate('c'),
        ];
    }
}
