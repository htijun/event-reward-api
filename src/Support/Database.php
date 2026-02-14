<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;

        $host = getenv('DB_HOST') ?: 'db';          // docker compose service name
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'event_reward';
        $user = getenv('DB_USER') ?: 'app';
        $pass = getenv('DB_PASS') ?: 'app123';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('DB connection failed: ' . $e->getMessage());
        }

        self::$pdo = $pdo;
        return $pdo;
    }
}
