<?php

declare(strict_types=1);

/*
 * Llama Scout one-time hotfix
 * Fixes Landscape + setting multiselect/chip layout and switches Hurricane risk
 * to assets/icons/hurricane.svg.
 *
 * Upload to the Llama Scout repository root, run once with the token from the
 * included README, then this file deletes itself after a successful update.
 */

$requiredToken = 'LS-20260925-chipfix-8c4d7a';

header('Content-Type: text/plain; charset=UTF-8');

if (!hash_equals($requiredToken, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo "Invalid or missing update token.\n";
    exit;
}

$root = __DIR__;
$targets = [
    'app/place-report.php',
    'css/site/features/place-report-form.css',
];

$atomicWrite = static function (string $path, string $content): void {
    $temp = $path . '.llama-hotfix-' . bin2hex(random_bytes(4)) . '.tmp';

    if (file_put_contents($temp, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not write temporary file: ' . $temp);
    }

    if (!rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException('Could not replace file: ' . $path);
    }
};

try {
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

    $iconPath = $root . '/assets/icons/hurricane.svg';
    if (!is_file($iconPath)) {
        throw new RuntimeException(
            "assets/icons/hurricane.svg was not found.\n"
            . "Upload hurricane.svg there first, then run this updater again."
        );
    }

    /* ========================================================
     * app/place-report.php
     * ======================================================== */
    $app = $source['app/place-report.php'];

    $oldIcon = "        'hurricane_risk' => 'at-heavy-rain',";
    $newIcon = "        'hurricane_risk' => 'hurricane',";

    if (str_contains($app, $oldIcon)) {
        $app = str_replace($oldIcon, $newIcon, $app, $count);

        if ($count !== 1) {
            throw new RuntimeException(
                'Hurricane icon mapping appeared an unexpected number of times: '
                . (string) $count
            );
        }
    } elseif (!str_contains($app, $newIcon)) {
        throw new RuntimeException(
            "Could not find the Hurricane risk icon mapping in app/place-report.php.\n"
            . "Nothing was changed."
        );
    }

    /* ========================================================
     * css/site/features/place-report-form.css
     * ======================================================== */
    $css = $source['css/site/features/place-report-form.css'];

    $markerStart = '/* LLAMA LANDSCAPE CHIP HOTFIX 2026-09-25 */';
    $markerEnd = '/* END LLAMA LANDSCAPE CHIP HOTFIX 2026-09-25 */';

    $hotfixCss = <<<'CSS'
/* LLAMA LANDSCAPE CHIP HOTFIX 2026-09-25 */

/*
 * The shared form has broader label/input rules that can leak into these
 * multiselect controls. Keep this component fully self-contained so its
 * search box and selectable chips stay aligned in contributor and admin forms.
 */
.place-report-form .place-report-multiselect-menu {
    box-sizing: border-box !important;
    width: 100% !important;
    min-width: 0 !important;
    overflow-x: hidden !important;
}

.place-report-form input.place-report-multiselect-search {
    position: static !important;
    inset: auto !important;
    display: block !important;
    width: 100% !important;
    min-width: 0 !important;
    max-width: none !important;
    min-height: 40px !important;
    box-sizing: border-box !important;
    margin: 0 0 10px !important;
    padding: 9px 11px !important;
    transform: none !important;
    translate: none !important;
    text-indent: 0 !important;
    opacity: 1 !important;
}

.place-report-form .place-report-multiselect-options {
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: flex-start !important;
    justify-content: flex-start !important;
    gap: 7px !important;
    width: 100% !important;
    min-width: 0 !important;
}

.place-report-form label.place-report-multiselect-option {
    position: relative !important;
    inset: auto !important;
    display: inline-flex !important;
    flex: 0 1 auto !important;
    align-items: center !important;
    justify-content: flex-start !important;
    grid-template-columns: none !important;
    width: auto !important;
    min-width: 0 !important;
    max-width: 100% !important;
    min-height: 34px !important;
    box-sizing: border-box !important;
    gap: 0 !important;
    margin: 0 !important;
    padding: 7px 11px !important;
    border: 1px solid var(--border) !important;
    border-radius: 999px !important;
    background: color-mix(in srgb, var(--text) 3%, var(--background)) !important;
    color: var(--text) !important;
    text-align: left !important;
    cursor: pointer !important;
    overflow: hidden !important;
}

.place-report-form label.place-report-multiselect-option:hover,
.place-report-form label.place-report-multiselect-option:has(input:focus-visible) {
    border-color: color-mix(in srgb, var(--text) 55%, var(--border)) !important;
    background: color-mix(in srgb, var(--text) 7%, var(--background)) !important;
}

.place-report-form label.place-report-multiselect-option:has(input:checked) {
    border-color: color-mix(in srgb, var(--text) 72%, var(--border)) !important;
    background: color-mix(in srgb, var(--text) 11%, var(--background)) !important;
    box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--text) 24%, transparent) !important;
}

.place-report-form label.place-report-multiselect-option > input[type="checkbox"] {
    position: absolute !important;
    inset: auto !important;
    width: 1px !important;
    min-width: 1px !important;
    max-width: 1px !important;
    height: 1px !important;
    min-height: 1px !important;
    max-height: 1px !important;
    margin: -1px !important;
    padding: 0 !important;
    border: 0 !important;
    clip: rect(0 0 0 0) !important;
    clip-path: inset(50%) !important;
    overflow: hidden !important;
    white-space: nowrap !important;
    opacity: 0 !important;
    pointer-events: none !important;
}

.place-report-form label.place-report-multiselect-option > span {
    display: inline !important;
    width: auto !important;
    min-width: 0 !important;
    max-width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    color: inherit !important;
    font-size: .74rem !important;
    font-weight: 740 !important;
    line-height: 1.2 !important;
    text-align: left !important;
    white-space: normal !important;
    overflow-wrap: anywhere !important;
}

.place-report-form label.place-report-multiselect-option:has(input:checked) > span::before {
    content: "✓";
    display: inline-block;
    margin-right: 6px;
    font-weight: 900;
}

.place-report-form .place-report-chip-list {
    width: 100% !important;
    min-width: 0 !important;
    margin-top: 1px !important;
}

@media (max-width: 520px) {
    .place-report-form label.place-report-multiselect-option {
        min-height: 32px !important;
        padding: 6px 9px !important;
    }

    .place-report-form label.place-report-multiselect-option > span {
        font-size: .7rem !important;
    }
}

/* END LLAMA LANDSCAPE CHIP HOTFIX 2026-09-25 */
CSS;

    if (str_contains($css, $markerStart)) {
        $pattern = '/\/\* LLAMA LANDSCAPE CHIP HOTFIX 2026-09-25 \*\/.*?\/\* END LLAMA LANDSCAPE CHIP HOTFIX 2026-09-25 \*\//s';
        $css = preg_replace($pattern, $hotfixCss, $css, 1, $count);

        if (!is_string($css) || $count !== 1) {
            throw new RuntimeException('Could not refresh the existing chip hotfix block.');
        }
    } else {
        $css = rtrim($css) . "\n\n" . $hotfixCss . "\n";
    }

    /* Validate modified PHP before writing live files. */
    if (function_exists('exec')) {
        $lintFile = sys_get_temp_dir() . '/llama-place-report-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($lintFile, $app);
        $lines = [];
        $status = 0;
        exec('php -l ' . escapeshellarg($lintFile) . ' 2>&1', $lines, $status);
        @unlink($lintFile);

        if ($status !== 0) {
            throw new RuntimeException(
                "PHP syntax check failed for app/place-report.php:\n"
                . implode("\n", $lines)
            );
        }
    }

    $backupRoot = $root
        . '/private/update-backups/landscape-chip-hotfix-'
        . gmdate('Ymd-His');

    foreach ($source as $relative => $content) {
        $backupPath = $backupRoot . '/' . $relative;
        $backupDir = dirname($backupPath);

        if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Could not create backup directory: ' . $backupDir);
        }

        if (file_put_contents($backupPath, $content, LOCK_EX) === false) {
            throw new RuntimeException('Could not back up: ' . $relative);
        }
    }

    $output = [
        'app/place-report.php' => $app,
        'css/site/features/place-report-form.css' => $css,
    ];

    try {
        foreach ($output as $relative => $content) {
            $atomicWrite($root . '/' . $relative, $content);
        }
    } catch (Throwable $writeError) {
        foreach ($source as $relative => $content) {
            try {
                $atomicWrite($root . '/' . $relative, $content);
            } catch (Throwable) {
                /* Preserve original error. Backup remains available. */
            }
        }

        throw $writeError;
    }

    echo "Llama Scout Landscape chip hotfix applied.\n\n";
    echo "Changed:\n";
    echo "  - app/place-report.php (Hurricane risk now uses hurricane.svg)\n";
    echo "  - css/site/features/place-report-form.css (chip selector layout)\n";
    echo "\nBackup:\n  " . $backupRoot . "\n";
    echo "\nThe updater is deleting itself now.\n";

    @unlink(__FILE__);
} catch (Throwable $error) {
    http_response_code(500);
    echo "UPDATE STOPPED\n\n";
    echo $error->getMessage() . "\n\n";
    echo "No intentional source changes were made unless the message above says a write failed.\n";
    exit;
}
