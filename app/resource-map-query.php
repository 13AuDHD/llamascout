<?php
declare(strict_types=1);

require_once __DIR__ . '/resources.php';

const LLAMA_RESOURCE_MAP_LIMIT = 250;
const LLAMA_RESOURCE_MAP_MIN_ZOOM = 7;
const LLAMA_RESOURCE_MAP_DETAIL_ZOOM = 10;

function llama_resource_map_query_input(array $input): array
{
    $numbers=[];
    foreach (['south','north','west','east','zoom'] as $key) {
        $value=$input[$key] ?? null;
        if (!is_scalar($value) || !is_numeric($value) || !is_finite((float)$value)) {
            throw new InvalidArgumentException('Valid map bounds and zoom are required.');
        }
        $numbers[$key]=(float)$value;
    }
    ['south'=>$south,'north'=>$north,'west'=>$west,'east'=>$east,'zoom'=>$zoom]=$numbers;
    if ($south < -90 || $north > 90 || $south >= $north || $east <= $west
        || abs($west)>100000 || abs($east)>100000 || $zoom<0 || $zoom>20) {
        throw new InvalidArgumentException('Invalid map bounds or zoom.');
    }
    // Leaflet bounds are continuous, even across a world copy.
    $width=$east-$west;
    // Bound the SQL work even for forged high-zoom requests. Allow a much
    // wider viewport than a typical desktop or mobile map at each zoom.
    if ($zoom>=7 && ($width>min(180,120 / (2 ** (floor($zoom)-7))) || $north-$south>min(180,120 / (2 ** (floor($zoom)-7))))) {
        throw new InvalidArgumentException('Map bounds are too wide for the selected zoom.');
    }
    if ($width>=360) { $west=-180.0; $east=180.0; }
    else {
        $west=fmod(fmod($west+180,360)+360,360)-180;
        $east=$west+$width;
    }
    $raw=$input['services'] ?? '';
    $search=$input['search'] ?? '';
    if (!is_string($raw) || strlen($raw)>512 || !is_string($search) || mb_strlen($search)>120) {
        throw new InvalidArgumentException('Invalid Resource filters.');
    }
    $services=$raw===''?[]:array_values(array_unique(explode(',',$raw)));
    foreach ($services as $service) {
        if (!isset(llama_resource_service_types()[$service])) throw new InvalidArgumentException('Unknown Resource service.');
    }
    return ['south'=>$south,'north'=>$north,'west'=>$west,'east'=>$east,'zoom'=>(int)floor($zoom),
        'services'=>$services,'search'=>mb_strtolower(trim($search))];
}

/** Same filters for counts and markers. EXISTS avoids duplicates for multi-service Resources. */
function llama_resource_map_where(PDO $db,array $q): array
{
    $where=['r.is_active=1','r.latitude BETWEEN ? AND ?','NOT (r.latitude=0 AND r.longitude=0)'];
    $params=[$q['south'],$q['north']];
    if ($q['east']>180) {
        $where[]='(r.longitude>=? OR r.longitude<=?)';
        array_push($params,$q['west'],$q['east']-360);
    } else {
        $where[]='r.longitude BETWEEN ? AND ?';
        array_push($params,$q['west'],$q['east']);
    }
    if (llama_resource_parent_place_ready($db)) $where[]='r.parent_place_id IS NULL';
    $in=implode(',',array_fill(0,count($q['services']),'?'));
    $where[]="EXISTS (SELECT 1 FROM resource_services ms WHERE ms.resource_id=r.id AND ms.is_active=1 AND ms.service_type IN ($in))";
    array_push($params,...$q['services']);
    if ($q['search']!=='') {
        // Literal substring search. SQL wildcard characters are not patterns.
        $search=$q['search'];
        $parts=["LOCATE(?,LOWER(CONCAT_WS(' ',r.name,r.resource_type,r.street_address,r.city,r.state_region,r.postal_code)))>0",
            "EXISTS (SELECT 1 FROM resource_services ss WHERE ss.resource_id=r.id AND ss.is_active=1 AND LOCATE(?,LOWER(ss.service_type))>0)"];
        array_push($params,$search,$search);
        $types=array_keys(array_filter(llama_resource_types(),static fn($label)=>str_contains(mb_strtolower($label),$search)));
        if ($types) { $parts[]='r.resource_type IN ('.implode(',',array_fill(0,count($types),'?')).')'; array_push($params,...$types); }
        $types=array_keys(array_filter(llama_resource_service_types(),static fn($label)=>str_contains(mb_strtolower($label),$search)));
        if ($types) {
            $parts[]='EXISTS (SELECT 1 FROM resource_services sl WHERE sl.resource_id=r.id AND sl.is_active=1 AND sl.service_type IN ('.implode(',',array_fill(0,count($types),'?')).'))';
            array_push($params,...$types);
        }
        $where[]='('.implode(' OR ',$parts).')';
    }
    return [implode(' AND ',$where),$params];
}

