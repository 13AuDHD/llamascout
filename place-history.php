<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/compare-reports.php';

function place_history_h(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$slug = llama_compare_report_slug(
    $_GET['place']
    ?? $_GET['slug']
    ?? ''
);

$requestedReportKey = strtolower(
    trim(
        (string) (
            $_GET['report']
            ?? ''
        )
    )
);

$validatedReportKeys =
    llama_compare_requested_report_keys(
        [$requestedReportKey]
    );

$reportKey =
    $validatedReportKeys[0]
    ?? '';

if ($slug === '' || $reportKey === '') {
    http_response_code(404);
    $pageTitle = 'Historical Report Not Found | Llama Scout';
    $pageRobots = 'noindex,follow';
    require __DIR__ . '/partials/header.php';
    ?>
    <main class="historical-report-page" id="main-content">
        <section class="historical-report-empty">
            <h1>Historical report not found</h1>
            <p>The requested Place report version is unavailable.</p>
            <a href="/map.php">Explore the map</a>
        </section>
    </main>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$user = current_user();
$userId = !empty($user['id'])
    ? (int) $user['id']
    : 0;
$hasGlobalMemberAccess =
    user_has_member_access(
        $userId > 0
            ? $userId
            : null
    );

$hasMemberAccess =
    $hasGlobalMemberAccess;

$livePlace =
    $hasGlobalMemberAccess
        ? place_member_by_slug($slug)
        : place_public_by_slug($slug);

if (
    !$hasGlobalMemberAccess
    && $livePlace
    && $userId > 0
    && user_has_place_complete_access(
        (int) $livePlace['id'],
        $userId
    )
) {
    $hasMemberAccess = true;
    $livePlace = place_member_by_slug($slug);
}

if (!$livePlace) {
    http_response_code(404);
    $pageTitle = 'Place Not Found | Llama Scout';
    $pageRobots = 'noindex,follow';
    require __DIR__ . '/partials/header.php';
    ?>
    <main class="historical-report-page" id="main-content">
        <section class="historical-report-empty">
            <h1>Place not found</h1>
            <p>This Place is unavailable or has not been published.</p>
            <a href="/map.php">Explore the map</a>
        </section>
    </main>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$reportVersions =
    llama_compare_report_versions(
        $slug
    );

$historicalReport =
    llama_compare_report_version_by_key(
        $reportVersions,
        $reportKey
    );

if (!$historicalReport) {
    http_response_code(404);
    $pageTitle = 'Historical Report Not Found | Llama Scout';
    $pageRobots = 'noindex,follow';
    require __DIR__ . '/partials/header.php';
    ?>
    <main class="historical-report-page" id="main-content">
        <section class="historical-report-empty">
            <h1>Historical report not found</h1>
            <p>This report version is no longer available in the Place history.</p>
            <a href="/place.php?slug=<?= place_history_h($slug) ?>">View live Place</a>
        </section>
    </main>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$historicalData =
    is_array(
        $historicalReport['data']
        ?? null
    )
        ? $historicalReport['data']
        : [];

$historicalName = trim(
    (string) (
        $historicalData['name']
        ?? $livePlace['name']
        ?? 'Place'
    )
);

if ($historicalName === '') {
    $historicalName =
        (string) (
            $livePlace['name']
            ?? 'Place'
        );
}

$reportDateRaw = trim(
    (string) (
        $historicalReport['report_date']
        ?? $historicalReport['reviewed_at']
        ?? ''
    )
);

$reportDateLabel =
    llama_compare_report_date_label(
        $reportDateRaw
    );

$reviewedAt = trim(
    (string) (
        $historicalReport['reviewed_at']
        ?? ''
    )
);

$reviewedDateLabel =
    $reviewedAt !== ''
        ? llama_compare_report_date_label(
            $reviewedAt
        )
        : '';

$contributorName = trim(
    (string) (
        $historicalReport['contributor_name']
        ?? 'Llama Scout contributor'
    )
);

$contributorUsername = trim(
    (string) (
        $historicalReport['contributor_username']
        ?? ''
    )
);

$contributionRecord = [
    'user_id' =>
        (int) (
            $historicalReport['user_id']
            ?? 0
        ),
    'role_at_time' =>
        (string) (
            $historicalReport['role_at_time']
            ?? 'user'
        ),
];

$contributionLevel =
    llama_contribution_level_for_record(
        db(),
        $contributionRecord
    );

$locationParts = array_filter([
    trim((string) ($historicalData['city'] ?? $livePlace['city'] ?? '')),
    !empty($historicalData['county'] ?? $livePlace['county'] ?? null)
        ? trim((string) ($historicalData['county'] ?? $livePlace['county'])) . ' County'
        : null,
    trim((string) ($historicalData['state'] ?? $livePlace['state'] ?? '')),
]);

$currentIndex = null;

foreach ($reportVersions as $index => $version) {
    if (
        (string) (
            $version['key']
            ?? ''
        ) === $reportKey
    ) {
        $currentIndex = $index;
        break;
    }
}

$newerReport =
    $currentIndex !== null
    && $currentIndex > 0
        ? $reportVersions[
            $currentIndex - 1
        ]
        : null;

$olderReport =
    $currentIndex !== null
    && isset(
        $reportVersions[
            $currentIndex + 1
        ]
    )
        ? $reportVersions[
            $currentIndex + 1
        ]
        : null;

$livePlaceUrl =
    '/place.php?slug=' .
    rawurlencode($slug);

$compareReportsUrl =
    llama_compare_reports_url(
        $slug,
        [$reportKey]
    );

$pageTitle =
    $historicalName .
    ' Historical Report | Llama Scout';

$pageDescription =
    'View a historical Llama Scout Place report for ' .
    $historicalName .
    ' from ' .
    $reportDateLabel .
    '.';

$pageRobots = 'noindex,follow';
$canonicalUrl =
    'https://llamascout.com/place.php?slug=' .
    rawurlencode($slug);

require __DIR__ . '/partials/header.php';
?>

<main class="historical-report-page" id="main-content">

    <section class="historical-report-banner" role="status">
        <div class="historical-report-shell historical-report-banner-inner">
            <div>
                <p class="historical-report-eyebrow">Historical Place Report</p>
                <strong>
                    You are currently viewing this Place report from
                    <?= place_history_h($reportDateLabel) ?>.
                </strong>
            </div>

            <a class="historical-report-button" href="<?= place_history_h($livePlaceUrl) ?>">
                <i aria-hidden="true"><?= llama_icon('arrow-right') ?></i>
                View live Place
            </a>
        </div>
    </section>

    <section class="historical-report-hero">
        <div class="historical-report-shell">
            <a class="historical-report-back" href="<?= place_history_h($livePlaceUrl) ?>#place-history">
                <i aria-hidden="true"><?= llama_icon('arrow-left') ?></i>
                Back to Place history
            </a>

            <div class="historical-report-heading-grid">
                <div>
                    <p class="historical-report-eyebrow">
                        <?= place_history_h(
                            (string) (
                                $historicalReport['label']
                                ?? 'Historical report'
                            )
                        ) ?>
                    </p>

                    <h1><?= place_history_h($historicalName) ?></h1>

                    <?php if ($locationParts): ?>
                        <p class="historical-report-location">
                            <i aria-hidden="true"><?= llama_icon('map-pin') ?></i>
                            <?= place_history_h(implode(', ', $locationParts)) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="historical-report-version-card">
                    <span class="historical-report-level is-<?= place_history_h($contributionLevel) ?>">
                        <i aria-hidden="true"><?= llama_icon(llama_contribution_level_icon($contributionLevel)) ?></i>
                        <?= place_history_h(llama_contribution_level_label($contributionLevel)) ?>
                    </span>

                    <dl>
                        <div>
                            <dt>Report date</dt>
                            <dd><?= place_history_h($reportDateLabel) ?></dd>
                        </div>

                        <?php if ($reviewedDateLabel !== ''): ?>
                            <div>
                                <dt>Approved</dt>
                                <dd><?= place_history_h($reviewedDateLabel) ?></dd>
                            </div>
                        <?php endif; ?>

                        <div>
                            <dt>Contributor</dt>
                            <dd>
                                <?php if ($contributorUsername !== ''): ?>
                                    <a href="/<?= rawurlencode($contributorUsername) ?>">
                                        <?= place_history_h($contributorName) ?>
                                    </a>
                                <?php else: ?>
                                    <?= place_history_h($contributorName) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </section>

    <div class="historical-report-shell historical-report-navigation">
        <div>
            <?php if ($olderReport): ?>
                <a
                    class="historical-report-nav-link"
                    href="<?= place_history_h(
                        llama_historical_report_url(
                            $slug,
                            (string) $olderReport['key']
                        )
                    ) ?>"
                >
                    <i aria-hidden="true"><?= llama_icon('arrow-left') ?></i>
                    Older report
                </a>
            <?php endif; ?>
        </div>

        <div class="historical-report-navigation-actions">
            <?php if ($hasMemberAccess): ?>
                <a class="historical-report-nav-link" href="<?= place_history_h($compareReportsUrl) ?>">
                    <i aria-hidden="true"><?= llama_icon('arrows-diff') ?></i>
                    Compare Reports
                </a>
            <?php endif; ?>

            <?php if ($newerReport): ?>
                <a
                    class="historical-report-nav-link"
                    href="<?= place_history_h(
                        llama_historical_report_url(
                            $slug,
                            (string) $newerReport['key']
                        )
                    ) ?>"
                >
                    Newer report
                    <i aria-hidden="true"><?= llama_icon('arrow-right') ?></i>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($hasMemberAccess): ?>
        <?php
        $placeReportData = $historicalData;
        $placeReportReadMode = 'historical-report';
        ?>

        <link
            rel="stylesheet"
            href="/css/site/features/place-report-form.css"
        >

        <section class="historical-report-content scout-report">
            <header class="scout-report-header">
                <div>
                    <p class="eyebrow">Historical details</p>
                    <h2>Scout Report</h2>
                </div>
            </header>

            <?php
            require __DIR__ . '/partials/place-report/read-only.php';
            ?>
        </section>

    <?php else: ?>
        <section class="historical-report-access-card">
            <span class="historical-report-access-icon">
                <i aria-hidden="true"><?= llama_icon('lock') ?></i>
            </span>

            <div>
                <p class="historical-report-eyebrow">Complete Access</p>
                <h2>The historical Scout Report is available with Complete Access.</h2>
                <p>
                    You can see that this report version exists and who contributed it,
                    while exact locations and detailed historical Place information stay
                    behind the same access rules as the live report.
                </p>

                <a class="historical-report-button" href="/membership.php">
                    View Membership
                </a>
            </div>
        </section>
    <?php endif; ?>

    <section class="historical-report-note">
        <div class="historical-report-shell">
            <i aria-hidden="true"><?= llama_icon('history') ?></i>
            <div>
                <strong>This is a historical version.</strong>
                <p>
                    Conditions, access, rules, amenities, and other Place details may
                    have changed since this report was recorded. Use the live Place page
                    for the most current information.
                </p>
            </div>
        </div>
    </section>

</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
