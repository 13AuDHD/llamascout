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

function llama_database_connection(
    array $database,
    string $label
): PDO {
    if (
        trim(
            (string) (
                $database['name']
                ?? ''
            )
        ) === ''
    ) {
        throw new RuntimeException(
            $label . ' database configuration is missing.'
        );
    }

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

    $pdo->exec(
        "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "SET time_zone = '+00:00'"
    );

    return $pdo;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo =
        llama_database_connection(
            (array) (
                llama_config()['database']
                ?? []
            ),
            'Primary'
        );

    return $pdo;
}


/* =========================================================
   CELL COVERAGE DATABASE
   ========================================================= */

function cell_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo =
        llama_database_connection(
            (array) (
                llama_config()['cell_database']
                ?? []
            ),
            'Cell coverage'
        );

    return $pdo;
}


/* =========================================================
   REFERENCE DATABASE
   ========================================================= */

function reference_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo =
        llama_database_connection(
            (array) (
                llama_config()['reference_database']
                ?? []
            ),
            'Reference'
        );

    return $pdo;
}


/* =========================================================
   RIDB DATABASE

   Recreation.gov / Recreation Information Database source
   records live in their own database so imported federal
   reference data never has to be mixed into the primary
   Llama Scout application database.
   ========================================================= */

function ridb_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo =
        llama_database_connection(
            (array) (
                llama_config()['ridb_database']
                ?? []
            ),
            'RIDB'
        );

    return $pdo;
}
