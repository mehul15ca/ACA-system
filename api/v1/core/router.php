<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Router
{
    /**
     * [METHOD][PATH] => callable
     */
    private array $routes = [];

    /* -----------------------------
       Route registration
    ----------------------------- */

    public function get(string $path, callable $handler): void
    {
        $this->map('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->map('POST', $path, $handler);
    }

    private function map(string $method, string $path, callable $handler): void
    {
        $this->routes[$method][$this->normalize($path)] = $handler;
    }

    /* -----------------------------
       Middleware wrapper
    ----------------------------- */

    public function with(callable $middleware, callable|array $handler): callable
    {
        return function () use ($middleware, $handler) {
            $middleware();

            if (is_array($handler)) {
                [$class, $method] = $handler;
                (new $class())->$method();
                return;
            }

            call_user_func($handler);
        };
    }

    /* -----------------------------
       Dispatch
    ----------------------------- */

    public function dispatch(): void
    {
        $method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path    = $this->resolvePath();
        $handler = $this->routes[$method][$path] ?? null;

        if (!$handler) {
            Response::error(
                'Not Found',
                404,
                'ROUTE_NOT_FOUND',
                ['method' => $method, 'path' => $path]
            );
        }

        try {
            $handler();
        } catch (\Throwable $e) {
            Response::error(
                'Server Error',
                500,
                'SERVER_ERROR',
                ['message' => $e->getMessage()]
            );
        }
    }

    /* -----------------------------
       Helpers
    ----------------------------- */

    private function resolvePath(): string
{
    $uri    = $_SERVER['REQUEST_URI'] ?? '/';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';

    // Remove query string
    $uri = strtok($uri, '?');

    // Remove script directory from URI
    $base = rtrim(dirname($script), '/');
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }

    // Remove index.php if present
    $uri = str_replace('/index.php', '', $uri);

    return $this->normalize($uri ?: '/');
}


    private function normalize(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return rtrim($path, '/') ?: '/';
    }
}
