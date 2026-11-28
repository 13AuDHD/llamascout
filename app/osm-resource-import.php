<?php
declare(strict_types=1);

require_once __DIR__ . '/osm-nearest-data.php';

/** Fixed Geofabrik state slugs; no arbitrary URLs or region names. */
function llama_osm_resource_states(): array
{
    return [
        'alabama'=>'Alabama','alaska'=>'Alaska','arizona'=>'Arizona','arkansas'=>'Arkansas',
        'california'=>'California','colorado'=>'Colorado','connecticut'=>'Connecticut','delaware'=>'Delaware',
        'district-of-columbia'=>'District of Columbia','florida'=>'Florida','georgia'=>'Georgia','hawaii'=>'Hawaii',
        'idaho'=>'Idaho','illinois'=>'Illinois','indiana'=>'Indiana','iowa'=>'Iowa','kansas'=>'Kansas',
        'kentucky'=>'Kentucky','louisiana'=>'Louisiana','maine'=>'Maine','maryland'=>'Maryland',
        'massachusetts'=>'Massachusetts','michigan'=>'Michigan','minnesota'=>'Minnesota','mississippi'=>'Mississippi',
        'missouri'=>'Missouri','montana'=>'Montana','nebraska'=>'Nebraska','nevada'=>'Nevada',
        'new-hampshire'=>'New Hampshire','new-jersey'=>'New Jersey','new-mexico'=>'New Mexico',
        'new-york'=>'New York','north-carolina'=>'North Carolina','north-dakota'=>'North Dakota',
        'ohio'=>'Ohio','oklahoma'=>'Oklahoma','oregon'=>'Oregon','pennsylvania'=>'Pennsylvania',
        'rhode-island'=>'Rhode Island','south-carolina'=>'South Carolina','south-dakota'=>'South Dakota',
        'tennessee'=>'Tennessee','texas'=>'Texas','utah'=>'Utah','vermont'=>'Vermont',
        'virginia'=>'Virginia','washington'=>'Washington','west-virginia'=>'West Virginia',
        'wisconsin'=>'Wisconsin','wyoming'=>'Wyoming',
    ];
}

function llama_osm_resource_priorities(): array
{
    return [
        'potable_water'=>'Potable water','dump_station'=>'RV dump stations','shower'=>'Showers',
        'laundry'=>'Laundry','propane'=>'Propane','toilet'=>'Public toilets','trash'=>'Trash disposal',
        'fuel'=>'Fuel','groceries'=>'Groceries','ev_charging'=>'EV charging',
    ];
}

function llama_osm_resource_config(): array
{
    return (array) (llama_config()['osm_resource_import'] ?? []);
}

function llama_osm_resource_ready(PDO $db): bool
{
    try {
        foreach (['osm_nearest_services','osm_resource_import_jobs','osm_resource_import_stage'] as $table) {
            $db->query('SELECT 1 FROM ' . $table . ' LIMIT 0');
        }
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') return false;
        throw $e;
    }
}

function llama_osm_resource_validate_regions(array $regions): array
{
    $states = llama_osm_resource_states();
    foreach ($regions as $region) {
        if (!is_string($region) || !isset($states[$region])) throw new InvalidArgumentException('Invalid state.');
    }
    $regions = array_values(array_unique($regions));
    sort($regions, SORT_STRING);
    if (!$regions) throw new InvalidArgumentException('Choose at least one state.');
    return $regions;
}

