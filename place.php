<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

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
            <i
                class="fa-solid <?= place_h($icon) ?> scout-report-value-icon"
                aria-hidden="true"
            ></i>
        <?php endif; ?>

    </div>
    <?php
}

$slug = trim((string) ($_GET['slug'] ?? ''));
$user = current_user();
$userId = !empty($user['id']) ? (int) $user['id'] : 0;
$hasMemberAccess = user_has_member_access();
$place = null;

if ($slug !== '') {
    $place = $hasMemberAccess
        ? place_member_by_slug($slug)
        : place_public_by_slug($slug);
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

        if ($userId < 1 || !place_report_verify_csrf($csrfToken)) {
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
    'toilets' => ['fa-restroom', 'Toilets'],
    'potable_water' => ['fa-faucet-drip', 'Potable water'],
    'trash' => ['fa-trash-can', 'Trash'],
    'fire_ring' => ['fa-fire', 'Metal fire ring'],
    'picnic_table' => ['fa-utensils', 'Picnic table'],
    'bear_box' => ['fa-box', 'Bear box'],
    'showers' => ['fa-shower', 'Showers'],
    'electricity' => ['fa-bolt', 'Electricity'],
    'dump_station' => ['fa-truck-droplet', 'Dump station'],
    'food_storage_required' => ['fa-box-archive', 'Food storage required'],
];

$details = $hasMemberAccess ? ($place['details'] ?? []) : [];
$connectivity = $hasMemberAccess ? ($place['connectivity'] ?? []) : [];
$sensory = $hasMemberAccess ? ($place['sensory'] ?? []) : [];
$sensoryDetails = $hasMemberAccess ? ($place['sensory_details'] ?? []) : [];
$rules = $hasMemberAccess ? ($place['rules'] ?? []) : [];
$experience = $hasMemberAccess ? ($place['experience'] ?? []) : [];
$db = db();

$historyProvenance = [];
$recentPlaceActivity = [];

try {
    $stmt = $db->prepare(
        'SELECT pp.origin_type, pp.established_at, pp.original_contributor_id,
                u.username AS contributor_username,
                u.display_name AS contributor_display_name
         FROM place_provenance pp
         LEFT JOIN users u ON u.id = pp.original_contributor_id
         WHERE pp.place_id = ?
         LIMIT 1'
    );
    $stmt->execute([(int) $place['id']]);
    $historyProvenance = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $db->prepare(
        'SELECT pc.id, pc.user_id, pc.contribution_type,
                pc.points_awarded, pc.visited_at, pc.approved_at,
                u.username, u.display_name
         FROM place_contributions pc
         LEFT JOIN users u ON u.id = pc.user_id
         WHERE pc.place_id = ?
           AND pc.status = ?
         ORDER BY COALESCE(pc.approved_at, pc.created_at) DESC, pc.id DESC
         LIMIT 8'
    );
    $stmt->execute([(int) $place['id'], 'approved']);
    $recentPlaceActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('Llama Scout public Place history error: ' . $exception->getMessage());
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

$pageDescription =
    trim(
        (string) (
            $place['public_summary']
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

    <?php require __DIR__ . '/partials/place/gallery.php'; ?>

    <?php require __DIR__ . '/partials/place/facts-weather-amenities.php'; ?>

    <?php require __DIR__ . '/partials/place/member-content.php'; ?>

    <?php require __DIR__ . '/partials/place/history.php'; ?>

    <?php require __DIR__ . '/partials/place/report-problem.php'; ?>

</article>

<script src="/js/place-gallery.js"></script>
<script src="/js/place.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
