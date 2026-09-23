<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/compare-reports.php';
require_once __DIR__ . '/app/place-history-timeline.php';

function place_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function place_image_url(?string $src): string
{
    $src = trim((string) $src);
    if ($src === '') return '';
    if (preg_match('~^https?://~i', $src)) return $src;
    return '/' . ltrim($src, '/');
}

function place_yes_no(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return (int) $value === 1 ? 'Yes' : 'No';
}

function place_report_rating_item(string $label, mixed $value): void
{
    if ($value === null || $value === '') {
        return;
    }

    $rating = (int) $value;

    if ($rating < 1 || $rating > 5) {
        return;
    }
    ?>

    <div class="scout-report-item scout-report-rating-item">

        <div class="scout-rating-content">
            <span><?= place_h($label) ?></span>
            <strong><?= place_h($rating) ?>/5</strong>
        </div>

        <div
            class="scout-rating-dots"
            aria-label="<?= place_h($rating) ?> out of 5"
        >
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span
                    class="scout-rating-dot<?= $i <= $rating ? ' is-filled' : '' ?>"
                    aria-hidden="true"
                ></span>
            <?php endfor; ?>
        </div>

    </div>

    <?php
}


function place_report_item(string $label, mixed $value, ?string $icon = null): void
{
    if ($value === null || $value === '') {
        return;
    }
    ?>
    <div class="scout-report-item scout-report-value-item">

        <div class="scout-report-value-content">
            <span><?= place_h($label) ?></span>
            <strong><?= place_h($value) ?></strong>
        </div>

        <?php if ($icon): ?>
            <i aria-hidden="true">
                <?= llama_icon($icon, ['class' => 'scout-report-value-icon']) ?>
            </i>
        <?php endif; ?>

    </div>
    <?php
}

$slug = trim((string) ($_GET['slug'] ?? ''));
$user = current_user();
$userId = !empty($user['id']) ? (int) $user['id'] : 0;
$hasGlobalMemberAccess = user_has_member_access($userId > 0 ? $userId : null);
$hasContributorPlaceAccess = false;
$hasMemberAccess = $hasGlobalMemberAccess;
$place = null;

if ($slug !== '') {
    if ($hasGlobalMemberAccess) {
        $place = place_member_by_slug($slug);
    } else {
        $place = place_public_by_slug($slug);

        if (
            $place
            && $userId > 0
            && user_has_place_complete_access(
                (int) $place['id'],
                $userId
            )
        ) {
            $hasContributorPlaceAccess = true;
            $hasMemberAccess = true;
            $place = place_member_by_slug($slug);
        }
    }
}

if (!$place) {
    http_response_code(404);
    $pageTitle = 'Place Not Found | Llama Scout';
    require __DIR__ . '/partials/header.php';
    ?>
    <section class="place-not-found">
        <h1>Place not found</h1>
        <p>This place is unavailable or has not been published.</p>
        <p><a href="/map.php">Return to the map</a></p>
    </section>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$isSaved = $userId > 0
    ? user_has_saved_place($userId, (int) $place['id'])
    : false;

$reportError = null;
$reportSubmitted = isset($_GET['reported']) && $_GET['reported'] === '1';
$reportOpen = isset($_GET['report']) && $_GET['report'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['saved_place_action'])) {
        $action = (string) ($_POST['saved_place_action'] ?? '');
        $csrfToken = (string) ($_POST['csrf_token'] ?? '');

        if (
            $userId < 1
            || !in_array($action, ['save', 'remove'], true)
            || !saved_places_verify_csrf($csrfToken)
        ) {
            http_response_code(400);
            exit('Invalid request.');
        }

        if ($action === 'save') {
            save_place_for_user(
                $userId,
                (int) $place['id'],
                (string) $place['slug'],
                (string) $place['name']
            );
        } else {
            remove_saved_place_for_user(
                $userId,
                (int) $place['id']
            );
        }

        header(
            'Location: /place.php?slug=' .
            rawurlencode((string) $place['slug']),
            true,
            303
        );
        exit;
    }

    if (isset($_POST['place_report_action'])) {
        $csrfToken = (string) ($_POST['csrf_token'] ?? '');
        $problemType = trim((string) ($_POST['problem_type'] ?? ''));
        $reportDetails = trim((string) ($_POST['report_details'] ?? ''));
        $reportPhotoToken = trim((string) ($_POST['photo_stage_token'] ?? ''));
        $reportPhotos = llama_photo_decode_form_photos(
            $_POST['photos_json'] ?? '[]'
        );

        if (
            $userId < 1
            || !llama_contributor_can(db(), $userId, 'report_problem')
            || !place_report_verify_csrf($csrfToken)
        ) {
            http_response_code(400);
            exit('Invalid request.');
        }

        try {
            submit_place_report(
                $userId,
                (int) $place['id'],
                $problemType,
                $reportDetails,
                $reportPhotoToken,
                $reportPhotos
            );

            header(
                'Location: /place.php?slug=' .
                rawurlencode((string) $place['slug']) .
                '&reported=1#report-place',
                true,
                303
            );
            exit;
        } catch (InvalidArgumentException $exception) {
            $reportError = $exception->getMessage();
        }
    }
}

