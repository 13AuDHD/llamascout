<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/compare-places.php';

$currentUser =
    current_user();

$currentUserId =
    is_array($currentUser)
        ? (int) (
            $currentUser['id']
            ?? 0
        )
        : 0;

if ($currentUserId < 1) {
    $returnUrl =
        '/compare.php';

    if (
        !empty(
            $_SERVER['QUERY_STRING']
        )
    ) {
        $returnUrl .=
            '?'
            . (string) $_SERVER[
                'QUERY_STRING'
            ];
    }

    header(
        'Location: https://account.llamascout.com/login.php?return='
        . rawurlencode(
            $returnUrl
        )
    );

    exit;
}

$hasMemberAccess =
    user_has_member_access(
        $currentUserId
    );

$requestedSlugs =
    llama_compare_requested_slugs(
        $_GET['places']
        ?? []
    );

$placeOptions =
    llama_compare_place_options();

$comparePlaces =
    $hasMemberAccess
        ? llama_compare_places(
            $requestedSlugs
        )
        : [];

$compareSlugs =
    array_values(
        array_map(
            static fn (
                array $place
            ): string =>
                (string) (
                    $place['slug']
                    ?? ''
                ),
            $comparePlaces
        )
    );

$compareUrl =
    llama_compare_url(
        $compareSlugs
    );

$pageTitle =
    'Compare Places | Llama Scout';

$pageDescription =
    'Compare Llama Scout Places side by side using complete member access, sensory, road, connectivity, amenity, and site data.';

$pageRobots =
    'noindex,nofollow';

require __DIR__ . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="/css/site/pages/compare.css"
>


<main
    id="main-content"
    class="compare-page"
>

    <header class="compare-hero">

        <div class="compare-shell">

            <div class="compare-hero-copy">

                <p class="compare-eyebrow">
                    Complete Access
                </p>

                <h1>
                    Compare Places.
                </h1>

                <p>
                    Put up to four Places side by side and compare
                    the details that are hard to judge from a map:
                    road access, vehicle fit, sensory conditions,
                    connectivity, amenities, elevation, and more.
                </p>

            </div>


            <div class="compare-hero-actions">

                <a
                    class="compare-button is-secondary"
                    href="/map.php"
                >
                    <i
                        class="fa-solid fa-map-location-dot"
                        aria-hidden="true"
                    ></i>

                    Back to Map
                </a>

                <?php if (
                    $hasMemberAccess
                    && count($comparePlaces) >= 2
                ): ?>

                    <button
                        class="compare-button"
                        type="button"
                        data-copy-compare-link
                        data-compare-url="<?= llama_compare_h($compareUrl) ?>"
                    >
                        <i
                            class="fa-solid fa-link"
                            aria-hidden="true"
                        ></i>

                        <span data-copy-label>
                            Copy Comparison Link
                        </span>
                    </button>

                <?php endif; ?>

            </div>

        </div>

    </header>


    <div class="compare-shell compare-content">

        <?php if (!$hasMemberAccess): ?>

            <section class="compare-access-card">

                <span class="compare-access-icon">
                    <i
                        class="fa-solid fa-lock"
                        aria-hidden="true"
                    ></i>
                </span>

                <div>
                    <p class="compare-eyebrow">
                        Member Feature
                    </p>

                    <h2>
                        Place comparison uses the complete Scout Report.
                    </h2>

                    <p>
                        Exact locations, access details, sensory information,
                        connectivity, and the other comparison data remain
                        part of Complete Access.
                    </p>

                    <a
                        class="compare-button"
                        href="/membership.php"
                    >
                        See Membership
                    </a>
                </div>

            </section>

        <?php else: ?>

            <?php
            require __DIR__
                . '/partials/compare/picker.php';
            ?>


            <?php if (
                count($comparePlaces)
                >= LLAMA_COMPARE_MIN_PLACES
            ): ?>

                <?php
                require __DIR__
                    . '/partials/compare/table.php';
                ?>

            <?php elseif ($requestedSlugs): ?>

                <div
                    class="compare-notice"
                    role="status"
                >
                    Select at least two available Places
                    to start a comparison.
                </div>

            <?php else: ?>

                <section class="compare-empty">

                    <i
                        class="fa-solid fa-code-compare"
                        aria-hidden="true"
                    ></i>

                    <h2>
                        Start with two Places.
                    </h2>

                    <p>
                        Search the list above, choose two to four,
                        then build the comparison.
                    </p>

                </section>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</main>


<script src="/js/compare.js"></script>

<?php
require __DIR__
    . '/partials/footer.php';
?>
