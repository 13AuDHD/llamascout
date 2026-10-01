<?php

declare(strict_types=1);

/*
 * Llama Scout Landscape recovery orchestrator
 * Replays the exact Sept. 25 Landscape + setting update and all follow-up
 * display fixes against the known old/current source state, then exports the
 * final changed source files for syncing back to GitHub.
 */

$requiredToken = 'LS-20261001-landscape-recover-7cc9dc60c8de0066';
header('Content-Type: text/plain; charset=UTF-8');

if (!hash_equals($requiredToken, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo "Invalid or missing recovery token.\n";
    exit;
}

$root = __DIR__;
$payloadDir = $root . '/_landscape_recovery_payload';

$gitBlobSha = static function (string $content): string {
    return sha1('blob ' . strlen($content) . "\0" . $content);
};

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        throw new RuntimeException('Missing required file: ' . $relative);
    }
    $content = file_get_contents($path);
    if (!is_string($content)) {
        throw new RuntimeException('Could not read: ' . $relative);
    }
    return $content;
};

$rrmdir = static function (string $dir) use (&$rrmdir): void {
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
};

$runUpdater = static function (
    string $payloadName,
    string $token,
    ?callable $prepare = null
) use ($root, $payloadDir): string {
    $source = $payloadDir . '/' . $payloadName;
    $target = $root . '/' . $payloadName;

    if (!is_file($source)) {
        throw new RuntimeException('Recovery payload is missing: ' . $payloadName);
    }

    $content = file_get_contents($source);
    if (!is_string($content)) {
        throw new RuntimeException('Could not read recovery payload: ' . $payloadName);
    }

    if ($prepare !== null) {
        $content = $prepare($content);
        if (!is_string($content)) {
            throw new RuntimeException('Could not prepare recovery payload: ' . $payloadName);
        }
    }

    if (file_put_contents($target, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not stage updater in site root: ' . $payloadName);
    }

    $oldGet = $_GET;
    $_GET['token'] = $token;

    ob_start();
    try {
        include $target;
        $output = (string) ob_get_clean();
    } catch (Throwable $e) {
        $buffer = (string) ob_get_clean();
        $_GET = $oldGet;
        @unlink($target);
        throw new RuntimeException(
            'Updater failed: ' . $payloadName . "\n" . $buffer . "\n" . $e->getMessage(),
            0,
            $e
        );
    }

    $_GET = $oldGet;
    @unlink($target);
    return $output;
};

$finalLooksComplete = static function () use ($read): bool {
    try {
        $app = $read('app/place-report.php');
        $form = $read('partials/place-report/form.php');
        $readonly = $read('partials/place-report/read-only.php');
        $formCss = $read('css/site/features/place-report-form.css');
        $cardsCss = $read('css/scout-report-cards.css');
        $landscapeCss = $read('css/site/features/place-report-landscape.css');
        $multiJs = $read('js/place-report-multiselect.js');
    } catch (Throwable) {
        return false;
    }

    return str_contains($app, "'landscape_primary'")
        && str_contains($app, "'hurricane_risk' => 'hurricane'")
        && str_contains($app, "'wildfire_risk' => 'wildfire'")
        && str_contains($app, "'environment_water_nearby' => 'water-waves'")
        && str_contains($form, '/js/place-report-multiselect.js')
        && str_contains($readonly, 'LLAMA LANDSCAPE DISPLAY CARDS 2026-09-25')
        && str_contains($formCss, 'LLAMA LANDSCAPE CHIP HOTFIX 2026-09-25')
        && str_contains($cardsCss, 'LLAMA LANDSCAPE CARD ALIGNMENT FIX 2026-09-25')
        && str_contains($landscapeCss, 'scout-report-landscape-summary-grid')
        && str_contains($multiJs, 'data-place-report-multiselect');
};

$exportSource = static function () use ($root, $read, $gitBlobSha): string {
    $files = [
        'app/place-report.php',
        'partials/place-report/form.php',
        'partials/place-report/read-only.php',
        'css/site/features/place-report-form.css',
        'css/site/features/place-report-landscape.css',
        'css/scout-report-cards.css',
        'js/place-report-multiselect.js',
    ];

    $exportDir = $root . '/private/update-exports';
    if (!is_dir($exportDir) && !mkdir($exportDir, 0775, true) && !is_dir($exportDir)) {
        throw new RuntimeException('Could not create private/update-exports.');
    }

    $stamp = gmdate('Ymd-His');
    $manifest = "Llama Scout Landscape recovery source export\n"
        . "Created UTC: " . gmdate('c') . "\n\n"
        . "Replace these exact paths in GitHub main:\n";

    foreach ($files as $relative) {
        $content = $read($relative);
        $manifest .= $relative . "\n  git blob: " . $gitBlobSha($content) . "\n";
    }

    $manifest .= "\nThis export contains the post-recovery source of truth.\n"
        . "After these files are committed to GitHub, future deployments will keep the restored Landscape + setting form.\n";

    if (class_exists('ZipArchive')) {
        $path = $exportDir . '/llama-scout-landscape-recovered-source-' . $stamp . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the GitHub source ZIP.');
        }
        foreach ($files as $relative) {
            $zip->addFromString($relative, $read($relative));
        }
        $zip->addFromString('SOURCE-SYNC.txt', $manifest);
        $zip->close();
        return str_replace($root . '/', '', $path);
    }

    if (class_exists('PharData')) {
        $path = $exportDir . '/llama-scout-landscape-recovered-source-' . $stamp . '.tar';
        $tar = new PharData($path);
        foreach ($files as $relative) {
            $tar->addFromString($relative, $read($relative));
        }
        $tar->addFromString('SOURCE-SYNC.txt', $manifest);
        return str_replace($root . '/', '', $path);
    }

    throw new RuntimeException(
        'The Landscape update completed, but PHP has neither ZipArchive nor PharData, so the source export could not be created.'
    );
};

