<?php
declare(strict_types=1);
require_once __DIR__.'/resource-photo-pilot.php';

const LLAMA_KARTAVIEW_RADIUS=100;
const LLAMA_KARTAVIEW_HOURLY_LIMIT=90;

function llama_kartaview_ready(PDO $db): bool {
    try {
        $db->query('SELECT resource_id FROM resource_kartaview_discovery LIMIT 0');
        $db->query('SELECT requested_at FROM resource_kartaview_requests LIMIT 0');
        return true;
    } catch (PDOException $e) {return false;}
}

function llama_kartaview_storage_url(string $url): ?string {
    $url=preg_replace('~^http://~','https://',$url);
    if (!is_string($url) || strlen($url)>500
        || !preg_match('~^https://storage[0-9]*\.(?:openstreetcam|kartaview)\.org/files/photo/[0-9/]+/proc/[a-zA-Z0-9_.-]+\.(?:jpe?g|png|webp)$~iD',$url)) return null;
    return $url;
}

function llama_kartaview_cdn_url(string $url): ?string {
    if (strlen($url)>500 || !preg_match('~^https://cdn\.kartaview\.org/pr:sharp(?:/rs:fit:[0-9]{1,4}:[0-9]{1,4})?/([A-Za-z0-9_-]+={0,2})$~D',$url,$m)) return null;
    $inner=base64_decode(strtr($m[1],'-_','+/'),true);
    if (!is_string($inner) || !llama_kartaview_storage_url($inner)) return null;
    return $url;
}

function llama_kartaview_processed_urls(array $photo): array {
    $urls=[];
    $cdn=llama_kartaview_cdn_url((string)($photo['imageProcUrl'] ?? ''));
    if ($cdn) $urls[]=$cdn;
    $storage=llama_kartaview_storage_url((string)($photo['fileurlProc'] ?? ''));
    if (!$storage) {
        $template=(string)($photo['fileurl'] ?? '');
        if (str_contains($template,'[[sizeprefix]]')) $storage=llama_kartaview_storage_url(str_replace('[[sizeprefix]]','proc',$template));
    }
    if ($storage) {
        $proxy='https://cdn.kartaview.org/pr:sharp/'.rtrim(strtr(base64_encode($storage),'+/','-_'),'=');
        if (llama_kartaview_cdn_url($proxy)) $urls[]=$proxy;
        $urls[]=$storage;
    }
    return array_values(array_unique($urls));
}

function llama_kartaview_processed_url(array $photo): ?string {
    return llama_kartaview_processed_urls($photo)[0] ?? null;
}

function llama_kartaview_candidates(array $r,?callable $request=null): array {
    $request ??= 'llama_photo_pilot_json';
    $lat=(float)$r['latitude'];$lon=(float)$r['longitude'];
    if (!is_finite($lat)||!is_finite($lon)||abs($lat)>90||abs($lon)>180||($lat===0.0&&$lon===0.0)) return ['raw'=>0,'photos'=>[]];
    $data=$request('https://api.kartaview.org/2.0/photo/',['lat'=>$lat,'lng'=>$lon,'radius'=>LLAMA_KARTAVIEW_RADIUS,'zoomLevel'=>18,'itemsPerPage'=>20]);
    $code=(string)($data['status']['apiCode'] ?? '');
    if ($code==='601') return ['raw'=>0,'photos'=>[]];
    if ($code!=='600' || !is_array($data['result']['data'] ?? null)) throw new RuntimeException('KartaView API returned an unexpected status ('.$code.'). No result saved; resume later.');
    $rows=$data['result']['data'];$photos=[];
    foreach ($rows as $p) {
        if (!is_array($p)||!is_numeric($p['lat'] ?? null)||!is_numeric($p['lng'] ?? null)) continue;
        $a=(float)$p['lat'];$b=(float)$p['lng'];
        if (!is_finite($a)||!is_finite($b)||abs($a)>90||abs($b)>180) continue;
        $distance=llama_photo_pilot_distance($lat,$lon,$a,$b);
        if ($distance>LLAMA_KARTAVIEW_RADIUS) continue;
        if (isset($p['projection']) && strtoupper((string)$p['projection'])==='SPHERE') continue;
        $urls=llama_kartaview_processed_urls($p);
        if (!$urls) continue;
        $src=$urls[0];
        $date=(string)($p['dateAdded'] ?? '');
        $date=preg_match('/^\d{4}-\d{2}-\d{2}/',$date,$m)?$m[0]:'';
        $caption='Nearby street view ('.round($distance).' m from Resource) · © Grab and KartaView Contributors · CC BY-SA 4.0';
        if ($date!=='') $caption.=' · Image added '.$date;
        $photos[]=['src'=>$src,'urls'=>$urls,'distance'=>$distance,'credit'=>$caption,'source'=>'https://kartaview.org/map/@'.$a.','.$b.',18z','photo_id'=>(string)($p['id'] ?? '')];
    }
    usort($photos,static fn($a,$b)=>$a['distance']<=>$b['distance']);
    return ['raw'=>count($rows),'photos'=>array_slice($photos,0,3)];
}

