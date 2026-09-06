<?php

declare(strict_types=1);

/**
 * Return public stylesheets needed by the current request.
 *
 * site.css is intentionally not returned here because it is the
 * universal foundation and is loaded directly by partials/header.php.
 *
 * Callers may also provide $pageStyles before requiring the header.
 */
function llama_page_styles(string $scriptName = ''): array
{
    $scriptName = str_replace('\\', '/', trim($scriptName));
    $path = strtolower($scriptName);
    $basename = basename($path);

    $styles = [
        'mobile-menu.css',
        'footer.css',
        'promotion-banner.css',
    ];

    $isAccount = str_contains($path, '/account/');

    /*
     * Public landing and editorial pages.
     */
    if (
        !$isAccount
        && in_array(
            $basename,
            [
                'index.php',
                'field-guides.php',
                'field-guide.php',
                'membership.php',
            ],
            true
        )
    ) {
        $styles[] = 'public-facing.css';
    }

    /*
     * Homepage and dedicated map.
     * The homepage contains the embedded discovery map.
     */
    if (
        !$isAccount
        && in_array(
            $basename,
            [
                'index.php',
                'map.php',
            ],
            true
        )
    ) {
        $styles[] = 'map.css';
    }

    /*
     * Place detail.
     */
    if (!$isAccount && $basename === 'place.php') {
        $styles[] = 'place-detail.css';
        $styles[] = 'site/features/place-shared.css';
        $styles[] = 'contributor-attribution.css';
        $styles[] = 'share.css';
    }

    /*
     * Public profiles, community directory, and badges.
     */
    if (
        !$isAccount
        && in_array(
            $basename,
            [
                'profile.php',
                'community.php',
                'badge.php',
            ],
            true
        )
    ) {
        $styles[] = 'community-profiles.css';

        if ($basename === 'profile.php') {
            $styles[] = 'site/features/public-profile-scout.css';
        }
    }

    /*
     * Public commerce.
     */
    if (
        !$isAccount
        && in_array(
            $basename,
            [
                'shop.php',
                'product.php',
                'cart.php',
                'checkout.php',
                'checkout-complete.php',
                'returns.php',
                'shop-fulfillment-routing.php',
            ],
            true
        )
    ) {
        $styles[] = 'shop.css';
    }

    /*
     * Legal and policy pages.
     */
    if (
        !$isAccount
        && in_array(
            $basename,
            [
                'privacy.php',
                'privacy-choices.php',
                'terms.php',
                'disclaimer.php',
                'accessibility.php',
                'safety.php',
                'returns.php',
            ],
            true
        )
    ) {
        $styles[] = 'legal.css';
    }

    if (!$isAccount && $basename === 'about.php') {
        $styles[] = 'about.css';
    }

    if (!$isAccount && $basename === 'contact.php') {
        $styles[] = 'support.css';
    }

    /*
     * Universal photo uploader only where the uploader is actually used.
     */
    if (!$isAccount && $basename === 'add-place.php') {
        $styles[] = 'site/pages/add-place.css';
        $styles[] = 'photo-uploader.css';
    }

    if (
        $isAccount
        && in_array(
            $basename,
            [
                'profile.php',
                'update-place.php',
            ],
            true
        )
    ) {
        $styles[] = 'photo-uploader.css';

        if ($basename === 'update-place.php') {
            $styles[] = 'account/pages/update-place.css';
        }
    }

    /*
     * Account pages own their Account-specific CSS themselves.
     * The shared header supplies only the universal shell here.
     */

    return array_values(array_unique($styles));
}

/**
 * Merge manifest styles with optional page-supplied styles.
 *
 * A page can set:
 *
 * $pageStyles = [
 *     'some-page.css',
 *     'some-feature.css',
 * ];
 *
 * before requiring partials/header.php.
 */
function llama_merge_page_styles(
    array $manifestStyles,
    mixed $pageStyles
): array {
    $styles = $manifestStyles;

    if (is_string($pageStyles)) {
        $pageStyles = [$pageStyles];
    }

    if (is_array($pageStyles)) {
        foreach ($pageStyles as $style) {
            if (!is_string($style)) {
                continue;
            }

            $style = trim($style);

            if ($style === '') {
                continue;
            }

            $style = ltrim($style, '/');

            if (str_starts_with($style, 'css/')) {
                $style = substr($style, 4);
            }

            if (
                $style === ''
                || str_contains($style, '..')
                || preg_match('/^[a-z0-9][a-z0-9_\/.-]*\.css$/i', $style) !== 1
            ) {
                continue;
            }

            $styles[] = $style;
        }
    }

    return array_values(array_unique($styles));
}
