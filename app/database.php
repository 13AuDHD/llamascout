<?php

declare(strict_types=1);

function llama_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $configPath = dirname(__DIR__, 2) . '/private/config.php';

    if (!is_file($configPath)) {
        throw new RuntimeException(
            'Private Llama Scout configuration is missing.'
        );
    }

    $config = require $configPath;

    if (
        !is_array($config) ||
        empty($config['database'])
    ) {
        throw new RuntimeException(
            'Private Llama Scout configuration is invalid.'
        );
    }

    return $config;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $database =
        llama_config()['database'];

    /*
     * Llama Scout stores user-entered text throughout the platform.
     * Force full four-byte UTF-8 support at the connection level so
     * emoji and the full Unicode range are handled consistently,
     * regardless of an older charset value left in private/config.php.
     */
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $database['host'] ?? 'localhost',
        $database['name'] ?? ''
    );

    $pdo = new PDO(
        $dsn,
        $database['user'] ?? '',
        $database['password'] ?? '',
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES =>
                false,
        ]
    );

    /*
     * Keep the session explicit as well. This protects connections
     * from server defaults that may still be configured as utf8/utf8mb3.
     */
    $pdo->exec(
        "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    return $pdo;
}