try {
    if (!is_dir($payloadDir)) {
        throw new RuntimeException(
            "The _landscape_recovery_payload folder is missing.\n"
            . "Extract the entire recovery package into the Llama Scout root before running this file."
        );
    }

    echo "Llama Scout Landscape recovery\n";
    echo "==============================\n\n";

    if ($finalLooksComplete()) {
        echo "The final Landscape + setting code is already present. No source transformation was needed.\n\n";
    } else {
        $baseline = [
            'app/place-report.php' => [
                '2cbd87bfc2826479afd400a83e03aa9f207ac8a9',
                '77ef20b36c29a2c4b98a93605a83319a02bcf8ae',
            ],
            'partials/place-report/form.php' => [
                '4e45bc03ddaa16c41a908127fcb334a50e2b4470',
            ],
            'partials/place-report/read-only.php' => [
                '7c668ff04146910ed824309b1e3c268bce63292d',
            ],
            'css/site/features/place-report-form.css' => [
                'ca50bb8eb9918126e6b72e3782af150a88d1dbab',
            ],
        ];

        $actualAppSha = '';
        foreach ($baseline as $relative => $accepted) {
            $sha = $gitBlobSha($read($relative));
            if ($relative === 'app/place-report.php') {
                $actualAppSha = $sha;
            }
            if (!in_array($sha, $accepted, true)) {
                throw new RuntimeException(
                    $relative . " is not one of the known safe pre-recovery versions.\n"
                    . "Current git blob: " . $sha . "\n"
                    . "Nothing was changed. This safeguard prevents overwriting newer work."
                );
            }
        }

        foreach ([
            'hurricane.svg', 'forest.svg', 'mountain.svg', 'desert.svg', 'field.svg',
            'ground.svg', 'cactus.svg', 'wetland.svg', 'tropical.svg', 'pond.svg',
            'riverside.svg', 'urban.svg', 'suburban.svg', 'farm.svg', 'land-use.svg',
            'wildfire.svg', 'water-waves.svg',
        ] as $icon) {
            if (!is_file($root . '/assets/icons/' . $icon)) {
                throw new RuntimeException('Missing required icon: assets/icons/' . $icon . '. Nothing was changed.');
            }
        }

        echo "1/5 Restoring structured Landscape + setting form...\n";
        $mainOutput = $runUpdater(
            'llama_landscape_update.php',
            'LS-20260925-7f94c3a15d8e',
            static function (string $content) use ($actualAppSha): string {
                return str_replace(
                    "'app/place-report.php' => '2cbd87bfc2826479afd400a83e03aa9f207ac8a9'",
                    "'app/place-report.php' => '" . $actualAppSha . "'",
                    $content
                );
            }
        );
        echo "    Done.\n";

        echo "2/5 Restoring compact multiselect chips + Hurricane icon...\n";
        $runUpdater('llama_landscape_chip_hotfix.php', 'LS-20260925-chipfix-8c4d7a');
        echo "    Done.\n";

        echo "3/5 Restoring Landscape display cards + setting icons...\n";
        $runUpdater('llama_landscape_display_cards.php', 'LS-20260925-landcards-4f8b2c');
        echo "    Done.\n";

        echo "4/5 Restoring display alignment + Water nearby icon...\n";
        $runUpdater('llama_landscape_display_hotfix.php', 'LS-20260925-landfix-91c7e4');
        echo "    Done.\n";

        echo "5/5 Restoring final Landscape card typography/alignment...\n";
        $runUpdater('llama_landscape_two_fix.php', 'LS-20260925-land-twofix-63e29a');
        echo "    Done.\n\n";

        if (!$finalLooksComplete()) {
            throw new RuntimeException(
                "The updater sequence finished, but final verification did not pass.\n"
                . "Private backups created by the individual update scripts are still available."
            );
        }
    }

    foreach ([
        'app/place-report.php',
        'partials/place-report/form.php',
        'partials/place-report/read-only.php',
    ] as $relative) {
        if (function_exists('exec')) {
            $lines = [];
            $status = 0;
            exec('php -l ' . escapeshellarg($root . '/' . $relative) . ' 2>&1', $lines, $status);
            if ($status !== 0) {
                throw new RuntimeException(
                    'Final PHP lint failed for ' . $relative . ":\n" . implode("\n", $lines)
                );
            }
        }
    }

    $export = $exportSource();

    echo "Recovery verified.\n\n";
    echo "GitHub source-sync archive created at:\n  " . $export . "\n\n";
    echo "IMPORTANT: download that archive from cPanel/File Manager and replace the matching files in GitHub main.\n";
    echo "That second step makes the restored version permanent so a later deployment cannot bring the old form back.\n\n";

    $rrmdir($payloadDir);
    @unlink(__FILE__);

    echo "The temporary recovery scripts have been removed from the public site.\n";

} catch (Throwable $e) {
    http_response_code(500);
    echo "RECOVERY STOPPED\n\n";
    echo $e->getMessage() . "\n\n";
    echo "The recovery wrapper and payload were left in place so the problem can be inspected safely.\n";
    exit;
}
