<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report/season-answers.php';

$seasonAvailable = array_values(array_filter(
    $adminPlaceSeasons,
    static fn (array $row): bool => (int) ($row['is_active'] ?? 0) === 1
));

if (!$seasonAvailable):
?>
    <p class="admin-place-seasons-message">
        Save and activate a season above to begin editing its services,
        rules, and Site operating status.
    </p>
<?php
else:
    try {
        $seasonTargetChoices = llama_report_targets($db, $placeId);
        $seasonTargetError = '';
    } catch (Throwable $targetException) {
        $seasonTargetChoices = [];
        $seasonTargetError = 'Reporting targets could not be loaded. Review this Place’s Site associations.';
        if (function_exists('llama_log_caught_exception')) {
            llama_log_caught_exception($targetException, 'admin.place.season-targets');
        }
    }

    $seasonEditingAnswers = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && ($action ?? '') === 'save-season-answers' && ($error ?? '') !== '';

    $requestedSeason = (int) (
        $seasonEditingAnswers
            ? ($_POST['season_id'] ?? 0)
            : ($_GET['season_view'] ?? 0)
    );
    $chosenSeason = null;
    foreach ($seasonAvailable as $item) {
        if ((int) ($item['id'] ?? 0) === $requestedSeason) {
            $chosenSeason = $item;
            break;
        }
    }
    if ($chosenSeason === null) {
        foreach ($seasonAvailable as $item) {
            if ((int) ($item['is_default'] ?? 0) === 1) {
                $chosenSeason = $item;
                break;
            }
        }
    }
    $chosenSeason ??= $seasonAvailable[0];
    $chosenSeasonId = (int) $chosenSeason['id'];

    $targetToken = trim((string) (
        $seasonEditingAnswers
            ? ($_POST['season_target'] ?? '')
            : ($_GET['season_target'] ?? '')
    ));
    $seasonTargetMap = [];
    foreach ($seasonTargetChoices as $targetOption) {
        $token = (string) $targetOption['scope'] . ':'
            . (string) $targetOption['source'] . ':'
            . (int) $targetOption['id'];
        $seasonTargetMap[$token] = $targetOption;
    }
    if (!isset($seasonTargetMap[$targetToken])) {
        $targetToken = 'place:place:' . $placeId;
    }
    $chosenTarget = $seasonTargetMap[$targetToken] ?? null;
