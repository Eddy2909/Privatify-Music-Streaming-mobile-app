<?php

declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $db = Config::get('db');
        if (!is_array($db)) {
            throw new RuntimeException('Datenbankkonfiguration fehlt.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'] ?? 'localhost',
            (int) ($db['port'] ?? 3306),
            $db['database'] ?? ''
        );

        self::$pdo = new PDO($dsn, (string) ($db['username'] ?? ''), (string) ($db['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // PHP 8.5 deprecates PDO::MYSQL_ATTR_INIT_COMMAND. The DSN charset plus this
        // explicit command keeps utf8mb4 behavior without using deprecated constants.
        self::$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

        return self::$pdo;
    }
}