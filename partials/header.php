<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/shop-cart.php';
require_once dirname(__DIR__) . '/app/promotion-banner.php';
require_once dirname(__DIR__) . '/app/page-styles.php';

$user = current_user();
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

$userId = !empty($user['id']) ? (int) $user['id'] : 0;
$isOwner = $userId > 0 && user_has_role('owner', $userId);
$isAdmin = $userId > 0 && user_has_role('admin', $userId);
$canAccessAdmin = $isOwner || $isAdmin;

$pageRobots = trim((string) ($pageRobots ?? ''));
$pageDescription = trim((string) ($pageDescription ?? ''));
$canonicalUrl = trim((string) ($canonicalUrl ?? ''));
$pageSocialImage = trim((string) ($pageSocialImage ?? ''));
$pageSocialType = trim((string) ($pageSocialType ?? 'website'));

if ($pageSocialType === '') {
    $pageSocialType = 'website';
}

$requestScript = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

$pageStylesheets = llama_merge_page_styles(
    llama_page_styles($requestScript),
    $pageStyles ?? []
);

$activeWebsitePromotion = null;

try {
    $activeWebsitePromotion = llama_active_website_promotion(db());
} catch (Throwable) {
    $activeWebsitePromotion = null;
}

$promotionBannerText = $activeWebsitePromotion
    ? llama_promotion_banner_text($activeWebsitePromotion)
    : '';

$promotionBannerUrl = $activeWebsitePromotion
    ? llama_promotion_banner_url($activeWebsitePromotion, $siteUrl)
    : '';

