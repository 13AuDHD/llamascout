<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report/season-builder.php';

$availableSeasons = array_values(array_filter(
    (array) $adminPlaceSeasons,
    static fn (array $s): bool => (int) ($s['is_active'] ?? 0) === 1
));
?>

<section class="admin-place-season-answers" id="seasonal-conditions"
         aria-labelledby="season-answers-heading">
    <header class="admin-place-report-header">
        <p>Seasonal Scout Report</p>
        <h3 id="season-answers-heading">Seasonal conditions</h3>
        <span class="admin-place-muted">
            Choose a season and the Place, an Area, or several Sites.
            Add only the questions that actually change.
        </span>
    </header>

    <?php if (!$availableSeasons): ?>
        <p class="admin-place-seasons-message">
            Save an active season above before editing seasonal conditions.
        </p>
    <?php else: ?>
        <?php
        $loadError = '';
        $seasonTargetChoices = [];
        $selectedTargets = [];
        $seasonRows = [];
        $seasonQuestions = [];
        $selectedSeason = null;
        $buildingFailedPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($action ?? '') === 'save-season-builder' && ($error ?? '') !== '';
        try {
            $seasonTargetChoices = llama_report_targets($db, $placeId);
            $seasonRequest = $buildingFailedPost
                ? (int) ($_POST['season_id'] ?? 0)
                : (int) ($_GET['season_view'] ?? 0);
            foreach ($availableSeasons as $season) {
                if ((int) $season['id'] === $seasonRequest) {
                    $selectedSeason = $season;
                    break;
                }
            }
            if ($selectedSeason === null) {
                foreach ($availableSeasons as $season) {
                    if ((int) ($season['is_default'] ?? 0) === 1) {
                        $selectedSeason = $season;
                        break;
                    }
                }
            }
            $selectedSeason ??= $availableSeasons[0];
            $selectedSeasonId = (int) $selectedSeason['id'];

            $selection = $buildingFailedPost
                ? ($_POST['season_targets'] ?? null)
                : ($_GET['season_targets'] ?? null);
            // Compatibility with older single-target admin links.
            if ($selection === null && isset($_GET['season_target'])) {
                $selection = [(string) $_GET['season_target']];
            }
            $selectedTargets = llama_season_builder_targets(
                $seasonTargetChoices, $selection, $placeId
            );
            $firstTarget = reset($selectedTargets);
            $selectedScope = (string) $firstTarget['scope'];
            $seasonQuestions = llama_season_builder_questions($selectedScope);
            $seasonRows = llama_season_builder_existing(
                $db, $placeId, $selectedSeasonId, $selectedTargets, $seasonQuestions
            );
        } catch (Throwable $exception) {
            $loadError = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'Could not load seasonal conditions. Check the saved seasons and reporting targets.';
            if (function_exists('llama_log_caught_exception')) {
                llama_log_caught_exception($exception, 'admin.place.season-builder');
            }
        }
        ?>

        <?php if ($loadError !== ''): ?>
            <p class="admin-place-seasons-message is-error"><?= $e($loadError) ?></p>
            <a class="admin-button is-muted"
               href="/place.php?id=<?= (int) $placeId ?>#place-seasons">
                Reset seasonal selection
            </a>
        <?php else: ?>
            <?php
            $targetGroups = ['place' => [], 'area' => [], 'site' => []];
            foreach ($seasonTargetChoices as $target) {
                $scope = (string) $target['scope'];
                if (!isset($targetGroups[$scope])) {
                    continue;
                }
                $token = $scope . ':' . $target['source'] . ':' . (int) $target['id'];
                $targetGroups[$scope][$token] = $target;
            }
            $selectedCount = count($selectedTargets);
            $selectedSummary = $selectedCount === 1
                ? ucfirst((string) $firstTarget['scope']) . ': ' . (string) $firstTarget['label']
                : $selectedCount . ' Sites selected';
            ?>

            <form class="admin-place-season-switch" method="get"
                  action="/place.php#place-seasons" data-season-builder-load>
                <input type="hidden" name="id" value="<?= (int) $placeId ?>">
                <label class="admin-place-season-picker-label">
                    <span>Viewing season</span>
                    <select name="season_view" aria-label="Choose season">
                        <?php foreach ($availableSeasons as $season): ?>
                            <option value="<?= (int) $season['id'] ?>"
                                <?= (int) $season['id'] === $selectedSeasonId ? 'selected' : '' ?>>
                                <?= $e((string) $season['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="admin-place-season-target-selector">
                    <span class="admin-place-season-target-label">Report for</span>
                    <details class="admin-place-season-target-dropdown" data-season-targets>
                        <summary>
                            <span data-season-target-summary><?= $e($selectedSummary) ?></span>
                            <span aria-hidden="true">▾</span>
                        </summary>
                        <div class="admin-place-season-target-popover">
                            <p>Select one Place or Area, or several Sites.</p>
                            <input type="search" autocomplete="off"
                                   placeholder="Find Site number or name"
                                   aria-label="Search reporting targets"
                                   data-season-target-search>
                            <?php foreach ([
                                'place' => 'Place',
                                'area' => 'Areas',
                                'site' => 'Sites',
                            ] as $scope => $label): ?>
                                <?php if (!$targetGroups[$scope]) continue; ?>
                                <fieldset>
                                    <legend><?= $e($label) ?></legend>
                                    <?php foreach ($targetGroups[$scope] as $token => $target): ?>
                                        <label data-season-target-option>
                                            <input type="checkbox" name="season_targets[]"
                                                   value="<?= $e($token) ?>"
                                                   data-season-target-scope="<?= $e($scope) ?>"
                                                   <?= isset($selectedTargets[$token]) ? 'checked' : '' ?>>
                                            <span><?= $e((string) $target['label']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            <?php endforeach; ?>
                            <small data-season-target-count><?= $selectedCount ?> selected</small>
                        </div>
                    </details>
                </div>
                <button type="submit" class="admin-button is-muted">Load conditions</button>
            </form>

            <form method="post" action="/place.php?id=<?= (int) $placeId ?>#place-seasons"
                  class="admin-place-season-builder-form" data-season-builder-form>
                <input type="hidden" name="csrf_token"
                       value="<?= $e(moderation_csrf_token()) ?>">
                <input type="hidden" name="place_admin_action" value="save-season-builder">
                <input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
                <input type="hidden" name="season_id" value="<?= $selectedSeasonId ?>">
                <?php foreach (array_keys($selectedTargets) as $token): ?>
                    <input type="hidden" name="season_targets[]" value="<?= $e($token) ?>">
                <?php endforeach; ?>

                <div class="admin-place-season-builder-heading">
                    <div>
                        <h4><?= $e((string) $selectedSeason['name']) ?> changes</h4>
                        <p>
                            <?= $e($selectedSummary) ?>.
                            Only listed changes apply; other answers stay unchanged.
                        </p>
                    </div>
                    <button type="button" class="admin-button is-muted"
                            data-season-add-row>+ Add change</button>
                </div>

                <div class="admin-place-season-builder-rows" data-season-builder-rows
                     aria-live="polite"></div>
                <p class="admin-place-seasons-help" data-season-builder-hint>
                    Choose the question, then its seasonal answer. Use + to add a row
                    and × to remove an existing seasonal override. Mixed values are
                    left untouched unless you explicitly choose a new answer.
                </p>

                <label class="admin-place-season-source">
                    <span>Source URL (optional)</span>
                    <input type="url" name="season_answer_source_url"
                           maxlength="2048" placeholder="https://..."
                           value="<?= $e($buildingFailedPost
                               ? (string) ($_POST['season_answer_source_url'] ?? '')
                               : '') ?>">
                </label>

                <div class="admin-place-season-builder-footer">
                    <span data-season-builder-change-count>0 changes</span>
                    <button type="submit" class="admin-button" data-season-builder-save
                            disabled>Save seasonal changes</button>
                </div>
                <script type="application/json" data-season-builder-config><?= json_encode(
                    [
                        'questions' => array_values($seasonQuestions),
                        'rows' => $seasonRows,
                        'targets' => $selectedCount,
                    ],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
                    | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
                ) ?></script>
            </form>
            <script defer src="/js/admin/place-season-builder.js"></script>
        <?php endif; ?>
    <?php endif; ?>
</section>
