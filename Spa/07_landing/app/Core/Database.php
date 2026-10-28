<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = require dirname(__DIR__, 2) . '/config/database.php';
        self::$pdo = self::connect($config);

        return self::$pdo;
    }

    public static function connect(array $config): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['sslmode']
        );

        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec("SET default_transaction_read_only = on");
        $pdo->exec("SET statement_timeout = '5s'");
        $pdo->exec("SET lock_timeout = '2s'");
        $pdo->exec("SET idle_in_transaction_session_timeout = '5s'");

        return $pdo;
    }
}
