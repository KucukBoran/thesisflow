<?php
declare(strict_types=1);
namespace ThesisFlow;

use PDO;

final class Database
{
    public static function connect(?string $path = null): PDO
    {
        $path ??= \project_path(
            getenv("DB_PATH") ?: "storage/thesisflow.sqlite",
        );
        if ($path !== ":memory:" && !is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        $db = new PDO("sqlite:" . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $db->exec(
            "PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000; PRAGMA journal_mode = WAL;",
        );
        $db->exec(file_get_contents(dirname(__DIR__) . "/database/schema.sql"));
        return $db;
    }
}
