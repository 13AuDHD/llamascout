<?php

declare(strict_types=1);

/**
 * M4-001: Season definitions are managed inside the existing Admin Place
 * Report page, but in an independent form so Save Place Report cannot mutate
 * ordinary answers when the user edits a season.
 */
$seasonEditId = max(0, (int) ($_GET['season_edit'] ?? 0));
$seasonEditing = null;
foreach ($adminPlaceSeasons as $candidate) {
    if ((int) ($candidate['id'] ?? 0) === $seasonEditId) {
        $seasonEditing = $candidate;
        break;
    }
}

$seasonDraft = $seasonEditing ?? [
    'id' => 0,
    'name' => '',
    'start_mmdd' => '',
    'end_mmdd' => '',
    'is_default' => empty($adminPlaceSeasons) ? 1 : 0,
    'is_active' => 1,
    'sort_order' => 0,
    'notes' => '',
    'source_url' => '',
];

// Keep the user's inputs visible after a failed save.
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && ($action ?? '') === 'save-season'
    && ($error ?? '') !== ''
) {
    $seasonDraft = [
        'id' => (int) ($_POST['season_id'] ?? 0),
        'name' => (string) ($_POST['season_name'] ?? ''),
        'start_mmdd' => (string) ($_POST['start_mmdd'] ?? ''),
        'end_mmdd' => (string) ($_POST['end_mmdd'] ?? ''),
        'is_default' => (int) ($_POST['is_default'] ?? 0),
        'is_active' => (int) ($_POST['is_active'] ?? 0),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'notes' => (string) ($_POST['season_notes'] ?? ''),
        'source_url' => (string) ($_POST['season_source_url'] ?? ''),
    ];
}

$seasonSaved = (string) ($_GET['saved'] ?? '');
$seasonRequest = (string) ($action ?? '');
$showSeasons = isset($_GET['season_edit'])
    || isset($_GET['season_view'])
    || str_starts_with($seasonSaved, 'season')
    || (
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && str_starts_with($seasonRequest, 'save-season')
    );
$seasonCount = count($adminPlaceSeasons);
?>

