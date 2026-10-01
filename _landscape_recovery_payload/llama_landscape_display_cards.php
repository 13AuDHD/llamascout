<?php

declare(strict_types=1);

/*
 * Llama Scout one-time updater
 * Landscape display cards + dynamic Primary setting icons + wildfire icon.
 *
 * Upload this file to the Llama Scout root and run it once with the token
 * from the included README. It backs up touched files, validates the live
 * landscape schema from the prior update, lints PHP when possible, and
 * deletes itself after a successful run.
 */

$requiredToken = 'LS-20260925-landcards-4f8b2c';

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
    'desert.svg',       // User mapping: Canyon
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
];

$atomicWrite = static function (string $path, string $content): void {
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create directory: ' . $dir);
    }

    $temp = $path . '.llama-landcards-' . bin2hex(random_bytes(4)) . '.tmp';

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

    $oldWildfire = "        'wildfire_risk' => 'flame',";
    $newWildfire = "        'wildfire_risk' => 'wildfire',";

    if (str_contains($app, $oldWildfire)) {
        $app = str_replace($oldWildfire, $newWildfire, $app, $count);

        if ($count !== 1) {
            throw new RuntimeException(
                'Wildfire icon mapping appeared an unexpected number of times: '
                . (string) $count
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
        $oldCssLink = <<<'OLD'
<link
    rel="stylesheet"
    href="/css/site/features/scout-warning-compact.css"
>
OLD;
        $newCssLink = $oldCssLink . <<<'NEW'
<link
    rel="stylesheet"
    href="/css/site/features/place-report-landscape.css"
>
NEW;

        if (substr_count($readOnly, $oldCssLink) !== 1) {
            throw new RuntimeException(
                'Could not safely add the Landscape display stylesheet. Nothing was changed.'
            );
        }

        $readOnly = str_replace($oldCssLink, $newCssLink, $readOnly);
    }

    $markerStart = '<?php /* LLAMA LANDSCAPE DISPLAY CARDS 2026-09-25 */ ?>';
    $markerEnd = '<?php /* END LLAMA LANDSCAPE DISPLAY CARDS 2026-09-25 */ ?>';

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
                            class="scout-report-item scout-report-value-item scout-report-landscape-card scout-report-landscape-primary<?= $primaryState === 'unanswered' ? ' is-unanswered' : '' ?><?= $primaryState === 'unknown' ? ' is-explicit-unknown' : '' ?>"
                        >
                            <div class="scout-report-value-content">
                                <span>Primary setting</span>
                                <strong><?= $e($primaryValue ?? 'Not provided') ?></strong>
                            </div>

                            <?= llama_icon(
                                $primaryIcon,
                                [
                                    'class' => 'scout-report-value-icon scout-report-landscape-primary-icon',
                                ]
                            ) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($showDetails): ?>
                        <div
                            class="scout-report-item scout-report-landscape-card scout-report-landscape-list-card<?= $detailLabels === [] ? ' is-unanswered' : '' ?>"
                        >
                            <span class="scout-report-landscape-card-label">Setting details</span>

                            <div class="scout-report-landscape-values">
                                <?php if ($detailLabels): ?>
                                    <?php foreach ($detailLabels as $label): ?>
                                        <strong><?= $e($label) ?></strong>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <strong>Not provided</strong>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($showViews): ?>
                        <div
                            class="scout-report-item scout-report-landscape-card scout-report-landscape-list-card<?= $viewLabels === [] ? ' is-unanswered' : '' ?>"
                        >
                            <span class="scout-report-landscape-card-label">Views</span>

                            <div class="scout-report-landscape-values">
                                <?php if ($viewLabels): ?>
                                    <?php foreach ($viewLabels as $label): ?>
                                        <strong><?= $e($label) ?></strong>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <strong>Not provided</strong>
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

    if (str_contains($readOnly, $markerStart)) {
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
    } else {
        $needle = "        <?php if (\$sectionKey === 'sensory'): ?>";

        if (substr_count($readOnly, $needle) !== 1) {
            throw new RuntimeException(
                "Could not find the read-only section renderer insertion point.\n"
                . 'Nothing was changed.'
            );
        }

        $readOnly = str_replace(
            $needle,
            $landscapeBranch,
            $readOnly,
            $insertCount
        );

        if ($insertCount !== 1) {
            throw new RuntimeException(
                'Landscape display-card insertion count was not exactly one.'
            );
        }
    }

    /* ========================================================
     * css/site/features/place-report-landscape.css
     * ======================================================== */
    $landscapeCss = <<<'CSS'
/* =========================================================
   LANDSCAPE AND SETTING... READ-ONLY DISPLAY
   Used by moderation and the public Place Scout Report.
   ========================================================= */

/* The three summary cards use the normal Scout Report grid rhythm. */
.scout-report-landscape-summary-grid {
    align-items: stretch;
}

.scout-report-landscape-card {
    min-height: 88px;
}

/* Primary setting behaves like every other value card, including its icon
   on the lower-right side. */
.scout-report-landscape-primary > .scout-report-value-content > strong {
    max-width: 100%;
}

.scout-report-landscape-primary-icon {
    width: 34px !important;
    height: 34px !important;
}

/* Setting details and Views are vertical read-only lists, not editable chips. */
.scout-report-landscape-list-card {
    display: grid;
    grid-template-rows: auto 8px auto;
    align-content: start;
}

.scout-report-landscape-card-label {
    min-width: 0;
    color: var(--text-muted);
    font-size: .9rem;
    line-height: 1.28;
    overflow-wrap: anywhere;
}

.scout-report-landscape-values {
    display: grid;
    gap: 5px;
}

.scout-report-landscape-values > strong {
    min-width: 0;
    margin: 0;
    font-size: 1rem;
    line-height: 1.2;
    overflow-wrap: anywhere;
}

/* Legacy chip classes remain harmless for older cached markup, but new
   moderation and Place output uses the stacked cards above. */
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

    .scout-report-landscape-primary-icon {
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
                . '/llama-landcards-'
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
        . '/private/update-backups/landscape-display-cards-'
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

    echo "Landscape display-card update completed successfully.\n\n";
    echo "Changed:\n";
    echo "- Primary setting / Setting details / Views are now three vertical read-only cards.\n";
    echo "- Primary setting icon changes by selected setting and stays on the right.\n";
    echo "- Setting details and Views stack one value per line.\n";
    echo "- Wildfire risk now uses wildfire.svg.\n";
    echo "- Editable form behavior was not changed.\n\n";
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
