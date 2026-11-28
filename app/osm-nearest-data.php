<?php
declare(strict_types=1);

const LLAMA_OSM_NEAREST_TYPES = ['town','fuel','ev_charging','alcohol_sales','propane','groceries','potable_water','toilet','hospital','dump_station','shower','laundry','trash'];

function llama_osm_nearest_region_valid(string $region): bool
{
    return preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/D', $region) === 1;
}

function llama_osm_nearest_ready(PDO $db): bool
{
    try {
        return $db->query('SELECT 1 FROM osm_nearest_services LIMIT 1')->fetchColumn() !== false;
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') return false;
        throw $e;
    }
}

function llama_osm_nearest_header(string $region, string $date): array
{
    return ['format'=>'llama-osm-nearest-v1', 'region'=>$region, 'snapshot_date'=>$date,
        'attribution'=>'© OpenStreetMap contributors', 'license'=>'ODbL-1.0',
        'license_url'=>'https://www.openstreetmap.org/copyright'];
}

function llama_osm_nearest_decode_row(string $line): array
{
    $row = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($row) || array_is_list($row)) throw new RuntimeException('Expected a JSON object.');
    return $row;
}

/** Atomic replacement of one complete regional snapshot, never Scout data. */
function llama_osm_nearest_import(PDO $db, $stream): int
{
    $line = fgets($stream, 65536);
    if ($line === false) throw new RuntimeException('Missing snapshot header.');
    $header = llama_osm_nearest_decode_row($line);
    $region = (string) ($header['region'] ?? '');
    $date = (string) ($header['snapshot_date'] ?? '');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (($header['format'] ?? '') !== 'llama-osm-nearest-v1'
        || ($header['license'] ?? '') !== 'ODbL-1.0'
        || !llama_osm_nearest_region_valid($region)
        || !$parsed || $parsed->format('Y-m-d') !== $date || $date > gmdate('Y-m-d')) {
        throw new RuntimeException('Invalid OSM snapshot metadata.');
    }
    $lockName = 'llama-osm-' . substr(hash('sha256', (string) $db->query('SELECT DATABASE()')->fetchColumn() . ':' . $region), 0, 40);
    $lock = $db->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) throw new RuntimeException('Another import is running for this region.');
    try {
        $db->beginTransaction();
        $previous = $db->prepare('SELECT MAX(snapshot_date) FROM osm_nearest_services WHERE region = ?');
        $previous->execute([$region]);
        $oldDate = $previous->fetchColumn();
        if (is_string($oldDate) && $oldDate > $date) throw new RuntimeException('Refusing to replace newer regional data.');
        $delete = $db->prepare('DELETE FROM osm_nearest_services WHERE region = ?');
        $delete->execute([$region]);
        $insert = $db->prepare('INSERT INTO osm_nearest_services
            (region,osm_type,osm_id,service_type,name,latitude,longitude,snapshot_date)
            VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE name=VALUES(name),latitude=VALUES(latitude),longitude=VALUES(longitude)');
        $count = 0;
        $ended = false;
        while (($line = fgets($stream, 65536)) !== false) {
            if (trim($line) === '') continue;
            if ($ended) throw new RuntimeException('Unexpected content after snapshot trailer.');
            $row = llama_osm_nearest_decode_row($line);
            if (($row['end'] ?? false) === true) {
                if (!is_int($row['count'] ?? null) || $row['count'] !== $count || $count === 0) {
                    throw new RuntimeException('Incomplete or empty snapshot.');
                }
                $ended = true;
                continue;
            }
            $kind = $row['osm_type'] ?? null;
            $id = $row['osm_id'] ?? null;
            $service = $row['service_type'] ?? null;
            $name = $row['name'] ?? null;
            $lat = $row['latitude'] ?? null;
            $lng = $row['longitude'] ?? null;
            if (!in_array($kind,['node','way','relation'],true) || !is_int($id) || $id < 1
                || !in_array($service, LLAMA_OSM_NEAREST_TYPES,true)
                || !is_string($name) || trim($name) === '' || mb_strlen($name) > 255
                || (!is_int($lat) && !is_float($lat)) || (!is_int($lng) && !is_float($lng))
                || !is_finite((float)$lat) || !is_finite((float)$lng)
                || abs($lat)>90 || abs($lng)>180 || ($lat==0 && $lng==0)) {
                throw new RuntimeException('Invalid OSM service row at record ' . ($count+1));
            }
            $insert->execute([$region,$kind,$id,$service,$name,$lat,$lng,$date]);
            $count++;
        }
        if (!$ended) throw new RuntimeException('Snapshot trailer missing; import rolled back.');
        $db->commit();
        return $count;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    } finally {
        $release = $db->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}

/** Regional public download metadata, no proprietary Resource rows. */
function llama_osm_nearest_regions(PDO $db): array
{
    if (!llama_osm_nearest_ready($db)) return [];
    return $db->query('SELECT region,MAX(snapshot_date) AS snapshot_date,COUNT(*) AS count
        FROM osm_nearest_services GROUP BY region ORDER BY region')->fetchAll(PDO::FETCH_ASSOC);
}
