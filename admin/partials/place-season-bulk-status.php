<?php

declare(strict_types=1);

/**
 * M4-004: normal seasonal status for multiple canonical Sites in one action.
 * Actual active Site IDs are selected explicitly; Site numbers are labels,
 * never interpreted as database IDs. This form never modifies closure records.
 */
$bulkSiteChoices = [];
foreach ($seasonTargetChoices as $item) {
    if ((string) ($item['scope'] ?? '') === 'site'
        && (string) ($item['source'] ?? '') === 'campsite_record') {
        $bulkSiteChoices[(int) $item['id']] = $item;
    }
}
$bulkSavedStatuses = [];
$bulkLoadError = '';
if ($bulkSiteChoices) {
    try {
        $query = $db->prepare(
            'SELECT target_id, operating_status
             FROM place_season_site_status
             WHERE place_id = ? AND season_id = ?
               AND target_source = ?'
        );
        $query->execute([$placeId, $chosenSeasonId, 'campsite_record']);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $bulkSavedStatuses[(int) $row['target_id']] =
                (string) $row['operating_status'];
        }
    } catch (Throwable $bulkException) {
        $bulkLoadError =
            'Seasonal Site statuses could not be loaded. Check the M4-002 migration.';
        if (function_exists('llama_log_caught_exception')) {
            llama_log_caught_exception($bulkException, 'admin.place.season-bulk');
        }
    }
}
$bulkStatus = 'closed';
$bulkSelected = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && ($action ?? '') === 'save-season-bulk-sites'
    && ($error ?? '') !== '') {
    $bulkStatus = (string) ($_POST['season_bulk_status'] ?? 'closed');
    $rawSelection = $_POST['season_site_ids'] ?? [];
    if (is_array($rawSelection)) {
        foreach ($rawSelection as $id) {
            if ((is_string($id) || is_int($id))
                && ctype_digit((string) $id)) {
                $bulkSelected[(int) $id] = true;
            }
        }
    }
}
?>

<section class="admin-place-season-bulk" aria-labelledby="season-bulk-heading">
    <header class="admin-place-report-header">
        <p>Normal seasonal Site schedules</p>
        <h3 id="season-bulk-heading">Update multiple Sites</h3>
        <span class="admin-place-muted">
            Select the Sites affected during <?= $e((string) $chosenSeason['name']) ?>
            and set their normal seasonal operating status together.
            Sites you do not select will not change.
        </span>
    </header>

    <?php if (!$bulkSiteChoices): ?>
        <p class="admin-place-seasons-message">
            No active canonical Sites are available for bulk editing at this Place.
        </p>
    <?php elseif ($bulkLoadError !== ''): ?>
        <p class="admin-place-seasons-message is-error"><?= $e($bulkLoadError) ?></p>
    <?php else: ?>
        <form method="post" action="/place.php?id=<?= (int) $placeId ?>#place-seasons"
              class="admin-place-season-bulk-form" data-season-bulk-form>
            <input type="hidden" name="csrf_token"
                   value="<?= $e(moderation_csrf_token()) ?>">
            <input type="hidden" name="place_admin_action"
                   value="save-season-bulk-sites">
            <input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
            <input type="hidden" name="season_id" value="<?= $chosenSeasonId ?>">

            <div class="admin-place-season-bulk-toolbar">
                <label>
                    <span>Seasonal status</span>
                    <select name="season_bulk_status">
                        <?php foreach ([
                            'closed' => 'Normally closed this season',
                            'open' => 'Normally open this season',
                            'inherit' => 'Clear seasonal status (use normal)',
                        ] as $value => $label): ?>
                            <option value="<?= $e($value) ?>"
                                <?= $bulkStatus === $value ? 'selected' : '' ?>>
                                <?= $e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Find Sites</span>
                    <input type="search" autocomplete="off"
                           placeholder="Search Site number or name"
                           data-season-bulk-search>
                </label>
            </div>

            <div class="admin-place-season-bulk-select-actions">
                <button type="button" data-season-bulk-select-visible>
                    Select matching Sites
                </button>
                <button type="button" data-season-bulk-clear>
                    Clear selection
                </button>
                <strong data-season-bulk-count aria-live="polite">0 selected</strong>
            </div>

            <div class="admin-place-season-bulk-sites" role="group"
                 aria-label="Sites to update during <?= $e((string) $chosenSeason['name']) ?>">
                <?php foreach ($bulkSiteChoices as $siteId => $target): ?>
                    <?php
                    $current = $bulkSavedStatuses[$siteId] ?? '';
                    $currentLabel = match ($current) {
                        'closed' => 'Seasonally closed',
                        'open' => 'Seasonally open',
                        default => 'Normal status',
                    };
                    ?>
                    <label class="admin-place-season-bulk-site" data-season-bulk-site>
                        <input type="checkbox" name="season_site_ids[]"
                               value="<?= (int) $siteId ?>"
                               <?= isset($bulkSelected[$siteId]) ? 'checked' : '' ?>>
                        <span><?= $e((string) $target['label']) ?></span>
                        <small><?= $e($currentLabel) ?></small>
                    </label>
                <?php endforeach; ?>
            </div>

            <label class="admin-place-season-source">
                <span>Source URL (optional)</span>
                <input type="url" name="season_bulk_source_url"
                       placeholder="https://..."
                       value="<?= $e(
                           (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
                            && ($action ?? '') === 'save-season-bulk-sites'
                            && ($error ?? '') !== '')
                               ? (string) ($_POST['season_bulk_source_url'] ?? '')
                               : ''
                       ) ?>">
            </label>
            <p class="admin-place-seasons-help">
                Maximum 250 Sites per save. This affects only the chosen season,
                not Site dimensions, other seasons or verified closures.
                Use Clear seasonal status to restore normal behavior for selected Sites.
            </p>
            <button class="admin-button" type="submit" data-season-bulk-save disabled>
                Save selected Site statuses
            </button>
        </form>
        <script defer src="/js/admin/place-season-bulk-status.js"></script>
    <?php endif; ?>
</section>
