<?php
declare(strict_types=1);

require_once __DIR__ . '/osm-resource-import.php';
require_once __DIR__ . '/resources.php';

const LLAMA_OSM_PUBLICATION_LIMIT = 250;
const LLAMA_OSM_PUBLICATION_BATCH = 25;

function llama_osm_publication_categories(): array
{
    return [
        'potable_water'=>'water_station','dump_station'=>'dump_station','shower'=>'shower_facility',
        'laundry'=>'laundry','propane'=>'propane_fill','toilet'=>'restroom_facility','trash'=>'waste_disposal',
    ];
}

function llama_osm_publication_ready(PDO $db): bool
{
    try {
        $db->query('SELECT osm_type,osm_id,resource_id,source_snapshot,disposition FROM osm_resource_publications LIMIT 0');
        return true;
    } catch (PDOException $e) {
        if ($e->getCode()==='42S02') return false;
        throw $e;
    }
}

function llama_osm_publication_counts(PDO $db): array
{
    $result=['published'=>0,'review'=>0];
    foreach ($db->query('SELECT disposition,COUNT(*) AS total FROM osm_resource_publications GROUP BY disposition')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[$row['disposition']]=(int)$row['total'];
    }
    return $result;
}

/** One regional source feature, not one pin per service. Exclude prior decisions globally. */
function llama_osm_publication_candidates(PDO $db,string $region,string $category=''): array
{
    if (!isset(llama_osm_resource_states()[$region])) throw new InvalidArgumentException('Select a valid state.');
    $categories=llama_osm_publication_categories();
    if ($category!=='' && !isset($categories[$category])) throw new InvalidArgumentException('Select a priority service.');
    $keys=array_keys($categories);
    $in=implode(',',array_fill(0,count($keys),'?'));
    $sql="SELECT s.osm_type,s.osm_id,MIN(s.name) AS name,MIN(s.latitude) AS latitude,MIN(s.longitude) AS longitude,
        MAX(s.snapshot_date) AS snapshot_date,GROUP_CONCAT(DISTINCT s.service_type ORDER BY s.service_type) AS services
        FROM osm_nearest_services s
        WHERE s.region=? AND s.service_type IN ($in)
        AND NOT EXISTS (SELECT 1 FROM osm_resource_publications p WHERE p.osm_type=s.osm_type AND p.osm_id=s.osm_id)
        GROUP BY s.osm_type,s.osm_id";
    $params=[$region,...$keys];
    if ($category!=='') { $sql.=' HAVING SUM(s.service_type=?)>0'; $params[]=$category; }
    $sql.=" ORDER BY CASE
        WHEN SUM(s.service_type='potable_water')>0 THEN 1
        WHEN SUM(s.service_type='dump_station')>0 THEN 2
        WHEN SUM(s.service_type='shower')>0 THEN 3
        WHEN SUM(s.service_type='laundry')>0 THEN 4
        WHEN SUM(s.service_type='propane')>0 THEN 5
        WHEN SUM(s.service_type='toilet')>0 THEN 6 ELSE 7 END,s.osm_type,s.osm_id LIMIT ".LLAMA_OSM_PUBLICATION_BATCH;
    $stmt=$db->prepare($sql);
    $stmt->execute($params);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['region']=$region;
        $row['services']=array_values(array_intersect($keys,explode(',',$row['services'])));
    }
    unset($row);
    return $rows;
}

/** Conservative review flag; never auto-merge or change an existing Resource. */
function llama_osm_publication_conflict(PDO $db,array $candidate): ?string
{
    $lat=(float)$candidate['latitude'];
    $lon=(float)$candidate['longitude'];
    $external=$candidate['osm_type'].'/'.$candidate['osm_id'];
    $existing=$db->prepare("SELECT id FROM resources WHERE source_provider='OpenStreetMap' AND source_external_id=? LIMIT 1");
    $existing->execute([$external]);
    if ($existing->fetchColumn()) return 'An existing Resource already has this OSM identity.';
    $dy=0.0014;
    $dx=min(180.0,$dy/max(0.001,cos(deg2rad($lat))));
    $west=$lon-$dx;
    $east=$lon+$dx;
    $where='latitude BETWEEN ? AND ?';
    $params=[max(-90,$lat-$dy),min(90,$lat+$dy)];
    if ($west<-180) { $where.=' AND (longitude>=? OR longitude<=?)'; array_push($params,$west+360,$east); }
    elseif ($east>180) { $where.=' AND (longitude>=? OR longitude<=?)'; array_push($params,$west,$east-360); }
    else { $where.=' AND longitude BETWEEN ? AND ?'; array_push($params,$west,$east); }
    // Include inactive records and linked Facilities so a new pin cannot
    // silently resurrect a removed location or duplicate a known Facility.
    $near=$db->prepare('SELECT id FROM resources WHERE '.$where.' LIMIT 1');
    $near->execute($params);
    if ($near->fetchColumn()) return 'Nearby Resource or Facility; review possible duplicate.';
    $place=$db->prepare('SELECT id FROM places WHERE '.$where.' LIMIT 1');
    $place->execute($params);
    if ($place->fetchColumn()) return 'Nearby Place; review whether this belongs to a Facility.';
    return null;
}

