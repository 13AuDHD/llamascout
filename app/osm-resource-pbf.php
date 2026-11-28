<?php
declare(strict_types=1);

// Minimal bounded OSM PBF point decoder for shared hosting. No shell,
// Python, whole-state downloads or third-party HTTP query service.
function llama_pbf_varint(string $data,int &$pos): int
{
    $result = 0;
    $length = strlen($data);
    for ($shift=0;$shift<=63;$shift+=7) {
        if ($pos >= $length) throw new RuntimeException('Truncated PBF varint.');
        $byte = ord($data[$pos++]);
        if ($shift===63 && ($byte & 127)>1) throw new RuntimeException('PBF varint overflow.');
        $result |= ($byte & 127) << $shift;
        if (($byte & 128)===0) return $result;
    }
    throw new RuntimeException('Invalid PBF varint.');
}

function llama_pbf_signed(int $value): int
{
    return (($value >> 1) & PHP_INT_MAX) ^ -($value & 1);
}

/** Yield field number, wire type and value without materializing packed arrays. */
function llama_pbf_fields(string $data): Generator
{
    $pos = 0;
    $length = strlen($data);
    while ($pos < $length) {
        $tag = llama_pbf_varint($data,$pos);
        $field = $tag >> 3;
        $wire = $tag & 7;
        if ($field<1) throw new RuntimeException('Invalid PBF field.');
        if ($wire===0) {
            yield [$field,$wire,llama_pbf_varint($data,$pos)];
        } elseif (in_array($wire,[1,2,5],true)) {
            $size = $wire===2 ? llama_pbf_varint($data,$pos) : ($wire===1?8:4);
            if ($size<0 || $size>$length-$pos) throw new RuntimeException('Truncated PBF field.');
            yield [$field,$wire,substr($data,$pos,$size)];
            $pos += $size;
        } else {
            throw new RuntimeException('Unsupported PBF wire type.');
        }
    }
}

/** One complete framed blob, or null if the range ends mid-frame. */
function llama_pbf_frame(string $data,int $offset): ?array
{
    $available = strlen($data)-$offset;
    if ($available<4) return null;
    $headerSize = unpack('N',substr($data,$offset,4))[1];
    if ($headerSize<1 || $headerSize>65536) throw new RuntimeException('Invalid PBF blob header size.');
    if ($available<4+$headerSize) return null;
    $type = null;
    $size = null;
    foreach (llama_pbf_fields(substr($data,$offset+4,$headerSize)) as [$field,$wire,$value]) {
        if ($field===1 && $wire===2) $type=$value;
        if ($field===3 && $wire===0) $size=$value;
    }
    if (!in_array($type,['OSMHeader','OSMData'],true) || !is_int($size) || $size<1 || $size>8388608) {
        throw new RuntimeException('Unsupported PBF frame or compressed block exceeds the shared-host safety limit.');
    }
    $length = 4+$headerSize+$size;
    if ($available<$length) return null;
    return ['type'=>$type,'length'=>$length,'blob'=>substr($data,$offset+4+$headerSize,$size)];
}

function llama_pbf_unblob(string $blob): string
{
    $raw = null;
    $zlib = null;
    $size = null;
    foreach (llama_pbf_fields($blob) as [$field,$wire,$value]) {
        if ($field===1 && $wire===2) $raw=$value;
        elseif ($field===2 && $wire===0) $size=$value;
        elseif ($field===3 && $wire===2) $zlib=$value;
    }
    $limit = 16777216;
    if ($raw!==null && $zlib===null && strlen($raw)<=$limit) return $raw;
    if ($raw!==null || $zlib===null || !is_int($size) || $size<1 || $size>$limit) {
        throw new RuntimeException('Unsupported PBF compression or oversized decoded block.');
    }
    $decoded = @gzuncompress($zlib,$limit);
    if ($decoded===false || strlen($decoded)!==$size) throw new RuntimeException('Invalid compressed PBF block.');
    return $decoded;
}

function llama_pbf_snapshot_date(string $header): string
{
    $features = [];
    $timestamp = null;
    foreach (llama_pbf_fields($header) as [$field,$wire,$value]) {
        if ($field===4 && $wire===2) $features[]=$value;
        if ($field===32 && $wire===0) $timestamp=$value;
    }
    if (!in_array('OsmSchema-V0.6',$features,true)
        || array_diff($features,['OsmSchema-V0.6','DenseNodes'])
        || !is_int($timestamp) || $timestamp<1 || $timestamp>time()+3600) {
        throw new RuntimeException('Unsupported PBF header or missing source snapshot timestamp.');
    }
    return gmdate('Y-m-d',$timestamp);
}

