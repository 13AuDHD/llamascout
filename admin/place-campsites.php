<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-places.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db =
    db();

$placeId =
    (int) (
        $_GET['place_id']
        ?? $_POST['place_id']
        ?? 0
    );

if ($placeId < 1) {
    header('Location: /places.php');
    exit;
}

$place =
    admin_place_get(
        $db,
        $placeId
    );

if (!$place) {
    http_response_code(404);
    exit('Place not found.');
}

$sites =
    llama_place_campsites(
        $db,
        $placeId
    );

$selectedSiteId =
    (int) (
        $_GET['site']
        ?? $_POST['campsite_id']
        ?? (
            $sites[0]['id']
            ?? 0
        )
    );

$selectedSite = [];

foreach ($sites as $site) {
    if (
        (int) ($site['id'] ?? 0)
        === $selectedSiteId
    ) {
        $selectedSite = $site;
        break;
    }
}

if (
    !$selectedSite
    && $sites
) {
    $selectedSite =
        $sites[0];

    $selectedSiteId =
        (int) $selectedSite['id'];
}

function admin_campsite_feature_value(
    array $site,
    string $key
): string {
    foreach (
        (array) (
            $site['features']
            ?? []
        )
        as $feature
    ) {
        if (
            (string) (
                $feature['feature_key']
                ?? ''
            )
            !== $key
        ) {
            continue;
        }

        return trim(
            (string) (
                $feature['feature_value']
                ?? ''
            )
        );
    }

    return '';
}

function admin_campsite_nullable_number(
    mixed $value
): ?float {
    $value =
        trim(
            (string) $value
        );

    if (
        $value === ''
        || !is_numeric($value)
    ) {
        return null;
    }

    return (float) $value;
}

function admin_campsite_nullable_int(
    mixed $value
): ?int {
    $number =
        admin_campsite_nullable_number(
            $value
        );

    return $number === null
        ? null
        : (int) round($number);
}

function admin_campsite_tri(
    mixed $value
): ?int {
    $value =
        trim(
            (string) $value
        );

    if ($value === '1') {
        return 1;
    }

    if ($value === '0') {
        return 0;
    }

    return null;
}

function admin_campsite_feature_override(
    PDO $db,
    int $campsiteId,
    string $key,
    mixed $value
): void {
    $key =
        trim($key);

    if (
        $campsiteId < 1
        || $key === ''
    ) {
        return;
    }

    $db->prepare(
        'UPDATE place_campsite_features
         SET is_active = 0
         WHERE campsite_id = ?
           AND feature_key = ?'
    )->execute([
        $campsiteId,
        $key,
    ]);

    $value =
        trim(
            (string) $value
        );

    if ($value === '') {
        return;
    }

    $stmt =
        $db->prepare(
            'INSERT INTO place_campsite_features
            (
                campsite_id,
                feature_key,
                feature_value,
                qualifier,
                source_provider,
                source_external_id,
                source_updated_at,
                sort_order,
                is_active
            )
            VALUES
            (
                ?,
                ?,
                ?,
                NULL,
                ?,
                NULL,
                CURRENT_TIMESTAMP,
                0,
                1
            )'
        );

    $stmt->execute([
        $campsiteId,
        $key,
        $value,
        'llama-scout',
    ]);
}

