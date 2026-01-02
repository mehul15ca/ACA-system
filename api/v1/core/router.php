<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Router
{
    private array $routes = [];

    /* -----------------------------
       Route registration
    ----------------------------- */

    public function get(string $path, $handler): void
    {
        $this->map('GET', $path, $handler);
    }

    public function post(string $path, $handler): void
    {
        $this->map('POST', $path, $handler);
    }

    /* -----------------------------
       Core routing
    ----------------------------- */

    private function map(string $method, string $path, $handler): void
    {
        if (!is_callable($handler)) {
            throw new \InvalidArgumentException('Route handler is not callable');
        }

        $this->routes[$method][$this->normalize($path)] = $handler;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path   = $this->resolvePath();

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
            call_user_func($handler);
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
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = explode('?', $uri, 2)[0];

        if (($pos = strpos($uri, '/api/v1')) !== false) {
            $uri = substr($uri, $pos + 7);
        }

        $uri = str_replace('/index.php', '', $uri);

        return $this->normalize($uri ?: '/');
    }

    private function normalize(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return rtrim($path, '/') ?: '/';
    }
}
