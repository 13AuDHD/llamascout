<?php
declare(strict_types=1);
require_once __DIR__.'/address-autocomplete.php';

const LLAMA_RESOURCE_ADDRESS_DAILY_LIMIT = 1000;
const LLAMA_RESOURCE_ADDRESS_FIELDS = ['street_address'=>'address_line_1','city'=>'city','county'=>'county','state_region'=>'state_name','postal_code'=>'postal_code','country_code'=>'country_code'];

function llama_resource_address_ready(PDO $db): bool {
    try {
        $db->query('SELECT resource_id FROM resource_address_enrichment LIMIT 0');
        $db->query('SELECT usage_date FROM resource_address_daily_usage LIMIT 0');
        return true;
    } catch (PDOException $e) {return false;}
}

function llama_resource_address_missing(string $alias='r'): string {
    return '('.implode(' OR ',array_map(static fn($key)=>"TRIM(COALESCE($alias.$key,''))=''",array_keys(LLAMA_RESOURCE_ADDRESS_FIELDS))).')';
}

function llama_resource_address_candidate(array $r,array $raw): array {
    $address=llama_address_result_from_row($raw,true) ?? [];
    $fields=[];
    foreach (LLAMA_RESOURCE_ADDRESS_FIELDS as $field=>$key) {
        $value=trim((string)($address[$key] ?? ''));
        $limits=['street_address'=>255,'city'=>120,'county'=>120,'state_region'=>120,'postal_code'=>32,'country_code'=>2];
        if ($value!=='' && mb_strlen($value)<=$limits[$field]) $fields[$field]=$value;
    }
    // A US state may already be stored as a full name or postal abbreviation.
    $same=static fn($a,$b)=>strcasecmp(trim((string)$a),trim((string)$b))===0;
    $existingState=trim((string)($r['state_region'] ?? ''));
    $existingCountry=trim((string)($r['country_code'] ?? ''));
    $countryOk=isset($fields['country_code']) && ($existingCountry!==''?$same($existingCountry,$fields['country_code']):$same('US',$fields['country_code']));
    $stateOk=$existingState==='' || (isset($fields['state_region']) && $same($existingState,$fields['state_region']))
        || (!empty($address['state']) && $same($existingState,$address['state']));
    $distance=is_numeric($raw['distance'] ?? null)?(float)$raw['distance']:null;
    $confidence=is_numeric($raw['rank']['confidence'] ?? null)?(float)$raw['rank']['confidence']:null;
    $type=(string)($raw['result_type'] ?? '');
    $street=isset($fields['street_address']) && trim((string)($raw['housenumber'] ?? ''))!==''
        && $distance!==null && $distance<=25 && $distance>=0 && $confidence!==null && $confidence>=0.8
        && $type==='building';
    $auto=[];
    if ($countryOk && $stateOk) {
        // Admin requested the same best available address as the manual coordinate search.
        foreach ($fields as $field=>$value) {
            if (trim((string)($r[$field] ?? ''))==='') $auto[$field]=$value;
        }
    }
    return ['fields'=>$fields,'automatic'=>$auto,'distance'=>$distance,'confidence'=>$confidence,
        'result_type'=>$type,'label'=>(string)($address['label'] ?? ''),
        'reason'=>!$countryOk || !$stateOk?'Country or state mismatch':(!$street?'Street address is unconfirmed or unavailable':'Close building match'),
        'attribution'=>'Geoapify / OpenStreetMap contributors'];
}

function llama_resource_address_apply(PDO $db,array $r,array $fields): array {
    $set=[];$params=[];$applied=[];
    foreach (LLAMA_RESOURCE_ADDRESS_FIELDS as $field=>$key) {
        if (trim((string)($r[$field] ?? ''))!=='' || !isset($fields[$field])) continue;
        $value=trim((string)$fields[$field]);
        $limits=['street_address'=>255,'city'=>120,'county'=>120,'state_region'=>120,'postal_code'=>32,'country_code'=>2];
        if ($value==='' || mb_strlen($value)>$limits[$field] || ($field==='country_code'&&!preg_match('/^[A-Z]{2}$/D',$value))) continue;
        $set[]=$field.'=?';$params[]=$value;$applied[$field]=$value;
    }
    if ($set) {
        $params[]=$r['id'];
        $db->prepare('UPDATE resources SET '.implode(',',$set).' WHERE id=?')->execute($params);
    }
    return $applied;
}

