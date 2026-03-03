<?php

class Database
{
    private static $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = getenv('DB_HOST');
        if ($host === false) { $host = '127.0.0.1'; }

        $port = getenv('DB_PORT');
        if ($port === false) { $port = '3306'; }

        $name = getenv('DB_NAME');
        if ($name === false) { $name = 'rss_feed'; }

        $user = getenv('DB_USER');
        if ($user === false) { $user = 'rss_user'; }

        $pass = getenv('DB_PASS');
        if ($pass === false) { $pass = 'rss_secret'; }

        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }
}
