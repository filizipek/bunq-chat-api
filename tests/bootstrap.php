<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$testDb = '/tmp/chat_test.sqlite';
putenv('SQLITE_PATH=' . $testDb);

if (file_exists($testDb)) {
    unlink($testDb);
}

\App\Database\Connection::reset();

require __DIR__ . '/../scripts/migrate.php';
