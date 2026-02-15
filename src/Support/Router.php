<?php
declare(strict_types=1);

namespace App\Support;

final class Router {
    private array $routes = [];

    public function get(string $path, callable $handler): void {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(Request $req): void {
        $m = $req->method();
        $p = $req->path();

        $handler = $this->routes[$m][$p] ?? null;
        if (!$handler) {
            // 같은 path가 다른 메서드로는 있으면 405
            $allowed = [];
            foreach ($this->routes as $method => $map) {
                if (isset($map[$p])) $allowed[] = $method;
            }
            if (!empty($allowed)) {
                header('Allow: ' . implode(', ', $allowed));
                Response::json(['ok' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'method not allowed']], 405);
                return;
            }

            Response::json(['ok' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'route not found']], 404);
            return;
        }

        $handler();
    }
}