function llama_osm_publication_baseline(array $row): array
{
    return [
        'osm_type'=>$row['osm_type'],'osm_id'=>(int)$row['osm_id'],'region'=>$row['region'],
        'name'=>$row['name'],'latitude'=>(float)$row['latitude'],'longitude'=>(float)$row['longitude'],
        'snapshot_date'=>$row['snapshot_date'],'services'=>array_values($row['services']),
    ];
}

/** Real native Resource with existing page, service and Scout update flows. */
function llama_osm_publication_create(PDO $db,array $candidate,int $actor): int
{
    $services=$candidate['services'];
    $primary=$services[0] ?? '';
    $type=llama_osm_publication_categories()[$primary] ?? null;
    if (!$type || !llama_resource_coordinates_valid($candidate['latitude'],$candidate['longitude'])) throw new RuntimeException('Invalid candidate.');
    $state=llama_osm_resource_states()[$candidate['region']];
    $identity=$candidate['osm_type'].'/'.$candidate['osm_id'];
    // Stable source suffix only for repeated names; preserve the original
    // source name independently in the publication baseline.
    $name=mb_substr(trim($candidate['name']),0,160);
    if ($name==='') throw new RuntimeException('Missing source name.');
    $check=$db->prepare('SELECT id FROM resources WHERE name=? OR slug=? LIMIT 1');
    $slug=llama_resource_slug($name);
    $check->execute([$name,$slug]);
    if ($slug==='' || $check->fetchColumn()) {
        $suffix=' ('.$state.' '.$candidate['osm_type'].' '.$candidate['osm_id'].')';
        $name=mb_substr($name,0,160-mb_strlen($suffix)).$suffix;
        $slug=llama_resource_slug($name);
    }
    $check->execute([$name,$slug]);
    if ($slug==='' || $check->fetchColumn()) throw new RuntimeException('Resource name or URL collision.');
    $insert=$db->prepare("INSERT INTO resources
        (resource_type,marker_service_type,name,slug,latitude,longitude,state_region,country_code,hours_mode,status,
         source_provider,source_external_id,source_url,source_updated_at,verified_at,created_by,updated_by,is_active)
        VALUES (?,?,?,?,?,?,?,'US','unknown','unknown','OpenStreetMap',?,?,?,NULL,?,?,1)");
    $insert->execute([$type,$primary,$name,$slug,$candidate['latitude'],$candidate['longitude'],$state,$identity,
        'https://www.openstreetmap.org/'.$identity,$candidate['snapshot_date'].' 00:00:00',null,$actor ?: null]);
    $id=(int)$db->lastInsertId();
    if ($id<1) throw new RuntimeException('Resource creation failed.');
    $insertService=$db->prepare("INSERT INTO resource_services (resource_id,service_type,status,fee_status,sort_order,is_active)
        VALUES (?,?,'unknown','unknown',?,1)");
    foreach ($services as $order=>$service) $insertService->execute([$id,$service,$order]);
    return $id;
}

/** Explicit Admin approval, 25 candidates at a time, capped until map loading scales. */
function llama_osm_publication_publish(PDO $db,string $region,string $category,array $identities,int $actor): array
{
    if ($actor<1 || !$identities || count($identities)>LLAMA_OSM_PUBLICATION_BATCH) throw new InvalidArgumentException('Select up to 25 candidates.');
    foreach ($identities as $identity) {
        if (!is_string($identity) || !preg_match('~^(node|way|relation)/[1-9][0-9]{0,19}$~D',$identity)) {
            throw new InvalidArgumentException('Invalid source feature selection.');
        }
    }
    $selected=array_fill_keys($identities,true);
    $lockName='llama-osm-publication-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $lock=$db->prepare('SELECT GET_LOCK(?,0)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn()!==1) throw new RuntimeException('Another publication batch is running.');
    try {
        $db->beginTransaction();
        $counts=llama_osm_publication_counts($db);
        $remaining=max(0,LLAMA_OSM_PUBLICATION_LIMIT-$counts['published']);
        if (!$remaining) throw new RuntimeException('Pilot publication limit reached. Expand map loading before publishing more.');
        $rows=llama_osm_publication_candidates($db,$region,$category);
        $result=['published'=>0,'review'=>0];
        $decision=$db->prepare('INSERT INTO osm_resource_publications
            (osm_type,osm_id,region,source_snapshot,disposition,resource_id,reason) VALUES (?,?,?,?,?,?,?)');
        foreach ($rows as $row) {
            $identity=$row['osm_type'].'/'.$row['osm_id'];
            if (!isset($selected[$identity])) continue;
            if ($remaining===0) break;
            $reason=llama_osm_publication_conflict($db,$row);
            $id=null;
            if ($reason===null) {
                $id=llama_osm_publication_create($db,$row,$actor);
                $result['published']++;
                $remaining--;
            } else $result['review']++;
            $decision->execute([$row['osm_type'],$row['osm_id'],$region,
                json_encode(llama_osm_publication_baseline($row),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
                $id?'published':'review',$id,$reason]);
        }
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    } finally {
        $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
}
