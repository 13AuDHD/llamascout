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

    <script>
        (function () {
            try {
                const theme = localStorage.getItem('llama-theme');
                const fontSize = localStorage.getItem('llama-font-size');
                const reducedMotion = localStorage.getItem('llama-reduced-motion');

                if (theme) {
                    document.documentElement.dataset.theme = theme;
                }

                if (fontSize) {
                    document.documentElement.dataset.fontSize = fontSize;
                }

                if (reducedMotion === 'true') {
                    document.documentElement.dataset.reducedMotion = 'true';
                }
            } catch (e) {
                // Preferences are optional.
            }
        })();
    </script>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link rel="stylesheet" href="/css/site.css">
</head>

<body>

<a class="skip-link" href="#main-content">
    Skip to main content
</a>

<header class="site-header">
    <div class="site-header-inner">

        <a class="site-brand" href="/index.php" aria-label="Llama Scout home">
            <img src="/images/logo.png" alt="Llama Scout">
        </a>

        <nav class="site-nav" aria-label="Main navigation">

            <a href="/index.php">
                <i class="fa-solid fa-house" aria-hidden="true"></i>
                <span>Home</span>
            </a>

            <a href="/map.php">
                <i class="fa-solid fa-map" aria-hidden="true"></i>
                <span>Map</span>
            </a>

            <?php if ($user): ?>
                <a href="<?= htmlspecialchars($accountUrl . '/index.php', ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <span>Account</span>
                </a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($accountUrl . '/login.php', ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                    <span>Sign in</span>
                </a>
            <?php endif; ?>

            <button
                class="accessibility-button"
                type="button"
                id="accessibility-button"
                aria-controls="accessibility-panel"
                aria-expanded="false"
            >
                <i class="fa-solid fa-universal-access" aria-hidden="true"></i>
                <span class="visually-hidden">Accessibility settings</span>
            </button>

        </nav>

    </div>

    <div
        class="accessibility-panel"
        id="accessibility-panel"
        hidden
    >
        <div class="accessibility-panel-inner">

            <div class="accessibility-panel-heading">
                <h2>Accessibility</h2>

                <button
                    type="button"
                    class="accessibility-close"
                    id="accessibility-close"
                    aria-label="Close accessibility settings"
                >
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="accessibility-setting">
                <label for="theme-select">
                    <i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i>
                    Appearance
                </label>

                <select id="theme-select">
                    <option value="system">Use device setting</option>
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                </select>
            </div>

            <div class="accessibility-setting">
                <label for="font-size-select">
                    <i class="fa-solid fa-text-height" aria-hidden="true"></i>
                    Text size
                </label>

                <select id="font-size-select">
                    <option value="normal">Normal</option>
                    <option value="large">Large</option>
                    <option value="larger">Extra large</option>
                </select>
            </div>

            <div class="accessibility-setting accessibility-checkbox">
                <input type="checkbox" id="reduced-motion">

                <label for="reduced-motion">
                    <i class="fa-solid fa-person-walking-arrow-right" aria-hidden="true"></i>
                    Reduce motion
                </label>
            </div>

            <button
                type="button"
                class="accessibility-reset"
                id="accessibility-reset"
            >
                Reset accessibility settings
            </button>

        </div>
    </div>
</header>

<main class="site-main" id="main-content">
