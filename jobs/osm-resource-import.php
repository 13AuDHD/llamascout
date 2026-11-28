<?php
declare(strict_types=1);

// Run only from the server's CLI / cron. Never launch raw PBF processing
// from a website request or download into the public document root.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/database.php';
require_once dirname(__DIR__) . '/app/osm-resource-local.php';
umask(0077);
set_time_limit(0);

function osm_local_status(string $root,string $state,string $message): void
{
    $tmp = $root . '/worker-status.' . getmypid() . '.tmp';
    if (file_put_contents($tmp,json_encode(['state'=>$state,'message'=>$message,'at'=>gmdate('c')],JSON_THROW_ON_ERROR),LOCK_EX) === false
        || !rename($tmp,$root . '/worker-status.json')) {
        throw new RuntimeException('Cannot write local worker status.');
    }
}

/** Fixed command arguments, no shell interpolation; low CPU priority and a hard process timeout. */
function osm_local_command(array $args,string $directory,int $seconds,?callable $heartbeat=null): string
{
    if (!function_exists('proc_open')) throw new RuntimeException('PHP CLI proc_open is unavailable.');
    $stdout = $directory . '/command.stdout';
    $stderr = $directory . '/command.stderr';
    $process = proc_open(['timeout','--kill-after=10s',(string)$seconds,'nice','-n','15',...$args],[
        0=>['file','/dev/null','r'],1=>['file',$stdout,'w'],2=>['file',$stderr,'w'],
    ],$pipes,$directory);
    if (!is_resource($process)) throw new RuntimeException('Cannot launch local extraction command.');
    $last = 0;
    $exit = -1;
    try {
        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) { $exit = (int)$status['exitcode']; break; }
            if ($heartbeat && time()-$last>=10) { $heartbeat(); $last=time(); }
            sleep(1);
        }
    } catch (Throwable $e) {
        proc_terminate($process);
        throw $e;
    } finally {
        $closed = proc_close($process);
        if ($exit === -1) $exit = $closed;
    }
    if ($exit!==0) {
        $detail = is_file($stderr) ? trim((string)file_get_contents($stderr,false,null,0,4096)) : '';
        throw new RuntimeException('Local ' . basename($args[0]) . ' command failed (' . $exit . '): ' . $detail);
    }
    return trim((string)file_get_contents($stdout,false,null,0,8192));
}

function osm_local_python(): string
{
    $python = (string)(llama_osm_resource_config()['python'] ?? 'python3');
    if ($python==='' || str_contains($python,"\0")) throw new RuntimeException('Invalid local Python executable.');
    return $python;
}

function osm_local_check(string $root): void
{
    osm_local_command(['curl','--version'],$root,20);
    osm_local_command(['osmium','--version'],$root,20);
    osm_local_command([osm_local_python(),'-c','import osmium, shapely'],$root,30);
}

