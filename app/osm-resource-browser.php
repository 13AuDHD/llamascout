<?php
declare(strict_types=1);

require_once __DIR__ . '/osm-resource-import.php';
require_once __DIR__ . '/osm-resource-pbf.php';

final class LlamaOsmBrowserRetry extends RuntimeException {}

/** Fetch a bounded byte range from the fixed state source. No arbitrary URLs. */
function llama_osm_browser_range(string $region,int $offset,int $length,string $validator=''): array
{
    if (!isset(llama_osm_resource_states()[$region]) || $offset<0 || $length<1 || $length>8454148) {
        throw new InvalidArgumentException('Invalid source range.');
    }
    if (!function_exists('curl_init') || !function_exists('gzuncompress') || PHP_INT_SIZE<8) {
        throw new RuntimeException('Browser importer requires PHP cURL, zlib and 64-bit PHP.');
    }
    $headers=[];
    $body='';
    $requestHeaders=['Accept-Encoding: identity'];
    if ($validator!=='') $requestHeaders[]='If-Range: ' . substr($validator,strpos($validator,':')+1);
    $curl=curl_init('https://download.geofabrik.de/north-america/us/' . $region . '-latest.osm.pbf');
    curl_setopt_array($curl,[
        CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>4,
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_RANGE=>$offset . '-' . ($offset+$length-1),
        CURLOPT_HTTPHEADER=>$requestHeaders,CURLOPT_USERAGENT=>'LlamaScout-State-Importer/1.0 (+https://llamascout.com)',
        CURLOPT_HEADERFUNCTION=>static function($handle,string $line) use (&$headers): int {
            if (str_starts_with($line,'HTTP/')) $headers=[];
            elseif (str_contains($line,':')) {
                [$key,$value]=explode(':',$line,2);
                $headers[strtolower(trim($key))]=trim($value);
            }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION=>static function($handle,string $data) use (&$body,$length): int {
            if (strlen($body)+strlen($data)>$length) return 0;
            $body.=$data;
            return strlen($data);
        },
    ]);
    $ok=curl_exec($curl);
    $code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($code===200 && $validator!=='') throw new RuntimeException('The state source changed during import. Restart this state to use the new snapshot.');
    if ($code===200) throw new RuntimeException('The source did not honor a bounded download. No full-state download was attempted.');
    if ($code===404) throw new RuntimeException('State extract is unavailable at the configured source.');
    if ($ok===false || $code===429 || $code>=500 || $code===0) throw new LlamaOsmBrowserRetry('State source temporarily unavailable; resume to retry.');
    if ($code!==206 || !preg_match('/^bytes (\d+)-(\d+)\/(\d+)$/D',$headers['content-range'] ?? '',$m)) {
        throw new RuntimeException('The source did not return a valid partial response.');
    }
    $size=(int)$m[3];
    if ((int)$m[1]!==$offset || (int)$m[2]!==$offset+strlen($body)-1 || strlen($body)<1 || $size<1
        || (int)$m[2]>=$size || (int)$m[2]!==min($offset+$length-1,$size-1)) {
        throw new RuntimeException('Incomplete source range; no records committed.');
    }
    // Require a stable version, including across daily redirects of latest.
    $etag=$headers['etag'] ?? '';
    $modified=$headers['last-modified'] ?? '';
    $actual=$etag!=='' && !str_starts_with($etag,'W/') ? 'etag:' . $etag : ($modified!==''?'modified:' . $modified:'');
    if ($actual==='' || strlen($actual)>255 || ($validator!=='' && !hash_equals($validator,$actual))) {
        throw new RuntimeException('The state source version changed or cannot be verified. Restart this state.');
    }
    return ['data'=>$body,'size'=>$size,'validator'=>$actual];
}

/** Advance at most 100 mapped rows and a bounded number of compressed bytes. */
function llama_osm_browser_slice(string $buffer,int $offset,int $total,int $skip): array
{
    $start=microtime(true);
    $pos=0;
    $rows=[];
    $nextSkip=$skip;
    while ($pos<strlen($buffer)) {
        $frame=llama_pbf_frame($buffer,$pos);
        if ($frame===null) {
            if ($offset+strlen($buffer)===$total) throw new RuntimeException('Truncated state source frame.');
            break;
        }
        if ($frame['type']!=='OSMData') throw new RuntimeException('Unexpected state source header.');
        $seen=0;
        foreach (llama_pbf_block_rows(llama_pbf_unblob($frame['blob'])) as $row) {
            if ($seen++<$nextSkip) continue;
            if (count($rows)===LLAMA_OSM_IMPORT_CHUNK_SIZE) {
                return ['offset'=>$offset+$pos,'row_offset'=>$seen-1,'rows'=>$rows];
            }
            $rows[]=$row;
        }
        if ($seen<$nextSkip) throw new RuntimeException('Saved block cursor is invalid. Restart this state.');
        $pos+=$frame['length'];
        $nextSkip=0;
        if (count($rows)>=LLAMA_OSM_IMPORT_CHUNK_SIZE || microtime(true)-$start>=2.5) break;
    }
    return ['offset'=>$offset+$pos,'row_offset'=>$nextSkip,'rows'=>$rows];
}

function llama_osm_browser_job(PDO $db): ?array
{
    return $db->query("SELECT * FROM osm_resource_import_jobs
        WHERE status='queued' OR (status='importing' AND import_mode='browser')
        ORDER BY CASE WHEN status='importing' THEN 0 ELSE 1 END,updated_at,region LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
}

function llama_osm_browser_public(array $job): array
{
    $total=(int)$job['source_bytes'];
    $offset=(int)$job['source_offset'];
    return ['region'=>$job['region'],'name'=>llama_osm_resource_states()[$job['region']] ?? $job['region'],
        'status'=>$job['status'],'message'=>$job['message'],'rows'=>(int)$job['received_rows'],
        'offset'=>$offset,'bytes'=>$total,'percent'=>$total>0?min(100,(int)floor($offset*100/$total)):0];
}

function llama_osm_browser_read_locked(PDO $db,array $job): array
{
    $stmt=$db->prepare('SELECT * FROM osm_resource_import_jobs WHERE region=? FOR UPDATE');
    $stmt->execute([$job['region']]);
    $current=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current || !hash_equals($job['batch_id'],$current['batch_id'])
        || $current['status']!==$job['status'] || (int)$current['source_offset']!==(int)$job['source_offset']
        || (int)$current['source_row_offset']!==(int)$job['source_row_offset']) {
        throw new LlamaOsmBrowserRetry('Import was restarted or changed; reload the queue.');
    }
    return $current;
}

function llama_osm_browser_step(PDO $db,?callable $fetch=null,?int $maxRows=null): array
{
    if (!llama_osm_resource_browser_ready($db)) throw new RuntimeException('Run the browser import SQL migration first.');
    $fetch ??= 'llama_osm_browser_range';
    $maxRows=max(1,min(200000,$maxRows ?? (int)(llama_osm_resource_config()['max_state_rows'] ?? LLAMA_OSM_IMPORT_DEFAULT_MAX_ROWS)));
    // Same whole-job lock as the optional CLI worker: no competing local
    // extraction, multiple browser tabs, or simultaneous state writes.
    $lockName='llama-osm-local-' . substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $lock=$db->prepare('SELECT GET_LOCK(?,0)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn()!==1) throw new LlamaOsmBrowserRetry('Another import step is active; retry shortly.');
    $job=null;
    try {
        $job=llama_osm_browser_job($db);
        if (!$job) return ['done'=>true];
        $region=$job['region'];
        $batch=$job['batch_id'];
        $initialize=$job['status']==='queued';
        $offset=$initialize?0:(int)$job['source_offset'];
        $total=$initialize?0:(int)$job['source_bytes'];
        $validator=$initialize?'':(string)$job['source_validator'];
        $skip=$initialize?0:(int)$job['source_row_offset'];
        $finished=!$initialize && $total>0 && $offset===$total && $skip===0;
        $slice=null;
        $date=null;
        $range=null;
        if (!$finished) {
            $range=$fetch($region,$offset,1048576,$validator);
            if (!$initialize && ($range['size']!==$total || !hash_equals($validator,$range['validator']))) {
                throw new RuntimeException('State source changed. Restart the state.');
            }
            if ($initialize) {
                $header=llama_pbf_frame($range['data'],0);
                if (!$header || $header['type']!=='OSMHeader') throw new RuntimeException('Invalid state source header.');
                $date=llama_pbf_snapshot_date(llama_pbf_unblob($header['blob']));
                $slice=['offset'=>$header['length'],'row_offset'=>0,'rows'=>[]];
            } else {
                $slice=llama_osm_browser_slice($range['data'],$offset,$total,$skip);
                if ($slice['offset']===$offset && $slice['row_offset']===$skip && !$slice['rows']) {
                    $range=$fetch($region,$offset,8454148,$validator);
                    if ($range['size']!==$total || !hash_equals($validator,$range['validator'])) throw new RuntimeException('State source changed. Restart the state.');
                    $slice=llama_osm_browser_slice($range['data'],$offset,$total,$skip);
                    if ($slice['offset']===$offset && $slice['row_offset']===$skip && !$slice['rows']) throw new RuntimeException('Source block exceeds the bounded import size.');
                }
            }
        }

        // Do not hold a database transaction during downloads or decoding.
        $db->beginTransaction();
        $current=llama_osm_browser_read_locked($db,$job);
        $clear=$db->prepare('DELETE FROM osm_resource_import_stage WHERE region=?');
        if ($initialize) {
            $old=$db->prepare("SELECT MAX(snapshot_date) FROM osm_nearest_services WHERE region=? AND osm_type='node'");
            $old->execute([$region]);
            if ((string)$old->fetchColumn()>$date) throw new RuntimeException('A newer point snapshot is already stored.');
            $clear->execute([$region]);
            $db->prepare("UPDATE osm_resource_import_jobs SET status='importing',import_mode='browser',
                snapshot_date=?,source_bytes=?,source_offset=?,source_row_offset=0,source_validator=?,
                received_rows=0,expected_rows=0,next_chunk=0,message='Scanning state points in browser batches' WHERE region=? AND batch_id=?")
                ->execute([$date,$range['size'],$slice['offset'],$range['validator'],$region,$batch]);
        } elseif ($finished) {
            $count=$db->prepare("SELECT COUNT(*) FROM osm_resource_import_stage WHERE region=? AND osm_type='node'");
            $count->execute([$region]);
            $newCount=(int)$count->fetchColumn();
            if ($newCount<1 || $newCount!==(int)$current['received_rows'] || $newCount>$maxRows) throw new RuntimeException('Incomplete or oversized point snapshot; existing data retained.');
            $old=$db->prepare("SELECT COUNT(*) AS total,MAX(snapshot_date) AS snapshot_date FROM osm_nearest_services WHERE region=? AND osm_type='node'");
            $old->execute([$region]);
            $previous=$old->fetch(PDO::FETCH_ASSOC);
            if ((int)$previous['total']>$maxRows || (string)$previous['snapshot_date']>$current['snapshot_date']) {
                throw new RuntimeException('Existing point snapshot exceeds the replacement guard or is newer.');
            }
            // Only points are refreshed by this fallback. Existing area
            // records from the full extractor stay intact, even if older.
            $db->prepare("DELETE FROM osm_nearest_services WHERE region=? AND osm_type='node'")->execute([$region]);
            $db->prepare("INSERT INTO osm_nearest_services (region,osm_type,osm_id,service_type,name,latitude,longitude,snapshot_date)
                SELECT region,osm_type,osm_id,service_type,name,latitude,longitude,snapshot_date
                FROM osm_resource_import_stage WHERE region=? AND osm_type='node'")->execute([$region]);
            $clear->execute([$region]);
            $db->prepare("UPDATE osm_resource_import_jobs SET status='complete',expected_rows=received_rows,
                message='Point import complete; existing mapped areas retained' WHERE region=? AND batch_id=?")->execute([$region,$batch]);
        } else {
            $rows=$slice['rows'];
            if ((int)$current['received_rows']+count($rows)>$maxRows) throw new RuntimeException('State exceeds the configured point import limit; existing data retained.');
            $insert=$db->prepare('INSERT INTO osm_resource_import_stage
                (region,osm_type,osm_id,service_type,name,latitude,longitude,snapshot_date) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($rows as $row) $insert->execute([$region,...llama_osm_resource_row($row),$current['snapshot_date']]);
            $db->prepare("UPDATE osm_resource_import_jobs SET source_offset=?,source_row_offset=?,
                received_rows=received_rows+?,message='Scanning state points in browser batches' WHERE region=? AND batch_id=?")
                ->execute([$slice['offset'],$slice['row_offset'],count($rows),$region,$batch]);
        }
        $stmt=$db->prepare('SELECT * FROM osm_resource_import_jobs WHERE region=?');
        $stmt->execute([$region]);
        $result=$stmt->fetch(PDO::FETCH_ASSOC);
        $db->commit();
        return ['done'=>false,'job'=>llama_osm_browser_public($result)];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($job && !$e instanceof LlamaOsmBrowserRetry && !$e instanceof PDOException) {
            // Keep the previous published snapshot. Restart clears staging
            // only after the next source header has been validated.
            $db->prepare("UPDATE osm_resource_import_jobs SET status='error',message=?
                WHERE region=? AND batch_id=? AND status IN ('queued','importing')")
                ->execute([mb_substr($e->getMessage(),0,255),$job['region'],$job['batch_id']]);
        }
        throw $e;
    } finally {
        $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
}
