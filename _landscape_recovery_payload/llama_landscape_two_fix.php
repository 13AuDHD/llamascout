<?php

declare(strict_types=1);

/*
 * Llama Scout one-time updater
 * ONLY fixes two Landscape display issues:
 *   1) Landscape summary-card typography matches normal Scout Report cards.
 *   2) Primary-setting icon is anchored to the lower-right like other cards.
 *
 * No PHP/report schema/form/data changes.
 */

$requiredToken = 'LS-20260925-land-twofix-63e29a';

header('Content-Type: text/plain; charset=UTF-8');

if (!hash_equals($requiredToken, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo "Invalid or missing update token.\n";
    exit;
}

$root = __DIR__;
$relative = 'css/scout-report-cards.css';
$path = $root . '/' . $relative;

$startMarker = '/* LLAMA LANDSCAPE CARD ALIGNMENT FIX 2026-09-25 */';
$endMarker = '/* END LLAMA LANDSCAPE CARD ALIGNMENT FIX 2026-09-25 */';

$cssBlock = <<<'CSS'
/* LLAMA LANDSCAPE CARD ALIGNMENT FIX 2026-09-25 */
/* Keep Landscape summary-card text identical to the standard value cards. */
.scout-report-landscape-card > .scout-report-landscape-card-label {
    min-width: 0;
    margin: 0;
    color: var(--text-muted);
    font-size: .9rem;
    font-weight: 400;
    line-height: 1.28;
    overflow-wrap: anywhere;
}

.scout-report-landscape-card .scout-report-landscape-value > strong {
    display: block;
    min-width: 0;
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.15;
    overflow-wrap: anywhere;
}

/* Primary-setting icon belongs on the lower-right, matching normal value cards. */
.scout-report-landscape-primary {
    position: relative;
    padding-right: 58px;
}

.scout-report-landscape-primary > .scout-report-landscape-primary-icon {
    position: absolute !important;
    right: 13px !important;
    bottom: 11px !important;
    left: auto !important;
    top: auto !important;
    width: 30px !important;
    height: 30px !important;
    margin: 0 !important;
}

@media (max-width: 600px) {
    .scout-report-landscape-primary {
        padding-right: 54px;
    }

    .scout-report-landscape-primary > .scout-report-landscape-primary-icon {
        right: 12px !important;
        bottom: 10px !important;
        width: 30px !important;
        height: 30px !important;
    }
}
/* END LLAMA LANDSCAPE CARD ALIGNMENT FIX 2026-09-25 */
CSS;

$atomicWrite = static function (string $target, string $content): void {
    $temp = $target . '.llama-twofix-' . bin2hex(random_bytes(4)) . '.tmp';

    if (file_put_contents($temp, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not write temporary file.');
    }

    if (!rename($temp, $target)) {
        @unlink($temp);
        throw new RuntimeException('Could not replace ' . basename($target) . '.');
    }
};

try {
    if (!is_file($path)) {
        throw new RuntimeException('Missing required file: ' . $relative . "\nNothing was changed.");
    }

    $css = file_get_contents($path);

    if (!is_string($css)) {
        throw new RuntimeException('Could not read ' . $relative . ".\nNothing was changed.");
    }

    /* Make sure this is actually the Scout Report stylesheet before touching it. */
    foreach (
        [
            '.scout-report-value-item',
            '.scout-report-value-content',
            '.scout-report-value-icon',
        ] as $requiredSelector
    ) {
        if (!str_contains($css, $requiredSelector)) {
            throw new RuntimeException(
                'Expected Scout Report selector not found: ' . $requiredSelector
                . "\nNothing was changed."
            );
        }
    }

    if (str_contains($css, $startMarker) && str_contains($css, $endMarker)) {
        $pattern =
            '~' . preg_quote($startMarker, '~')
            . '.*?'
            . preg_quote($endMarker, '~') . '~s';

        $updated = preg_replace($pattern, $cssBlock, $css, 1, $count);

        if (!is_string($updated) || $count !== 1) {
            throw new RuntimeException('Could not refresh the existing Landscape alignment fix.');
        }

        $css = $updated;
    } elseif (!str_contains($css, $startMarker) && !str_contains($css, $endMarker)) {
        $css = rtrim($css) . "\n\n\n" . $cssBlock . "\n";
    } else {
        throw new RuntimeException(
            "Found only one Landscape alignment marker.\nNothing was changed."
        );
    }

    $backupDir = $root . '/private/update-backups/landscape-twofix-' . date('Ymd-His');

    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Could not create backup directory.');
    }

    if (!copy($path, $backupDir . '/scout-report-cards.css')) {
        throw new RuntimeException('Could not back up Scout Report CSS. Nothing was changed.');
    }

    $atomicWrite($path, $css);

    echo "SUCCESS\n\n";
    echo "Changed exactly one file:\n";
    echo "  - css/scout-report-cards.css\n\n";
    echo "Visible changes only:\n";
    echo "  - Landscape summary-card text now matches normal report-card typography.\n";
    echo "  - Primary-setting icon is fixed to the lower-right.\n\n";
    echo "No PHP, report fields, form behavior, icons, or stored data were changed.\n";
    echo "Backup: " . str_replace($root . '/', '', $backupDir) . "\n\n";

    if (@unlink(__FILE__)) {
        echo "Updater deleted itself.\n";
    } else {
        echo "Delete this updater from the site root now.\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "UPDATE STOPPED\n\n";
    echo $e->getMessage() . "\n";
    echo "No intentional changes were made after the failure point.\n";
}