/** Reuse previously paid-for results, including partial results, before calling Geoapify again. */
function llama_resource_address_cached(PDO $db,string $state): ?array {
    $sql="SELECT r.*,e.candidate,e.applied_fields FROM resources r JOIN resource_address_enrichment e ON e.resource_id=r.id
        AND e.latitude=r.latitude AND e.longitude=r.longitude
        WHERE r.is_active=1 AND r.parent_place_id IS NULL AND ".llama_resource_address_missing()."
        AND e.status IN ('partial','review','none')
        AND JSON_EXTRACT(e.candidate,'$.bulk_policy_v2') IS NULL";
    $params=[];
    if ($state!=='') {$sql.=' AND r.state_region=?';$params[]=$state;}
    $db->beginTransaction();
    $stmt=$db->prepare($sql.' ORDER BY r.id LIMIT 100 FOR UPDATE'); $stmt->execute($params);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {$db->commit();return null;}
    $count=0;$appliedCount=0;
    foreach ($rows as $r) {
        $candidate=json_decode($r['candidate'],true,32,JSON_THROW_ON_ERROR);
        $previous=json_decode($r['applied_fields'],true,32,JSON_THROW_ON_ERROR);
        $fields=$candidate['fields'] ?? [];
        // Retain the explicit state/country conflict guard. No existing value is replaced.
        $applied=($candidate['reason'] ?? '')==='Country or state mismatch'?[]:llama_resource_address_apply($db,$r,$fields);
        $candidate['bulk_policy_v2']=true;
        $final=array_replace($r,$applied);
        $missing=array_filter(array_keys(LLAMA_RESOURCE_ADDRESS_FIELDS),static fn($key)=>trim((string)($final[$key] ?? ''))==='');
        $history=array_replace((array)$previous,$applied);
        $status=!$missing?'filled':($history?'partial':($fields?'review':'none'));
        $db->prepare('UPDATE resource_address_enrichment SET candidate=?,applied_fields=?,status=? WHERE resource_id=?')
            ->execute([json_encode($candidate,JSON_THROW_ON_ERROR),json_encode((object)$history,JSON_THROW_ON_ERROR),$status,$r['id']]);
        $count++;$appliedCount+=count($applied);
    }
    $db->commit();
    return ['done'=>false,'name'=>'Previously retrieved addresses','status'=>'saved','processed'=>$count,'field_count'=>$appliedCount,'applied'=>[]];
}

function llama_resource_address_step(PDO $db,string $state=''): array {
    if (!llama_resource_address_ready($db)) throw new RuntimeException('Run the address enrichment SQL first.');
    $lock='llama-address-enrichment-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $stmt=$db->prepare('SELECT GET_LOCK(?,0)');$stmt->execute([$lock]);
    if ((int)$stmt->fetchColumn()!==1) throw new RuntimeException('Another address lookup is running.');
    try {
        $cached=llama_resource_address_cached($db,$state);
        if ($cached!==null) return $cached;
        $sql="SELECT r.* FROM resources r LEFT JOIN resource_address_enrichment e ON e.resource_id=r.id
            WHERE r.is_active=1 AND r.parent_place_id IS NULL AND ".llama_resource_address_missing()."
            AND (e.resource_id IS NULL OR e.status='pending' OR e.latitude<>r.latitude OR e.longitude<>r.longitude)";
        $params=[];
        if ($state!=='') {$sql.=' AND r.state_region=?';$params[]=$state;}
        $stmt=$db->prepare($sql.' ORDER BY r.id LIMIT 1');$stmt->execute($params);$r=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['done'=>true];
        llama_address_geoapify_key();
        $db->exec('INSERT IGNORE INTO resource_address_daily_usage (usage_date,requests) VALUES (UTC_DATE(),0)');
        $stmt=$db->prepare('UPDATE resource_address_daily_usage SET requests=requests+1 WHERE usage_date=UTC_DATE() AND requests<?');
        $stmt->execute([LLAMA_RESOURCE_ADDRESS_DAILY_LIMIT]);
        if ($stmt->rowCount()!==1) return ['done'=>true,'limit'=>true];
        // Existing provider, bounded single reverse lookup. Errors stop the batch rather than classifying remaining Resources as missing.
        $data=llama_address_geoapify_request('reverse',['lat'=>(float)$r['latitude'],'lon'=>(float)$r['longitude'],'limit'=>1]);
        if (!isset($data['results']) || !is_array($data['results'])) throw new RuntimeException('Unexpected address provider response; resume to retry.');
        $candidate=llama_resource_address_candidate($r,$data['results'][0] ?? []);
        $candidate['bulk_policy_v2']=true;
        $db->beginTransaction();
        $stmt=$db->prepare('SELECT * FROM resources WHERE id=? AND is_active=1 AND parent_place_id IS NULL FOR UPDATE');
        $stmt->execute([$r['id']]);$fresh=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fresh || $fresh['latitude']!==$r['latitude'] || $fresh['longitude']!==$r['longitude']
            || $fresh['state_region']!==$r['state_region'] || $fresh['country_code']!==$r['country_code']) {
            throw new RuntimeException('Resource changed during lookup; resume to retry.');
        }
        $applied=llama_resource_address_apply($db,$fresh,$candidate['automatic']);
        $final=array_replace($fresh,$applied);
        $missing=array_filter(array_keys(LLAMA_RESOURCE_ADDRESS_FIELDS),static fn($key)=>trim((string)($final[$key] ?? ''))==='');
        $status=!$missing?'filled':($applied?'partial':($candidate['fields']?'review':'none'));
        $stmt=$db->prepare('INSERT INTO resource_address_enrichment (resource_id,latitude,longitude,status,candidate,applied_fields)
            VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE latitude=VALUES(latitude),longitude=VALUES(longitude),status=VALUES(status),
            candidate=VALUES(candidate),applied_fields=JSON_MERGE_PATCH(applied_fields,VALUES(applied_fields)),updated_at=CURRENT_TIMESTAMP');
        $stmt->execute([$r['id'],$r['latitude'],$r['longitude'],$status,json_encode($candidate,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE),json_encode((object)$applied,JSON_THROW_ON_ERROR)]);
        $db->commit();
        return ['done'=>false,'id'=>$r['id'],'name'=>$r['name'],'status'=>$status,'applied'=>array_keys($applied)];
    } finally {
        if ($db->inTransaction()) $db->rollBack();
        $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
    }
}