?>
    <section class="admin-place-season-answers" aria-labelledby="season-answers-heading">
        <header class="admin-place-report-header">
            <p>Season-specific Scout Report answers</p>
            <h3 id="season-answers-heading">Edit seasonal conditions</h3>
            <span class="admin-place-muted">
                Pick a saved season and a Place, Area, or Site. Only answers
                that can actually change with the season appear below.
                Use normal answer means no seasonal override.
            </span>
        </header>
        <?php if ($seasonTargetError !== ''): ?>
            <p class="admin-place-seasons-message is-error"><?= $e($seasonTargetError) ?></p>
        <?php elseif ($chosenTarget !== null): ?>
            <form method="get" action="/place.php#place-seasons"
                  class="admin-place-season-switch" data-season-switch>
                <input type="hidden" name="id" value="<?= (int) $placeId ?>">
                <label>
                    <span>Viewing season</span>
                    <select name="season_view" aria-label="Select an operating season">
                        <?php foreach ($seasonAvailable as $item): ?>
                            <option value="<?= (int) $item['id'] ?>"
                                <?= (int) $item['id'] === $chosenSeasonId ? 'selected' : '' ?>>
                                <?= $e($item['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Report for</span>
                    <select name="season_target" aria-label="Select Place Area or Site">
                        <?php foreach ($seasonTargetMap as $token => $item): ?>
                            <option value="<?= $e($token) ?>"
                                <?= $token === $targetToken ? 'selected' : '' ?>>
                                <?= $e(ucfirst((string) $item['scope']) . ': ' . (string) $item['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="admin-button is-muted">Load answers</button>
            </form>
            <?php
            $seasonReadError = '';
            try {
                $seasonStored = llama_season_answers_for_target(
                    $db, $placeId, $chosenSeasonId, $chosenTarget
                );
                $seasonGroups = llama_season_question_groups(
                    (string) $chosenTarget['scope']
                );
                $seasonSiteStatus = null;
                $seasonSiteReady = true;
                if ((string) $chosenTarget['scope'] === 'site') {
                    try {
                        $seasonSiteStatus = llama_season_site_status_for_target(
                            $db, $placeId, $chosenSeasonId, $chosenTarget
                        );
                    } catch (PDOException $statusException) {
                        $seasonSiteReady = false;
                        if (function_exists('llama_log_caught_exception')) {
                            llama_log_caught_exception(
                                $statusException, 'admin.place.season-site-status'
                            );
                        }
                    }
                }
            } catch (Throwable $seasonReadException) {
                $seasonReadError = 'Seasonal answers could not be loaded. Verify the M4-001 database migration.';
                if (function_exists('llama_log_caught_exception')) {
                    llama_log_caught_exception(
                        $seasonReadException, 'admin.place.season-answers'
                    );
                }
            }
            ?>
            <?php if ($seasonReadError !== ''): ?>
                <p class="admin-place-seasons-message is-error"><?= $e($seasonReadError) ?></p>
            <?php else: ?>
                <?php if (!$seasonSiteReady): ?>
                    <p class="admin-place-seasons-message is-error">
                        Run the M4-002 Site seasonal-status migration before
                        saving seasonal answers for individual Sites.
                    </p>
                <?php endif; ?>
                <form method="post" action="/place.php?id=<?= (int) $placeId ?>#place-seasons"
                      class="admin-place-season-answer-form" data-season-answers-form>
                    <input type="hidden" name="csrf_token"
                           value="<?= $e(moderation_csrf_token()) ?>">
                    <input type="hidden" name="place_admin_action" value="save-season-answers">
                    <input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
                    <input type="hidden" name="season_id" value="<?= $chosenSeasonId ?>">
                    <input type="hidden" name="season_target" value="<?= $e($targetToken) ?>">

                    <?php if ((string) $chosenTarget['scope'] === 'site'): ?>
                        <?php
                        $siteOperating = (string) ($seasonSiteStatus['operating_status'] ?? 'inherit');
                        if ($seasonEditingAnswers) {
                            $siteOperating = (string) ($_POST['season_site_status'] ?? $siteOperating);
                        }
                        ?>
                        <div class="admin-place-season-group">
                            <h4>Normal seasonal Site operations</h4>
                            <label class="admin-place-season-answer-row">
                                <span class="admin-place-season-answer-label">
                                    <strong>Site operating status</strong>
                                    <small>Normal schedule only. Verified closures still take priority.</small>
                                </span>
                                <select name="season_site_status" <?= $seasonSiteReady ? '' : 'disabled' ?>>
                                    <?php foreach ([
                                        'inherit' => 'Use normal Site status',
                                        'open' => 'Normally open this season',
                                        'closed' => 'Normally closed this season',
                                    ] as $statusKey => $statusLabel): ?>
                                        <option value="<?= $e($statusKey) ?>"
                                            <?= $siteOperating === $statusKey ? 'selected' : '' ?>>
                                            <?= $e($statusLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($seasonGroups as $heading => $fields): ?>
                        <div class="admin-place-season-group">
                            <h4><?= $e($heading) ?></h4>
                            <?php foreach ($fields as $key => $field): ?>
                                <?php
                                $saved = $seasonStored[$key] ?? null;
                                $mode = $saved === null
                                    ? 'inherit'
                                    : ((string) $saved['state'] === 'unknown' ? 'unknown' : 'answer');
                                $raw = $saved !== null && (string) $saved['state'] === 'answered'
                                    ? llama_place_report_answer_form_value($field, $saved['value'])
                                    : '';
                                if ($seasonEditingAnswers && isset($_POST['season_modes'][$key])) {
                                    $mode = (string) $_POST['season_modes'][$key];
                                    $raw = $_POST['season_values'][$key] ?? '';
                                }
                                $raw = is_scalar($raw) ? (string) $raw : '';
                                $type = (string) ($field['type'] ?? 'text');
                                $choices = [];
                                if (in_array($type, ['tri', 'checkbox'], true)) {
                                    $choices = ['1' => 'Yes', '0' => 'No'];
                                } elseif ($type === 'permission') {
                                    $choices = [
                                        '1' => 'Yes', '0' => 'No',
                                        '2' => 'Permit required', '3' => 'Conditional'
                                    ];
                                } elseif ($type === 'select') {
                                    $choices = (array) ($field['options'] ?? [])
                                        + (array) ($field['legacy_values'] ?? []);
                                }
                                ?>
                                <div class="admin-place-season-answer-row" data-season-question>
                                    <label class="admin-place-season-answer-label"
                                           for="season-value-<?= $e($key) ?>">
                                        <strong><?= $e(rtrim((string) $field['label'], ' *')) ?></strong>
                                    </label>
                                    <select name="season_modes[<?= $e($key) ?>]"
                                            aria-label="Seasonal answer mode for <?= $e($field['label']) ?>"
                                            data-season-mode>
                                        <option value="inherit" <?= $mode === 'inherit' ? 'selected' : '' ?>>
                                            Use normal answer
                                        </option>
                                        <option value="answer" <?= $mode === 'answer' ? 'selected' : '' ?>>
                                            Change for this season
                                        </option>
                                        <?php if (!empty($field['allow_unknown'])): ?>
                                            <option value="unknown" <?= $mode === 'unknown' ? 'selected' : '' ?>>
                                                Unknown this season
                                            </option>
                                        <?php endif; ?>
                                    </select>
                                    <?php if ($choices): ?>
                                        <select id="season-value-<?= $e($key) ?>"
                                                name="season_values[<?= $e($key) ?>]"
                                                data-season-value>
                                            <option value="">Choose an answer</option>
                                            <?php foreach ($choices as $value => $label): ?>
                                                <option value="<?= $e($value) ?>"
                                                    <?= $raw === (string) $value ? 'selected' : '' ?>>
                                                    <?= $e($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($type === 'textarea'): ?>
                                        <textarea id="season-value-<?= $e($key) ?>"
                                                  name="season_values[<?= $e($key) ?>]"
                                                  rows="2" data-season-value><?= $e($raw) ?></textarea>
                                    <?php else: ?>
                                        <input id="season-value-<?= $e($key) ?>"
                                               name="season_values[<?= $e($key) ?>]"
                                               data-season-value
                                               type="<?= $type === 'number' ? 'number' : 'text' ?>"
                                               value="<?= $e($raw) ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <label class="admin-place-season-source">
                        <span>Official source URL for these seasonal changes (optional)</span>
                        <input type="url" name="season_answer_source_url"
                               placeholder="<?= $e((string) ($chosenSeason['source_url'] ?? 'https://...')) ?>"
                               value="<?= $e($seasonEditingAnswers ? ($_POST['season_answer_source_url'] ?? '') : '') ?>">
                    </label>
                    <p class="admin-place-seasons-help">
                        Only changed answers are saved as overrides. Choose Use normal answer
                        to remove an override. Physical Site details never change here.
                        Current fire restrictions and verified closures still take priority.
                    </p>
                    <button type="submit" class="admin-button"
                        <?= $seasonSiteReady ? '' : 'disabled' ?>>
                        Save <?= $e($chosenSeason['name']) ?> answers
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
