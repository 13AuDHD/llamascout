<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    $database = $config['database'] ?? null;

    if (
        !is_array($database) ||
        empty($database['host']) ||
        empty($database['name']) ||
        empty($database['user']) ||
        !array_key_exists('password', $database)
    ) {
        throw new RuntimeException('Database configuration is incomplete.');
    }

    $charset = $database['charset'] ?? 'utf8mb4';

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $database['host'],
        $database['name'],
        $charset
    );

    $pdo = new PDO(
        $dsn,
        $database['user'],
        $database['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}
