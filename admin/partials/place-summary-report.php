<?php

declare(strict_types=1);

$placeReportSiteOptions = array_column($adminPlaceCampsites, 'label', 'id');
$requestedSiteId = (int) ($_GET['site_id'] ?? 0);
$requestedSite = 'campsite_record:' . $requestedSiteId;
$placeReportSelectedSite = isset($placeReportSiteOptions[$requestedSite]) ? $requestedSite : '';
?>

<section class="admin-place-summary">
    <div class="admin-place-summary-main">
        <div class="admin-place-summary-heading">
            <p>Place #<?= (int) $place['id'] ?></p>
            <h2><?= $e($place['name']) ?></h2>
            <span class="admin-status-pill">
                <?= $e(ucfirst((string) $place['status'])) ?>
            </span>
        </div>
        <span class="admin-place-summary-location">
            <?= $e(implode(', ', array_filter([$place['city'] ?? null, $place['state'] ?? null]))) ?>
        </span>
        <div class="admin-place-summary-scout-status">
            <span>Scout status</span>
            <strong class="<?= !empty($llamaScouted['ever_scouted']) ? 'is-good' : '' ?>">
                <i aria-hidden="true">
                    <?= llama_icon(!empty($llamaScouted['ever_scouted']) ? 'binoculars' : 'circle-minus') ?>
                </i>
                <?= !empty($llamaScouted['ever_scouted']) ? 'Llama Scouted' : 'Not yet Llama Scouted' ?>
            </strong>
        </div>
    </div>
    <div class="admin-place-summary-actions">
        <?php if (!empty($adminPlaceCampsites)): ?>
            <a class="admin-button is-muted"
               href="/place-campsites.php?place_id=<?= (int) $placeId ?>">
                Site privacy exceptions
            </a>
        <?php endif; ?>
        <?php if (in_array((string) $place['status'], ['active', 'featured'], true)): ?>
            <a class="admin-button is-muted"
               href="https://llamascout.com/place.php?slug=<?= rawurlencode((string) $place['slug']) ?>"
               target="_blank" rel="noopener">View public Place</a>
        <?php endif; ?>
    </div>
</section>

<section class="admin-place-operations-strip" aria-label="Place operational summary">
    <div><span>Contributions</span><strong><?= number_format((int) $operationalCounts['contributions']) ?></strong></div>
    <div><span>Pending updates</span><strong><?= number_format((int) $operationalCounts['pending_updates']) ?></strong></div>
    <div><span>Open reports</span><strong><?= number_format((int) $operationalCounts['open_reports']) ?></strong></div>
    <div><span>Photos</span><strong><?= number_format(count($images)) ?></strong></div>
    <div><span>Verifications</span><strong><?= number_format((int) $operationalCounts['verifications']) ?></strong></div>
</section>

<nav class="admin-place-section-nav">
    <a href="#place-report">Scout Report</a>
    <a href="/campsite-pricing.php?place_id=<?= (int) $placeId ?>">Campsite Pricing</a>
    <a href="#photos">Photos</a>
    <a href="#notes">Notes</a>
    <a href="#verification">Verification</a>
    <a href="#status">Status</a>
    <a href="#history">History</a>
</nav>

<?php require __DIR__ . '/place-map.php'; ?>



<div class="contribution-tab-shell" data-contribution-tabs data-contribution-split
    data-admin-facility-source="<?= $selectedFacilityResource || $creatingFacility ? 'resource' : 'map_feature' ?>"
    data-contribution-initial="<?= in_array((string) ($_GET['mode'] ?? ''), ['facility', 'area'], true) ? 'facility' : ($placeReportSelectedSite !== '' ? 'site' : 'place') ?>">
    <?php require dirname(__DIR__, 2) . '/partials/place-report/contribution-tabs.php'; ?>
    <?php require __DIR__ . '/place-report-area-selector.php'; ?>

