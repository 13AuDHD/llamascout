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

            } elseif ($action === 'save-details') {

                admin_place_save_details(
                    $db,
                    $actorUserId,
                    $placeId,
                    $_POST
                );

                $notice =
                    'Road, access, environment, and safety details updated.';

            } elseif ($action === 'save-sensory') {

                admin_place_save_sensory_details(
                    $db,
                    $actorUserId,
                    $placeId,
                    $_POST
                );

                $notice =
                    'Sensory conditions updated.';

            } elseif ($action === 'save-rules') {

                admin_place_save_rules(
                    $db,
                    $actorUserId,
                    $placeId,
                    $_POST
                );

                $notice =
                    'Rules and seasonal information updated.';

            } elseif ($action === 'save-experience') {

                admin_place_save_experience(
                    $db,
                    $actorUserId,
                    $placeId,
                    $_POST
                );

                $notice =
                    'Experience ratings updated.';

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

            } elseif ($action === 'save-image-meta') {

                admin_place_save_image_metadata(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['image_id']
                        ?? 0
                    ),
                    (string) (
                        $_POST['alt_text']
                        ?? ''
                    ),
                    (int) (
                        $_POST['sort_order']
                        ?? 0
                    )
                );

                $notice =
                    'Photo caption and order updated.';

            } elseif ($action === 'add-note') {

                admin_place_add_note(
                    $db,
                    $actorUserId,
                    $placeId,
                    (string) (
                        $_POST['note']
                        ?? ''
                    )
                );

                $notice =
                    'Place note added.';

            } elseif ($action === 'delete-note') {

                admin_place_delete_note(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['note_id']
                        ?? 0
                    )
                );

                $notice =
                    'Place note deleted.';
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

$details =
    admin_place_row(
        $db,
        'place_details',
        $placeId
    );

$sensoryDetails =
    admin_place_row(
        $db,
        'place_sensory_details',
        $placeId
    );

$daytimeSensory =
    admin_place_sensory_period(
        $db,
        $placeId,
        'daytime'
    );

$nighttimeSensory =
    admin_place_sensory_period(
        $db,
        $placeId,
        'nighttime'
    );

$rules =
    admin_place_row(
        $db,
        'place_rules',
        $placeId
    );