/** Map-only payload: no nationwide hydration or per-Resource service/hour/gallery queries. */
function llama_resource_map_query(PDO $db,array $input): array
{
    $q=llama_resource_map_query_input($input);
    $empty=['mode'=>'hidden','resources'=>[],'clusters'=>[],'truncated'=>false,'count'=>0,'limit'=>LLAMA_RESOURCE_MAP_LIMIT];
    if (!$q['services'] || $q['zoom']<LLAMA_RESOURCE_MAP_MIN_ZOOM) return $empty;
    [$where,$params]=llama_resource_map_where($db,$q);
    $limit=LLAMA_RESOURCE_MAP_LIMIT+1;
    if ($q['zoom']<LLAMA_RESOURCE_MAP_DETAIL_ZOOM) {
        $cell=1.5 / (2 ** ($q['zoom']-LLAMA_RESOURCE_MAP_MIN_ZOOM));
        $sql="SELECT AVG(r.latitude) AS latitude,AVG(r.longitude) AS longitude,COUNT(*) AS total
            FROM resources r WHERE $where
            GROUP BY FLOOR(r.latitude / ?),FLOOR(r.longitude / ?)
            ORDER BY total DESC,MIN(r.id) LIMIT $limit";
        $stmt=$db->prepare($sql);
        $stmt->execute([...$params,$cell,$cell]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $truncated=count($rows)>LLAMA_RESOURCE_MAP_LIMIT;
        $clusters=[];
        foreach (array_slice($rows,0,LLAMA_RESOURCE_MAP_LIMIT) as $row) {
            $clusters[]=['latitude'=>(float)$row['latitude'],'longitude'=>(float)$row['longitude'],'count'=>(int)$row['total']];
        }
        return array_replace($empty,['mode'=>'clusters','clusters'=>$clusters,'truncated'=>$truncated,'count'=>array_sum(array_column($clusters,'count'))]);
    }
    $centerLat=($q['south']+$q['north'])/2;
    $centerLon=($q['west']+$q['east'])/2;
    if ($centerLon>180) $centerLon-=360;
    $county=llama_resource_has_county($db)?'r.county':'NULL AS county';
    $sql="SELECT r.id,r.resource_type,r.marker_service_type,r.name,r.slug,r.latitude,r.longitude,
        r.street_address,r.city,$county,r.state_region,r.postal_code,r.country_code,r.hours_text,r.hours_mode,
        r.fixed_open_hour,r.fixed_close_hour,r.status,r.source_provider,r.source_updated_at,r.verified_at,
        (SELECT ri.src FROM resource_images ri WHERE ri.resource_id=r.id ORDER BY ri.is_featured DESC,ri.sort_order,ri.id LIMIT 1) AS image_src
        FROM resources r WHERE $where
        ORDER BY POW(r.latitude-?,2)+POW(LEAST(ABS(r.longitude-?),360-ABS(r.longitude-?))*?,2),r.id LIMIT $limit";
    $stmt=$db->prepare($sql);
    $stmt->execute([...$params,$centerLat,$centerLon,$centerLon,cos(deg2rad($centerLat))]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $truncated=count($rows)>LLAMA_RESOURCE_MAP_LIMIT;
    $rows=array_slice($rows,0,LLAMA_RESOURCE_MAP_LIMIT);
    $ids=array_column($rows,'id');
    $byId=[];
    if ($ids) {
        $stmt=$db->prepare('SELECT resource_id,service_type,status,fee_status FROM resource_services WHERE is_active=1 AND resource_id IN ('
            .implode(',',array_fill(0,count($ids),'?')).') ORDER BY resource_id,sort_order,service_type');
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $service) {
            $service['service_type_label']=llama_resource_service_type_label($service['service_type']);
            $byId[$service['resource_id']][]=$service;
        }
    }
    $resources=[];
    foreach ($rows as $row) {
        $row['services']=$byId[$row['id']] ?? [];
        $row['has_coordinates']=true;
        $row['resource_type_label']=llama_resource_type_label($row['resource_type']);
        $row['marker_service_effective_type']=$row['marker_service_type'] ?: ($row['services'][0]['service_type'] ?? 'other');
        $row['marker_service_label']=llama_resource_service_type_label($row['marker_service_effective_type']);
        $row['hours_text']=llama_resource_hours_summary($row);
        $row['featured_image']=$row['image_src'] ? ['src'=>$row['image_src']] : [];
        $resources[]=llama_resource_public_view($row);
    }
    return array_replace($empty,['mode'=>'markers','resources'=>$resources,'truncated'=>$truncated,'count'=>count($resources)]);
}
