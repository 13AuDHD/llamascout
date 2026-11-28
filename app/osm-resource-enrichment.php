<?php
declare(strict_types=1);
require_once __DIR__ . '/osm-resource-browser.php';

function llama_enrichment_ready(PDO $db): bool {
    try {
        $db->query('SELECT source_offset FROM osm_resource_enrichment_jobs LIMIT 0');
        $db->query('SELECT tags FROM osm_resource_enrichment_facts LIMIT 0');
        $db->query('SELECT resource_id FROM osm_resource_publications LIMIT 0');
        return true;
    } catch (PDOException $e) { return false; }
}

/** Only business/location attributes; no contributor information or arbitrary tags. */
function llama_enrichment_tags(array $tags): array {
    $allowed=['name','brand','operator','addr:housenumber','addr:street','addr:full','addr:city','addr:town','addr:village','addr:postcode','addr:state','addr:country','addr:county','contact:phone','phone','contact:website','website','opening_hours','fee','access','wheelchair','description','drinking_water','toilets','shower','sanitary_dump_station','fuel:propane','propane','self_service','payment:cash','payment:credit_cards','payment:debit_cards','disused','abandoned','construction','demolished'];
    $result=[];
    foreach ($allowed as $key) {
        $value=$tags[$key] ?? '';
        if (is_string($value) && trim($value)!=='') $result[$key]=mb_substr(trim($value),0,2000);
    }
    return $result;
}

function llama_enrichment_decode_tags(string $keys,string $vals,array $strings): array {
    $kp=$vp=0; $tags=[];
    while ($kp<strlen($keys)) {
        $key=llama_pbf_tag($strings,llama_pbf_varint($keys,$kp));
        $tags[$key]=llama_pbf_tag($strings,llama_pbf_varint($vals,$vp));
    }
    if ($vp!==strlen($vals)) throw new RuntimeException('Misaligned object tags.');
    return llama_enrichment_tags($tags);
}

/** Exact identities only. Ways/relations need no geometry reconstruction for tag retrieval. */
function llama_enrichment_block(string $block,array $wanted): Generator {
    $strings=[]; $groups=[]; $granularity=100; $latOffset=$lonOffset=0;
    foreach (llama_pbf_fields($block) as [$field,$wire,$value]) {
        if ($field===1 && $wire===2) {
            foreach (llama_pbf_fields($value) as [$sf,$sw,$sv]) if ($sf===1 && $sw===2) $strings[]=$sv;
        } elseif ($field===2 && $wire===2) $groups[]=$value;
        elseif ($field===17 && $wire===0) $granularity=$value;
        elseif ($field===19 && $wire===0) $latOffset=$value;
        elseif ($field===20 && $wire===0) $lonOffset=$value;
    }
    if (($strings[0] ?? null)!=='' || $granularity<1 || $granularity>1000000000) throw new RuntimeException('Invalid primitive block.');
    foreach ($groups as $group) foreach (llama_pbf_fields($group) as [$field,$wire,$value]) {
        if ($wire!==2 || !in_array($field,[1,2,3,4],true)) continue;
        if ($field===2) {
            if (empty($wanted['node'])) continue;
            $packed=[];
            foreach (llama_pbf_fields($value) as [$f,$w,$v]) if ($w===2 && in_array($f,[1,8,9,10],true)) $packed[$f]=($packed[$f] ?? '').$v;
            $ids=$packed[1] ?? ''; $lats=$packed[8] ?? ''; $lons=$packed[9] ?? ''; $pairs=$packed[10] ?? '';
            $ip=$ap=$op=$tp=0; $id=$lat=$lon=0;
            while ($ip<strlen($ids)) {
                $id+=llama_pbf_signed(llama_pbf_varint($ids,$ip));
                $lat+=llama_pbf_signed(llama_pbf_varint($lats,$ap));
                $lon+=llama_pbf_signed(llama_pbf_varint($lons,$op));
                $match=isset($wanted['node'][$id]); $tags=[];
                if ($pairs!=='') while (true) {
                    $k=llama_pbf_varint($pairs,$tp);
                    if ($k===0) break;
                    $v=llama_pbf_varint($pairs,$tp);
                    if ($match) $tags[llama_pbf_tag($strings,$k)]=llama_pbf_tag($strings,$v);
                }
                if ($match) yield ['type'=>'node','id'=>$id,'tags'=>llama_enrichment_tags($tags),
                    'latitude'=>($latOffset+$lat*$granularity)/1e9,'longitude'=>($lonOffset+$lon*$granularity)/1e9];
            }
            if ($ap!==strlen($lats) || $op!==strlen($lons) || $tp!==strlen($pairs)) throw new RuntimeException('Misaligned dense nodes.');
            continue;
        }
        $type=[1=>'node',3=>'way',4=>'relation'][$field];
        if (empty($wanted[$type])) continue;
        $id=$lat=$lon=null; $keys=$vals='';
        foreach (llama_pbf_fields($value) as [$f,$w,$v]) {
            if ($f===1 && $w===0) $id=$type==='node'?llama_pbf_signed($v):$v;
            elseif ($f===2 && $w===2) $keys.=$v;
            elseif ($f===3 && $w===2) $vals.=$v;
            elseif ($type==='node' && $f===8 && $w===0) $lat=llama_pbf_signed($v);
            elseif ($type==='node' && $f===9 && $w===0) $lon=llama_pbf_signed($v);
        }
        if ($id===null || !isset($wanted[$type][$id])) continue;
        if ($type==='node' && ($lat===null || $lon===null)) throw new RuntimeException('Incomplete node.');
        yield ['type'=>$type,'id'=>$id,'tags'=>llama_enrichment_decode_tags($keys,$vals,$strings),
            'latitude'=>$lat===null?null:($latOffset+$lat*$granularity)/1e9,
            'longitude'=>$lon===null?null:($lonOffset+$lon*$granularity)/1e9];
    }
}

