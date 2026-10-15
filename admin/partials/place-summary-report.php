<?php

declare(strict_types=1);
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
    <a href="#place-report">Place Report</a>
    <a href="#photos">Photos</a>
    <a href="#notes">Notes</a>
    <a href="#verification">Verification</a>
    <a href="#status">Status</a>
    <a href="#history">History</a>
</nav>

<form method="post" class="admin-place-report-form place-report-form" id="place-report">
    <input type="hidden" name="csrf_token" value="<?= $e(moderation_csrf_token()) ?>">
    <input type="hidden" name="place_id" value="<?= $placeId ?>">
    <input type="hidden" name="place_admin_action" value="save-report">
    <input type="hidden" name="photo_stage_token"
           value="<?= $e((string) ($_POST['photo_stage_token'] ?? '')) ?>">
    <input type="hidden" name="photos_json"
           value="<?= $e((string) ($_POST['photos_json'] ?? '[]')) ?>">

    <section class="admin-place-admin-meta">
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
            <p>Shared Place Report</p>
            <h2>Edit Place Report</h2>
            <span class="admin-place-muted">
                This is the same question set and control system used by Add Place and moderation.
            </span>
        </header>

        <?php require __DIR__ . '/place-report-area-selector.php'; ?>

        <script type="application/json" data-admin-place-campsites><?= json_encode(
            [
                'sites' => $adminPlaceCampsites,
                'site_field_keys' => admin_place_campsite_field_keys(),
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

        <?php
        $placeReportPhotoContext = 'admin-place';
        require dirname(__DIR__, 2) . '/partials/place-report/form.php';
        ?>
    </section>

    <div class="admin-place-report-savebar">
        <button class="admin-button" type="submit" formnovalidate data-place-report-admin-save>
            <i aria-hidden="true"><?= llama_icon('device-floppy') ?></i>
            Save Place Report
        </button>
    </div>
</form>