<details class="admin-place-seasons" id="place-seasons"
         data-admin-seasons-disclosure
         <?= $showSeasons ? 'open' : '' ?>>
    <summary class="admin-place-seasons-toggle">
        <span class="admin-place-seasons-toggle-label">
            <strong>Seasonal conditions</strong>
            <small><?= $seasonCount > 0
                ? $e((string) $seasonCount . ($seasonCount === 1 ? ' season configured' : ' seasons configured'))
                : 'Optional · No seasons configured' ?></small>
        </span>
        <span class="admin-place-seasons-toggle-action">
            <span>Manage seasons</span>
            <span class="admin-place-seasons-toggle-chevron" aria-hidden="true">⌄</span>
        </span>
    </summary>
    <div class="admin-place-seasons-content">
        <header class="admin-place-report-header">
            <p>Scout Report · Operating seasons</p>
            <h2 id="admin-place-seasons-heading">Season definitions and changes</h2>
            <span class="admin-place-muted">
                Add seasons only when services or Site rules change through the year.
                Seasonal answers are separate from the main Scout Report and verified closures.
            </span>
        </header>

    <?php if ($adminPlaceSeasonsError !== ''): ?>
        <p class="admin-place-seasons-message is-error">
            <?= $e($adminPlaceSeasonsError) ?>
        </p>
    <?php else: ?>
        <?php if (!$adminPlaceSeasons): ?>
            <p class="admin-place-seasons-message">
                No operating seasons defined yet. A Place without seasons
                continues to use its normal Scout Report.
            </p>
        <?php else: ?>
            <div class="admin-place-seasons-list" aria-label="Configured seasons">
                <?php foreach ($adminPlaceSeasons as $item): ?>
                    <?php
                    $start = (string) ($item['start_mmdd'] ?? '');
                    $end = (string) ($item['end_mmdd'] ?? '');
                    $dates = $start !== '' && $end !== ''
                        ? $start . ' to ' . $end
                        : 'Manual selection only';
                    ?>
                    <a class="admin-place-season-entry<?= $seasonEditId === (int) $item['id'] ? ' is-selected' : '' ?>"
                       href="/place.php?id=<?= (int) $placeId ?>&season_edit=<?= (int) $item['id'] ?>#place-seasons">
                        <strong><?= $e($item['name']) ?></strong>
                        <span><?= $e($dates) ?></span>
                        <?php if ((int) ($item['is_default'] ?? 0) === 1): ?>
                            <small>Default</small>
                        <?php elseif ((int) ($item['is_active'] ?? 1) !== 1): ?>
                            <small>Inactive</small>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="admin-place-seasons-form">
            <input type="hidden" name="place_admin_action" value="save-season">
            <input type="hidden" name="csrf_token" value="<?= $e(moderation_csrf_token()) ?>">
            <input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
            <input type="hidden" name="season_id" value="<?= (int) ($seasonDraft['id'] ?? 0) ?>">
            <input type="hidden" name="sort_order" value="<?= (int) ($seasonDraft['sort_order'] ?? 0) ?>">

            <div class="admin-place-seasons-form-heading">
                <h3><?= (int) ($seasonDraft['id'] ?? 0) > 0 ? 'Edit season' : 'Add season' ?></h3>
                <?php if ((int) ($seasonDraft['id'] ?? 0) > 0): ?>
                    <a href="/place.php?id=<?= (int) $placeId ?>#place-seasons">
                        Add another season
                    </a>
                <?php endif; ?>
            </div>

            <div class="admin-place-seasons-inputs">
                <label class="admin-place-seasons-name">
                    <span>Season name</span>
                    <input type="text" name="season_name" maxlength="100"
                           placeholder="Winter limited services" required
                           value="<?= $e($seasonDraft['name'] ?? '') ?>">
                </label>
                <label>
                    <span>Starts (MM-DD)</span>
                    <input type="text" name="start_mmdd" maxlength="5"
                           inputmode="numeric" placeholder="11-16"
                           pattern="(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])"
                           value="<?= $e($seasonDraft['start_mmdd'] ?? '') ?>">
                </label>
                <label>
                    <span>Ends (MM-DD)</span>
                    <input type="text" name="end_mmdd" maxlength="5"
                           inputmode="numeric" placeholder="03-19"
                           pattern="(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])"
                           value="<?= $e($seasonDraft['end_mmdd'] ?? '') ?>">
                </label>
                <label class="admin-place-seasons-wide">
                    <span>Source URL (optional)</span>
                    <input type="url" name="season_source_url" maxlength="2048"
                           placeholder="https://..."
                           value="<?= $e($seasonDraft['source_url'] ?? '') ?>">
                </label>
                <label class="admin-place-seasons-wide">
                    <span>Notes (optional)</span>
                    <textarea name="season_notes" rows="2" maxlength="3000"
                              placeholder="Describe normal seasonal operations."><?= $e($seasonDraft['notes'] ?? '') ?></textarea>
                </label>
            </div>

            <div class="admin-place-seasons-footer">
                <label class="admin-place-season-check">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1"
                        <?= (int) ($seasonDraft['is_default'] ?? 0) === 1 ? 'checked' : '' ?>>
                    Default when no dated season matches
                </label>
                <label class="admin-place-season-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                        <?= (int) ($seasonDraft['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
                    Active
                </label>
                <button class="admin-button" type="submit">Save season</button>
            </div>
        </form>
        <p class="admin-place-seasons-help">
            Leave both dates empty for a manually selected season. Dates may
            cross New Year's Day but cannot overlap another active season.
            Seasonal amenity and Site-status editing will use these definitions.
        </p>
        <div class="admin-place-seasons-divider"></div>
        <?php require __DIR__ . '/place-report-season-answers.php'; ?>
    <?php endif; ?>
    </div>
</details>
