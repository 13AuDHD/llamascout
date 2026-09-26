<?php

declare(strict_types=1);

/*
 * Llama Scout one-time updater
 * Landscape display-card alignment / line-break hotfix + water icon.
 *
 * This is intentionally display-only for the Landscape summary cards.
 * It does not change the editable Scout Report form behavior.
 */

$requiredToken = 'LS-20260925-landfix-91c7e4';

header('Content-Type: text/plain; charset=UTF-8');

if (!hash_equals($requiredToken, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo "Invalid or missing update token.\n";
    exit;
}

$root = __DIR__;
$targets = [
    'app/place-report.php',
    'partials/place-report/read-only.php',
    'css/site/features/place-report-landscape.css',
];

$requiredIcons = [
    'forest.svg',
    'mountain.svg',
    'desert.svg',       // Intentional: Canyon
    'field.svg',
    'ground.svg',
    'cactus.svg',
    'wetland.svg',
    'tropical.svg',
    'pond.svg',
    'riverside.svg',
    'urban.svg',
    'suburban.svg',
    'farm.svg',
    'land-use.svg',
    'wildfire.svg',
    'water-waves.svg',
];

$atomicWrite = static function (string $path, string $content): void {
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create directory: ' . $dir);
    }

    $temp = $path . '.llama-landfix-' . bin2hex(random_bytes(4)) . '.tmp';

    if (file_put_contents($temp, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not write temporary file: ' . $temp);
    }

    if (!rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException('Could not replace file: ' . $path);
    }
};

try {
    foreach ($requiredIcons as $icon) {
        if (!is_file($root . '/assets/icons/' . $icon)) {
            throw new RuntimeException(
                'Missing required icon: assets/icons/' . $icon . "\n"
                . 'Nothing was changed.'
            );
        }
    }

    $source = [];

    foreach ($targets as $relative) {
        $path = $root . '/' . $relative;

        if (!is_file($path)) {
            throw new RuntimeException('Missing required file: ' . $relative);
        }

        $content = file_get_contents($path);

        if (!is_string($content)) {
            throw new RuntimeException('Could not read: ' . $relative);
        }

        $source[$relative] = $content;
    }

    /* ========================================================
     * app/place-report.php
     * ======================================================== */
    $app = $source['app/place-report.php'];

    if (!str_contains($app, "'landscape_primary'")) {
        throw new RuntimeException(
            "The structured Landscape and setting schema was not found.\n"
            . "Run the earlier Landscape update first. Nothing was changed."
        );
    }

    $oldWaterIcon = "        'environment_water_nearby' => 'ripple',";
    $newWaterIcon = "        'environment_water_nearby' => 'water-waves',";

    if (str_contains($app, $oldWaterIcon)) {
        $app = str_replace($oldWaterIcon, $newWaterIcon, $app, $waterCount);

        if ($waterCount !== 1) {
            throw new RuntimeException(
                'Water nearby icon mapping appeared an unexpected number of times: '
                . (string) $waterCount
            );
        }
    } elseif (!str_contains($app, $newWaterIcon)) {
        throw new RuntimeException(
            "Could not locate the Water nearby icon mapping. Nothing was changed."
        );
    }

    /* Keep the prior wildfire.svg correction intact, and repair it if needed. */
    $oldWildfire = "        'wildfire_risk' => 'flame',";
    $newWildfire = "        'wildfire_risk' => 'wildfire',";

    if (str_contains($app, $oldWildfire)) {
        $app = str_replace($oldWildfire, $newWildfire, $app, $wildfireCount);

        if ($wildfireCount !== 1) {
            throw new RuntimeException(
                'Wildfire icon mapping appeared an unexpected number of times: '
                . (string) $wildfireCount
            );
        }
    } elseif (!str_contains($app, $newWildfire)) {
        throw new RuntimeException(
            "Could not locate the Wildfire risk icon mapping. Nothing was changed."
        );
    }

    /* ========================================================
     * partials/place-report/read-only.php
     * ======================================================== */
    $readOnly = $source['partials/place-report/read-only.php'];

    if (!str_contains($readOnly, '/css/site/features/place-report-landscape.css')) {
        throw new RuntimeException(
            "The Landscape display stylesheet link was not found.\n"
            . "Run the earlier display-card update first. Nothing was changed."
        );
    }

    $markerStart = '<?php /* LLAMA LANDSCAPE DISPLAY CARDS 2026-09-25 */ ?>';
    $markerEnd = '<?php /* END LLAMA LANDSCAPE DISPLAY CARDS 2026-09-25 */ ?>';

    if (!str_contains($readOnly, $markerStart) || !str_contains($readOnly, $markerEnd)) {
        throw new RuntimeException(
            "The existing Landscape display-card block was not found.\n"
            . "Run the earlier display-card update first. Nothing was changed."
        );
    }

    $landscapeBranch = <<<'PHPBLOCK'
        <?php /* LLAMA LANDSCAPE DISPLAY CARDS 2026-09-25 */ ?>
        <?php if ($sectionKey === 'landscape_setting'): ?>
            <?php
            $landscapeSummaryKeys = [
                'landscape_primary',
                'landscape_details',
                'landscape_views',
            ];

            $primaryField =
                is_array($fields['landscape_primary'] ?? null)
                    ? $fields['landscape_primary']
                    : [];

            $primaryState =
                llama_place_report_answer_state(
                    $placeReportData,
                    'landscape_primary'
                );

            $primaryRaw =
                (string) llama_place_report_get_path(
                    $placeReportData,
                    (string) ($primaryField['storage'] ?? 'details.landscape_primary')
                );

            $primaryValue =
                llama_place_report_display_value(
                    $placeReportData,
                    'landscape_primary'
                );

            if ($primaryState === 'unanswered') {
                $primaryValue = 'Not provided';
            } elseif ($primaryState === 'unknown') {
                $primaryValue = 'Unknown';
            }

            $primaryIconMap = [
                'forest-woodland' => 'forest',
                'mountain-alpine' => 'mountain',
                'canyon' => 'desert',
                'grassland-prairie' => 'field',
                'shrubland-scrubland' => 'ground',
                'high-desert' => 'ground',
                'low-desert' => 'cactus',
                'wetland' => 'wetland',
                'beach-coastal' => 'tropical',
                'lakeside-reservoir' => 'pond',
                'riverside-creekside' => 'riverside',
                'urban-city' => 'urban',
                'suburban' => 'suburban',
                'farmland-ranchland' => 'farm',
                'mixed-transitional' => 'land-use',
                'other' => 'ground',
            ];

            $primaryIcon =
                $localIcon(
                    (string) (
                        $primaryIconMap[$primaryRaw]
                        ?? 'at-landscape'
                    ),
                    'landscape_primary'
                );

            $landscapeLabels =
                static function (
                    string $key
                ) use (
                    $fields,
                    $placeReportData
                ): array {
                    $field =
                        is_array($fields[$key] ?? null)
                            ? $fields[$key]
                            : [];

                    $options =
                        (array) ($field['options'] ?? []);

                    $storage =
                        (string) ($field['storage'] ?? '');

                    if ($storage === '') {
                        return [];
                    }

                    $rawValues =
                        llama_place_report_get_path(
                            $placeReportData,
                            $storage
                        );

                    $labels = [];

                    foreach ((array) $rawValues as $rawValue) {
                        $rawKey = (string) $rawValue;

                        if (
                            $rawKey !== ''
                            && array_key_exists($rawKey, $options)
                        ) {
                            $labels[] = (string) $options[$rawKey];
                        }
                    }

                    return array_values(array_unique($labels));
                };

            $detailLabels =
                $landscapeLabels('landscape_details');

            $viewLabels =
                $landscapeLabels('landscape_views');

            $showPrimary =
                $placeReportReadMode === 'moderation'
                || $primaryState !== 'unanswered';

            $showDetails =
                $placeReportReadMode === 'moderation'
                || $detailLabels !== [];

            $showViews =
                $placeReportReadMode === 'moderation'
                || $viewLabels !== [];

            $landscapeRemainingFields =
                array_filter(
                    $fields,
                    static fn (
                        array $field,
                        string|int $key
                    ): bool =>
                        !in_array(
                            (string) $key,
                            $landscapeSummaryKeys,
                            true
                        ),
                    ARRAY_FILTER_USE_BOTH
                );
            ?>

            <?php if ($showPrimary || $showDetails || $showViews): ?>
                <div class="scout-report-grid scout-report-landscape-summary-grid">
                    <?php if ($showPrimary): ?>
                        <div
                            class="scout-report-item scout-report-landscape-card scout-report-landscape-primary<?= $primaryState === 'unanswered' ? ' is-unanswered' : '' ?><?= $primaryState === 'unknown' ? ' is-explicit-unknown' : '' ?>"
                        >
                            <span class="scout-report-landscape-card-label">Primary setting</span>

                            <div class="scout-report-landscape-values">
                                <div class="scout-report-landscape-value">
                                    <strong><?= $e($primaryValue ?? 'Not provided') ?></strong>
                                </div>
                            </div>

                            <?= llama_icon(
                                $primaryIcon,
                                [
                                    'class' => 'scout-report-landscape-primary-icon',
                                ]
                            ) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($showDetails): ?>
                        <div
                            class="scout-report-item scout-report-landscape-card<?= $detailLabels === [] ? ' is-unanswered' : '' ?>"
                        >
                            <span class="scout-report-landscape-card-label">Setting details</span>

                            <div class="scout-report-landscape-values">
                                <?php if ($detailLabels): ?>
                                    <?php foreach ($detailLabels as $label): ?>
                                        <div class="scout-report-landscape-value">
                                            <strong><?= $e($label) ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="scout-report-landscape-value">
                                        <strong>Not provided</strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($showViews): ?>
                        <div
                            class="scout-report-item scout-report-landscape-card<?= $viewLabels === [] ? ' is-unanswered' : '' ?>"
                        >
                            <span class="scout-report-landscape-card-label">Views</span>

                            <div class="scout-report-landscape-values">
                                <?php if ($viewLabels): ?>
                                    <?php foreach ($viewLabels as $label): ?>
                                        <div class="scout-report-landscape-value">
                                            <strong><?= $e($label) ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="scout-report-landscape-value">
                                        <strong>Not provided</strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($landscapeRemainingFields): ?>
                <div class="scout-report-grid">
                    <?php foreach ($landscapeRemainingFields as $key => $field): ?>
                        <?php $renderValue((string) $key, $field); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($sectionKey === 'sensory'): ?>
        <?php /* END LLAMA LANDSCAPE DISPLAY CARDS 2026-09-25 */ ?>
PHPBLOCK;

    $pattern =
        '~<\?php /\* LLAMA LANDSCAPE DISPLAY CARDS 2026-09-25 \*/ \?>.*?<\?php /\* END LLAMA LANDSCAPE DISPLAY CARDS 2026-09-25 \*/ \?>~s';

    $readOnly = preg_replace_callback(
        $pattern,
        static fn (): string => $landscapeBranch,
        $readOnly,
        1,
        $replaceCount
    );

    if (!is_string($readOnly) || $replaceCount !== 1) {
        throw new RuntimeException(
            'Could not refresh the existing Landscape display-card block.'
        );
    }

    /* ========================================================
     * css/site/features/place-report-landscape.css
     * ======================================================== */
    $landscapeCss = <<<'CSS'
/* =========================================================
   LANDSCAPE AND SETTING... READ-ONLY DISPLAY
   Used by moderation and the public Place Scout Report.
   ========================================================= */

.scout-report-landscape-summary-grid {
    align-items: stretch;
}

/* All three summary cards intentionally share the exact same structure so
   their headings and values line up consistently. */
.scout-report-landscape-card {
    position: relative;
    display: grid;
    grid-template-rows: auto 8px auto;
    align-content: start;
    min-height: 88px;
}

.scout-report-landscape-card-label {
    min-width: 0;
    margin: 0;
    color: var(--text-muted);
    font-size: .9rem;
    font-weight: 400;
    line-height: 1.28;
    overflow-wrap: anywhere;
}

/* Each selected Setting detail and View gets its own block-level row.
   The extra wrapper makes the line break structural, not dependent on
   strong's default inline behavior. */
.scout-report-landscape-values {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 5px;
    min-width: 0;
}

.scout-report-landscape-value {
    display: block;
    min-width: 0;
}

.scout-report-landscape-value > strong {
    display: block;
    min-width: 0;
    margin: 0;
    font-size: 1rem;
    line-height: 1.2;
    overflow-wrap: anywhere;
}

/* Primary setting uses the same label/value layout as the other two cards,
   with only the icon added at the lower-right. */
.scout-report-landscape-primary {
    padding-right: 58px;
}

.scout-report-landscape-primary-icon {
    position: absolute;
    right: 13px;
    bottom: 11px;
    width: 34px !important;
    height: 34px !important;
    margin: 0;
}

/* Legacy chip classes remain harmless for older cached markup. */
.scout-report-chip-item {
    display: grid;
    gap: 8px;
}

.scout-report-chip-label {
    color: var(--text-muted);
    font-size: .68rem;
    font-weight: 760;
}

.scout-report-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.scout-report-chip {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 4px 9px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: color-mix(in srgb, var(--text) 5%, var(--background));
    color: var(--text);
    font-size: .7rem;
    font-weight: 760;
}

@media (max-width: 600px) {
    .scout-report-landscape-card {
        min-height: 68px;
    }

    .scout-report-landscape-primary {
        padding-right: 54px;
    }

    .scout-report-landscape-primary-icon {
        right: 12px;
        bottom: 10px;
        width: 30px !important;
        height: 30px !important;
    }
}
CSS;

    /* Validate modified PHP before touching live files. */
    if (function_exists('exec')) {
        foreach (
            [
                'app/place-report.php' => $app,
                'partials/place-report/read-only.php' => $readOnly,
            ]
            as $lintName => $lintContent
        ) {
            $lintFile =
                sys_get_temp_dir()
                . '/llama-landfix-'
                . bin2hex(random_bytes(4))
                . '.php';

            if (file_put_contents($lintFile, $lintContent) === false) {
                throw new RuntimeException('Could not create lint file for ' . $lintName);
            }

            $lines = [];
            $status = 0;
            exec('php -l ' . escapeshellarg($lintFile) . ' 2>&1', $lines, $status);
            @unlink($lintFile);

            if ($status !== 0) {
                throw new RuntimeException(
                    'PHP syntax check failed for '
                    . $lintName
                    . ":\n"
                    . implode("\n", $lines)
                );
            }
        }
    }

    $stamp = gmdate('Ymd-His');
    $backupDir =
        $root
        . '/private/update-backups/landscape-display-hotfix-'
        . $stamp;

    if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Could not create backup directory: ' . $backupDir);
    }

    foreach ($targets as $relative) {
        $backupPath = $backupDir . '/' . $relative;
        $backupParent = dirname($backupPath);

        if (!is_dir($backupParent) && !mkdir($backupParent, 0775, true) && !is_dir($backupParent)) {
            throw new RuntimeException('Could not create backup path: ' . $backupParent);
        }

        if (file_put_contents($backupPath, $source[$relative], LOCK_EX) === false) {
            throw new RuntimeException('Could not back up: ' . $relative);
        }
    }

    $atomicWrite($root . '/app/place-report.php', $app);
    $atomicWrite($root . '/partials/place-report/read-only.php', $readOnly);
    $atomicWrite(
        $root . '/css/site/features/place-report-landscape.css',
        $landscapeCss . "\n"
    );

    echo "Landscape display hotfix completed successfully.\n\n";
    echo "Changed:\n";
    echo "- Primary setting, Setting details, and Views now use identical heading/value structure.\n";
    echo "- Every Setting detail appears on its own line.\n";
    echo "- Every View appears on its own line.\n";
    echo "- Primary setting icon remains on the right side of its card.\n";
    echo "- Water nearby now uses water-waves.svg.\n";
    echo "- Wildfire risk remains mapped to wildfire.svg.\n";
    echo "- Editable Scout Report form behavior was not changed.\n\n";
    echo "Backup: " . str_replace($root . '/', '', $backupDir) . "\n";

    $self = __FILE__;
    if (@unlink($self)) {
        echo "Updater deleted itself.\n";
    } else {
        echo "IMPORTANT: Delete " . basename($self) . " manually.\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "UPDATE STOPPED\n\n";
    echo $e->getMessage() . "\n";
    echo "\nNo live files were intentionally changed before validation completed.\n";
}