/** Bounded GET: archived objects may return 200 to HEAD but fail on GET. */
function llama_kartaview_image_probe(string $src): array {
    if (!llama_kartaview_cdn_url($src) && llama_kartaview_storage_url($src)!==$src) return ['ok'=>false,'status'=>0,'detail'=>'Invalid processed URL'];
    $curl=curl_init($src);$prefix='';
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>5,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_RANGE=>'0-4095',
        CURLOPT_USERAGENT=>'LlamaScout-ResourcePhotos/1.0 (https://llamascout.com)',
        CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk) use (&$prefix):int {
            $prefix.=substr($chunk,0,max(0,4096-strlen($prefix)));
            // Abort intentionally after receiving a bounded prefix even when the host ignores Range.
            return strlen($prefix)>=4096?0:strlen($chunk);
        }]);
    curl_exec($curl);$errno=curl_errno($curl);$error=curl_error($curl);
    $code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($curl,CURLINFO_CONTENT_TYPE);curl_close($curl);
    $magic=str_starts_with($prefix,"\xff\xd8\xff") || str_starts_with($prefix,"\x89PNG\r\n\x1a\n")
        || (str_starts_with($prefix,'RIFF') && substr($prefix,8,4)==='WEBP');
    $ok=in_array($code,[200,206],true) && $magic && in_array($errno,[0,23],true);
    return ['ok'=>$ok,'status'=>$code,'detail'=>$ok?'Image bytes received':('HTTP '.$code.($errno?' / cURL '.$errno.' '.$error:'').' / '.$type)];
}

/** Try alternate URLs and subsequent candidates, without letting one broken host block the queue. */
function llama_kartaview_pick(array $photos,?callable $probe=null): array {
    $probe ??= 'llama_kartaview_image_probe';
    $failures=[];$attempted=[];
    foreach ($photos as $candidate) {
        foreach ($candidate['urls'] ?? [$candidate['src']] as $src) {
            if (isset($attempted[$src])) continue;
            $attempted[$src]=true;
            // Bound the total number of network probes per web request.
            if (count($attempted)>4) break 2;
            $result=$probe($src);
            if ($result['ok']) {
                $candidate['src']=$src;unset($candidate['urls']);
                return ['photo'=>$candidate,'failures'=>$failures];
            }
            $failures[]=['host'=>parse_url($src,PHP_URL_HOST),'status'=>$result['status'],'detail'=>$result['detail']];
            if ($result['status']===429) return ['photo'=>null,'failures'=>$failures,'throttled'=>true];
        }
    }
    return ['photo'=>null,'failures'=>$failures];
}

