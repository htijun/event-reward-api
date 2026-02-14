<?php
declare(strict_types=1);
namespace App\Support;

final class Request {
    public function method(): string {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
    public function path(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $q = strpos($uri, '?');
        return $q === false ? $uri : substr($uri, 0, $q);
    }
}
