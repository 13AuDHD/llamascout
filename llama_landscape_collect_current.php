<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

$requiredToken = 'LS-20261001-landscape-inspect-41d0a3f8d2e1';
if (!hash_equals($requiredToken, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo "Invalid or missing inspection token.\n";
    exit;
}

$root = __DIR__;
$files = [
    'app/place-report.php',
    'partials/place-report/form.php',
    'partials/place-report/read-only.php',
    'css/site/features/place-report-form.css',
    'css/site/features/place-report-landscape.css',
    'css/scout-report-cards.css',
    'js/place-report-multiselect.js',
];

$gitBlobSha = static function (string $content): string {
    return sha1('blob ' . strlen($content) . "\0" . $content);
};

try {
    $exportDir = $root . '/private/update-exports';
    if (!is_dir($exportDir) && !mkdir($exportDir, 0775, true) && !is_dir($exportDir)) {
        throw new RuntimeException('Could not create private/update-exports.');
    }

    $stamp = gmdate('Ymd-His');
    $manifest = "Llama Scout Landscape current-source inspection\n";
    $manifest .= "Created UTC: " . gmdate('c') . "\n\n";
    $manifest .= "No site source files were changed by this collector.\n\n";

    $available = [];
    foreach ($files as $relative) {
        $path = $root . '/' . $relative;
        if (!is_file($path)) {
            $manifest .= $relative . "\n  MISSING\n";
            continue;
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            $manifest .= $relative . "\n  READ FAILED\n";
            continue;
        }

        $available[$relative] = $content;
        $manifest .= $relative . "\n";
        $manifest .= "  bytes: " . strlen($content) . "\n";
        $manifest .= "  git blob: " . $gitBlobSha($content) . "\n";
        $manifest .= "  sha256: " . hash('sha256', $content) . "\n";
    }

    if (!$available) {
        throw new RuntimeException('None of the expected Landscape source files could be read.');
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is not available on this server.');
    }

    $relativeExport = 'private/update-exports/llama-scout-landscape-current-source-' . $stamp . '.zip';
    $zipPath = $root . '/' . $relativeExport;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create inspection ZIP.');
    }

    foreach ($available as $relative => $content) {
        $zip->addFromString($relative, $content);
    }
    $zip->addFromString('CURRENT-SOURCE-MANIFEST.txt', $manifest);
    $zip->close();

    echo "Llama Scout Landscape inspection complete\n";
    echo "========================================\n\n";
    echo "No source files were changed.\n\n";
    echo "Current source archive created at:\n  " . $relativeExport . "\n\n";
    echo "Download that ZIP from cPanel/File Manager and upload it to ChatGPT.\n";
    echo "The existing Landscape recovery package was left untouched.\n\n";

    @unlink(__FILE__);
    echo "The temporary collector script removed itself from the public site.\n";

} catch (Throwable $e) {
    http_response_code(500);
    echo "INSPECTION STOPPED\n\n";
    echo $e->getMessage() . "\n\n";
    echo "Nothing was changed.\n";
}
