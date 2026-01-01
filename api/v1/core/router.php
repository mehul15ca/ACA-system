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
    $path = $this->getRequestPath();

    $handler = $this->routes[$method][$path] ?? null;

    if (!$handler) {
      Response::error('Not Found', 404, 'ROUTE_NOT_FOUND', [
        'method' => $method,
        'path' => $path,
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
  $pos = strpos($uri, '/api/v1');
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
    // strip trailing slash except root
    if ($path !== '/' && str_ends_with($path, '/')) {
      $path = rtrim($path, '/');
    }
    return $path;
  }
}