function osm_local_update(PDO $db,array $job,string $message): void
{
    if (!llama_osm_local_job_current($db,$job)) throw new RuntimeException('Job was superseded.');
    $db->prepare("UPDATE osm_resource_import_jobs SET message=?,updated_at=UTC_TIMESTAMP()
        WHERE region=? AND batch_id=? AND status IN ('queued','importing')")
        ->execute([$message,$job['region'],$job['batch_id']]);
}

$root = null;
$lock = null;
$runnerLock = null;
$db = null;
$job = null;
$phase = 'starting';
$directory = null;
$exit = 0;
try {
    $root = llama_osm_local_directory();
    $lock = fopen($root . '/worker.lock','c');
    if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) exit(0);
    $db = db();
    if (!llama_osm_resource_ready($db)) throw new RuntimeException('Run the existing Resource import SQL migration first.');
    // Serialize whole local extraction jobs as well as individual SQL writes.
    $runnerLock = 'llama-osm-local-' . substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $stmt = $db->prepare('SELECT GET_LOCK(?,0)');
    $stmt->execute([$runnerLock]);
    if ((int)$stmt->fetchColumn()!==1) { $runnerLock=null; exit(0); }
    $phase = 'checking local dependencies';
    osm_local_check($root);
    osm_local_status($root,'ready','Local extraction tools and database are available.');
    if (in_array('--check',$argv,true)) {
        fwrite(STDOUT,"Local OSM import prerequisites are ready.\n");
        exit(0);
    }

    // Resume interrupted imports first. A cron run processes only one state.
    $job = $db->query("SELECT * FROM osm_resource_import_jobs WHERE status IN ('queued','importing')
        ORDER BY CASE WHEN status='importing' THEN 0 ELSE 1 END,updated_at,region LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$job) exit(0);
    $region = $job['region'];
    if (!isset(llama_osm_resource_states()[$region]) || !preg_match('/^[a-f0-9]{32}$/D',$job['batch_id'])) {
        throw new RuntimeException('Invalid queued state or job identity.');
    }
    $directory = $root . '/' . $region . '-' . $job['batch_id'];
    if (!is_dir($directory) && !mkdir($directory,0700) && !is_dir($directory)) throw new RuntimeException('Cannot create state work directory.');
    $snapshot = $directory . '/snapshot.ndjson';
    $raw = $directory . '/source.osm.pbf';
    $filtered = $directory . '/filtered.osm.pbf';
    $heartbeat = static function() use ($db,$job): void {
        if (!llama_osm_local_job_current($db,$job)) throw new RuntimeException('Job was superseded.');
    };
    osm_local_status($root,'working','Processing ' . llama_osm_resource_states()[$region] . '.');
    if (!is_file($snapshot)) {
        // Require room for the configured download cap, filter, and working
        // overhead. Never run a large download against an unknown disk limit.
        $maxBytes = max(104857600,min(8589934592,(int)(llama_osm_resource_config()['max_download_bytes'] ?? 4294967296)));
        $free = disk_free_space($directory);
        if ($free===false || $free < $maxBytes*2+536870912) throw new RuntimeException('Insufficient free disk space for a local state extract.');
        $phase = 'downloading';
        osm_local_update($db,$job,'Downloading state extract locally');
        osm_local_command(['curl','--fail','--location','--proto','=https','--proto-redir','=https',
            '--retry','2','--connect-timeout','20','--max-time','3600','--max-filesize',(string)$maxBytes,'--limit-rate','5M',
            '--output',$raw,'https://download.geofabrik.de/north-america/us/' . $region . '-latest.osm.pbf'],$directory,3700,$heartbeat);
        $timestamp = osm_local_command(['osmium','fileinfo','-g','header.option.osmosis_replication_timestamp',$raw],$directory,60,$heartbeat);
        $date = substr($timestamp,0,10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)) throw new RuntimeException('State snapshot date missing.');
        $phase = 'filtering';
        osm_local_update($db,$job,'Filtering camper-related map services');
        osm_local_command(['osmium','tags-filter',$raw,'-o',$filtered,'--overwrite',
            'nwr/amenity=fuel,charging_station,drinking_water,toilets,hospital,sanitary_dump_station,shower,waste_disposal',
            'nwr/shop=alcohol,wine,supermarket,grocery,convenience,laundry',
            'nwr/place=city,town,village','nwr/healthcare=hospital','nwr/drinking_water=yes','nwr/fuel:propane=yes','nwr/propane=yes'],
            $directory,3600,$heartbeat);
        unlink($raw);
        $phase = 'extracting';
        osm_local_update($db,$job,'Extracting compact reference records');
        osm_local_command([osm_local_python(),dirname(__DIR__) . '/app/osm-resource-extract.py',$filtered,$snapshot,
            '--region',$region,'--snapshot',$date],$directory,3600,$heartbeat);
        unlink($filtered);
    }
    $phase = 'importing';
    osm_local_update($db,$job,'Importing directly into the database in paced batches');
    $result = llama_osm_local_import_snapshot($db,$job,$snapshot,(int)(llama_osm_resource_config()['max_state_rows'] ?? LLAMA_OSM_IMPORT_DEFAULT_MAX_ROWS));
    if ($result['status']!=='complete') throw new RuntimeException('Snapshot was not committed.');
    osm_local_status($root,'ready','Finished ' . llama_osm_resource_states()[$region] . '.');
    fwrite(STDOUT,$region . ': imported ' . (int)$result['received_rows'] . " reference records.\n");
} catch (Throwable $e) {
    $exit = 1;
    if ($db instanceof PDO && $job) {
        try {
            if (llama_osm_local_job_current($db,$job)) {
            llama_osm_resource_worker($db,['region'=>$job['region'],'batch_id'=>$job['batch_id'],'action'=>'fail']);
            $db->prepare("UPDATE osm_resource_import_jobs SET message=? WHERE region=? AND batch_id=? AND status='error'")
                ->execute(['Local worker failed while ' . $phase . '; see server worker log. Retry this state.',$job['region'],$job['batch_id']]);
            }
        } catch (Throwable $ignored) {}
    }
    if ($root) {
        try {
            osm_local_status($root,'blocked',$job ? 'A state import failed. Check its job status and server log.'
                : 'Local worker setup is incomplete. Check PHP CLI, curl, osmium, Python dependencies and the server log.');
        } catch (Throwable $ignored) {}
    }
    fwrite(STDERR,$e->getMessage() . "\n");
} finally {
    // Keep raw extracts out of persistent storage on normal exit. Abrupt
    // interruptions resume the same batch using its finished compact file.
    if ($directory && is_dir($directory)) {
        foreach (['source.osm.pbf','filtered.osm.pbf','snapshot.ndjson','snapshot.ndjson.partial','command.stdout','command.stderr'] as $file) {
            if (is_file($directory . '/' . $file)) unlink($directory . '/' . $file);
        }
        rmdir($directory);
    }
    if ($db instanceof PDO && $runnerLock) {
        try { $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$runnerLock]); } catch (Throwable $ignored) {}
    }
    if (is_resource($lock)) { flock($lock,LOCK_UN); fclose($lock); }
}
exit($exit);