$promotionBannerEndsAt = $activeWebsitePromotion
    ? llama_promotion_banner_end_iso(
        (string) ($activeWebsitePromotion['ends_at'] ?? '')
    )
    : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars($pageTitle ?? 'Llama Scout', ENT_QUOTES, 'UTF-8') ?></title>

    <?php if ($pageDescription !== ''): ?>
        <meta
            name="description"
            content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>"
        >
    <?php endif; ?>

    <?php if ($pageRobots !== ''): ?>
        <meta
            name="robots"
            content="<?= htmlspecialchars($pageRobots, ENT_QUOTES, 'UTF-8') ?>"
        >
    <?php endif; ?>

    <?php if ($canonicalUrl !== ''): ?>
        <link
            rel="canonical"
            href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>"
        >
    <?php endif; ?>

    <meta
        property="og:title"
        content="<?= htmlspecialchars($pageTitle ?? 'Llama Scout', ENT_QUOTES, 'UTF-8') ?>"
    >

    <meta
        property="og:type"
        content="<?= htmlspecialchars($pageSocialType, ENT_QUOTES, 'UTF-8') ?>"
    >

    <?php if ($pageDescription !== ''): ?>
        <meta
            property="og:description"
            content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>"
        >
    <?php endif; ?>

    <?php if ($canonicalUrl !== ''): ?>
        <meta
            property="og:url"
            content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>"
        >
    <?php endif; ?>

    <?php if ($pageSocialImage !== ''): ?>
        <meta
            property="og:image"
            content="<?= htmlspecialchars($pageSocialImage, ENT_QUOTES, 'UTF-8') ?>"
        >
        <meta
            name="twitter:card"
            content="summary_large_image"
        >
    <?php else: ?>
        <meta
            name="twitter:card"
            content="summary"
        >
    <?php endif; ?>

    <meta
        name="twitter:title"
        content="<?= htmlspecialchars($pageTitle ?? 'Llama Scout', ENT_QUOTES, 'UTF-8') ?>"
    >

    <?php if ($pageDescription !== ''): ?>
        <meta
            name="twitter:description"
            content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>"
        >
    <?php endif; ?>

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
                // Accessibility preferences are optional.
            }
        })();
    </script>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($siteUrl . '/css/site.css', ENT_QUOTES, 'UTF-8') ?>"
    >

    <?php foreach ($pageStylesheets as $stylesheet): ?>
        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(
                $siteUrl . '/css/' . $stylesheet,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
    <?php endforeach; ?>
</head>

<body>

<a class="skip-link" href="#main-content">
    Skip to main content
</a>

<?php if ($activeWebsitePromotion && $promotionBannerText !== ''): ?>
<div
    class="site-promotion-banner"
    data-promotion-id="<?= (int) ($activeWebsitePromotion['id'] ?? 0) ?>"
>
    <div class="site-promotion-banner-inner">
        <a
            class="site-promotion-banner-link"
            href="<?= htmlspecialchars($promotionBannerUrl, ENT_QUOTES, 'UTF-8') ?>"
        >
            <?= htmlspecialchars($promotionBannerText, ENT_QUOTES, 'UTF-8') ?>
        </a>

        <?php if (
            !empty($activeWebsitePromotion['show_countdown'])
            && $promotionBannerEndsAt !== ''
        ): ?>
            <span
                class="site-promotion-countdown"
                data-promotion-countdown
                data-ends-at="<?= htmlspecialchars($promotionBannerEndsAt, ENT_QUOTES, 'UTF-8') ?>"
                aria-label="Promotion time remaining"
            >
                <span class="site-promotion-countdown-label">
                    Ends in
                </span>
                <strong data-promotion-countdown-value>--:--:--</strong>
            </span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<header class="site-header" id="site-header">

    <div class="site-header-inner">

        <a
            class="site-brand"
            href="<?= htmlspecialchars($siteUrl . '/', ENT_QUOTES, 'UTF-8') ?>"
            aria-label="Llama Scout home"
        >
            <img
                src="<?= htmlspecialchars($siteUrl . '/images/logo.png', ENT_QUOTES, 'UTF-8') ?>"
                alt="Llama Scout"
            >
        </a>


        <nav
            class="site-nav"
            id="site-navigation"
            aria-label="Main navigation"
        >

            <a href="<?= htmlspecialchars($siteUrl . '/', ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-house" aria-hidden="true"></i>
                <span>Home</span>
            </a>

            <a href="<?= htmlspecialchars($siteUrl . '/map.php', ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                <span>Map</span>
            </a>

            <a href="<?= htmlspecialchars($siteUrl . '/field-guides', ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-compass" aria-hidden="true"></i>
                <span>Guides</span>
            </a>

            <a href="<?= htmlspecialchars($siteUrl . '/about.php', ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>About</span>
            </a>

            <a href="<?= htmlspecialchars($siteUrl . '/membership', ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                <span>Membership</span>
            </a>

            <a href="<?= htmlspecialchars($siteUrl . '/shop.php', ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
                <span>Shop</span>
            </a>

            <a
                class="site-cart-link"
                href="<?= htmlspecialchars($siteUrl . '/cart.php', ENT_QUOTES, 'UTF-8') ?>"
            >
                <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                <span>Cart</span>

                <?php if (shop_cart_count() > 0): ?>
                    <b class="site-cart-count">
                        <?= shop_cart_count() ?>
                    </b>
                <?php endif; ?>
            </a>

            <?php if ($user): ?>

                <a href="<?= htmlspecialchars($siteUrl . '/add-place.php', ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <span>Add a Place</span>
                </a>

                <a href="<?= htmlspecialchars($accountUrl . '/', ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <span>Account</span>
                </a>

                <?php if ($canAccessAdmin): ?>
                    <a
                        class="site-nav-admin"
                        href="<?= htmlspecialchars($adminUrl . '/', ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                        <span>Admin</span>
                    </a>
                <?php endif; ?>

            <?php else: ?>

                <a href="<?= htmlspecialchars($accountUrl . '/login.php', ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                    <span>Sign in</span>
                </a>

            <?php endif; ?>


            <button
                class="accessibility-button accessibility-button-desktop"
                type="button"
                id="accessibility-button"
                aria-controls="accessibility-panel"
                aria-expanded="false"
            >
                <i class="fa-solid fa-universal-access" aria-hidden="true"></i>
                <span class="visually-hidden">Accessibility settings</span>
            </button>

        </nav>


        <div class="site-mobile-controls">

            <button
                class="accessibility-button accessibility-button-mobile"
                type="button"
                id="accessibility-button-mobile"
                aria-controls="accessibility-panel"
                aria-expanded="false"
            >
                <i class="fa-solid fa-universal-access" aria-hidden="true"></i>
                <span class="visually-hidden">Accessibility settings</span>
            </button>

            <button
                class="site-menu-toggle"
                type="button"
                id="site-menu-toggle"
                aria-controls="site-navigation"
                aria-expanded="false"
                aria-label="Open menu"
            >
                <span class="site-menu-toggle-icon" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>

                <span class="visually-hidden" id="site-menu-toggle-label">
                    Open menu
                </span>
            </button>

        </div>

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

<script
    src="<?= htmlspecialchars($siteUrl . '/js/promotion-banner.js', ENT_QUOTES, 'UTF-8') ?>"
    defer
></script>
