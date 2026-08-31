<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-places.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId =
    (int) ($adminUser['id'] ?? 0);

$placeId =
    (int) (
        $_GET['id']
        ?? $_POST['place_id']
        ?? 0
    );

if ($placeId < 1) {
    header('Location: /places.php');
    exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !moderation_verify_csrf(
            (string) ($_POST['csrf_token'] ?? '')
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action =
                (string) (
                    $_POST['place_admin_action']
                    ?? ''
                );

            if ($action === 'save-core') {

                admin_place_save_core(
                    $db,
                    $actorUserId,
                    $placeId,
                    $_POST
                );

                $notice =
                    'Place details updated.';

            } elseif ($action === 'save-amenities') {

                admin_place_save_amenities(
                    $db,
                    $actorUserId,
                    $placeId,
                    $_POST
                );

                $notice =
                    'Amenities updated.';

            } elseif ($action === 'save-connectivity') {

                admin_place_save_connectivity(
                    $db,
                    $actorUserId,
                    $placeId,
                    $_POST
                );

                $notice =
                    'Connectivity updated.';

            } elseif ($action === 'change-status') {

                admin_place_change_status(
                    $db,
                    $actorUserId,
                    $placeId,
                    (string) (
                        $_POST['status']
                        ?? ''
                    ),
                    (string) (
                        $_POST['status_reason']
                        ?? ''
                    )
                );

                $notice =
                    'Place status updated.';

            } elseif ($action === 'add-verification') {

                admin_place_add_verification(
                    $db,
                    $actorUserId,
                    $placeId,
                    $_POST
                );

                $notice =
                    'Verification added.';

            } elseif ($action === 'add-photos') {

                $photoToken = trim(
                    (string) (
                        $_POST['photo_stage_token']
                        ?? ''
                    )
                );

                $photos =
                    llama_photo_decode_form_photos(
                        $_POST['photos_json']
                        ?? '[]'
                    );

                if (
                    $photoToken === ''
                    || !$photos
                ) {
                    throw new RuntimeException(
                        'Choose at least one Place photo.'
                    );
                }

                $count =
                    admin_place_add_photos(
                        $db,
                        $actorUserId,
                        $placeId,
                        $photoToken,
                        $photos
                    );

                $notice =
                    $count === 1
                        ? 'Place photo added.'
                        : $count .
                            ' Place photos added.';

            } elseif ($action === 'featured-image') {

                admin_place_set_featured_image(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['image_id']
                        ?? 0
                    )
                );

                $notice =
                    'Featured image updated.';

            } elseif ($action === 'delete-image') {

                admin_place_delete_image(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['image_id']
                        ?? 0
                    )
                );

                $notice =
                    'Place image deleted.';
            }

        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();
        }
    }
}

$place =
    admin_place_get(
        $db,
        $placeId
    );

if (!$place) {
    header('Location: /places.php');
    exit;
}

$amenities =
    admin_place_row(
        $db,
        'place_amenities',
        $placeId
    );

$connectivity =
    admin_place_row(
        $db,
        'place_connectivity',
        $placeId
    );

$images =
    admin_place_images(
        $db,
        $placeId
    );

$verifications =
    admin_place_verifications(
        $db,
        $placeId
    );

$statusHistory =
    admin_place_status_history(
        $db,
        $placeId
    );

$openReportsStmt =
    $db->prepare(
        'SELECT COUNT(*)
         FROM place_reports
         WHERE place_id = ?
           AND status IN (
                "open",
                "investigating"
           )'
    );

$openReportsStmt->execute([$placeId]);

$openReports =
    (int) $openReportsStmt->fetchColumn();

$remainingPhotos =
    max(
        0,
        30 - count($images)
    );

$stats =
    admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle =
    (string) $place['name'];

$adminPageEyebrow =
    'Place Administration';

$adminActiveNav = 'places';

$adminNeedsPhotoUploader = true;

require __DIR__ . '/_header.php';

function admin_place_tri_option(
    mixed $value,
    mixed $current
): string {
    return (string) $value
        === (string) $current
            ? 'selected'
            : '';
}

function admin_place_rating_options(
    mixed $current
): void {
    ?>
    <option
        value=""
        <?= $current === null
            || $current === ''
                ? 'selected'
                : '' ?>
    >
        Unknown
    </option>

    <?php for ($i = 1; $i <= 5; $i++): ?>
        <option
            value="<?= $i ?>"
            <?= (string) $current === (string) $i
                ? 'selected'
                : '' ?>
        >
            <?= $i ?>/5
        </option>
    <?php endfor; ?>
    <?php
}
?>