$pageTitle = $place['name'] . ' | Llama Scout';

$locationParts = array_filter([
    $place['city'] ?? null,
    !empty($place['county']) ? $place['county'] . ' County' : null,
    $place['state'] ?? null,
]);

$amenityLabels = [
    'toilets' => ['at-toilet', 'Toilets'],
    'potable_water' => ['at-water-tap', 'Potable water'],
    'trash' => ['trash', 'Trash'],
    'fire_ring' => ['campfire', 'Metal fire ring'],
    'picnic_table' => ['picnic-table', 'Picnic table'],
    'bear_box' => ['bear', 'Bear box'],
    'showers' => ['at-shower-facilities', 'Showers'],
    'electricity' => ['at-electricity-socket', 'Electricity'],
    'dump_station' => ['caravan', 'Dump station'],
    'wifi' => ['at-wifi', 'WiFi'],
    'laundry' => ['wash-machine', 'Laundry'],
];

$details = $hasMemberAccess ? ($place['details'] ?? []) : [];
$connectivity = $hasMemberAccess ? ($place['connectivity'] ?? []) : [];
$sensory = $hasMemberAccess ? ($place['sensory'] ?? []) : [];
$sensoryDetails = $hasMemberAccess ? ($place['sensory_details'] ?? []) : [];
$rules = $hasMemberAccess ? ($place['rules'] ?? []) : [];
$experience = $hasMemberAccess ? ($place['experience'] ?? []) : [];
$db = db();

$reportCompleteness = [
    'percent' => 0,
    'answered' => 0,
    'total' => 0,
];

try {
    $completionSourcePlace =
        $hasMemberAccess
            ? $place
            : place_member_by_slug(
                (string) $place['slug']
            );

    if ($completionSourcePlace) {
        $publishedUnknownFields =
            llama_place_report_published_answer_state(
                $db,
                (int) $place['id']
            );

        $completionData =
            llama_place_report_data_from_published_place(
                $completionSourcePlace,
                $publishedUnknownFields
            );

        $completionPhotos =
            is_array(
                $completionSourcePlace['images']
                ?? null
            )
                ? count(
                    $completionSourcePlace['images']
                )
                : 0;

        $reportCompleteness =
            llama_place_report_completion_summary(
                llama_place_report_scoring_input_from_data(
                    $completionData
                ),
                $completionPhotos
            );

        /*
         * visited_at belongs to an individual contribution,
         * not to the live Place record. Published Places do
         * not store one permanent visit date, so it must not
         * reduce live Place completeness.
         */
        if (
            isset(
                $reportCompleteness['answered'],
                $reportCompleteness['total']
            )
            && (int) $reportCompleteness['total'] > 0
        ) {
            $liveCompletenessTotal =
                max(
                    0,
                    (int) $reportCompleteness['total'] - 1
                );

            $reportCompleteness['total'] =
                $liveCompletenessTotal;

            $reportCompleteness['percent'] =
                $liveCompletenessTotal > 0
                    ? min(
                        100,
                        (int) round(
                            100
                            * (
                                (int) $reportCompleteness['answered']
                                / $liveCompletenessTotal
                            )
                        )
                    )
                    : 0;
        }
    }
} catch (Throwable $exception) {
    error_log(
        'Llama Scout report completeness error for Place #'
        . (int) $place['id']
        . ': '
        . $exception->getMessage()
    );
}