function llama_pbf_tag(array $strings,int $index): string
{
    if ($index<0 || !isset($strings[$index])) throw new RuntimeException('Invalid PBF string table index.');
    return $strings[$index];
}

/** Keep the point-only fallback in parity with the canonical Python tag rules. */
function llama_pbf_services(array $t): array
{
    if (in_array($t['access'] ?? '',['private','no','customers','permit','members','residents','employees'],true)
        || ($t['opening_hours'] ?? '')==='closed') return [];
    foreach (['disused','abandoned','construction','demolished'] as $key) {
        if (($t[$key] ?? '')==='yes') return [];
    }
    $amenity=$t['amenity'] ?? '';
    $shop=$t['shop'] ?? '';
    $types=[];
    if (in_array($t['place'] ?? '',['city','town','village'],true) && !empty($t['name'])) $types[]='town';
    if ($amenity==='fuel') $types[]='fuel';
    if ($amenity==='charging_station') $types[]='ev_charging';
    if (in_array($shop,['alcohol','wine'],true) || (($t['alcohol'] ?? '')==='yes' && in_array($shop,['supermarket','convenience','grocery'],true))) $types[]='alcohol_sales';
    if (($t['fuel:propane'] ?? '')==='yes' || ($t['propane'] ?? '')==='yes') $types[]='propane';
    if (in_array($shop,['supermarket','grocery'],true)) $types[]='groceries';
    if (($t['drinking_water'] ?? '')!=='no' && ($amenity==='drinking_water' || ($t['drinking_water'] ?? '')==='yes')) $types[]='potable_water';
    if ($amenity==='toilets') $types[]='toilet';
    if ($amenity==='sanitary_dump_station') $types[]='dump_station';
    if ($amenity==='shower') $types[]='shower';
    if ($shop==='laundry' && ($t['self_service'] ?? '')!=='no') $types[]='laundry';
    if ($amenity==='waste_disposal') $types[]='trash';
    if ($amenity==='hospital' || ($t['healthcare'] ?? '')==='hospital') $types[]='hospital';
    sort($types,SORT_STRING);
    return $types;
}

function llama_pbf_point_rows(int $id,int $lat,int $lon,array $tags,int $granularity,int $latOffset,int $lonOffset): Generator
{
    $labels=['town'=>'Town','fuel'=>'Fuel station','ev_charging'=>'EV charging','alcohol_sales'=>'Liquor store',
        'propane'=>'Propane','groceries'=>'Grocery store','potable_water'=>'Drinking water','toilet'=>'Public toilet',
        'hospital'=>'Hospital','dump_station'=>'RV dump station','shower'=>'Public shower','laundry'=>'Laundry','trash'=>'Waste disposal'];
    $latitude=round(($latOffset+$lat*$granularity)/1000000000,7);
    $longitude=round(($lonOffset+$lon*$granularity)/1000000000,7);
    if ($id<1 || !is_finite($latitude) || !is_finite($longitude) || abs($latitude)>90 || abs($longitude)>180 || ($latitude==0 && $longitude==0)) {
        throw new RuntimeException('Invalid mapped point coordinates.');
    }
    foreach (llama_pbf_services($tags) as $type) {
        $name=trim($tags['name'] ?? '');
        if ($name==='') $name=trim($tags['brand'] ?? '');
        if ($name==='') $name=trim($tags['operator'] ?? '');
        if ($name==='') $name=$labels[$type];
        yield ['osm_type'=>'node','osm_id'=>$id,'service_type'=>$type,'name'=>mb_substr($name,0,255),
            'latitude'=>$latitude,'longitude'=>$longitude];
    }
}

