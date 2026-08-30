<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_login();

$user = current_user();

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account | Llama Scout</title>
</head>
<body>

<main>
    <h1>Your account</h1>

    <p>
        Signed in as
        <strong>
            <?= htmlspecialchars(
                (string) ($user['display_name'] ?? $user['username'] ?? $user['email']),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </p>

    <p>
        <a href="https://llamascout.com/">Return to Llama Scout</a>
    </p>

    <p>
        <a href="/logout.php">Log out</a>
    </p>
</main>

</body>
</html>