$canCheckIn =
    $userId > 0
    && llama_contributor_can($db, $userId, 'check_in')
    && llama_place_checkin_has_coordinates(
        $db,
        (int) $place['id']
    );

$canSuggestUpdate =
    $userId > 0
    && user_has_place_complete_access(
        (int) $place['id'],
        $userId
    )
    && llama_contributor_can(
        $db,
        $userId,
        'submit_update'
    );

$canReportProblem =
    $userId > 0
    && llama_contributor_can(
        $db,
        $userId,
        'report_problem'
    );

$placeHistoryTimeline = [];

try {
    $placeHistoryTimeline =
        llama_place_history_timeline(
            $db,
            (int) $place['id'],
            (string) $place['slug']
        );
} catch (Throwable $exception) {
    error_log(
        'Llama Scout public Place history error: '
        . $exception->getMessage()
    );
}

$galleryImages = [];
$heroImage = null;

if ($hasMemberAccess && !empty($place['images'])) {
    $galleryImages = array_values(array_filter(
        $place['images'],
        static fn(array $image): bool =>
            trim((string) ($image['src'] ?? '')) !== ''
    ));

    foreach ($galleryImages as $image) {
        if (!empty($image['is_featured'])) {
            $heroImage = $image;
            break;
        }
    }

    $heroImage ??= $galleryImages[0] ?? null;
} elseif (!empty($place['featured_image'])) {
    $heroImage = $place['featured_image'];
}

$canonicalUrl =
    'https://llamascout.com/place.php?slug=' .
    rawurlencode(
        (string) $place['slug']
    );

/*
 * LS-018 / LS-019:
 * Use the current editable Place Description for SEO and social metadata.
 * Legacy public_summary values no longer override current Place content.
 */
$pageDescription =
    trim(
        (string) (
            $place['description']
            ?? ''
        )
    );

if ($pageDescription === '') {
    $pageDescription =
        'Explore ' .
        (string) $place['name'] .
        ' on Llama Scout.';
}

$pageSocialImage = '';

if ($heroImage) {
    $pageSocialImage =
        place_image_url(
            (string) (
                $heroImage['src']
                ?? ''
            )
        );

    if (
        $pageSocialImage !== ''
        && !str_starts_with(
            $pageSocialImage,
            'http://'
        )
        && !str_starts_with(
            $pageSocialImage,
            'https://'
        )
    ) {
        $pageSocialImage =
            'https://llamascout.com/' .
            ltrim(
                $pageSocialImage,
                '/'
            );
    }
}

require __DIR__ . '/partials/header.php';
?>

<article class="place-page">

    <?php require __DIR__ . '/partials/place/hero.php'; ?>

    <?php require __DIR__ . '/partials/place/freshness.php'; ?>

    <?php require __DIR__ . '/partials/place/gallery.php'; ?>

    <?php require __DIR__ . '/partials/place/facts-weather-amenities.php'; ?>

    <?php require __DIR__ . '/partials/place/member-content.php'; ?>

    <?php require __DIR__ . '/partials/place/history.php'; ?>

    <?php require __DIR__ . '/partials/place/report-problem.php'; ?>

</article>

<script src="/js/place-gallery.js"></script>
<script src="/js/place.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