function llama_pbf_dense_rows(string $dense,array $strings,int $granularity,int $latOffset,int $lonOffset): Generator
{
    $packed=[];
    foreach (llama_pbf_fields($dense) as [$field,$wire,$value]) {
        if ($wire===2 && in_array($field,[1,8,9,10],true)) $packed[$field]=($packed[$field] ?? '').$value;
    }
    $ids=$packed[1] ?? '';
    $lats=$packed[8] ?? '';
    $lons=$packed[9] ?? '';
    $pairs=$packed[10] ?? '';
    // Empty keys_vals is the documented compact form for all-untagged nodes.
    if ($pairs==='') return;
    $ip=$ap=$op=$tp=0;
    $id=$lat=$lon=0;
    while ($ip<strlen($ids)) {
        $id+=llama_pbf_signed(llama_pbf_varint($ids,$ip));
        $lat+=llama_pbf_signed(llama_pbf_varint($lats,$ap));
        $lon+=llama_pbf_signed(llama_pbf_varint($lons,$op));
        $tags=[];
        while (true) {
            $key=llama_pbf_varint($pairs,$tp);
            if ($key===0) break;
            $val=llama_pbf_varint($pairs,$tp);
            $tags[llama_pbf_tag($strings,$key)]=llama_pbf_tag($strings,$val);
        }
        if ($tags) yield from llama_pbf_point_rows($id,$lat,$lon,$tags,$granularity,$latOffset,$lonOffset);
    }
    if ($ap!==strlen($lats) || $op!==strlen($lons) || $tp!==strlen($pairs)) throw new RuntimeException('Misaligned dense node fields.');
}

function llama_pbf_node_rows(string $node,array $strings,int $granularity,int $latOffset,int $lonOffset): Generator
{
    $id=$lat=$lon=null;
    $keys=$vals='';
    foreach (llama_pbf_fields($node) as [$field,$wire,$value]) {
        if ($wire===0 && $field===1) $id=llama_pbf_signed($value);
        elseif ($wire===0 && $field===8) $lat=llama_pbf_signed($value);
        elseif ($wire===0 && $field===9) $lon=llama_pbf_signed($value);
        elseif ($wire===2 && $field===2) $keys.=$value;
        elseif ($wire===2 && $field===3) $vals.=$value;
    }
    if ($keys==='') return;
    if ($id===null || $lat===null || $lon===null) throw new RuntimeException('Incomplete PBF node.');
    $kp=$vp=0;
    $tags=[];
    while ($kp<strlen($keys)) {
        $key=llama_pbf_varint($keys,$kp);
        $val=llama_pbf_varint($vals,$vp);
        $tags[llama_pbf_tag($strings,$key)]=llama_pbf_tag($strings,$val);
    }
    if ($vp!==strlen($vals)) throw new RuntimeException('Misaligned node tags.');
    yield from llama_pbf_point_rows($id,$lat,$lon,$tags,$granularity,$latOffset,$lonOffset);
}

/**
 * A block cannot contain a matching point if its string table does not
 * contain any possible classifier key/value pair. False positives are fine:
 * the ordinary classifier still decides which records qualify.
 */
function llama_pbf_block_may_match(array $strings): bool
{
    static $candidates = [
        'amenity'=>['fuel','charging_station','drinking_water','toilets','sanitary_dump_station','shower','waste_disposal','hospital'],
        'shop'=>['alcohol','wine','supermarket','convenience','grocery','laundry'],
        'place'=>['city','town','village'],
        'healthcare'=>['hospital'],
        'fuel:propane'=>['yes'],
        'propane'=>['yes'],
        'drinking_water'=>['yes'],
    ];
    $present=array_fill_keys($strings,true);
    foreach ($candidates as $key=>$values) {
        if (!isset($present[$key])) continue;
        foreach ($values as $value) if (isset($present[$value])) return true;
    }
    return false;
}

/** Ways and relations require a geometry index; do not guess their positions. */
function llama_pbf_block_rows(string $block): Generator
{
    $strings=[];
    $groups=[];
    $granularity=100;
    $latOffset=$lonOffset=0;
    foreach (llama_pbf_fields($block) as [$field,$wire,$value]) {
        if ($field===1 && $wire===2) {
            foreach (llama_pbf_fields($value) as [$sf,$sw,$sv]) if ($sf===1 && $sw===2) $strings[]=$sv;
        } elseif ($field===2 && $wire===2) $groups[]=$value;
        elseif ($field===17 && $wire===0) $granularity=$value;
        elseif ($field===19 && $wire===0) $latOffset=$value;
        elseif ($field===20 && $wire===0) $lonOffset=$value;
    }
    if (($strings[0] ?? null)!=='' || $granularity<1 || $granularity>1000000000) throw new RuntimeException('Invalid PBF primitive block.');
    if (!llama_pbf_block_may_match($strings)) return;
    foreach ($groups as $group) {
        foreach (llama_pbf_fields($group) as [$field,$wire,$value]) {
            if ($wire!==2) continue;
            if ($field===1) yield from llama_pbf_node_rows($value,$strings,$granularity,$latOffset,$lonOffset);
            elseif ($field===2) yield from llama_pbf_dense_rows($value,$strings,$granularity,$latOffset,$lonOffset);
        }
    }
}
