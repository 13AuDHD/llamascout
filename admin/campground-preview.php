<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';

$adminUser = moderation_require_admin();

$adminPageTitle = 'Campground Structure Preview';
$adminPageEyebrow = 'Places';
$adminActiveNav = 'places';

$error = '';
$place = null;
$sites = [];
$facilityFacts = [];
$facilityFeatures = [];
$state = [];
$selectedSite = [];

$slug = trim((string) ($_GET['slug'] ?? ''));

if ($slug !== '') {
    try {
        $place = place_member_by_slug($slug);

        if (!$place) {
            throw new RuntimeException(
                'That Place could not be found.'
            );
        }

        $placeId = (int) $place['id'];

        $sites = llama_place_campsites(
            db(),
            $placeId
        );

        $facilityFacts = llama_place_facility_facts(
            db(),
            $placeId
        );

        $facilityFeatures = llama_place_facility_features(
            db(),
            $placeId
        );

        $state = llama_place_campsite_browser_state(
            $place,
            $sites
        );

        $selectedFeatureId = max(
            0,
            (int) ($_GET['site'] ?? 0)
        );

        if ($selectedFeatureId > 0) {
            $selectedSite = llama_place_campsite(
                db(),
                $placeId,
                $selectedFeatureId
            );
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

require __DIR__ . '/_header.php';
?>

<?php if ($error !== '') { ?>
<section class="admin-panel">
    <strong>Preview error</strong>
    <p><?= moderation_e($error) ?></p>
</section>
<?php } ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Place-aware campground model</p>
            <h2>Preview a Place</h2>
        </div>
    </header>

    <form method="get">
        <label>
            <span>Place slug</span>

            <input
                type="text"
                name="slug"
                value="<?= moderation_e($slug) ?>"
                placeholder="example-campground"
                required
            >
        </label>

        <div class="admin-user-form-actions">
            <button
                class="admin-button"
                type="submit"
            >
                Preview
            </button>
        </div>
    </form>
</section>

<?php if ($place) { ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p><?= moderation_e((string) ($place['type'] ?? '')) ?></p>
            <h2><?= moderation_e((string) $place['name']) ?></h2>
        </div>
    </header>

    <dl class="admin-user-definition-list">
        <div>
            <dt>Experience mode</dt>
            <dd><?= moderation_e((string) ($state['mode'] ?? '')) ?></dd>
        </div>

        <div>
            <dt>Campsites</dt>
            <dd><?= number_format(count($sites)) ?></dd>
        </div>

        <div>
            <dt>Show campsite browser</dt>
            <dd><?= !empty($state['show_browser']) ? 'Yes' : 'No' ?></dd>
        </div>

        <div>
            <dt>Single-site treatment</dt>
            <dd>
                <?= !empty($state['show_single_site'])
                    ? 'Inline site detail'
                    : 'Not applicable' ?>
            </dd>
        </div>
    </dl>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Shared information</p>
            <h2>Facility layer</h2>
        </div>
    </header>

    <?php if (!$facilityFacts && !$facilityFeatures) { ?>
        <div class="admin-empty-state">
            <p>
                No structured facility facts have been stored yet.
                Existing Place information remains the shared layer.
            </p>
        </div>
    <?php } else { ?>

        <?php if ($facilityFacts) { ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <tbody>
                <?php foreach ($facilityFacts as $key => $value) { ?>
                    <?php
                    if ($value === null || $value === '') {
                        continue;
                    }
                    ?>
                    <tr>
                        <th><?= moderation_e((string) $key) ?></th>
                        <td><?= moderation_e((string) $value) ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>

        <?php if ($facilityFeatures) { ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>Value</th>
                        <th>Qualifier</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($facilityFeatures as $feature) { ?>
                    <tr>
                        <td><?= moderation_e((string) $feature['feature_key']) ?></td>
                        <td><?= moderation_e((string) ($feature['feature_value'] ?? '')) ?></td>
                        <td><?= moderation_e((string) ($feature['qualifier'] ?? '')) ?></td>
                        <td><?= moderation_e((string) ($feature['source_provider'] ?? '')) ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>

    <?php } ?>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Variable information</p>
            <h2>Campsites</h2>
        </div>
    </header>

    <?php if (!$sites) { ?>
        <div class="admin-empty-state">
            <p>
                No individual campsite entities are attached to this Place.
            </p>
        </div>
    <?php } else { ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Type</th>
                        <th>Parking</th>
                        <th>Hookups</th>
                        <th>Max vehicle</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($sites as $site) { ?>
                    <tr>
                        <td>
                            <strong><?= moderation_e((string) $site['display_name']) ?></strong>
                        </td>

                        <td>
                            <?= moderation_e(
                                llama_place_campsite_type_label(
                                    (string) ($site['site_type'] ?? '')
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                llama_place_campsite_parking_label(
                                    (string) ($site['parking_style'] ?? '')
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                llama_place_campsite_hookup_label(
                                    (string) ($site['hookup_status'] ?? '')
                                )
                            ) ?>
                        </td>

                        <td>
                            <?php
                            if (
                                isset($site['max_vehicle_length_ft'])
                                && is_numeric($site['max_vehicle_length_ft'])
                            ) {
                                echo moderation_e(
                                    rtrim(
                                        rtrim(
                                            number_format(
                                                (float) $site['max_vehicle_length_ft'],
                                                1
                                            ),
                                            '0'
                                        ),
                                        '.'
                                    )
                                    . ' ft'
                                );
                            }
                            ?>
                        </td>

                        <td>
                            <a
                                class="admin-button"
                                href="/campground-preview.php?slug=<?= rawurlencode($slug) ?>&site=<?= (int) $site['feature_id'] ?>#selected-site"
                            >
                                Inspect
                            </a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<?php if ($selectedSite) { ?>
<section
    class="admin-panel"
    id="selected-site"
>
    <header class="admin-panel-header">
        <div>
            <p>Selected campsite</p>
            <h2><?= moderation_e((string) $selectedSite['display_name']) ?></h2>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <tbody>
            <?php foreach ($selectedSite as $key => $value) { ?>
                <?php
                if (
                    $key === 'features'
                    || $value === null
                    || $value === ''
                ) {
                    continue;
                }
                ?>
                <tr>
                    <th><?= moderation_e((string) $key) ?></th>
                    <td>
                        <?= moderation_e(
                            is_scalar($value)
                                ? (string) $value
                                : (
                                    json_encode(
                                        $value,
                                        JSON_UNESCAPED_SLASHES
                                        | JSON_UNESCAPED_UNICODE
                                    )
                                    ?: ''
                                )
                        ) ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($selectedSite['features'])) { ?>
        <h3>Site features</h3>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>Value</th>
                        <th>Qualifier</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($selectedSite['features'] as $feature) { ?>
                    <tr>
                        <td><?= moderation_e((string) $feature['feature_key']) ?></td>
                        <td><?= moderation_e((string) ($feature['feature_value'] ?? '')) ?></td>
                        <td><?= moderation_e((string) ($feature['qualifier'] ?? '')) ?></td>
                        <td><?= moderation_e((string) ($feature['source_provider'] ?? '')) ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>
<?php } ?>

<?php } ?>

<?php require __DIR__ . '/_footer.php'; ?>
