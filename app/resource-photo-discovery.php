<?php
declare(strict_types=1);

function llama_resource_photo_discovery_ready(PDO $db): bool {
    try { $db->query('SELECT resource_id FROM resource_photo_discovery LIMIT 0'); return true; }
    catch (PDOException $e) { return false; }
}

function llama_resource_photo_hash(array $r): string {
    return hash('sha256',implode('|',[$r['id'],$r['name'],$r['latitude'],$r['longitude'],$r['source_external_id'] ?? '']));
}

function llama_commons_api(string $host,array $params): array {
    if (!in_array($host,['commons.wikimedia.org','www.wikidata.org'],true)) throw new InvalidArgumentException('Unsupported photo source.');
    $url='https://'.$host.'/w/api.php?'.http_build_query($params+['format'=>'json','formatversion'=>2,'maxlag'=>5]);
    $curl=curl_init($url); $body=''; $retry=0;
    curl_setopt_array($curl,[CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
        CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'LlamaScout-PhotoDiscovery/1.0 (https://llamascout.com)',
        CURLOPT_HEADERFUNCTION=>static function($handle,string $line) use (&$retry):int {
            if (preg_match('/^Retry-After:\s*(\d+)/i',$line,$m)) $retry=(int)$m[1];
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk) use (&$body):int {
            if (strlen($body)+strlen($chunk)>2097152) return 0;
            $body.=$chunk; return strlen($chunk);
        }]);
    $ok=curl_exec($curl); $code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    if ($code===429 || $code===503) throw new RuntimeException('Wikimedia requested a pause. Resume after at least '.max(60,$retry).' seconds.');
    if (!$ok || $code!==200) throw new RuntimeException('Photo source unavailable; no result was saved.');
    $json=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($json) || isset($json['error'])) throw new RuntimeException('Photo source temporarily unavailable; resume later.');
    return $json;
}

function llama_commons_file_title(string $value): ?string {
    $value=trim($value);
    if (preg_match('~^https://commons\.wikimedia\.org/wiki/(File:[^?#]+)(?:[?#].*)?$~i',$value,$m)) $value=rawurldecode($m[1]);
    if (!preg_match('~^File:[^|;\r\n#]{1,240}\.(?:jpe?g|png|webp)$~iu',$value)) return null;
    return $value;
}

function llama_commons_photo(array $page): ?array {
    $info=$page['imageinfo'][0] ?? null;
    if (!is_array($info) || !in_array($info['mime'] ?? '',['image/jpeg','image/png','image/webp'],true)) return null;
    $metadata=$info['extmetadata'] ?? [];
    $plain=static fn($key)=>trim(html_entity_decode(strip_tags((string)($metadata[$key]['value'] ?? '')),ENT_QUOTES|ENT_HTML5,'UTF-8'));
    $license=$plain('LicenseShortName');
    $licenseUrl=$plain('LicenseUrl');
    $author=$plain('Artist');
    $credit=$plain('Attribution');
    if ($credit==='') $credit=$author;
    $required=$plain('AttributionRequired');
    if (!preg_match('~^CC (?:BY(?:-SA)? (?:2\.0|2\.5|3\.0|4\.0)|0|CC0 1\.0)$~i',$license)
        && !in_array($license,['CC0','CC0 1.0'],true)) return null;
    if (!preg_match('~^https?://creativecommons\.org/(?:licenses/(?:by|by-sa)/(?:2\.0|2\.5|3\.0|4\.0)/|publicdomain/zero/1\.0/)$~i',$licenseUrl)) return null;
    if ($plain('Restrictions')!=='' || $plain('Permission')!=='' && str_contains(strtolower($plain('Permission')),'noncommercial')) return null;
    if ($credit==='' && !str_starts_with($license,'CC0')) return null;
    $src=(string)($info['thumburl'] ?? $info['url'] ?? '');
    $source=(string)($info['descriptionurl'] ?? '');
    $title=(string)($page['title'] ?? '');
    if (!str_starts_with($src,'https://upload.wikimedia.org/wikipedia/commons/') || strlen($src)>500
        || !str_starts_with($source,'https://commons.wikimedia.org/wiki/File:')
        || !llama_commons_file_title($title)) return null;
    $caption=trim($credit.' | '.$license);
    if (mb_strlen($caption)>500) return null;
    return ['title'=>$title,'src'=>$src,'source'=>$source,'credit'=>$caption,'license_url'=>$licenseUrl];
}