$notice = '';
$error = '';

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
    && (string) (
        $_POST['campsite_admin_action']
        ?? ''
    ) === 'save'
) {
    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } elseif (!$selectedSite) {
        $error =
            'Choose a campsite before saving.';
    } else {
        try {
            $db->beginTransaction();

            $campsiteId =
                (int) $selectedSite['id'];

            $db->prepare(
                'UPDATE place_campsites
                 SET
                    site_code = ?,
                    site_name = ?,
                    site_type = ?,
                    parking_style = ?,
                    hookup_status = ?,
                    accessible_status = ?
                 WHERE id = ?
                   AND place_id = ?'
            )->execute([
                trim((string) ($_POST['site_code'] ?? '')) ?: null,
                trim((string) ($_POST['site_name'] ?? '')) ?: null,
                trim((string) ($_POST['site_type'] ?? 'other')) ?: 'other',
                trim((string) ($_POST['parking_style'] ?? 'unknown')) ?: 'unknown',
                trim((string) ($_POST['hookup_status'] ?? 'unknown')) ?: 'unknown',
                trim((string) ($_POST['accessible_status'] ?? 'unknown')) ?: 'unknown',
                $campsiteId,
                $placeId,
            ]);

            $exists =
                $db->prepare(
                    'SELECT campsite_id
                     FROM place_campsite_facts
                     WHERE campsite_id = ?
                     LIMIT 1'
                );

            $exists->execute([
                $campsiteId,
            ]);

            if (!$exists->fetchColumn()) {
                $db->prepare(
                    'INSERT INTO place_campsite_facts
                    (
                        campsite_id
                    )
                    VALUES
                    (
                        ?
                    )'
                )->execute([
                    $campsiteId,
                ]);
            }

            $facts = [
                'site_length_ft' =>
                    admin_campsite_nullable_number(
                        $_POST['site_length_ft']
                        ?? null
                    ),

                'site_width_ft' =>
                    admin_campsite_nullable_number(
                        $_POST['site_width_ft']
                        ?? null
                    ),

                'driveway_length_ft' =>
                    admin_campsite_nullable_number(
                        $_POST['driveway_length_ft']
                        ?? null
                    ),

                'driveway_grade' =>
                    trim(
                        (string) (
                            $_POST['driveway_grade']
                            ?? ''
                        )
                    ) ?: null,

                'driveway_surface' =>
                    trim(
                        (string) (
                            $_POST['driveway_surface']
                            ?? ''
                        )
                    ) ?: null,

                'overhead_clearance_ft' =>
                    admin_campsite_nullable_number(
                        $_POST['overhead_clearance_ft']
                        ?? null
                    ),

                'max_vehicle_length_ft' =>
                    admin_campsite_nullable_number(
                        $_POST['max_vehicle_length_ft']
                        ?? null
                    ),

                'max_people' =>
                    admin_campsite_nullable_int(
                        $_POST['max_people']
                        ?? null
                    ),

                'max_vehicles' =>
                    admin_campsite_nullable_int(
                        $_POST['max_vehicles']
                        ?? null
                    ),

                'tent_pad' =>
                    admin_campsite_tri(
                        $_POST['tent_pad']
                        ?? null
                    ),

                'tent_pad_length_ft' =>
                    admin_campsite_nullable_number(
                        $_POST['tent_pad_length_ft']
                        ?? null
                    ),

                'tent_pad_width_ft' =>
                    admin_campsite_nullable_number(
                        $_POST['tent_pad_width_ft']
                        ?? null
                    ),

                'electric_hookup' =>
                    admin_campsite_tri(
                        $_POST['electric_hookup']
                        ?? null
                    ),

                'electric_service' =>
                    trim(
                        (string) (
                            $_POST['electric_service']
                            ?? ''
                        )
                    ) ?: null,

                'water_hookup' =>
                    admin_campsite_tri(
                        $_POST['water_hookup']
                        ?? null
                    ),

                'sewer_hookup' =>
                    admin_campsite_tri(
                        $_POST['sewer_hookup']
                        ?? null
                    ),

                'checkin_time' =>
                    trim(
                        (string) (
                            $_POST['checkin_time']
                            ?? ''
                        )
                    ) ?: null,

                'checkout_time' =>
                    trim(
                        (string) (
                            $_POST['checkout_time']
                            ?? ''
                        )
                    ) ?: null,

                'proximity_to_water' =>
                    trim(
                        (string) (
                            $_POST['proximity_to_water']
                            ?? ''
                        )
                    ) ?: null,

                'shade_source_value' =>
                    trim(
                        (string) (
                            $_POST['shade_source_value']
                            ?? ''
                        )
                    ) ?: null,

                'privacy_source_value' =>
                    trim(
                        (string) (
                            $_POST['privacy_source_value']
                            ?? ''
                        )
                    ) ?: null,

                'quiet_area_source_value' =>
                    trim(
                        (string) (
                            $_POST['quiet_area_source_value']
                            ?? ''
                        )
                    ) ?: null,

                'max_horses' =>
                    admin_campsite_nullable_int(
                        $_POST['max_horses']
                        ?? null
                    ),
            ];

            $assignments = [];
            $values = [];

            foreach ($facts as $column => $value) {
                $assignments[] =
                    '`' . $column . '` = ?';

                $values[] =
                    $value;
            }

            $values[] =
                $campsiteId;

            $db->prepare(
                'UPDATE place_campsite_facts
                 SET '
                . implode(
                    ', ',
                    $assignments
                )
                . '
                 WHERE campsite_id = ?'
            )->execute(
                $values
            );

            foreach (
                [
                    'double_driveway',
                    'hike_in_distance',
                    'capacity_size_rating',
                    'site_rating',
                    'condition_rating',
                    'location_rating',
                    'picnic_table',
                    'grill',
                    'fire_ring',
                    'pets_allowed',
                    'food_storage',
                    'lantern_post',
                ]
                as $featureKey
            ) {
                admin_campsite_feature_override(
                    $db,
                    $campsiteId,
                    $featureKey,
                    $_POST[$featureKey]
                    ?? ''
                );
            }

            $db->commit();

            header(
                'Location: /place-campsites.php?place_id='
                . $placeId
                . '&site='
                . $campsiteId
                . '&saved=1',
                true,
                303
            );

            exit;

        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $error =
                $exception->getMessage();
        }
    }
}