/** Source snapshots are visible only after a complete, validated replacement. */
function llama_osm_resource_inventory(PDO $db): array
{
    $rows = $db->query('SELECT region,MAX(snapshot_date) AS snapshot_date,COUNT(*) AS service_rows
        FROM osm_nearest_services GROUP BY region')->fetchAll(PDO::FETCH_ASSOC);
    $jobs = $db->query('SELECT * FROM osm_resource_import_jobs')->fetchAll(PDO::FETCH_ASSOC);
    $byRegion = array_column($rows,null,'region');
    $byJob = array_column($jobs,null,'region');
    $states = [];
    foreach (llama_osm_resource_states() as $region=>$label) {
        $states[$region] = ['name'=>$label,'snapshot'=>$byRegion[$region] ?? null,'job'=>$byJob[$region] ?? null];
    }
    return $states;
}

/** Only dispatch the fixed repository workflow. Tokens never reach the browser. */
function llama_osm_resource_dispatch(array $regions, string $batch, string $token): void
{
    if ($token === '' || !function_exists('curl_init')) throw new RuntimeException('Configure the GitHub dispatch credential and PHP cURL first.');
    $curl = curl_init('https://api.github.com/repos/13audhd/llamascout/actions/workflows/osm-resource-import.yml/dispatches');
    curl_setopt_array($curl, [
        CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>25,
        CURLOPT_HTTPHEADER=>['Accept: application/vnd.github+json','Authorization: Bearer ' . $token,
            'X-GitHub-Api-Version: 2022-11-28','Content-Type: application/json','User-Agent: LlamaScout-OSM-Importer'],
        CURLOPT_POSTFIELDS=>json_encode(['ref'=>'main','inputs'=>['regions'=>json_encode($regions,JSON_THROW_ON_ERROR),'batch_id'=>$batch]],JSON_THROW_ON_ERROR),
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($body === false || !in_array($status,[200,204],true)) throw new RuntimeException('Extraction dispatch was not confirmed (HTTP ' . $status . '). Check the credential and retry selected states.');
}

function llama_osm_resource_queue(PDO $db, array $regions, bool $restart = false): array
{
    $regions = llama_osm_resource_validate_regions($regions);
    $config = llama_osm_resource_config();
    $token = trim((string) ($config['github_token'] ?? ''));
    if ($token === '' || strlen((string) ($config['worker_key'] ?? '')) < 32) {
        throw new RuntimeException('Configure the import worker and GitHub dispatch token first.');
    }
    $batch = bin2hex(random_bytes(16));
    $queued = [];
    $db->beginTransaction();
    try {
        $read = $db->prepare('SELECT status FROM osm_resource_import_jobs WHERE region=? FOR UPDATE');
        $write = $db->prepare("INSERT INTO osm_resource_import_jobs (region,batch_id,status,message)
            VALUES (?,?,'queued','Waiting for extraction')
            ON DUPLICATE KEY UPDATE batch_id=VALUES(batch_id),status='queued',snapshot_date=NULL,
            expected_rows=0,received_rows=0,next_chunk=0,message=VALUES(message),updated_at=UTC_TIMESTAMP()");
        foreach ($regions as $region) {
            $read->execute([$region]);
            if (!$restart && in_array($read->fetchColumn(),['queued','importing'],true)) continue;
            $write->execute([$region,$batch]);
            $queued[] = $region;
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    if (!$queued) return [];
    try {
        llama_osm_resource_dispatch($queued,$batch,$token);
    } catch (Throwable $e) {
        // A lost dispatch response is ambiguous. A worker that has already
        // started continues; an explicitly restarted batch supersedes old workers.
        $stmt = $db->prepare("UPDATE osm_resource_import_jobs SET message='Dispatch not confirmed; restart selected if no worker starts'
            WHERE batch_id=? AND status='queued'");
        $stmt->execute([$batch]);
        throw $e;
    }
    return $queued;
}

/** Validated compact row, no arbitrary SQL, keys or Scout writes. */
function llama_osm_resource_row(array $row): array
{
    $kind = $row['osm_type'] ?? null;
    $id = $row['osm_id'] ?? null;
    $type = $row['service_type'] ?? null;
    $name = $row['name'] ?? null;
    $lat = $row['latitude'] ?? null;
    $lon = $row['longitude'] ?? null;
    if (!in_array($kind,['node','way','relation'],true) || !is_int($id) || $id < 1
        || !in_array($type,LLAMA_OSM_NEAREST_TYPES,true) || !is_string($name) || trim($name)==='' || mb_strlen($name)>255
        || (!is_int($lat)&&!is_float($lat)) || (!is_int($lon)&&!is_float($lon))
        || !is_finite((float)$lat) || !is_finite((float)$lon) || abs($lat)>90 || abs($lon)>180 || ($lat==0&&$lon==0)) {
        throw new InvalidArgumentException('Invalid reference record.');
    }
    return [$kind,$id,$type,$name,$lat,$lon];
}

const LLAMA_OSM_IMPORT_DEFAULT_MAX_ROWS = 50000;
const LLAMA_OSM_IMPORT_CHUNK_SIZE = 100;

final class LlamaOsmImportBusy extends RuntimeException {}

/**
 * Global transport guard, including separately dispatched workflows. Do not
 * occupy a PHP worker waiting for another import or sleeping between batches.
 * All successful mutations advance the persisted job timestamp.
 */
function llama_osm_resource_paced_worker(PDO $db, array $input, int $maxRows = LLAMA_OSM_IMPORT_DEFAULT_MAX_ROWS): array
{
    $lockName = 'llama-osm-writes-' . substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $lock = $db->prepare('SELECT GET_LOCK(?,0)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) throw new LlamaOsmImportBusy('Another import write is active.');
    try {
        $elapsed = $db->query('SELECT TIMESTAMPDIFF(SECOND,MAX(updated_at),UTC_TIMESTAMP()) FROM osm_resource_import_jobs')->fetchColumn();
        if ($elapsed !== null && $elapsed !== false && (int)$elapsed < 2) {
            throw new LlamaOsmImportBusy('Import cooling down; retry shortly.');
        }
        return llama_osm_resource_worker($db,$input,$maxRows);
    } finally {
        $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
}

/** Worker mutations are serialized by region and bound to a unique queue batch. */
function llama_osm_resource_worker(PDO $db, array $input, int $maxRows = LLAMA_OSM_IMPORT_DEFAULT_MAX_ROWS): array
{
    $maxRows = max(1,min(200000,$maxRows));
    $region = (string) ($input['region'] ?? '');
    $batch = (string) ($input['batch_id'] ?? '');
    $action = (string) ($input['action'] ?? '');
    if (!isset(llama_osm_resource_states()[$region]) || !preg_match('/^[a-f0-9]{32}$/D',$batch)
        || !in_array($action,['begin','chunk','finish','fail'],true)) throw new InvalidArgumentException('Invalid import command.');
    $lockName = 'llama-osm-' . substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn() . ':' . $region),0,40);
    $lock = $db->prepare('SELECT GET_LOCK(?,10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Region is busy.');
    try {
        $db->beginTransaction();
        $read = $db->prepare('SELECT * FROM osm_resource_import_jobs WHERE region=? FOR UPDATE');
        $read->execute([$region]);
        $job = $read->fetch(PDO::FETCH_ASSOC);
        if (!$job || !hash_equals($job['batch_id'],$batch) || in_array($job['status'],['cancelled','error'],true)) {
            throw new InvalidArgumentException('This import was superseded or cancelled.');
        }
        $clear = $db->prepare('DELETE FROM osm_resource_import_stage WHERE region=?');
        if ($action === 'fail') {
            if ($job['status'] !== 'complete') {
                $clear->execute([$region]);
                $db->prepare("UPDATE osm_resource_import_jobs SET status='error',message='Extraction or transfer failed; retry this state' WHERE region=?")->execute([$region]);
            }
        } elseif ($action === 'begin') {
            $date = (string)($input['snapshot_date'] ?? '');
            $count = $input['count'] ?? null;
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date || $date > gmdate('Y-m-d') || !is_int($count) || $count < 1 || $count > $maxRows) {
                throw new InvalidArgumentException('Invalid snapshot metadata or state exceeds configured safety limit.');
            }
            if ($job['status'] === 'queued') {
                $clear->execute([$region]);
                $db->prepare("UPDATE osm_resource_import_jobs SET status='importing',snapshot_date=?,expected_rows=?,received_rows=0,next_chunk=0,message='Receiving records' WHERE region=?")->execute([$date,$count,$region]);
            } elseif ($job['snapshot_date'] !== $date || (int)$job['expected_rows'] !== $count) {
                throw new InvalidArgumentException('Snapshot changed during import.');
            }
        } elseif ($action === 'chunk') {
            $seq = $input['seq'] ?? null;
            $rows = $input['rows'] ?? null;
            if (!is_int($seq) || $seq < 0 || !is_array($rows) || !array_is_list($rows) || !$rows || count($rows)>LLAMA_OSM_IMPORT_CHUNK_SIZE) throw new InvalidArgumentException('Invalid batch.');
            if ($job['status'] === 'complete' || ($job['status'] === 'importing' && $seq < (int)$job['next_chunk'])) {
                // Idempotent retry after an acknowledged transaction.
            } elseif ($job['status'] !== 'importing' || $seq !== (int)$job['next_chunk']
                || (int)$job['received_rows'] + count($rows) > (int)$job['expected_rows']) {
                throw new InvalidArgumentException('Unexpected chunk sequence.');
            } else {
                $insert = $db->prepare('INSERT INTO osm_resource_import_stage
                    (region,osm_type,osm_id,service_type,name,latitude,longitude,snapshot_date) VALUES (?,?,?,?,?,?,?,?)');
                foreach ($rows as $row) $insert->execute([$region,...llama_osm_resource_row($row),$job['snapshot_date']]);
                $db->prepare('UPDATE osm_resource_import_jobs SET received_rows=received_rows+?,next_chunk=next_chunk+1 WHERE region=?')->execute([count($rows),$region]);
            }
        } elseif ($job['status'] !== 'complete') {
            if ($job['status'] !== 'importing' || (int)$job['received_rows'] !== (int)$job['expected_rows']) throw new InvalidArgumentException('Incomplete snapshot.');
            $count = $db->prepare('SELECT COUNT(*) FROM osm_resource_import_stage WHERE region=?');
            $count->execute([$region]);
            if ((int)$count->fetchColumn() !== (int)$job['expected_rows']) throw new RuntimeException('Staged row count does not match.');
            $previous = $db->prepare('SELECT MAX(snapshot_date) FROM osm_nearest_services WHERE region=?');
            $previous->execute([$region]);
            if ((string)$previous->fetchColumn() > $job['snapshot_date']) throw new InvalidArgumentException('A newer snapshot already exists.');
            $oldCount = $db->prepare('SELECT COUNT(*) FROM osm_nearest_services WHERE region=?');
            $oldCount->execute([$region]);
            if ((int)$oldCount->fetchColumn() > $maxRows || (int)$job['expected_rows'] > $maxRows) {
                throw new InvalidArgumentException('State exceeds configured atomic replacement safety limit.');
            }
            $db->prepare('DELETE FROM osm_nearest_services WHERE region=?')->execute([$region]);
            $db->prepare('INSERT INTO osm_nearest_services (region,osm_type,osm_id,service_type,name,latitude,longitude,snapshot_date)
                SELECT region,osm_type,osm_id,service_type,name,latitude,longitude,snapshot_date FROM osm_resource_import_stage WHERE region=?')->execute([$region]);
            $clear->execute([$region]);
            $db->prepare("UPDATE osm_resource_import_jobs SET status='complete',message='Snapshot imported' WHERE region=?")->execute([$region]);
        }
        $read->execute([$region]);
        $result = $read->fetch(PDO::FETCH_ASSOC);
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    } finally {
        $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
}