function llama_resource_photo_candidates(array $r,array $tags,?callable $api=null): array {
    $api ??= 'llama_commons_api';
    $titles=[];
    foreach (['wikimedia_commons','image'] as $key) {
        $title=llama_commons_file_title((string)($tags[$key] ?? ''));
        if ($title) $titles[$title]=true;
    }
    if (!$titles && preg_match('/^Q[1-9][0-9]{0,15}$/D',(string)($tags['wikidata'] ?? ''))) {
        $qid=$tags['wikidata'];
        $data=$api('www.wikidata.org',['action'=>'wbgetclaims','entity'=>$qid,'property'=>'P18']);
        foreach ($data['claims']['P18'] ?? [] as $claim) {
            if (($claim['rank'] ?? '')==='deprecated') continue;
            $value=$claim['mainsnak']['datavalue']['value'] ?? '';
            if (is_string($value) && ($title=llama_commons_file_title('File:'.$value))) $titles[$title]=true;
            if (count($titles)>=3) break;
        }
    }
    $base=['action'=>'query','prop'=>'imageinfo','iiprop'=>'url|mime|extmetadata','iiurlwidth'=>1280,
        'iiextmetadatafilter'=>'Artist|Attribution|LicenseShortName|LicenseUrl|AttributionRequired|Restrictions|Permission','redirects'=>1];
    if ($titles) {
        $data=$api('commons.wikimedia.org',$base+['titles'=>implode('|',array_keys($titles))]);
        $result=[];
        foreach ($data['query']['pages'] ?? [] as $page) if ($photo=llama_commons_photo($page)) $result[]=$photo+['match'=>'linked'];
        if ($result) return $result;
    }
    $lat=(float)$r['latitude']; $lon=(float)$r['longitude'];
    if (!is_finite($lat)||!is_finite($lon)||abs($lat)>90||abs($lon)>180||($lat===0.0&&$lon===0.0)) return [];
    $data=$api('commons.wikimedia.org',$base+['generator'=>'geosearch','ggsnamespace'=>6,
        'ggscoord'=>$lat.'|'.$lon,'ggsradius'=>100,'ggslimit'=>8]);
    $result=[];
    foreach ($data['query']['pages'] ?? [] as $page) if ($photo=llama_commons_photo($page)) $result[]=$photo+['match'=>'nearby'];
    return $result;
}

function llama_resource_photo_attach(PDO $db,array $r,array $photo): void {
    $stmt=$db->prepare('SELECT * FROM resources WHERE id=? AND is_active=1 AND parent_place_id IS NULL FOR UPDATE');
    $stmt->execute([$r['id']]); $fresh=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fresh || llama_resource_photo_hash($fresh)!==llama_resource_photo_hash($r)) throw new RuntimeException('Resource changed; search again.');
    $stmt=$db->prepare('SELECT COUNT(*) FROM resource_images WHERE resource_id=?'); $stmt->execute([$r['id']]);
    if ((int)$stmt->fetchColumn()!==0) throw new RuntimeException('Resource already has a photo; existing gallery preserved.');
    $db->prepare('INSERT INTO resource_images (resource_id,src,alt_text,caption,is_featured,sort_order,source_provider,source_url)
        VALUES (?,?,?,?,1,0,?,?)')->execute([$r['id'],$photo['src'],mb_substr($r['name'],0,300),$photo['credit'],'Wikimedia Commons',$photo['source']]);
}

function llama_resource_photo_discover_step(PDO $db,string $state=''): array {
    if (!llama_resource_photo_discovery_ready($db)) throw new RuntimeException('Run photo discovery SQL first.');
    $lock='llama-photo-discovery-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $stmt=$db->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
    if ((int)$stmt->fetchColumn()!==1) throw new RuntimeException('Another photo discovery request is active.');
    try {
        $elapsed=$db->query('SELECT TIMESTAMPDIFF(SECOND,MAX(updated_at),CURRENT_TIMESTAMP) FROM resource_photo_discovery')->fetchColumn();
        if ($elapsed!==null && $elapsed!==false && (int)$elapsed<2) throw new RuntimeException('Photo discovery cooling down. Resume shortly.');
        $sql="SELECT r.* FROM resources r LEFT JOIN resource_photo_discovery d ON d.resource_id=r.id
            WHERE r.is_active=1 AND r.parent_place_id IS NULL AND d.resource_id IS NULL
            AND NOT EXISTS (SELECT 1 FROM resource_images i WHERE i.resource_id=r.id)";
        $params=[];
        if ($state!=='') {$sql.=' AND r.state_region=?';$params[]=$state;}
        $stmt=$db->prepare($sql.' ORDER BY r.id LIMIT 1');$stmt->execute($params);$r=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['done'=>true];
        $tags=[];
        if (($r['source_provider'] ?? '')==='OpenStreetMap' && preg_match('~^(node|way|relation)/([1-9][0-9]{0,18})$~D',(string)$r['source_external_id'],$m)) {
            try {
                $stmt=$db->prepare("SELECT tags,review_reason FROM osm_resource_enrichment_facts WHERE osm_type=? AND osm_id=?");
                $stmt->execute([$m[1],$m[2]]);$facts=$stmt->fetch(PDO::FETCH_ASSOC);
                if ($facts && !in_array($facts['review_reason'],['Resource identity or status changed','Resource coordinates edited since import','OSM node moved; confirm location','Source indicates changed access or closure'],true)) $tags=json_decode($facts['tags'],true) ?: [];
            } catch (PDOException $e) { /* Older enrichment migration is optional. */ }
        }
        $photos=llama_resource_photo_candidates($r,$tags);
        $status=$photos?'review':'none';
        $db->beginTransaction();
        if ($photos && $photos[0]['match']==='linked') {
            llama_resource_photo_attach($db,$r,$photos[0]);
            $status='attached';
        }
        $db->prepare('INSERT INTO resource_photo_discovery (resource_id,location_hash,status,candidates) VALUES (?,?,?,?)')
            ->execute([$r['id'],llama_resource_photo_hash($r),$status,json_encode($photos,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE)]);
        $db->commit();
        return ['done'=>false,'name'=>$r['name'],'status'=>$status,'count'=>count($photos)];
    } finally {
        if ($db->inTransaction()) $db->rollBack();
        $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
    }
}
