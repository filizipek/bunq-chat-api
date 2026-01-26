<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        // Use env override for tests, default to the normal storage DB
        $dbPath = getenv('SQLITE_PATH') ?: '/app/storage/chat.sqlite';

        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Good SQLite defaults
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA busy_timeout = 5000;');

        self::$pdo = $pdo;
        return self::$pdo;
    }

    // Helpful for tests: reset connection between runs
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
