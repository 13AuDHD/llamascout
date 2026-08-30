<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

try {
    $pdo = db();

    $result = $pdo->query('SELECT DATABASE() AS database_name, VERSION() AS database_version');
    $database = $result->fetch();

    ?><!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Llama Scout v2</title>
    </head>
    <body>
        <main>
            <h1>Llama Scout v2</h1>
            <p>PHP bootstrap loaded successfully.</p>
            <p>Database connection successful.</p>
            <p>
                Database:
                <strong><?= htmlspecialchars((string) $database['database_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
            <p>
                MariaDB/MySQL:
                <strong><?= htmlspecialchars((string) $database['database_version'], ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
        </main>
    </body>
    </html>
    <?php
} catch (Throwable $e) {
    http_response_code(500);

    ?><!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Llama Scout v2 Setup Error</title>
    </head>
    <body>
        <main>
            <h1>Llama Scout v2 Setup Error</h1>
            <p><?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?></p>
        </main>
    </body>
    </html>
    <?php
}