<?php if ($notice !== ''): ?>
<div class="admin-user-notice is-success">
    <?= moderation_e($notice) ?>
</div>
<?php endif; ?>

<?php if ($error !== ''): ?>
<div class="admin-user-notice is-error">
    <?= moderation_e($error) ?>
</div>
<?php endif; ?>


<section class="admin-place-summary">

<div>

<div class="admin-place-summary-heading">
    <p>
        Place #<?= (int) $place['id'] ?>
    </p>

    <h2>
        <?= moderation_e(
            (string) $place['name']
        ) ?>
    </h2>

    <span class="admin-status-pill">
        <?= moderation_e(
            ucfirst(
                (string) $place['status']
            )
        ) ?>
    </span>
</div>

<span>
    <?= moderation_e(
        (string) (
            $place['public_location_label']
            ?: implode(
                ', ',
                array_filter(
                    [
                        $place['city'],
                        $place['state'],
                    ]
                )
            )
        )
    ) ?>
</span>

</div>


<div class="admin-place-summary-actions">

<?php if ($openReports > 0): ?>
<a
    class="admin-button admin-place-report-alert"
    href="/reports.php"
>
    <i
        class="fa-solid fa-triangle-exclamation"
        aria-hidden="true"
    ></i>

    <?= number_format($openReports) ?>
    open report<?= $openReports === 1 ? '' : 's' ?>
</a>
<?php endif; ?>

<?php if (
    in_array(
        (string) $place['status'],
        ['active','featured'],
        true
    )
): ?>
<a
    class="admin-button is-muted"
    href="https://llamascout.com/place.php?slug=<?= rawurlencode(
        (string) $place['slug']
    ) ?>"
    target="_blank"
    rel="noopener"
>
    View public Place
</a>
<?php endif; ?>

</div>

</section>


<nav class="admin-place-section-nav">
    <a href="#identity">Identity</a>
    <a href="#location">Location</a>
    <a href="#amenities">Amenities</a>
    <a href="#connectivity">Connectivity</a>
    <a href="#photos">Photos</a>
    <a href="#verification">Verification</a>
    <a href="#status">Status</a>
</nav>


<div class="admin-place-editor-grid">

<div class="admin-user-detail-main">

<form method="post">

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
<input type="hidden" name="place_admin_action" value="save-core">


<section
    class="admin-panel admin-place-editor-section"
    id="identity"
>

<header class="admin-panel-header">
    <div>
        <p>Place</p>
        <h2>Identity + Description</h2>
    </div>
</header>

<div class="admin-user-form">

<div class="admin-user-form-grid">

<label>
    <span>Name</span>
    <input
        type="text"
        name="name"
        value="<?= moderation_e(
            (string) $place['name']
        ) ?>"
        required
    >
</label>

<label>
    <span>Type</span>
    <input
        type="text"
        name="type"
        value="<?= moderation_e(
            (string) $place['type']
        ) ?>"
        required
    >
</label>

<label class="is-wide">
    <span>Full description (paid)</span>
    <textarea
        name="description"
        rows="8"
    ><?= moderation_e(
        (string) ($place['description'] ?? '')
    ) ?></textarea>
</label>

<label class="is-wide">
    <span>Public summary</span>
    <textarea
        name="public_summary"
        rows="4"
    ><?= moderation_e(
        (string) ($place['public_summary'] ?? '')
    ) ?></textarea>
</label>

<label class="is-wide">
    <span>Sensory summary</span>
    <textarea
        name="sensory_summary"
        rows="4"
    ><?= moderation_e(
        (string) ($place['sensory_summary'] ?? '')
    ) ?></textarea>
</label>

<label class="is-wide">
    <span>Access summary</span>
    <textarea
        name="access_summary"
        rows="4"
    ><?= moderation_e(
        (string) ($place['access_summary'] ?? '')
    ) ?></textarea>
</label>

</div>

</div>

</section>


<section
    class="admin-panel admin-place-editor-section"
    id="location"
>

<header class="admin-panel-header">
    <div>
        <p>Coordinates + Land</p>
        <h2>Location</h2>
    </div>
</header>

<div class="admin-user-form">

<div class="admin-user-form-grid">

