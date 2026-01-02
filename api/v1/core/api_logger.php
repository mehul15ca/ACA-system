<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class ApiLogger
{
    public static function log(array $data): void
    {
        try {
            $pdo = DB::conn();

            $stmt = $pdo->prepare("
                INSERT INTO api_logs
                (request_id, method, path, status_code, duration_ms, ip, user_agent, user_id, role, error_code)
                VALUES
                (:rid, :m, :p, :sc, :ms, :ip, :ua, :uid, :role, :ecode)
            ");

            $stmt->execute([
                ':rid' => (string)($data['request_id'] ?? ''),
                ':m' => (string)($data['method'] ?? ''),
                ':p' => (string)($data['path'] ?? ''),
                ':sc' => (int)($data['status_code'] ?? 200),
                ':ms' => (int)($data['duration_ms'] ?? 0),
                ':ip' => (string)($data['ip'] ?? ''),
                ':ua' => (string)($data['user_agent'] ?? null),
                ':uid' => $data['user_id'] ?? null,
                ':role' => $data['role'] ?? null,
                ':ecode' => $data['error_code'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Never break API because logging failed
        }
    }
}