function llama_kartaview_retry_unusable(PDO $db,string $state=''): int {
    $lock='llama-photo-discovery-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $stmt=$db->prepare('SELECT GET_LOCK(?,0)');$stmt->execute([$lock]);
    if ((int)$stmt->fetchColumn()!==1) throw new RuntimeException('Pause photo discovery before resetting failed results.');
    try {
        $sql="DELETE k FROM resource_kartaview_discovery k JOIN resources r ON r.id=k.resource_id
            WHERE k.status='unusable' AND r.is_active=1 AND r.parent_place_id IS NULL
            AND NOT EXISTS (SELECT 1 FROM resource_images i WHERE i.resource_id=r.id)";
        $params=[];
        if ($state!=='') {$sql.=' AND r.state_region=?';$params[]=$state;}
        $stmt=$db->prepare($sql);$stmt->execute($params);return $stmt->rowCount();
    } finally {$db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
}

function llama_kartaview_step(PDO $db,string $state=''): array {
    if (!llama_kartaview_ready($db)) throw new RuntimeException('Run KartaView SQL first.');
    // Shared lock with Commons discovery; never race automatic photo insertion.
    $lock='llama-photo-discovery-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $stmt=$db->prepare('SELECT GET_LOCK(?,0)');$stmt->execute([$lock]);
    if ((int)$stmt->fetchColumn()!==1) throw new RuntimeException('Another photo discovery request is active.');
    try {
        $sql="SELECT r.* FROM resources r LEFT JOIN resource_kartaview_discovery k ON k.resource_id=r.id
            WHERE r.is_active=1 AND r.parent_place_id IS NULL AND k.resource_id IS NULL
            AND NOT EXISTS (SELECT 1 FROM resource_images i WHERE i.resource_id=r.id)";
        $params=[];
        if ($state!=='') {$sql.=' AND r.state_region=?';$params[]=$state;}
        $stmt=$db->prepare($sql.' ORDER BY r.id LIMIT 1');$stmt->execute($params);$r=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['done'=>true];
        // Persist attempts including failures. Public API documents 100 requests per hour.
        $db->exec('DELETE FROM resource_kartaview_requests WHERE requested_at<CURRENT_TIMESTAMP-INTERVAL 1 HOUR');
        if ((int)$db->query('SELECT COUNT(*) FROM resource_kartaview_requests')->fetchColumn()>=LLAMA_KARTAVIEW_HOURLY_LIMIT) {
            $wait=(int)$db->query('SELECT TIMESTAMPDIFF(SECOND,CURRENT_TIMESTAMP,MIN(requested_at)+INTERVAL 1 HOUR) FROM resource_kartaview_requests')->fetchColumn();
            return ['done'=>true,'limit'=>true,'retry_after'=>max(5,$wait+5)];
        }
        $db->exec('INSERT INTO resource_kartaview_requests () VALUES ()');
        $result=llama_kartaview_candidates($r);
        $picked=llama_kartaview_pick($result['photos']);
        if (!empty($picked['throttled'])) return ['done'=>true,'limit'=>true,'retry_after'=>300];
        $photo=$picked['photo'];
        $status=$photo?'attached':($result['raw']?'unusable':'none');
        $db->beginTransaction();
        $stmt=$db->prepare('SELECT * FROM resources WHERE id=? AND is_active=1 AND parent_place_id IS NULL FOR UPDATE');
        $stmt->execute([$r['id']]);$fresh=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fresh || llama_resource_photo_hash($fresh)!==llama_resource_photo_hash($r)) throw new RuntimeException('Resource changed during lookup; resume.');
        $stmt=$db->prepare('SELECT COUNT(*) FROM resource_images WHERE resource_id=?');$stmt->execute([$r['id']]);
        if ((int)$stmt->fetchColumn()>0) {$db->commit();return ['done'=>false,'name'=>$r['name'],'status'=>'existing photo','count'=>0];}
        if ($photo) {
            $db->prepare('INSERT INTO resource_images (resource_id,src,alt_text,caption,is_featured,sort_order,source_provider,source_url)
                VALUES (?,?,?,?,1,0,?,?)')->execute([$r['id'],$photo['src'],'Nearby street view of the Resource area',$photo['credit'],'KartaView',$photo['source']]);
        }
        $db->prepare('INSERT INTO resource_kartaview_discovery (resource_id,location_hash,status,photo) VALUES (?,?,?,?)')
            ->execute([$r['id'],llama_resource_photo_hash($r),$status,json_encode($photo??['raw'=>$result['raw'],'failures'=>$picked['failures']],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE)]);
        $db->commit();
        return ['done'=>false,'name'=>$r['name'],'status'=>$status,'count'=>$photo?1:0,'detail'=>!$photo && $picked['failures']?($picked['failures'][0]['detail']):''];
    } finally {
        if ($db->inTransaction()) $db->rollBack();
        $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
    }
}
