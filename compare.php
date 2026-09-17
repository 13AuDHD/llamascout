<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/compare-places.php';
require_once __DIR__ . '/app/compare-reports.php';

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

$compareMode =
    strtolower(
        trim(
            (string) (
                $_GET['mode']
                ?? 'places'
            )
        )
    );

if (
    !in_array(
        $compareMode,
        [
            'places',
            'reports',
        ],
        true
    )
) {
    $compareMode =
        'places';
}


/* =========================================================
   COMPARE PLACES
   ========================================================= */

$requestedSlugs = [];
$placeOptions = [];
$comparePlaces = [];
$compareSlugs = [];
$compareUrl =
    'https://llamascout.com/compare.php';

if ($compareMode === 'places') {
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
}


/* =========================================================
   COMPARE REPORTS
   ========================================================= */

$reportPlaceSlug = '';
$reportPlace = null;
$reportVersions = [];
$requestedReportKeys = [];
$compareReports = [];
$compareReportKeys = [];

if ($compareMode === 'reports') {
    $placeOptions =
        llama_compare_place_options();

    $reportPlaceSlug =
        llama_compare_report_slug(
            $_GET['place']
            ?? ''
        );

    if (
        $hasMemberAccess
        && $reportPlaceSlug !== ''
    ) {
        $reportPlace =
            place_member_by_slug(
                $reportPlaceSlug
            );

        $reportVersions =
            llama_compare_report_versions(
                $reportPlaceSlug
            );

        $requestedReportKeys =
            llama_compare_requested_report_keys(
                $_GET['reports']
                ?? []
            );

        /*
         * Opening Compare Reports from a Place should be useful
         * immediately. If no explicit report selection exists yet,
         * select the two newest historical reports automatically.
         */
        if (
            !$requestedReportKeys
            && count($reportVersions)
                >= LLAMA_COMPARE_MIN_REPORTS
        ) {
            $requestedReportKeys =
                array_values(
                    array_map(
                        static fn (
                            array $report
                        ): string =>
                            (string) (
                                $report['key']
                                ?? ''
                            ),
                        array_slice(
                            $reportVersions,
                            0,
                            LLAMA_COMPARE_MIN_REPORTS
                        )
                    )
                );
        }

        $compareReports =
            llama_compare_reports_by_keys(
                $reportVersions,
                $requestedReportKeys
            );

        $compareReportKeys =
            array_values(
                array_map(
                    static fn (
                        array $report
                    ): string =>
                        (string) (
                            $report['key']
                            ?? ''
                        ),
                    $compareReports
                )
            );

        $compareUrl =
            llama_compare_reports_url(
                $reportPlaceSlug,
                $compareReportKeys
            );
    }
}


$pageTitle =
    $compareMode === 'reports'
        ? 'Compare Reports | Llama Scout'
        : 'Compare Places | Llama Scout';

$pageDescription =
    $compareMode === 'reports'
        ? 'Compare approved Llama Scout report history for one Place side by side over time.'
        : 'Compare Llama Scout Places side by side using complete member access, sensory, road, connectivity, amenity, and site data.';

$pageRobots =
    'noindex,nofollow';

require __DIR__ . '/partials/header.php';
?>

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
                    <?= $compareMode === 'reports'
                        ? 'Compare Reports.'
                        : 'Compare Places.' ?>
                </h1>

                <p>
                    <?php if ($compareMode === 'reports'): ?>
                        See how the same Place has been documented over time.
                        Put approved reports side by side to compare sensory
                        ratings, access, site conditions, connectivity, and
                        other details from different visits and contributors.
                    <?php else: ?>
                        Put up to four Places side by side and compare
                        the details that are hard to judge from a map:
                        road access, vehicle fit, sensory conditions,
                        connectivity, amenities, elevation, and more.
                    <?php endif; ?>
                </p>
            </div>

            <div class="compare-hero-actions">
                <a
                    class="compare-button is-secondary"
                    href="/map.php"
                >
                    <i aria-hidden="true"><?= llama_icon('map-pin') ?></i>

                    Back to Map
                </a>

                <?php if (
                    $hasMemberAccess
                    && (
                        (
                            $compareMode === 'places'
                            && count($comparePlaces) >= LLAMA_COMPARE_MIN_PLACES
                        )
                        || (
                            $compareMode === 'reports'
                            && count($compareReports) >= LLAMA_COMPARE_MIN_REPORTS
                        )
                    )
                ): ?>
                    <button
                        class="compare-button"
                        type="button"
                        data-copy-compare-link
                        data-compare-url="<?= llama_compare_h($compareUrl) ?>"
                    >
                        <i aria-hidden="true"><?= llama_icon('link') ?></i>

                        <span data-copy-label>
                            Copy Comparison Link
                        </span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="compare-shell compare-content">

        <nav
            class="compare-mode-switch"
            aria-label="Comparison type"
        >
            <a
                class="<?= $compareMode === 'places' ? 'is-active' : '' ?>"
                href="/compare.php"
            >
                <i aria-hidden="true"><?= llama_icon('arrows-diff') ?></i>
                Compare Places
            </a>

            <a
                class="<?= $compareMode === 'reports' ? 'is-active' : '' ?>"
                href="<?= $reportPlaceSlug !== ''
                    ? '/compare.php?mode=reports&place='
                        . rawurlencode($reportPlaceSlug)
                    : '/compare.php?mode=reports' ?>"
            >
                <i aria-hidden="true"><?= llama_icon('history') ?></i>
                Compare Reports
            </a>
        </nav>

        <?php if (!$hasMemberAccess): ?>
            <section class="compare-access-card">
                <span class="compare-access-icon">
                    <i aria-hidden="true"><?= llama_icon('lock') ?></i>
                </span>

                <div>
                    <p class="compare-eyebrow">
                        Member Feature
                    </p>

                    <h2>
                        Comparison uses the complete Scout Report.
                    </h2>

                    <p>
                        Exact locations, access details, sensory information,
                        connectivity, and historical report comparisons remain
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

        <?php elseif ($compareMode === 'reports'): ?>

            <?php
            require __DIR__
                . '/partials/compare/report-picker.php';
            ?>

            <?php if (
                count($compareReports)
                >= LLAMA_COMPARE_MIN_REPORTS
            ): ?>
                <?php
                require __DIR__
                    . '/partials/compare/report-table.php';
                ?>
            <?php elseif (
                $reportPlaceSlug !== ''
                && count($reportVersions)
                    < LLAMA_COMPARE_MIN_REPORTS
            ): ?>
                <section class="compare-empty">
                    <i aria-hidden="true"><?= llama_icon('history') ?></i>

                    <h2>
                        Not enough report history yet.
                    </h2>

                    <p>
                        This Place needs at least two approved historical
                        reports before they can be compared side by side.
                    </p>
                </section>
            <?php endif; ?>

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
                    Add at least one more Place to start comparing.
                </div>
            <?php else: ?>
                <section class="compare-empty">
                    <i aria-hidden="true"><?= llama_icon('arrows-diff') ?></i>

                    <h2>
                        Start with two Places.
                    </h2>

                    <p>
                        Search above and add the Places you want to compare.
                    </p>
                </section>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<script src="/js/compare.js"></script>

<?php if ($compareMode === 'reports'): ?>
    <script src="/js/compare-reports.js"></script>
<?php endif; ?>

<?php
require __DIR__
    . '/partials/footer.php';
?>