<label>
    <span>Exact latitude</span>
    <input
        type="number"
        step="0.0000001"
        name="latitude"
        value="<?= moderation_e(
            (string) ($place['latitude'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>Exact longitude</span>
    <input
        type="number"
        step="0.0000001"
        name="longitude"
        value="<?= moderation_e(
            (string) ($place['longitude'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>Public latitude</span>
    <input
        type="number"
        step="0.0000001"
        name="public_latitude"
        value="<?= moderation_e(
            (string) ($place['public_latitude'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>Public longitude</span>
    <input
        type="number"
        step="0.0000001"
        name="public_longitude"
        value="<?= moderation_e(
            (string) ($place['public_longitude'] ?? '')
        ) ?>"
    >
</label>

<label class="is-wide">
    <span>Public location label</span>
    <input
        type="text"
        name="public_location_label"
        value="<?= moderation_e(
            (string) ($place['public_location_label'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>Elevation (feet)</span>
    <input
        type="number"
        step="1"
        name="elevation_feet"
        value="<?= moderation_e(
            (string) ($place['elevation_feet'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>Road</span>
    <input
        type="text"
        name="road"
        value="<?= moderation_e(
            (string) ($place['road'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>City / nearest town</span>
    <input
        type="text"
        name="city"
        value="<?= moderation_e(
            (string) ($place['city'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>County</span>
    <input
        type="text"
        name="county"
        value="<?= moderation_e(
            (string) ($place['county'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>State</span>
    <input
        type="text"
        name="state"
        value="<?= moderation_e(
            (string) ($place['state'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>Region</span>
    <input
        type="text"
        name="region"
        value="<?= moderation_e(
            (string) ($place['region'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>Land manager</span>
    <input
        type="text"
        name="land_manager"
        value="<?= moderation_e(
            (string) ($place['land_manager'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>Land type</span>
    <input
        type="text"
        name="land_type"
        value="<?= moderation_e(
            (string) ($place['land_type'] ?? '')
        ) ?>"
    >
</label>

</div>


<div class="admin-user-form-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Save Place details
    </button>
</div>

</div>

</section>

</form>


<section
    class="admin-panel admin-place-editor-section"
    id="amenities"
>

<header class="admin-panel-header">
    <div>
        <p>Public Data</p>
        <h2>Amenities</h2>
    </div>
</header>

<form
    class="admin-place-compact-form"
    method="post"
>

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
<input type="hidden" name="place_admin_action" value="save-amenities">

<div class="admin-place-tristate-grid">

<?php foreach (
    [
        'toilets' => 'Toilets',
        'potable_water' => 'Potable water',
        'trash' => 'Trash',
        'fire_ring' => 'Fire ring',
        'picnic_table' => 'Picnic table',
        'bear_box' => 'Bear box',
        'showers' => 'Showers',
        'electricity' => 'Electricity',
        'dump_station' => 'Dump station',
        'food_storage_required' => 'Food storage required',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>

    <select name="<?= moderation_e($field) ?>">
        <option
            value=""
            <?= !array_key_exists($field, $amenities)
                || $amenities[$field] === null
                    ? 'selected'
                    : '' ?>
        >
            Unknown
        </option>

        <option
            value="1"
            <?= admin_place_tri_option(
                1,
                $amenities[$field] ?? null
            ) ?>
        >
            Yes
        </option>

        <option
            value="0"
            <?= array_key_exists($field, $amenities)
                && $amenities[$field] !== null
                && (int) $amenities[$field] === 0
                    ? 'selected'
                    : '' ?>
        >
            No
        </option>
    </select>
</label>
<?php endforeach; ?>

</div>

<div class="admin-user-form-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Save amenities
    </button>
</div>

</form>

</section>


<section
    class="admin-panel admin-place-editor-section"
    id="connectivity"
>

<header class="admin-panel-header">
    <div>
        <p>Scout Report</p>
        <h2>Connectivity</h2>
    </div>
</header>

<form
    class="admin-place-compact-form"
    method="post"
>

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
<input type="hidden" name="place_admin_action" value="save-connectivity">

<div class="admin-place-rating-grid">

<?php foreach (
    [
        'overall' => 'Overall',
        't_mobile' => 'T-Mobile',
        'verizon' => 'Verizon',
        'att' => 'AT&T',
        'other_cell' => 'Other cellular',
        'starlink' => 'Starlink',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>

    <select name="<?= moderation_e($field) ?>">
        <?php
        admin_place_rating_options(
            $connectivity[$field] ?? null
        );
        ?>
    </select>
</label>
<?php endforeach; ?>


<label>
    <span>Starlink tested</span>

    <select name="starlink_tested">
        <option
            value=""
            <?= !array_key_exists(
                'starlink_tested',
                $connectivity
            ) || $connectivity['starlink_tested'] === null
                ? 'selected'
                : '' ?>
        >
            Unknown
        </option>

        <option
            value="1"
            <?= admin_place_tri_option(
                1,
                $connectivity['starlink_tested'] ?? null
            ) ?>
        >
            Yes
        </option>

        <option
            value="0"
            <?= array_key_exists(
                'starlink_tested',
                $connectivity
            )
            && $connectivity['starlink_tested'] !== null
            && (int) $connectivity['starlink_tested'] === 0
                ? 'selected'
                : '' ?>
        >
            No
        </option>
    </select>
</label>

<label class="is-wide">
    <span>Starlink note</span>

    <textarea
        name="starlink_note"
        rows="4"
    ><?= moderation_e(
        (string) (
            $connectivity['starlink_note']
            ?? ''
        )
    ) ?></textarea>
</label>

</div>

<div class="admin-user-form-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Save connectivity
    </button>
</div>

</form>

</section>


<section
    class="admin-panel admin-place-editor-section"
    id="photos"
>

<header class="admin-panel-header">
    <div>
        <p>Media</p>
        <h2>Place Photos</h2>
    </div>

    <span>
        <?= number_format(count($images)) ?>
        of 30
    </span>
</header>

<?php if ($images): ?>

<div class="admin-place-image-grid">

<?php foreach ($images as $image): ?>

<article>

<div class="admin-place-image-preview">

<img
    src="<?= moderation_e(
        llama_photo_public_url(
            (string) $image['src']
        )
    ) ?>"
    alt="<?= moderation_e(
        (string) (
            $image['alt_text']
            ?? ''
        )
    ) ?>"
    loading="lazy"
>

<?php if ((int) $image['is_featured'] === 1): ?>
<span>Featured</span>
<?php endif; ?>

</div>

<div class="admin-place-image-actions">

<?php if ((int) $image['is_featured'] !== 1): ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
    <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
    <input type="hidden" name="place_admin_action" value="featured-image">

    <button
        class="admin-button is-muted"
        type="submit"
    >
        Make featured
    </button>
</form>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
    <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
    <input type="hidden" name="place_admin_action" value="delete-image">

    <button
        class="admin-button admin-commerce-delete-photo"
        type="submit"
    >
        Delete
    </button>
</form>

</div>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>


<?php if ($remainingPhotos > 0): ?>

<form
    class="admin-place-photo-upload"
    method="post"
>

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
<input type="hidden" name="place_admin_action" value="add-photos">
<input type="hidden" name="photo_stage_token" value="">
<input type="hidden" name="photos_json" value="[]">

<div
    data-photo-uploader
    data-photo-context="add-place"
    data-photo-max="<?= min(
        10,
        $remainingPhotos
    ) ?>"
    data-photo-csrf="<?= moderation_e(
        llama_photo_csrf_token()
    ) ?>"
    data-photo-endpoint="/photo-upload.php"
    data-photo-title="Add Place photos"
    data-photo-help="Add up to <?= min(10, $remainingPhotos) ?> photos in this batch. They are cleaned, resized, and stripped of location metadata before permanent storage."
></div>

<div class="admin-user-form-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Add photos
    </button>
</div>

</form>

<?php endif; ?>

</section>


<section
    class="admin-panel admin-place-editor-section"
    id="verification"
>

<header class="admin-panel-header">
    <div>
        <p>Trust + Freshness</p>
        <h2>Verification</h2>
    </div>

    <span>
        Last verified:
        <?= moderation_e(
            (string) (
                $place['last_verified_at']
                ?: 'Never'
            )
        ) ?>
    </span>
</header>

<form
    class="admin-place-verification-form"
    method="post"
>

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
<input type="hidden" name="place_admin_action" value="add-verification">

<label>
    <span>Verification type</span>
    <select name="verification_type">
        <option value="llama-scouted">Llama Scouted field visit</option>
        <option value="official-source">Official source</option>
        <option value="admin-review">Admin review</option>
        <option value="member-evidence">Member evidence</option>
    </select>
</label>

<label>
    <span>Visited date</span>
    <input
        type="date"
        name="visited_at"
    >
</label>

<label class="is-wide">
    <span>Source</span>
    <input
        type="text"
        name="source"
        placeholder="URL, agency, field visit, report, etc."
    >
</label>

<label class="is-wide">
    <span>Notes</span>
    <textarea
        name="notes"
        rows="4"
    ></textarea>
</label>

<label class="admin-place-inline-check is-wide">
    <input
        type="checkbox"
        name="public_data_verified"
        value="1"
    >
    <span>
        Public-facing location and basic Place data were checked.
    </span>
</label>

<div class="admin-user-form-actions is-wide">
    <button
        class="admin-button"
        type="submit"
    >
        Record verification
    </button>
</div>

</form>


<?php if ($verifications): ?>

<div class="admin-place-history-list">

<?php foreach ($verifications as $entry): ?>

<div>
    <strong>
        <?= moderation_e(
            ucwords(
                str_replace(
                    '-',
                    ' ',
                    (string) $entry['verification_type']
                )
            )
        ) ?>
    </strong>

    <span>
        <?= moderation_e(
            (string) $entry['verifier_name']
        ) ?>
        ·
        <?= moderation_e(
            (string) $entry['verified_at']
        ) ?>

        <?php if (!empty($entry['visited_at'])): ?>
            · visited
            <?= moderation_e(
                (string) $entry['visited_at']
            ) ?>
        <?php endif; ?>
    </span>

    <?php if (!empty($entry['notes'])): ?>
        <p>
            <?= moderation_e(
                (string) $entry['notes']
            ) ?>
        </p>
    <?php endif; ?>
</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

</div>


<aside class="admin-user-detail-side">

<section
    class="admin-panel"
    id="status"
>

<header class="admin-panel-header">
    <div>
        <p>Publication</p>
        <h2>Status</h2>
    </div>
</header>

<form
    class="admin-user-action-box"
    method="post"
>

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
<input type="hidden" name="place_admin_action" value="change-status">

<label>
    <span>Status</span>

    <select name="status">
        <?php foreach (
            [
                'draft',
                'active',
                'featured',
                'unlisted',
                'archived',
                'removed',
            ] as $status
        ): ?>
            <option
                value="<?= moderation_e($status) ?>"
                <?= (string) $place['status'] === $status
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e(
                    ucfirst($status)
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Reason for change</span>

    <textarea
        name="status_reason"
        rows="4"
        placeholder="Required when changing status."
    ></textarea>
</label>

<button
    class="admin-button"
    type="submit"
>
    Save status
</button>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Record</p>
        <h2>Place Facts</h2>
    </div>
</header>

<dl class="admin-user-definition-list">

<div>
    <dt>Slug</dt>
    <dd>
        <?= moderation_e(
            (string) $place['slug']
        ) ?>
    </dd>
</div>

<div>
    <dt>Source</dt>
    <dd>
        <?= moderation_e(
            (string) $place['source_type']
        ) ?>
    </dd>
</div>

<div>
    <dt>Created</dt>
    <dd>
        <?= moderation_e(
            (string) $place['created_at']
        ) ?>
    </dd>
</div>

<div>
    <dt>Published</dt>
    <dd>
        <?= moderation_e(
            (string) (
                $place['published_at']
                ?: 'Not published'
            )
        ) ?>
    </dd>
</div>

<div>
    <dt>Updated</dt>
    <dd>
        <?= moderation_e(
            (string) $place['updated_at']
        ) ?>
    </dd>
</div>

</dl>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>History</p>
        <h2>Status Timeline</h2>
    </div>
</header>

<?php if (!$statusHistory): ?>

<div class="admin-empty-state">
    <p>No status changes recorded.</p>
</div>

<?php else: ?>

<div class="admin-user-audit-list">

<?php foreach ($statusHistory as $entry): ?>

<div>
    <strong>
        <?= moderation_e(
            (string) (
                $entry['old_status']
                ?: 'Created'
            )
        ) ?>
        →
        <?= moderation_e(
            (string) $entry['new_status']
        ) ?>
    </strong>

    <span>
        <?= moderation_e(
            (string) $entry['changed_by_name']
        ) ?>
        ·
        <?= moderation_e(
            (string) $entry['changed_at']
        ) ?>
    </span>

    <?php if (!empty($entry['reason'])): ?>
        <span>
            <?= moderation_e(
                (string) $entry['reason']
            ) ?>
        </span>
    <?php endif; ?>
</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

</aside>

</div>

<?php require __DIR__ . '/_footer.php'; ?>