if (
    (string) (
        $_GET['saved']
        ?? ''
    ) === '1'
) {
    $notice =
        'Campsite saved.';
}

if ($selectedSiteId > 0) {
    $sites =
        llama_place_campsites(
            $db,
            $placeId
        );

    foreach ($sites as $site) {
        if (
            (int) ($site['id'] ?? 0)
            === $selectedSiteId
        ) {
            $selectedSite = $site;
            break;
        }
    }
}

$adminPageTitle =
    (string) $place['name']
    . ' Campsites';

$adminPageEyebrow =
    'Place Administration';

$adminActiveNav =
    'places';

require __DIR__ . '/_header.php';

$e =
    static fn (
        mixed $value
    ): string =>
        htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );

$feature =
    static fn (
        string $key
    ): string =>
        admin_campsite_feature_value(
            $selectedSite,
            $key
        );

$selectedName =
    trim(
        (string) (
            $selectedSite['display_name']
            ?? ''
        )
    );
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/admin/pages/place.css"
>

<?php if ($notice !== ''): ?>
    <div class="admin-user-notice is-success">
        <?= $e($notice) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= $e($error) ?>
    </div>
<?php endif; ?>

<div class="admin-place-shared-page">

    <section class="admin-place-summary">
        <div class="admin-place-summary-main">
            <div class="admin-place-summary-heading">
                <p>
                    Place #<?= $placeId ?>
                </p>

                <h2>
                    <?= $e($place['name']) ?>
                </h2>
            </div>

            <span class="admin-place-summary-location">
                Campsite records are stored separately from campground-wide Place information.
            </span>
        </div>

        <div class="admin-place-summary-actions">
            <a
                class="admin-button is-muted"
                href="/place.php?id=<?= $placeId ?>"
            >
                Back to Place
            </a>
        </div>
    </section>

    <?php if (!$sites): ?>
        <section class="admin-place-admin-section">
            <div class="admin-place-empty">
                This Place does not have individual campsite records yet.
            </div>
        </section>
    <?php else: ?>

        <section class="admin-place-admin-section">
            <header>
                <p>Campsite selector</p>
                <h2>Choose a campsite</h2>
            </header>

            <form method="get">
                <input
                    type="hidden"
                    name="place_id"
                    value="<?= $placeId ?>"
                >

                <div class="admin-place-meta-grid">
                    <label>
                        <span>Campsite</span>

                        <select
                            name="site"
                            onchange="this.form.submit()"
                        >
                            <?php foreach ($sites as $site): ?>
                                <option
                                    value="<?= (int) $site['id'] ?>"
                                    <?= (int) $site['id'] === $selectedSiteId
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= $e(
                                        $site['display_name']
                                        ?? ('Site ' . $site['id'])
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </form>
        </section>

        <form
            method="post"
            class="admin-place-report-form place-report-form"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= $e(moderation_csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="place_id"
                value="<?= $placeId ?>"
            >

            <input
                type="hidden"
                name="campsite_id"
                value="<?= $selectedSiteId ?>"
            >

            <input
                type="hidden"
                name="campsite_admin_action"
                value="save"
            >

            <section class="admin-place-report-shell">
                <header class="admin-place-report-header">
                    <p>Individual campsite</p>
                    <h2>
                        <?= $selectedName !== ''
                            ? $e($selectedName)
                            : 'Campsite details' ?>
                    </h2>

                    <span class="admin-place-muted">
                        These answers belong only to this campsite and do not overwrite campground-wide Place information.
                    </span>
                </header>

                <details class="contribution-section" open>
                    <summary>
                        <span>
                            <?= llama_icon('camper') ?>
                            Site and vehicle fit
                        </span>

                        <small>
                            Capacity, parking, dimensions, tents, access, and hookups
                        </small>
                    </summary>

                    <div class="contribution-section-body">
                        <div class="contribution-grid">

                            <label>
                                <span>Site number / identifier</span>
                                <input
                                    type="text"
                                    name="site_code"
                                    value="<?= $e($selectedSite['site_code'] ?? '') ?>"
                                >
                            </label>

                            <label>
                                <span>Site name</span>
                                <input
                                    type="text"
                                    name="site_name"
                                    value="<?= $e($selectedSite['site_name'] ?? '') ?>"
                                >
                            </label>

                            <label>
                                <span>Site accessible?</span>
                                <select name="accessible_status">
                                    <?php foreach ([
                                        'unknown' => 'Unknown',
                                        'yes' => 'Yes',
                                        'no' => 'No',
                                    ] as $value => $label): ?>
                                        <option
                                            value="<?= $e($value) ?>"
                                            <?= (string) ($selectedSite['accessible_status'] ?? 'unknown') === $value
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= $e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>Site type</span>
                                <select name="site_type">
                                    <?php foreach ([
                                        'rv_site' => 'RV site',
                                        'tent_site' => 'Tent site',
                                        'mixed_site' => 'Tent / RV',
                                        'vehicle_site' => 'Vehicle site',
                                        'group_site' => 'Group site',
                                        'other' => 'Other',
                                    ] as $value => $label): ?>
                                        <option
                                            value="<?= $e($value) ?>"
                                            <?= (string) ($selectedSite['site_type'] ?? 'other') === $value
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= $e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <?php
                            $numberFields = [
                                'max_people' => 'Maximum people',
                                'max_vehicles' => 'Maximum vehicles',
                                'max_vehicle_length_ft' => 'Maximum vehicle length (ft)',
                                'site_length_ft' => 'Site length (ft)',
                                'site_width_ft' => 'Site width (ft)',
                                'driveway_length_ft' => 'Parking / driveway length (ft)',
                                'overhead_clearance_ft' => 'Overhead clearance (ft)',
                                'tent_pad_length_ft' => 'Tent pad length (ft)',
                                'tent_pad_width_ft' => 'Tent pad width (ft)',
                                'max_horses' => 'Maximum horses',
                            ];
                            ?>

                            <?php foreach ($numberFields as $key => $label): ?>
                                <label>
                                    <span><?= $e($label) ?></span>
                                    <input
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        name="<?= $e($key) ?>"
                                        value="<?= $e($selectedSite[$key] ?? '') ?>"
                                    >
                                </label>
                            <?php endforeach; ?>

                            <label>
                                <span>Parking style</span>
                                <select name="parking_style">
                                    <?php foreach ([
                                        'unknown' => 'Unknown',
                                        'pull_through' => 'Pull-through',
                                        'back_in' => 'Back-in',
                                        'pull_in' => 'Pull-in',
                                        'parallel' => 'Parallel',
                                        'other' => 'Other',
                                    ] as $value => $label): ?>
                                        <option
                                            value="<?= $e($value) ?>"
                                            <?= (string) ($selectedSite['parking_style'] ?? 'unknown') === $value
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= $e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>Driveway surface</span>
                                <input
                                    type="text"
                                    name="driveway_surface"
                                    value="<?= $e($selectedSite['driveway_surface'] ?? '') ?>"
                                >
                            </label>

                            <label>
                                <span>Driveway grade</span>
                                <input
                                    type="text"
                                    name="driveway_grade"
                                    value="<?= $e($selectedSite['driveway_grade'] ?? '') ?>"
                                >
                            </label>

                            <label>
                                <span>Tent pad / platform?</span>
                                <select name="tent_pad">
                                    <option value="">Unknown</option>
                                    <option value="1" <?= (string) ($selectedSite['tent_pad'] ?? '') === '1' ? 'selected' : '' ?>>Yes</option>
                                    <option value="0" <?= (string) ($selectedSite['tent_pad'] ?? '') === '0' ? 'selected' : '' ?>>No</option>
                                </select>
                            </label>

                            <label>
                                <span>Double driveway?</span>
                                <select name="double_driveway">
                                    <option value="">Unknown</option>
                                    <option value="Yes" <?= strtolower($feature('double_driveway')) === 'yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="No" <?= strtolower($feature('double_driveway')) === 'no' ? 'selected' : '' ?>>No</option>
                                </select>
                            </label>

                            <label>
                                <span>Hike-in distance</span>
                                <input
                                    type="text"
                                    name="hike_in_distance"
                                    value="<?= $e($feature('hike_in_distance')) ?>"
                                >
                            </label>

                            <label>
                                <span>Hookups</span>
                                <select name="hookup_status">
                                    <?php foreach ([
                                        'unknown' => 'Unknown',
                                        'full' => 'Full hookups',
                                        'electric_water' => 'Electric + water',
                                        'electric_only' => 'Electric only',
                                        'water_only' => 'Water only',
                                        'none' => 'No hookups',
                                        'varies' => 'Varies',
                                    ] as $value => $label): ?>
                                        <option
                                            value="<?= $e($value) ?>"
                                            <?= (string) ($selectedSite['hookup_status'] ?? 'unknown') === $value
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= $e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <?php foreach ([
                                'electric_hookup' => 'Electric hookup',
                                'water_hookup' => 'Water hookup',
                                'sewer_hookup' => 'Sewer hookup',
                            ] as $key => $label): ?>
                                <label>
                                    <span><?= $e($label) ?></span>
                                    <select name="<?= $e($key) ?>">
                                        <option value="">Unknown</option>
                                        <option value="1" <?= (string) ($selectedSite[$key] ?? '') === '1' ? 'selected' : '' ?>>Yes</option>
                                        <option value="0" <?= (string) ($selectedSite[$key] ?? '') === '0' ? 'selected' : '' ?>>No</option>
                                    </select>
                                </label>
                            <?php endforeach; ?>

                            <label>
                                <span>Electric service</span>
                                <input
                                    type="text"
                                    name="electric_service"
                                    value="<?= $e($selectedSite['electric_service'] ?? '') ?>"
                                >
                            </label>

                        </div>
                    </div>
                </details>

                <details class="contribution-section">
                    <summary>
                        <span>
                            <?= llama_icon('picnic-table') ?>
                            Site amenities
                        </span>

                        <small>
                            Amenities attached to this individual campsite
                        </small>
                    </summary>

                    <div class="contribution-section-body">
                        <div class="contribution-grid">

                            <?php foreach ([
                                'picnic_table' => 'Picnic table',
                                'grill' => 'Grill / barbecue',
                                'fire_ring' => 'Fire ring',
                                'pets_allowed' => 'Pets allowed',
                                'food_storage' => 'Food storage',
                                'lantern_post' => 'Lantern post',
                            ] as $key => $label): ?>
                                <label>
                                    <span><?= $e($label) ?></span>
                                    <select name="<?= $e($key) ?>">
                                        <option value="">Unknown</option>
                                        <option value="Yes" <?= strtolower($feature($key)) === 'yes' ? 'selected' : '' ?>>Yes</option>
                                        <option value="No" <?= strtolower($feature($key)) === 'no' ? 'selected' : '' ?>>No</option>
                                    </select>
                                </label>
                            <?php endforeach; ?>

                        </div>
                    </div>
                </details>

                <details class="contribution-section">
                    <summary>
                        <span>
                            <?= llama_icon('star') ?>
                            Experience and recommendations
                        </span>

                        <small>
                            Site-specific quality, condition, and location ratings
                        </small>
                    </summary>

                    <div class="contribution-section-body">
                        <div class="contribution-grid">

                            <?php foreach ([
                                'site_rating' => 'Site rating',
                                'condition_rating' => 'Condition rating',
                                'location_rating' => 'Location rating',
                            ] as $key => $label): ?>
                                <label>
                                    <span><?= $e($label) ?></span>
                                    <input
                                        type="text"
                                        name="<?= $e($key) ?>"
                                        value="<?= $e($feature($key)) ?>"
                                    >
                                </label>
                            <?php endforeach; ?>

                            <label>
                                <span>Capacity / size rating</span>
                                <input
                                    type="text"
                                    name="capacity_size_rating"
                                    value="<?= $e($feature('capacity_size_rating')) ?>"
                                >
                            </label>

                        </div>
                    </div>
                </details>

                <details class="contribution-section">
                    <summary>
                        <span>
                            <?= llama_icon('at-landscape') ?>
                            Site environment
                        </span>

                        <small>
                            Conditions that can vary from one campsite to another
                        </small>
                    </summary>

                    <div class="contribution-section-body">
                        <div class="contribution-grid">

                            <?php foreach ([
                                'proximity_to_water' => 'Proximity to water',
                                'shade_source_value' => 'Shade',
                                'privacy_source_value' => 'Privacy',
                                'quiet_area_source_value' => 'Quiet area',
                            ] as $key => $label): ?>
                                <label>
                                    <span><?= $e($label) ?></span>
                                    <input
                                        type="text"
                                        name="<?= $e($key) ?>"
                                        value="<?= $e($selectedSite[$key] ?? '') ?>"
                                    >
                                </label>
                            <?php endforeach; ?>

                        </div>
                    </div>
                </details>

            </section>

            <div class="admin-place-report-savebar">
                <button
                    class="admin-button"
                    type="submit"
                >
                    <?= llama_icon('device-floppy') ?>
                    Save campsite
                </button>
            </div>
        </form>

    <?php endif; ?>

</div>

<?php require __DIR__ . '/_footer.php'; ?>