<form method="post" class="admin-place-report-form place-report-form" id="place-report" data-contribution-report
    <?php if ($placeReportSelectedSite !== ''): ?>data-admin-site-report-id="<?= $requestedSiteId ?>"<?php endif; ?>>
    <input type="hidden" name="csrf_token" value="<?= $e(moderation_csrf_token()) ?>">
    <input type="hidden" name="place_id" value="<?= $placeId ?>">
    <input type="hidden" name="place_admin_action" value="save-report">
    <input type="hidden" name="photo_stage_token"
           value="<?= $e((string) ($_POST['photo_stage_token'] ?? '')) ?>">
    <input type="hidden" name="photos_json"
           value="<?= $e((string) ($_POST['photos_json'] ?? '[]')) ?>">

    <section class="admin-place-admin-meta" data-contribution-place-only>
        <header><p>Admin metadata</p><h2>Publishing + URL</h2></header>
        <div class="admin-place-meta-grid">
            <label>
                <span>URL slug</span>
                <input type="text" name="admin_slug" value="<?= $e($place['slug'] ?? '') ?>" required>
            </label>
            <label>
                <span>Record source</span>
                <select name="admin_source_type">
                    <?php foreach ([
                        'llama-scouted' => 'Llama Scouted',
                        'community-scouted' => 'Community Scouted',
                        'external' => 'External source',
                        'legacy' => 'Legacy',
                    ] as $value => $label): ?>
                        <option value="<?= $e($value) ?>"
                            <?= (string) ($place['source_type'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= $e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </section>

    <section class="admin-place-report-shell">
        <header class="admin-place-report-header">
            <p>Place Administration</p>
            <h2 data-admin-report-mode-heading>Edit Place Report</h2>
            <span class="admin-place-muted">
                Place, Site and Facility share this Administration page. All existing
                Area records appear as Facilities, retaining their original report answers.
            </span>
        </header>

        <?php require __DIR__ . '/place-report-facility-metadata.php'; ?>
        <?php require __DIR__ . '/place-report-facility-questions.php'; ?>

        <script type="application/json" data-admin-place-campsites><?= json_encode(
            [
                'sites' => $adminPlaceCampsites,
                'site_field_keys' => admin_place_campsite_field_keys(),
                'site_shared_field_keys' => admin_place_site_shared_question_keys(),
                'place_shared_answers' => array_intersect_key(
                    $placeReportValues,
                    array_fill_keys(admin_place_site_shared_question_keys(), true)
                ),
                'place_field_keys' => admin_place_site_section_place_field_keys(),
                'unknown_token' => llama_place_report_unknown_token(),
                'unanswered_token' => llama_place_report_unanswered_token(),
            ],
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        ) ?></script>

        <script type="application/json" data-admin-place-photo-targets><?= json_encode(
            [
                'place_id' => $placeId,
                'targets' => $adminPlacePhotoTargets,
            ],
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        ) ?></script>

        <?php require __DIR__ . '/place-context.php'; ?>

        <?php
        $placeReportPhotoContext = 'admin-place';

        require dirname(__DIR__, 2) . '/partials/place-report/form.php';

        // Place-layout questions also eligible for Site scope get their own
        // Site-only controls. They cannot share the parent Place inputs.
        $adminSiteSharedFields = array_intersect_key(
            llama_report_questions_for_scope('site', true),
            array_fill_keys(admin_place_site_shared_question_keys(), true)
        );
        $adminSiteSharedGroups = llama_report_group_selected_questions($adminSiteSharedFields);
        ?>
        <?php if ($adminSiteSharedFields): ?>
            <fieldset data-admin-site-shared-questions hidden disabled>
                <legend>Site rules and policies</legend>
                <p>These answers apply only to the selected Site. Existing Place
                   policies appear as inherited values until you change them.</p>
                <?php foreach ($adminSiteSharedGroups as $group): ?>
                    <?php if (empty($group['fields'])) continue; ?>
                    <section class="contribution-section" data-admin-site-policy-group>
                        <h3><?= $e($group['label']) ?></h3>
                        <div class="contribution-section-body">
                            <?php foreach ($group['fields'] as $siteField): ?>
                                <?php
                                // In the same HTML form, equal radio names
                                // form one group even if one fieldset is hidden.
                                // Namespace ONLY the Site control names. The
                                // data-report-field ID stays canonical.
                                ob_start();
                                $renderField($siteField, 'admin-site-');
                                $siteControlHtml = (string) ob_get_clean();
                                echo preg_replace_callback(
                                    '/\\bname="([^"]+)"/',
                                    static fn (array $match): string =>
                                        'name="admin_site_' . $match[1] . '"',
                                    $siteControlHtml
                                );
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </fieldset>
        <?php endif; ?>
    </section>

    <div class="admin-place-report-savebar">
        <button class="admin-button" type="submit" formnovalidate data-place-report-admin-save>
            <i aria-hidden="true"><?= llama_icon('device-floppy') ?></i>
            <span data-admin-save-report-label>Save Place Report</span>
        </button>
    </div>
</form>
<?php require __DIR__ . '/place-linked-facility-editor.php'; ?>
</div>

<?php // Season creation, pricing and Site availability now live only in Campsite Pricing. ?>
