<?php
declare(strict_types=1);
namespace App\Support;

final class Router {
    private array $routes = [];

    public function get(string $path, callable $handler): void {
        $this->routes['GET'][$path] = $handler;
    }

    public function dispatch(Request $req): void {
        $m = $req->method();
        $p = $req->path();

        $handler = $this->routes[$m][$p] ?? null;
        if (!$handler) {
            Response::json(['ok' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'route not found']], 404);
            return;
        }
        $handler();
    }
}
