<?php
declare(strict_types=1);

namespace ACA\Api\Core;

final class Router
{
    /**
     * Routes format:
     * [METHOD][PATH] => [
     *   'handler' => callable,
     *   'middleware' => callable[]
     * ]
     */
    private array $routes = [];

    /* -------------------------------------------------
       Route registration
    ------------------------------------------------- */

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->map('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->map('POST', $path, $handler, $middleware);
    }

    /* -------------------------------------------------
       Core mapping
    ------------------------------------------------- */

    private function map(
        string $method,
        string $path,
        callable|array $handler,
        array $middleware = []
    ): void {
        if (!is_callable($handler)) {
            throw new \InvalidArgumentException('Route handler is not callable');
        }

        foreach ($middleware as $mw) {
            if (!is_callable($mw)) {
                throw new \InvalidArgumentException('Middleware must be callable');
            }
        }

        $this->routes[$method][$this->normalize($path)] = [
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    /* -------------------------------------------------
       Dispatch
    ------------------------------------------------- */

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path   = $this->resolvePath();

        $route = $this->routes[$method][$path] ?? null;

        if (!$route) {
            Response::error(
                'Not Found',
                404,
                'ROUTE_NOT_FOUND',
                ['method' => $method, 'path' => $path]
            );
        }

        try {
            // Run middleware in order
            $context = null;
            foreach ($route['middleware'] as $mw) {
                $context = $mw($context);
            }

            // Call handler
            call_user_func($route['handler']);
        } catch (\Throwable $e) {
            Response::error(
                'Server Error',
                500,
                'SERVER_ERROR',
                ['message' => $e->getMessage()]
            );
        }
    }

    /* -------------------------------------------------
       Helpers
    ------------------------------------------------- */

    private function resolvePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = explode('?', $uri, 2)[0];

        // Strip /api/v1
        if (($pos = strpos($uri, '/api/v1')) !== false) {
            $uri = substr($uri, $pos + strlen('/api/v1'));
        }

        // Strip index.php
        $uri = str_replace('/index.php', '', $uri);

        return $this->normalize($uri ?: '/');
    }

    private function normalize(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return rtrim($path, '/') ?: '/';
    }
}
