<?php

declare(strict_types=1);

if (!isset($adminUser) || !is_array($adminUser)) {
    $adminUser = moderation_require_admin();
}

$config = llama_config();

$siteUrl = rtrim(
    (string) ($config['app']['url'] ?? 'https://llamascout.com'),
    '/'
);

$accountUrl = rtrim(
    (string) ($config['app']['account_url'] ?? 'https://account.llamascout.com'),
    '/'
);

$adminUrl = rtrim(
    (string) ($config['app']['admin_url'] ?? 'https://admin.llamascout.com'),
    '/'
);

$adminPageTitle = (string) ($adminPageTitle ?? 'Dashboard');
$adminPageEyebrow = (string) ($adminPageEyebrow ?? 'Llama Scout Admin');
$adminActiveNav = (string) ($adminActiveNav ?? '');
$adminPageActions = (string) ($adminPageActions ?? '');

$adminUserId = (int) ($adminUser['id'] ?? 0);
$adminDisplayName = trim((string) ($adminUser['display_name'] ?? ''));
$adminUsername = trim((string) ($adminUser['username'] ?? ''));

if ($adminDisplayName === '') {
    $adminDisplayName = $adminUsername !== ''
        ? $adminUsername
        : 'Admin';
}

function admin_shell_nav_class(
    string $key,
    string $active
): string {
    return $key === $active
        ? 'admin-nav-link is-active'
        : 'admin-nav-link';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= moderation_e($adminPageTitle) ?> | Llama Scout Admin</title>

    <meta name="robots" content="noindex,nofollow,noarchive">

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
            } catch (error) {
                // Preferences are optional.
            }
        })();
    </script>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= moderation_e($siteUrl . '/css/site.css') ?>"
    >

    <link
        rel="stylesheet"
        href="<?= moderation_e($siteUrl . '/css/admin.css') ?>"
    >

    <link
        rel="stylesheet"
        href="<?= moderation_e($siteUrl . '/css/admin-moderation.css') ?>"
    >

    <?php if (!empty($adminNeedsPhotoUploader)): ?>
        <link
            rel="stylesheet"
            href="<?= moderation_e($siteUrl . '/css/photo-uploader.css') ?>"
        >
    <?php endif; ?>
</head>

<body class="admin-body">

<a class="skip-link" href="#admin-main">Skip to admin content</a>

