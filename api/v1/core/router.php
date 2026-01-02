<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Router
{
    private array $routes = []; // [METHOD][PATH] => callable

    public function get(string $path, callable $handler): void
    {
        $this->map('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->map('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->map('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): void
    {
        $this->map('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->map('DELETE', $path, $handler);
    }

    private function map(string $method, string $path, callable $handler): void
    {
        $path = $this->normalizePath($path);
        $this->routes[$method][$path] = $handler;
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path   = $this->getRequestPath();
        $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        /*
        |--------------------------------------------------------------------------
        | API version pinning (CENTRAL – Step 4)
        |--------------------------------------------------------------------------
        | This router serves v1 only.
        | When v2 is introduced, it will have its own router.
        */
        $GLOBALS['API_VERSION'] = 'v1';

        if ($GLOBALS['API_VERSION'] !== 'v1') {
            Response::error(
                'Unsupported API version',
                400,
                'API_VERSION_UNSUPPORTED'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Rate limiting
        |--------------------------------------------------------------------------
        */
        // Global: 120 requests / 60s per IP
        RateLimiter::check($ip . '|global', 120, 60);

        // Per-route: 60 requests / 60s per IP + route
        RateLimiter::check($ip . '|' . $method . '|' . $path, 60, 60);

        /*
        |--------------------------------------------------------------------------
        | Register shutdown logger (always runs)
        |--------------------------------------------------------------------------
        */
        register_shutdown_function(function () use ($ip, $method, $path) {
            $endMs = (int) floor(microtime(true) * 1000);
            $durMs = max(0, $endMs - (int)($GLOBALS['ACA_START_MS'] ?? $endMs));

            $userId = null;
            $role   = null;

            try {
                $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
                    $payload = JWT::decode($m[1]);
                    if ($payload) {
                        $userId = $payload['user_id'] ?? null;
                        $role   = $payload['role'] ?? null;
                    }
                }
            } catch (\Throwable $e) {
                // Never break logging
            }

            ApiLogger::log([
                'request_id'  => $GLOBALS['ACA_REQUEST_ID'] ?? '',
                'method'      => $method,
                'path'        => $path,
                'status_code' => Response::statusCode(),
                'duration_ms' => $durMs,
                'ip'          => $ip,
                'user_agent'  => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'user_id'     => $userId,
                'role'        => $role,
                'error_code'  => Response::lastErrorCode(),
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Dispatch route
        |--------------------------------------------------------------------------
        */
        $handler = $this->routes[$method][$path] ?? null;

        if (!$handler) {
            Response::error('Not Found', 404, 'ROUTE_NOT_FOUND', [
                'method' => $method,
                'path'   => $path,
            ]);
        }

        try {
            $handler();
        } catch (\Throwable $e) {
            Response::error('Server Error', 500, 'SERVER_ERROR', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function getRequestPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        $uri = explode('?', $uri, 2)[0];

        // Remove everything before /api/v1
        $pos  = strpos($uri, '/api/v1');
        $path = $pos !== false ? substr($uri, $pos + strlen('/api/v1')) : $uri;

        // Remove index.php if present
        $path = str_replace('/index.php', '', $path);

        if ($path === '') {
            $path = '/';
        }

        return $this->normalizePath($path);
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        // Strip trailing slash except root
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path;
    }
}
