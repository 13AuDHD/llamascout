<?php
declare(strict_types=1);

require_once __DIR__ . '/osm-resource-import.php';

/** Only complete, validated private snapshots may enter the local SQL queue. */
function llama_osm_local_snapshot_rows(string $path, string $region, ?string &$date = null): Generator
{
    $stream = fopen($path,'rb');
    if ($stream === false) throw new RuntimeException('Snapshot file could not be opened.');
    try {
        $line = fgets($stream,65536);
        if ($line === false) throw new RuntimeException('Snapshot header missing.');
        $header = llama_osm_nearest_decode_row($line);
        $date = (string)($header['snapshot_date'] ?? '');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (($header['format'] ?? '') !== 'llama-osm-nearest-v1' || ($header['license'] ?? '') !== 'ODbL-1.0'
            || ($header['region'] ?? '') !== $region || !$parsed || $parsed->format('Y-m-d') !== $date || $date > gmdate('Y-m-d')) {
            throw new RuntimeException('Invalid local snapshot header.');
        }
        $seen = [];
        $rawCount = 0;
        $ended = false;
        while (($line = fgets($stream,65536)) !== false) {
            if (trim($line)==='') continue;
            if ($ended) throw new RuntimeException('Unexpected content after snapshot trailer.');
            $row = llama_osm_nearest_decode_row($line);
            if (($row['end'] ?? false) === true) {
                if (!is_int($row['count'] ?? null) || $rawCount < 1 || $row['count'] !== $rawCount) {
                    throw new RuntimeException('Incomplete snapshot.');
                }
                $ended = true;
                continue;
            }
            $values = llama_osm_resource_row($row);
            $rawCount++;
            $identity = $values[0] . ':' . $values[1] . ':' . $values[2];
            if (isset($seen[$identity])) continue;
            $seen[$identity] = true;
            yield $row;
        }
        if (!$ended) throw new RuntimeException('Snapshot trailer missing.');
    } finally {
        fclose($stream);
    }
}

/**
 * Import through the same guarded SQL transactions as the old worker, with
 * no remote requests. A killed CLI run can resume the same private snapshot.
 */
function llama_osm_local_import_snapshot(PDO $db, array $job, string $path, int $maxRows, ?callable $mutate = null): array
{
    $maxRows = max(1,min(200000,$maxRows));
    $region = (string)$job['region'];
    $base = ['region'=>$region,'batch_id'=>(string)$job['batch_id']];
    $date = null;
    $count = 0;
    foreach (llama_osm_local_snapshot_rows($path,$region,$date) as $row) {
        if (++$count > $maxRows) throw new RuntimeException('Snapshot exceeds the configured state safety limit.');
    }
    $mutate ??= static function(array $input) use ($db,$maxRows): array {
        // Deliberate pause outside the database transaction, not in a PHP web request.
        usleep(2500000);
        for ($attempt=0; $attempt<7; $attempt++) {
            try {
                return llama_osm_resource_paced_worker($db,$input,$maxRows);
            } catch (LlamaOsmImportBusy $e) {
                if ($attempt===6) throw $e;
                sleep(min(30,2 ** ($attempt+1)));
            }
        }
        throw new RuntimeException('Import remained busy.');
    };
    $current = $mutate($base+['action'=>'begin','snapshot_date'=>$date,'count'=>$count]);
    if ($current['status']==='complete') return $current;
    $next = (int)$current['next_chunk'];
    $seq = 0;
    $chunk = [];
    foreach (llama_osm_local_snapshot_rows($path,$region) as $row) {
        $chunk[] = $row;
        if (count($chunk)===LLAMA_OSM_IMPORT_CHUNK_SIZE) {
            if ($seq >= $next) $mutate($base+['action'=>'chunk','seq'=>$seq,'rows'=>$chunk]);
            $seq++;
            $chunk = [];
        }
    }
    if ($chunk && $seq >= $next) $mutate($base+['action'=>'chunk','seq'=>$seq,'rows'=>$chunk]);
    return $mutate($base+['action'=>'finish']);
}

function llama_osm_local_job_current(PDO $db, array $job): bool
{
    $stmt = $db->prepare("SELECT 1 FROM osm_resource_import_jobs WHERE region=? AND batch_id=? AND status IN ('queued','importing')");
    $stmt->execute([$job['region'],$job['batch_id']]);
    return $stmt->fetchColumn() !== false;
}

/** Private working files are not placed under a document root. */
function llama_osm_local_directory(): string
{
    $root = dirname(__DIR__,2) . '/private/osm-resource-import';
    if (!is_dir($root) && !mkdir($root,0700,true) && !is_dir($root)) throw new RuntimeException('Cannot create private OSM working directory.');
    return $root;
}