<div class="admin-app">

    <aside class="admin-sidebar" id="admin-sidebar">

        <div class="admin-sidebar-top">

            <a
                class="admin-brand"
                href="<?= moderation_e($adminUrl . '/') ?>"
                aria-label="Llama Scout Admin dashboard"
            >
                <img
                    src="<?= moderation_e($siteUrl . '/images/logo.png') ?>"
                    alt="Llama Scout"
                >
                <span>Admin</span>
            </a>

            <button
                class="admin-sidebar-close"
                type="button"
                data-admin-menu-close
                aria-label="Close admin navigation"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>

        </div>


        <nav class="admin-nav" aria-label="Admin">

            <p class="admin-nav-label">Overview</p>

            <a
                class="<?= admin_shell_nav_class('dashboard', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/') ?>"
            >
                <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                <span>Dashboard</span>
            </a>


            <p class="admin-nav-label">Places</p>

            <a
                class="<?= admin_shell_nav_class('moderation', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/moderation.php') ?>"
            >
                <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                <span>Moderation</span>
                <?php
                $moderationTotal =
                    (int) ($adminNavCounts['new_places'] ?? 0)
                    + (int) ($adminNavCounts['updates'] ?? 0)
                    + (int) ($adminNavCounts['reports'] ?? 0);
                ?>
                <?php if ($moderationTotal > 0): ?>
                    <b><?= $moderationTotal ?></b>
                <?php endif; ?>
            </a>

            <a
                class="<?= admin_shell_nav_class('places', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/places.php') ?>"
            >
                <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                <span>Places</span>
            </a>

            <a
                class="<?= admin_shell_nav_class('verifications', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/verifications.php') ?>"
            >
                <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
                <span>Verifications</span>
            </a>

            <a
                class="<?= admin_shell_nav_class('submissions', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/submissions.php') ?>"
            >
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <span>New Places</span>
                <?php if (!empty($adminNavCounts['new_places'])): ?>
                    <b><?= (int) $adminNavCounts['new_places'] ?></b>
                <?php endif; ?>
            </a>

            <a
                class="<?= admin_shell_nav_class('updates', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/updates.php') ?>"
            >
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                <span>Place Updates</span>
                <?php if (!empty($adminNavCounts['updates'])): ?>
                    <b><?= (int) $adminNavCounts['updates'] ?></b>
                <?php endif; ?>
            </a>

            <a
                class="<?= admin_shell_nav_class('reports', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/reports.php') ?>"
            >
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <span>Reports</span>
                <?php if (!empty($adminNavCounts['reports'])): ?>
                    <b><?= (int) $adminNavCounts['reports'] ?></b>
                <?php endif; ?>
            </a>


            <p class="admin-nav-label">People</p>

            <a
                class="<?= admin_shell_nav_class('users', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/users.php') ?>"
            >
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <span>Users</span>
            </a>

            <a
                class="<?= admin_shell_nav_class('scouts', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/scouts.php') ?>"
            >
                <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
                <span>Scouts</span>
                <?php if (!empty($adminNavCounts['scout_reviews'])): ?>
                    <b><?= (int) $adminNavCounts['scout_reviews'] ?></b>
                <?php endif; ?>
            </a>


            <p class="admin-nav-label">Commerce</p>

            <a
                class="<?= admin_shell_nav_class('products', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/products.php') ?>"
            >
                <i class="fa-solid fa-shirt" aria-hidden="true"></i>
                <span>Products</span>
            </a>

            <a
                class="<?= admin_shell_nav_class('orders', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/orders.php') ?>"
            >
                <i class="fa-solid fa-box" aria-hidden="true"></i>
                <span>Orders</span>
                <?php if (!empty($adminNavCounts['orders'])): ?>
                    <b><?= (int) $adminNavCounts['orders'] ?></b>
                <?php endif; ?>
            </a>


            <p class="admin-nav-label">Configuration</p>

            <a
                class="<?= admin_shell_nav_class('policies', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/policies.php') ?>"
            >
                <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
                <span>Policies</span>
            </a>

            <a
                class="<?= admin_shell_nav_class('points', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/points.php') ?>"
            >
                <i class="fa-solid fa-star" aria-hidden="true"></i>
                <span>Points</span>
            </a>

            <a
                class="<?= admin_shell_nav_class('system', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/system.php') ?>"
            >
                <i class="fa-solid fa-gears" aria-hidden="true"></i>
                <span>System</span>
            </a>

            <a
                class="<?= admin_shell_nav_class('audit', $adminActiveNav) ?>"
                href="<?= moderation_e($adminUrl . '/audit.php') ?>"
            >
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <span>Audit Log</span>
            </a>

        </nav>


        <div class="admin-sidebar-bottom">

            <a
                class="admin-sidebar-utility"
                href="<?= moderation_e($siteUrl . '/') ?>"
                target="_blank"
                rel="noopener"
            >
                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                View public site
            </a>

            <a
                class="admin-sidebar-utility"
                href="<?= moderation_e($accountUrl . '/') ?>"
            >
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                My account
            </a>

        </div>

    </aside>


    <div class="admin-sidebar-scrim" data-admin-menu-close></div>


    <div class="admin-workspace">

        <header class="admin-topbar">

            <button
                class="admin-menu-button"
                type="button"
                data-admin-menu-open
                aria-controls="admin-sidebar"
                aria-expanded="false"
                aria-label="Open admin navigation"
            >
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>

            <div class="admin-topbar-title">
                <span><?= moderation_e($adminPageEyebrow) ?></span>
                <strong><?= moderation_e($adminPageTitle) ?></strong>
            </div>

            <div class="admin-topbar-user">
                <span class="admin-user-avatar" aria-hidden="true">
                    <?= moderation_e(strtoupper(substr($adminDisplayName, 0, 1))) ?>
                </span>

                <div>
                    <strong><?= moderation_e($adminDisplayName) ?></strong>
                    <?php if ($adminUsername !== ''): ?>
                        <span>@<?= moderation_e($adminUsername) ?></span>
                    <?php endif; ?>
                </div>
            </div>

        </header>


        <main class="admin-main" id="admin-main">

            <header class="admin-page-header">
                <div>
                    <p><?= moderation_e($adminPageEyebrow) ?></p>
                    <h1><?= moderation_e($adminPageTitle) ?></h1>
                </div>

                <?php if ($adminPageActions !== ''): ?>
                    <div class="admin-page-actions">
                        <?= $adminPageActions ?>
                    </div>
                <?php endif; ?>
            </header>
