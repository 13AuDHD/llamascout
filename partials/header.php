<?php

declare(strict_types=1);

$user = current_user();
$config = llama_config();

$accountUrl = rtrim(
    (string) ($config['app']['account_url'] ?? 'https://account.llamascout.com'),
    '/'
);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars($pageTitle ?? 'Llama Scout', ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="stylesheet" href="/css/site.css?v=1">
</head>
<body>

<header class="site-header">
    <div class="site-header-inner">

        <a class="site-brand" href="/index.php">
            <img src="/images/logo.png" alt="Llama Scout">
        </a>

        <nav class="site-nav" aria-label="Main navigation">
            <a href="/index.php">Home</a>
            <a href="/map.php">Map</a>

            <?php if ($user): ?>
                <a href="<?= htmlspecialchars($accountUrl . '/index.php', ENT_QUOTES, 'UTF-8') ?>">
                    Account
                </a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($accountUrl . '/login.php', ENT_QUOTES, 'UTF-8') ?>">
                    Sign in
                </a>
            <?php endif; ?>
        </nav>

    </div>
</header>

<main class="site-main">
