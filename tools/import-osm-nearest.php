<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/database.php';
require_once dirname(__DIR__) . '/app/osm-nearest-data.php';

if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php tools/import-osm-nearest.php /path/to/region.ndjson\n");
    exit(1);
}
$stream = fopen($argv[1], 'rb');
if ($stream === false) exit(1);
try {
    $count = llama_osm_nearest_import(db(), $stream);
    fwrite(STDOUT, "Imported $count OSM service rows.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "Import failed: " . $exception->getMessage() . "\n");
    exit(1);
} finally { fclose($stream); }