function llama_enrichment_values(array $tags): array {
    $get=static function(string ...$keys) use ($tags): string {
        foreach ($keys as $key) if (!empty($tags[$key])) return $tags[$key];
        return '';
    };
    $street=$get('addr:street');
    $site=$get('contact:website','website');
    $country=strtoupper($get('addr:country'));
    $fields=['street_address'=>$street!==''?trim($get('addr:housenumber').' '.$street):$get('addr:full'),
        'city'=>$get('addr:city','addr:town','addr:village'),'county'=>$get('addr:county'),
        'postal_code'=>$get('addr:postcode'),'phone'=>$get('contact:phone','phone'),
        'website_url'=>preg_match('~^https?://~i',$site) && filter_var($site,FILTER_VALIDATE_URL)?$site:'',
        'country_code'=>preg_match('/^[A-Z]{2}$/D',$country)?$country:''];
    $limits=['street_address'=>255,'city'=>120,'county'=>120,'postal_code'=>32,'phone'=>64,'website_url'=>2000,'country_code'=>2];
    return array_filter($fields,static fn($v,$k)=>$v!=='' && mb_strlen($v)<=$limits[$k],ARRAY_FILTER_USE_BOTH);
}

/** Only exact source facts; do not set verification or infer missing address/fees. */
function llama_enrichment_apply(PDO $db,array $object,array $source,string $region,string $date): void {
    $tags=$object['tags']; $id=(int)$source['resource_id'];
    $prior=$db->prepare('SELECT snapshot_date FROM osm_resource_enrichment_facts WHERE osm_type=? AND osm_id=?');
    $prior->execute([$object['type'],$object['id']]);
    if ((string)$prior->fetchColumn()>$date) return;
    $stmt=$db->prepare('SELECT * FROM resources WHERE id=? FOR UPDATE');
    $stmt->execute([$id]); $r=$stmt->fetch(PDO::FETCH_ASSOC);
    $baseline=json_decode($source['source_snapshot'],true,32,JSON_THROW_ON_ERROR);
    $reason=null; $applied=[];
    if (!$r || !(int)$r['is_active'] || !empty($r['parent_place_id']) || $r['source_provider']!=='OpenStreetMap'
        || $r['source_external_id']!==$object['type'].'/'.$object['id']) $reason='Resource identity or status changed';
    elseif (!isset($baseline['latitude'],$baseline['longitude'])
        || abs((float)$r['latitude']-(float)$baseline['latitude'])>0.000001
        || abs((float)$r['longitude']-(float)$baseline['longitude'])>0.000001) $reason='Resource coordinates edited since import';
    elseif ($object['type']==='node' && (abs((float)$r['latitude']-$object['latitude'])>0.001
        || abs((float)$r['longitude']-$object['longitude'])>0.001)) $reason='OSM node moved; confirm location';
    elseif (in_array($tags['access'] ?? '',['private','no','customers','permit','members','residents','employees'],true)
        || ($tags['opening_hours'] ?? '')==='closed' || array_intersect_key(array_filter($tags,static fn($v)=>$v==='yes'),array_flip(['disused','abandoned','construction','demolished']))) $reason='Source indicates changed access or closure';
    else {
        $values=llama_enrichment_values($tags);
        $set=[]; $params=[];
        foreach ($values as $field=>$value) {
            if (trim((string)($r[$field] ?? ''))!=='') continue;
            $set[]=$field.'=?'; $params[]=$value; $applied[]=$field;
        }
        $hours=$tags['opening_hours'] ?? '';
        if (in_array($r['hours_mode'] ?? '',['','unknown',null],true) && trim((string)($r['hours_text'] ?? ''))==='') {
            if ($hours==='24/7') {
                $set[]="hours_mode='24_hours'"; $applied[]='hours_mode';
            } elseif (preg_match('/^(?:Mo-Su )?([01][0-9]|2[0-3]):00-([01][0-9]|2[0-3]):00$/D',$hours,$m) && $m[1]!==$m[2]) {
                $set[]="hours_mode='fixed_hours'"; $set[]='fixed_open_hour=?'; $set[]='fixed_close_hour=?';
                array_push($params,(int)$m[1],(int)$m[2]); $applied[]='hours_mode';
            } elseif ($hours!=='') $reason='Opening hours require review';
        }
        if ($set) {
            $params[]=$id;
            $db->prepare('UPDATE resources SET '.implode(',',$set).' WHERE id=?')->execute($params);
        }
        if (!$applied && !$reason) $reason='No additional blank fields could be filled';
    }
    $db->prepare('INSERT INTO osm_resource_enrichment_facts (osm_type,osm_id,region,snapshot_date,tags,applied_fields,review_reason)
        VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE region=VALUES(region),snapshot_date=VALUES(snapshot_date),tags=VALUES(tags),
        applied_fields=IF(JSON_LENGTH(VALUES(applied_fields))=0,applied_fields,VALUES(applied_fields)),review_reason=VALUES(review_reason)')
        ->execute([$object['type'],$object['id'],$region,$date,json_encode($tags,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE),
            json_encode($applied,JSON_THROW_ON_ERROR),$reason]);
}

function llama_enrichment_step(PDO $db,string $selection='',?callable $fetch=null): array {
    $fetch ??= 'llama_osm_browser_range';
    if (!llama_enrichment_ready($db)) throw new RuntimeException('Run the enrichment SQL migration first.');
    if ($selection!=='' && !isset(llama_osm_resource_states()[$selection])) throw new InvalidArgumentException('Invalid state.');
    // Same lock as browser/CLI imports.
    $lockName='llama-osm-local-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $lock=$db->prepare('SELECT GET_LOCK(?,0)'); $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn()!==1) throw new LlamaOsmBrowserRetry('Another state operation is active.');
    try {
        $query="SELECT p.region FROM osm_resource_publications p
            LEFT JOIN osm_resource_enrichment_jobs j ON j.region=p.region
            WHERE p.disposition='published' AND (j.status IS NULL OR j.status='scanning')";
        $params=[];
        if ($selection!=='') { $query.=' AND p.region=?'; $params[]=$selection; }
        $stmt=$db->prepare($query.' GROUP BY p.region ORDER BY p.region LIMIT 1'); $stmt->execute($params);
        $region=$stmt->fetchColumn();
        if (!$region) return ['done'=>true];
        $db->prepare('INSERT IGNORE INTO osm_resource_enrichment_jobs (region) VALUES (?)')->execute([$region]);
        $stmt=$db->prepare('SELECT * FROM osm_resource_enrichment_jobs WHERE region=?'); $stmt->execute([$region]); $job=$stmt->fetch(PDO::FETCH_ASSOC);
        $offset=(int)$job['source_offset']; $total=(int)$job['source_bytes']; $validator=(string)$job['source_validator'];
        $date=$job['snapshot_date'];
        $stmt=$db->prepare("SELECT osm_type,osm_id,resource_id,source_snapshot FROM osm_resource_publications WHERE region=? AND disposition='published'");
        $stmt->execute([$region]); $wanted=[];
        while ($row=$stmt->fetch(PDO::FETCH_ASSOC)) $wanted[$row['osm_type']][(int)$row['osm_id']]=$row;
        $range=$fetch($region,$offset,$offset===0?65536:8454148,$validator);
        if ($offset!==0 && ($range['size']!==$total || !hash_equals($validator,$range['validator']))) throw new RuntimeException('State source changed; restart this state.');
        $total=$range['size']; $validator=$range['validator']; $pos=0; $matches=[]; $start=microtime(true);
        if ($offset===0) {
            $header=llama_pbf_frame($range['data'],0);
            if (!$header || $header['type']!=='OSMHeader') throw new RuntimeException('Invalid state source header.');
            $date=llama_pbf_snapshot_date(llama_pbf_unblob($header['blob'])); $pos=$header['length'];
        } else {
            while ($pos<strlen($range['data'])) {
                $frame=llama_pbf_frame($range['data'],$pos);
                if (!$frame) {
                    if ($offset+strlen($range['data'])===$total) throw new RuntimeException('Truncated source frame.');
                    break;
                }
                if ($frame['type']!=='OSMData') throw new RuntimeException('Unexpected state source header.');
                foreach (llama_enrichment_block(llama_pbf_unblob($frame['blob']),$wanted) as $object) $matches[]=$object;
                $pos+=$frame['length'];
                if (count($matches)>=250 || microtime(true)-$start>=5) break;
            }
        }
        if ($pos===0) throw new RuntimeException('Source frame exceeds bounded scan size.');
        $next=$offset+$pos;
        $db->beginTransaction();
        foreach ($matches as $object) llama_enrichment_apply($db,$object,$wanted[$object['type']][$object['id']],$region,$date);
        $db->prepare('UPDATE osm_resource_enrichment_jobs SET source_offset=?,source_bytes=?,source_validator=?,snapshot_date=?,status=? WHERE region=?')
            ->execute([$next,$total,$validator,$date,$next===$total?'complete':'scanning',$region]);
        $db->commit();
        return ['done'=>false,'region'=>$region,'percent'=>min(100,(int)floor($next*100/$total)),'matches'=>count($matches)];
    } finally {
        if ($db->inTransaction()) $db->rollBack();
        $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
}