$experience =
    admin_place_row(
        $db,
        'place_experience',
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

$provenance =
    admin_place_provenance(
        $db,
        $placeId
    );

$contributions =
    admin_place_contributions(
        $db,
        $placeId
    );

$updateHistory =
    admin_place_update_history(
        $db,
        $placeId
    );

$reportHistory =
    admin_place_reports_history(
        $db,
        $placeId
    );

$placeNotes =
    admin_place_notes(
        $db,
        $placeId
    );

$placeAuditHistory =
    admin_place_audit_history(
        $db,
        $placeId
    );

$llamaScouted =
    admin_place_llama_scouted_state(
        $db,
        $placeId
    );

$operationalCounts =
    admin_place_operational_counts(
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

function admin_place_yes_no_options(
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

    <option
        value="1"
        <?= (string) $current === '1'
            ? 'selected'
            : '' ?>
    >
        Yes
    </option>

    <option
        value="0"
        <?= $current !== null
            && $current !== ''
            && (string) $current === '0'
                ? 'selected'
                : '' ?>
    >
        No
    </option>
    <?php
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
    href="/reports.php?q=<?= rawurlencode((string) $place['name']) ?>"
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


<section class="admin-place-operations-strip" aria-label="Place operational summary">

<div>
    <span>Scout status</span>
    <strong class="<?= !empty($llamaScouted['ever_scouted']) ? 'is-good' : '' ?>">
        <i
            class="fa-solid <?= !empty($llamaScouted['ever_scouted']) ? 'fa-binoculars' : 'fa-circle-minus' ?>"
            aria-hidden="true"
        ></i>
        <?= !empty($llamaScouted['ever_scouted'])
            ? 'Llama Scouted'
            : 'Not yet Llama Scouted' ?>
    </strong>
</div>

<div>
    <span>Contributions</span>
    <strong><?= number_format((int) $operationalCounts['contributions']) ?></strong>
</div>

<div class="<?= (int) $operationalCounts['pending_updates'] > 0 ? 'has-attention' : '' ?>">
    <span>Pending updates</span>
    <strong><?= number_format((int) $operationalCounts['pending_updates']) ?></strong>
</div>

<div class="<?= (int) $operationalCounts['open_reports'] > 0 ? 'has-alert' : '' ?>">
    <span>Open reports</span>
    <strong><?= number_format((int) $operationalCounts['open_reports']) ?></strong>
</div>

<div>
    <span>Photos</span>
    <strong><?= number_format(count($images)) ?></strong>
</div>

<div>
    <span>Verifications</span>
    <strong><?= number_format((int) $operationalCounts['verifications']) ?></strong>
</div>

</section>


<nav class="admin-place-section-nav">
    <a href="#identity">Identity</a>
    <a href="#location">Location</a>
    <a href="#amenities">Amenities</a>
    <a href="#connectivity">Connectivity</a>
    <a href="#road-access">Road + Access</a>
    <a href="#sensory-report">Sensory</a>
    <a href="#rules">Rules + Seasons</a>
    <a href="#experience">Experience</a>
    <a href="#photos">Photos</a>
    <a href="#notes">Notes</a>
    <a href="#verification">Verification</a>
    <a href="#status">Status</a>
    <a href="#history">History</a>
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

<label>
    <span>URL slug</span>
    <input
        type="text"
        name="slug"
        value="<?= moderation_e(
            (string) $place['slug']
        ) ?>"
        required
    >
</label>

<label>
    <span>Record source</span>
    <select name="source_type">
        <?php foreach (
            [
                'llama-scouted' => 'Llama Scouted',
                'community-scouted' => 'Community Scouted',
                'external' => 'External source',
                'legacy' => 'Legacy',
            ]
            as $value => $label
        ): ?>
            <option
                value="<?= moderation_e($value) ?>"
                <?= (string) $place['source_type'] === $value
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e($label) ?>
            </option>
        <?php endforeach; ?>
    </select>
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
    <span>Public summary / metadata note</span>
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
    id="road-access"
>

<header class="admin-panel-header">
    <div>
        <p>Scout Report</p>
        <h2>Road + Site Access</h2>
    </div>
</header>

<form class="admin-place-compact-form" method="post">

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
<input type="hidden" name="place_admin_action" value="save-details">

<h3 class="admin-place-subsection-title">Site + Vehicle Fit</h3>

<div class="admin-place-report-grid">

<?php foreach (
    [
        'vehicle_capacity' => 'Vehicle capacity',
        'max_vehicle_length_feet' => 'Maximum vehicle length (ft)',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <input
        type="number"
        step="1"
        min="0"
        name="<?= moderation_e($field) ?>"
        value="<?= moderation_e((string) ($details[$field] ?? '')) ?>"
    >
</label>
<?php endforeach; ?>

<?php foreach (
    [
        'tent_camping_suitable' => 'Tent camping suitable',
        'rv_suitable' => 'RV suitable',
        'trailer_suitable' => 'Trailer suitable',
        'leveling_required' => 'Leveling required',
        'turnaround_space' => 'Turnaround space',
        'pull_through' => 'Pull-through',
        'back_in' => 'Back-in',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <select name="<?= moderation_e($field) ?>">
        <?php admin_place_yes_no_options($details[$field] ?? null); ?>
    </select>
</label>
<?php endforeach; ?>

<label>
    <span>Parking surface</span>
    <input
        type="text"
        name="parking_surface"
        value="<?= moderation_e((string) ($details['parking_surface'] ?? '')) ?>"
    >
</label>

<label>
    <span>Ground condition</span>
    <input
        type="text"
        name="ground_condition"
        value="<?= moderation_e((string) ($details['ground_condition'] ?? '')) ?>"
    >
</label>

<?php foreach (
    [
        'levelness' => 'Levelness',
        'site_open_sky' => 'Open sky',
        'tree_cover' => 'Tree cover',
        'site_shade' => 'Site shade',
        'site_access_difficulty' => 'Site access difficulty',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <select name="<?= moderation_e($field) ?>">
        <?php admin_place_rating_options($details[$field] ?? null); ?>
    </select>
</label>
<?php endforeach; ?>

</div>


<h3 class="admin-place-subsection-title">Road Conditions</h3>

<div class="admin-place-report-grid">

<?php foreach (
    [
        'road_overall_difficulty' => 'Overall road difficulty',
        'road_difficulty' => 'Technical difficulty',
        'road_stress' => 'Driver stress',
        'rocks' => 'Rocks',
        'washboards' => 'Washboards',
        'potholes' => 'Potholes',
        'mud_risk' => 'Mud risk',
        'steep_grades' => 'Steep grades',
        'drop_off_exposure' => 'Drop-off exposure',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <select name="<?= moderation_e($field) ?>">
        <?php admin_place_rating_options($details[$field] ?? null); ?>
    </select>
</label>
<?php endforeach; ?>

<label>
    <span>Road surface</span>
    <input
        type="text"
        name="road_surface"
        value="<?= moderation_e((string) ($details['road_surface'] ?? '')) ?>"
    >
</label>

<label>
    <span>Road width</span>
    <input
        type="text"
        name="road_width"
        value="<?= moderation_e((string) ($details['road_width'] ?? '')) ?>"
    >
</label>

<?php foreach (
    [
        'sedan_accessible' => 'Sedan accessible',
        'high_clearance_recommended' => 'High clearance recommended',
        'four_wheel_drive_recommended' => '4WD recommended',
        'water_crossings' => 'Water crossings',
        'downed_tree_risk' => 'Downed-tree risk',
        'seasonal_closure' => 'Seasonal closure',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <select name="<?= moderation_e($field) ?>">
        <?php admin_place_yes_no_options($details[$field] ?? null); ?>
    </select>
</label>
<?php endforeach; ?>

</div>


<h3 class="admin-place-subsection-title">Environment + Accessibility</h3>

<div class="admin-place-report-grid">

<?php foreach (
    [
        'forest' => 'Forest',
        'mountains' => 'Mountains',
        'water_nearby' => 'Water nearby',
        'water_view' => 'Water view',
        'mountain_view' => 'Mountain view',
        'forest_view' => 'Forest view',
        'wildlife' => 'Wildlife',
        'bugs' => 'Bugs',
        'wheelchair_friendly' => 'Wheelchair friendly',
        'mobility_device_friendly' => 'Mobility-device friendly',
        'flat_walking_surface' => 'Flat walking surface',
        'step_free_access' => 'Step-free access',
        'accessible_toilet' => 'Accessible toilet',
        'accessible_picnic_table' => 'Accessible picnic table',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <select name="<?= moderation_e($field) ?>">
        <?php admin_place_yes_no_options($details[$field] ?? null); ?>
    </select>
</label>
<?php endforeach; ?>

<?php foreach (
    [
        'wind_exposure' => 'Wind exposure',
        'sun_exposure' => 'Sun exposure',
        'environment_shade' => 'Environment shade',
        'environment_open_sky' => 'Environment open sky',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <select name="<?= moderation_e($field) ?>">
        <?php admin_place_rating_options($details[$field] ?? null); ?>
    </select>
</label>
<?php endforeach; ?>

<label class="is-wide">
    <span>Walking distance from vehicle</span>
    <input
        type="text"
        name="walking_distance_from_vehicle"
        value="<?= moderation_e((string) ($details['walking_distance_from_vehicle'] ?? '')) ?>"
    >
</label>

</div>


<h3 class="admin-place-subsection-title">Safety + Warnings</h3>

<div class="admin-place-report-grid">

<?php foreach (
    [
        'felt_safe_daytime' => 'Felt safe daytime',
        'felt_safe_nighttime' => 'Felt safe nighttime',
        'flash_flood_risk' => 'Flash-flood risk',
        'wildfire_risk' => 'Wildfire risk',
        'fall_hazard' => 'Fall hazard',
        'cliff_exposure' => 'Cliff exposure',
        'rockfall_risk' => 'Rockfall risk',
        'wildlife_risk' => 'Wildlife risk',
        'traffic_hazard' => 'Traffic hazard',
        'emergency_access' => 'Emergency access',
        'warning_exposed_to_road' => 'Warning: exposed to road',
        'warning_zero_privacy' => 'Warning: zero privacy',
        'warning_passing_vehicle_dust' => 'Warning: passing vehicle dust',
        'warning_possible_downed_trees' => 'Warning: possible downed trees',
        'warning_no_tent_camping' => 'Warning: no tent camping',
        'warning_limited_vehicle_length' => 'Warning: limited vehicle length',
        'warning_leveling_may_be_required' => 'Warning: leveling may be required',
        'warning_no_amenities' => 'Warning: no amenities',
        'warning_motorized_recreation_traffic' => 'Warning: motorized recreation traffic',
        'warning_blind_turn_traffic_nearby' => 'Warning: blind-turn traffic nearby',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <select name="<?= moderation_e($field) ?>">
        <?php admin_place_yes_no_options($details[$field] ?? null); ?>
    </select>
</label>
<?php endforeach; ?>

</div>

<div class="admin-user-form-actions">
    <button class="admin-button" type="submit">
        Save road + access report
    </button>
</div>

</form>

</section>


<section
    class="admin-panel admin-place-editor-section"
    id="sensory-report"
>

<header class="admin-panel-header">
    <div>
        <p>Scout Report</p>
        <h2>Sensory Conditions</h2>
    </div>
</header>

<form class="admin-place-compact-form" method="post">

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
<input type="hidden" name="place_admin_action" value="save-sensory">

<div class="admin-place-day-night-grid">

<section>
    <h3>Daytime</h3>

    <div class="admin-place-report-grid">
    <?php foreach (
        [
            'noise' => 'Noise',
            'traffic' => 'Traffic',
            'crowds' => 'Crowds',
            'privacy' => 'Privacy',
            'light_pollution' => 'Light pollution',
            'sensory_comfort' => 'Sensory comfort',
            'social_interaction_likelihood' => 'Social interaction likelihood',
        ] as $field => $label
    ): ?>
        <label>
            <span><?= moderation_e($label) ?></span>
            <select name="daytime_<?= moderation_e($field) ?>">
                <?php admin_place_rating_options($daytimeSensory[$field] ?? null); ?>
            </select>
        </label>
    <?php endforeach; ?>
    </div>
</section>

<section>
    <h3>Nighttime</h3>

    <div class="admin-place-report-grid">
    <?php foreach (
        [
            'noise' => 'Noise',
            'traffic' => 'Traffic',
            'crowds' => 'Crowds',
            'privacy' => 'Privacy',
            'light_pollution' => 'Light pollution',
            'sensory_comfort' => 'Sensory comfort',
            'social_interaction_likelihood' => 'Social interaction likelihood',
        ] as $field => $label
    ): ?>
        <label>
            <span><?= moderation_e($label) ?></span>
            <select name="nighttime_<?= moderation_e($field) ?>">
                <?php admin_place_rating_options($nighttimeSensory[$field] ?? null); ?>
            </select>
        </label>
    <?php endforeach; ?>
    </div>
</section>

</div>


<h3 class="admin-place-subsection-title">Specific Sensory Inputs</h3>

<div class="admin-place-report-grid">

<?php foreach (
    [
        'dust_from_traffic' => 'Dust from traffic',
        'generator_noise' => 'Generator noise',
        'aircraft_noise' => 'Aircraft noise',
        'road_noise' => 'Road noise',
        'human_activity' => 'Human activity',
        'wildlife_noise' => 'Wildlife noise',
        'wind_noise' => 'Wind noise',
        'smoke_risk' => 'Smoke risk',
        'strong_odors' => 'Strong odors',
        'visual_exposure' => 'Visual exposure',
        'predictability' => 'Predictability',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <select name="<?= moderation_e($field) ?>">
        <?php admin_place_rating_options($sensoryDetails[$field] ?? null); ?>
    </select>
</label>
<?php endforeach; ?>

</div>

<div class="admin-user-form-actions">
    <button class="admin-button" type="submit">
        Save sensory report
    </button>
</div>

</form>

</section>


<section
    class="admin-panel admin-place-editor-section"
    id="rules"
>

<header class="admin-panel-header">
    <div>
        <p>Scout Report</p>
        <h2>Rules + Seasonal Access</h2>
    </div>
</header>

<form class="admin-place-compact-form" method="post">

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
<input type="hidden" name="place_admin_action" value="save-rules">

<div class="admin-place-report-grid">

<label class="is-wide">
    <span>Best months</span>
    <input
        type="text"
        name="best_months"
        value="<?= moderation_e((string) ($rules['best_months'] ?? '')) ?>"
        placeholder="May, June, July, August..."
    >
</label>

<label>
    <span>Winter access</span>
    <select name="winter_access">
        <?php admin_place_yes_no_options($rules['winter_access'] ?? null); ?>
    </select>
</label>

<?php foreach (
    [
        'snow_risk' => 'Snow risk',
        'mud_season_risk' => 'Mud-season risk',
        'monsoon_risk' => 'Monsoon risk',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <select name="<?= moderation_e($field) ?>">
        <?php admin_place_rating_options($rules[$field] ?? null); ?>
    </select>
</label>
<?php endforeach; ?>

<label class="is-wide">
    <span>Recommended travel season</span>
    <input
        type="text"
        name="recommended_travel_season"
        value="<?= moderation_e((string) ($rules['recommended_travel_season'] ?? '')) ?>"
    >
</label>

<label class="is-wide">
    <span>Seasonal access note</span>
    <textarea name="seasonal_access_note" rows="4"><?= moderation_e((string) ($rules['seasonal_access_note'] ?? '')) ?></textarea>
</label>

<?php foreach (
    [
        'overnight_camping_allowed' => 'Overnight camping allowed',
        'dispersed_camping_allowed' => 'Dispersed camping allowed',
        'permit_required' => 'Permit required',
        'campfire_allowed' => 'Campfire allowed',
        'existing_sites_encouraged' => 'Existing sites encouraged',
        'pack_it_in_pack_it_out' => 'Pack it in / pack it out',
        'residential_use_prohibited' => 'Residential use prohibited',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <select name="<?= moderation_e($field) ?>">
        <?php admin_place_yes_no_options($rules[$field] ?? null); ?>
    </select>
</label>
<?php endforeach; ?>

<label>
    <span>Stay limit (days)</span>
    <input type="number" min="0" step="1" name="stay_limit_days" value="<?= moderation_e((string) ($rules['stay_limit_days'] ?? '')) ?>">
</label>

<label>
    <span>Maximum days per 60-day period</span>
    <input type="number" min="0" step="1" name="maximum_days_per_60_day_period" value="<?= moderation_e((string) ($rules['maximum_days_per_60_day_period'] ?? '')) ?>">
</label>

<label>
    <span>Move distance after stay (miles)</span>
    <input type="number" min="0" step=".01" name="move_distance_after_stay_miles" value="<?= moderation_e((string) ($rules['move_distance_after_stay_miles'] ?? '')) ?>">
</label>

<label>
    <span>Fee</span>
    <input type="number" min="0" step=".01" name="fee" value="<?= moderation_e((string) ($rules['fee'] ?? '')) ?>">
</label>

<label class="is-wide">
    <span>Current fire restrictions URL</span>
    <input type="url" name="current_fire_restrictions_url" value="<?= moderation_e((string) ($rules['current_fire_restrictions_url'] ?? '')) ?>">
</label>

<label>
    <span>Max distance from road (ft)</span>
    <input type="number" min="0" step="1" name="vehicle_distance_from_road_max_feet" value="<?= moderation_e((string) ($rules['vehicle_distance_from_road_max_feet'] ?? '')) ?>">
</label>

<label>
    <span>Minimum distance from water (ft)</span>
    <input type="number" min="0" step="1" name="minimum_distance_from_water_feet" value="<?= moderation_e((string) ($rules['minimum_distance_from_water_feet'] ?? '')) ?>">
</label>

<?php foreach (
    [
        'nearest_town' => 'Nearest town',
        'nearest_fuel' => 'Nearest fuel',
        'nearest_grocery' => 'Nearest grocery',
        'nearest_water' => 'Nearest water',
        'nearest_toilet' => 'Nearest toilet',
        'nearest_hospital' => 'Nearest hospital',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <input
        type="text"
        name="<?= moderation_e($field) ?>"
        value="<?= moderation_e((string) ($rules[$field] ?? '')) ?>"
    >
</label>
<?php endforeach; ?>

</div>

<div class="admin-user-form-actions">
    <button class="admin-button" type="submit">
        Save rules + seasons
    </button>
</div>

</form>

</section>


<section
    class="admin-panel admin-place-editor-section"
    id="experience"
>

<header class="admin-panel-header">
    <div>
        <p>Scout Report</p>
        <h2>Experience + Recommendations</h2>
    </div>
</header>

<form class="admin-place-compact-form" method="post">

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
<input type="hidden" name="place_admin_action" value="save-experience">

<div class="admin-place-report-grid">

<?php foreach (
    [
        'sunrise_view' => 'Sunrise view',
        'sunset_view' => 'Sunset view',
        'mountain_view' => 'Mountain view',
        'forest_view' => 'Forest view',
        'night_sky' => 'Night sky',
        'stargazing' => 'Stargazing',
        'quiet_evening' => 'Quiet evening',
        'overnight_comfort' => 'Overnight comfort',
        'extended_stay_comfort' => 'Extended-stay comfort',
        'sensory_retreat' => 'Sensory retreat',
        'remote_work' => 'Remote work',
        'overall_scenery' => 'Overall scenery',
        'recommended_overnight_stop' => 'Recommended overnight stop',
        'recommended_quiet_evening' => 'Recommended quiet evening',
        'recommended_extended_stay' => 'Recommended extended stay',
        'recommended_sensory_retreat' => 'Recommended sensory retreat',
        'recommended_stargazing' => 'Recommended stargazing',
        'recommended_remote_work' => 'Recommended remote work',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <select name="<?= moderation_e($field) ?>">
        <?php admin_place_rating_options($experience[$field] ?? null); ?>
    </select>
</label>
<?php endforeach; ?>

<?php foreach (
    [
        'recommended_solo_travel' => 'Recommended for solo travel',
        'recommended_families' => 'Recommended for families',
        'recommended_large_groups' => 'Recommended for large groups',
    ] as $field => $label
): ?>
<label>
    <span><?= moderation_e($label) ?></span>
    <select name="<?= moderation_e($field) ?>">
        <?php admin_place_yes_no_options($experience[$field] ?? null); ?>
    </select>
</label>
<?php endforeach; ?>

<label class="is-wide">
    <span>Not recommended for</span>
    <textarea
        name="not_recommended_for"
        rows="4"
    ><?= moderation_e((string) ($experience['not_recommended_for'] ?? '')) ?></textarea>
</label>

</div>

<div class="admin-user-form-actions">
    <button class="admin-button" type="submit">
        Save experience report
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

<form
    class="admin-place-image-meta-form"
    method="post"
>
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
    <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
    <input type="hidden" name="place_admin_action" value="save-image-meta">

    <label>
        <span>Caption / alt text</span>
        <textarea
            name="alt_text"
            rows="2"
            maxlength="500"
        ><?= moderation_e((string) ($image['alt_text'] ?? '')) ?></textarea>
    </label>

    <label class="admin-place-image-order-field">
        <span>Order</span>
        <input
            type="number"
            name="sort_order"
            min="0"
            max="999"
            value="<?= (int) ($image['sort_order'] ?? 0) ?>"
        >
    </label>

    <button
        class="admin-button is-muted"
        type="submit"
    >
        Save photo details
    </button>
</form>

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
    id="notes"
>

<header class="admin-panel-header">
    <div>
        <p>Field Context</p>
        <h2>Place Notes</h2>
    </div>

    <span>
        <?= number_format(count($placeNotes)) ?>
    </span>
</header>

<?php if ($placeNotes): ?>

<div class="admin-place-notes-list">

<?php foreach ($placeNotes as $note): ?>

<div>
    <div>
        <p><?= nl2br(moderation_e((string) $note['note'])) ?></p>
        <span>
            <?= moderation_e((string) $note['author_name']) ?>
            ·
            <?= moderation_e((string) $note['created_at']) ?>
        </span>
    </div>

    <form
        method="post"
        onsubmit="return confirm('Delete this Place note?');"
    >
        <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
        <input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
        <input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>">
        <input type="hidden" name="place_admin_action" value="delete-note">

        <button
            class="admin-icon-button is-danger"
            type="submit"
            aria-label="Delete note"
        >
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
        </button>
    </form>
</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

<form method="post" class="admin-place-add-note-form">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
    <input type="hidden" name="place_admin_action" value="add-note">

    <label>
        <span>Add field / internal note</span>
        <textarea
            name="note"
            rows="3"
            maxlength="2000"
            placeholder="Useful context that should remain attached to this Place record."
        ></textarea>
    </label>

    <button class="admin-button" type="submit">
        Add note
    </button>
</form>

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


<section
    class="admin-panel admin-place-history-hub"
    id="history"
>

<header class="admin-panel-header">
    <div>
        <p>Canonical Record</p>
        <h2>History + Provenance</h2>
    </div>
</header>

<div class="admin-place-history-tabs">

<details open>
    <summary>
        Origin
        <span>1</span>
    </summary>

    <div class="admin-place-history-detail">
        <?php if ($provenance): ?>
            <dl class="admin-user-definition-list">
                <div>
                    <dt>Origin</dt>
                    <dd><?= moderation_e(ucwords(str_replace('-', ' ', (string) $provenance['origin_type']))) ?></dd>
                </div>

                <div>
                    <dt>Original contributor</dt>
                    <dd>
                        <?php if ((int) ($provenance['original_contributor_id'] ?? 0) > 0): ?>
                            <a href="/user.php?id=<?= (int) $provenance['original_contributor_id'] ?>">
                                <?= moderation_e((string) ($provenance['contributor_name'] ?? 'Former member')) ?>
                            </a>
                        <?php else: ?>
                            Unknown / legacy
                        <?php endif; ?>
                    </dd>
                </div>

                <div>
                    <dt>Established</dt>
                    <dd><?= moderation_e((string) ($provenance['established_at'] ?: $place['created_at'])) ?></dd>
                </div>

                <?php if (!empty($provenance['original_submission_id'])): ?>
                <div>
                    <dt>Original submission</dt>
                    <dd>#<?= (int) $provenance['original_submission_id'] ?></dd>
                </div>
                <?php endif; ?>
            </dl>
        <?php else: ?>
            <p class="admin-table-muted">
                No separate provenance row is recorded. The Place's created-by and source fields remain the source of record.
            </p>
        <?php endif; ?>

        <?php if (!empty($llamaScouted['ever_scouted'])): ?>
            <?php $scouted = $llamaScouted['first']; ?>
            <div class="admin-place-llama-scouted-banner">
                <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
                <div>
                    <strong>Llama Scouted</strong>
                    <span>
                        This Place has been personally field-scouted and keeps that historical distinction even after later community edits.
                        First recorded by <?= moderation_e((string) ($scouted['scout_name'] ?? 'a Llama Scout')) ?>
                        <?= !empty($scouted['visited_at']) ? 'on ' . moderation_e((string) $scouted['visited_at']) : '' ?>.
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</details>


<details>
    <summary>
        Contributions
        <span><?= number_format(count($contributions)) ?></span>
    </summary>

    <?php if (!$contributions): ?>
        <div class="admin-empty-state"><p>No contribution history recorded.</p></div>
    <?php else: ?>
        <div class="admin-place-history-list">
            <?php foreach ($contributions as $contribution): ?>
                <?php
                $fieldsChanged = json_decode((string) ($contribution['fields_changed'] ?? ''), true);
                $fieldCount = is_array($fieldsChanged) ? count($fieldsChanged) : 0;
                ?>
                <div>
                    <strong>
                        <?= moderation_e(ucwords(str_replace('_', ' ', (string) $contribution['contribution_type']))) ?>
                    </strong>
                    <span>
                        <a href="/user.php?id=<?= (int) $contribution['user_id'] ?>">
                            <?= moderation_e((string) $contribution['contributor_name']) ?>
                        </a>
                        · <?= moderation_e((string) ($contribution['approved_at'] ?: $contribution['created_at'])) ?>
                    </span>
                    <p>
                        <?= number_format((int) $contribution['points_awarded']) ?> points
                        <?php if ($fieldCount > 0): ?>
                            · <?= number_format($fieldCount) ?> changed field<?= $fieldCount === 1 ? '' : 's' ?>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($contribution['notes'])): ?>
                        <p><?= moderation_e((string) $contribution['notes']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</details>


<details>
    <summary>
        Suggested updates
        <span><?= number_format(count($updateHistory)) ?></span>
    </summary>

    <?php if (!$updateHistory): ?>
        <div class="admin-empty-state"><p>No update submissions recorded.</p></div>
    <?php else: ?>
        <div class="admin-place-history-list">
            <?php foreach ($updateHistory as $update): ?>
                <?php
                $changes = json_decode((string) $update['proposed_changes'], true);
                $changeCount = is_array($changes) ? count($changes) : 0;
                ?>
                <div>
                    <strong>
                        Update #<?= (int) $update['id'] ?>
                        · <?= moderation_e(ucwords(str_replace('-', ' ', (string) $update['status']))) ?>
                    </strong>
                    <span>
                        <a href="/user.php?id=<?= (int) $update['user_id'] ?>">
                            <?= moderation_e((string) $update['contributor_name']) ?>
                        </a>
                        · <?= moderation_e((string) $update['submitted_at']) ?>
                    </span>
                    <p>
                        <?= moderation_e(ucwords(str_replace('-', ' ', (string) $update['update_type']))) ?>
                        · <?= number_format($changeCount) ?> top-level change group<?= $changeCount === 1 ? '' : 's' ?>
                        · <?= number_format((int) $update['points_awarded']) ?> points
                    </p>
                    <?php if (in_array((string) $update['status'], ['pending','needs-changes'], true)): ?>
                        <a class="admin-inline-link" href="/moderate-update.php?id=<?= (int) $update['id'] ?>">
                            Review this update
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</details>


<details>
    <summary>
        Problem reports
        <span><?= number_format(count($reportHistory)) ?></span>
    </summary>

    <?php if (!$reportHistory): ?>
        <div class="admin-empty-state"><p>No problem reports recorded.</p></div>
    <?php else: ?>
        <div class="admin-place-history-list">
            <?php foreach ($reportHistory as $report): ?>
                <div class="<?= in_array((string) $report['status'], ['open','investigating'], true) ? 'has-attention' : '' ?>">
                    <strong>
                        Report #<?= (int) $report['id'] ?>
                        · <?= moderation_e(ucwords(str_replace('-', ' ', (string) $report['problem_type']))) ?>
                    </strong>
                    <span>
                        <?= moderation_e((string) $report['reporter_name']) ?>
                        · <?= moderation_e((string) $report['created_at']) ?>
                        · <?= moderation_e(ucfirst((string) $report['status'])) ?>
                    </span>
                    <?php if (!empty($report['details'])): ?>
                        <p><?= moderation_e((string) $report['details']) ?></p>
                    <?php endif; ?>
                    <a class="admin-inline-link" href="/moderate-report.php?id=<?= (int) $report['id'] ?>">
                        Open report
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</details>


<details>
    <summary>
        Administrative changes
        <span><?= number_format(count($placeAuditHistory)) ?></span>
    </summary>

    <?php if (!$placeAuditHistory): ?>
        <div class="admin-empty-state"><p>No matching Place audit entries were found.</p></div>
    <?php else: ?>
        <div class="admin-place-history-list">
            <?php foreach ($placeAuditHistory as $auditRow): ?>
                <div>
                    <strong><?= moderation_e((string) $auditRow['summary']) ?></strong>
                    <span>
                        <?= moderation_e((string) $auditRow['actor_name']) ?>
                        · <?= moderation_e((string) $auditRow['created_at']) ?>
                    </span>
                    <p><?= moderation_e((string) $auditRow['action']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="admin-place-history-footer">
        <a
            class="admin-button is-muted"
            href="/audit.php?category=places"
        >
            Open full audit console
        </a>
    </div>
</details>

</div>

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
    <dt>Llama Scouted</dt>
    <dd>
        <?= !empty($llamaScouted['ever_scouted'])
            ? 'Yes, historical field visit recorded'
            : 'No field Scout visit recorded' ?>
    </dd>
</div>

<div>
    <dt>Original contributor</dt>
    <dd>
        <?php if ((int) ($provenance['original_contributor_id'] ?? $place['created_by'] ?? 0) > 0): ?>
            <?php $originUserId = (int) ($provenance['original_contributor_id'] ?? $place['created_by']); ?>
            <a href="/user.php?id=<?= $originUserId ?>">
                <?= moderation_e((string) ($provenance['contributor_name'] ?? ('User #' . $originUserId))) ?>
            </a>
        <?php else: ?>
            Unknown / legacy
        <?php endif; ?>
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
