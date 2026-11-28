<?php
declare(strict_types=1);
require_once __DIR__.'/resource-photo-discovery.php';

function llama_photo_pilot_sample(PDO $db): array {
    // Deterministic spread across service categories, including the previous no-result records.
    $sql="SELECT id,name,resource_type,state_region,latitude,longitude FROM (
        SELECT r.id,r.name,r.resource_type,r.state_region,r.latitude,r.longitude,
            ROW_NUMBER() OVER (PARTITION BY r.resource_type ORDER BY SHA2(CONCAT(r.id,':photo-pilot'),256)) AS position
        FROM resources r JOIN resource_photo_discovery d ON d.resource_id=r.id AND d.status='none'
        WHERE r.is_active=1 AND r.parent_place_id IS NULL
        AND NOT EXISTS (SELECT 1 FROM resource_images i WHERE i.resource_id=r.id)
    ) sample ORDER BY position,SHA2(CONCAT(id,':photo-pilot'),256) LIMIT 20";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function llama_photo_pilot_json(string $url,array $params,array $headers=[]): array {
    $curl=curl_init($url.'?'.http_build_query($params)); $body='';
    curl_setopt_array($curl,[CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>10,
        CURLOPT_USERAGENT=>'LlamaScout-PhotoCoveragePilot/1.0 (https://llamascout.com)',
        CURLOPT_HTTPHEADER=>array_merge(['Accept: application/json'],$headers),
        CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk) use (&$body):int {
            if (strlen($body)+strlen($chunk)>2097152) return 0;
            $body.=$chunk; return strlen($chunk);
        }]);
    $ok=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    if (!$ok || $status!==200) throw new RuntimeException('Source request failed (HTTP '.$status.').');
    $data=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($data) || isset($data['error'])) throw new RuntimeException('Source returned an API error.');
    return $data;
}

function llama_photo_pilot_commons(array $r,?callable $api=null): array {
    $api ??= 'llama_commons_api';
    $data=$api('commons.wikimedia.org',['action'=>'query','generator'=>'geosearch','ggsprimary'=>'all',
        'ggsnamespace'=>6,'ggscoord'=>$r['latitude'].'|'.$r['longitude'],'ggsradius'=>500,'ggslimit'=>20,
        'prop'=>'imageinfo','iiprop'=>'url|mime|extmetadata','iiurlwidth'=>640,
        'iiextmetadatafilter'=>'Artist|Attribution|LicenseShortName|LicenseUrl|AttributionRequired|Restrictions|Permission','redirects'=>1]);
    $pages=$data['query']['pages'] ?? [];
    if (!is_array($pages)) throw new RuntimeException('Unexpected Commons response.');
    $accepted=[];$rejected=[];
    foreach ($pages as $page) {
        if (!is_array($page)) continue;
        $photo=llama_commons_photo($page);
        if ($photo) $accepted[]=['title'=>$photo['title'],'url'=>$photo['source'],'preview'=>$photo['src']];
        else {
            $license=strip_tags((string)($page['imageinfo'][0]['extmetadata']['LicenseShortName']['value'] ?? 'Missing license'));
            $rejected[]=['title'=>(string)($page['title'] ?? 'Untitled'),'license'=>mb_substr($license,0,100)];
        }
    }
    return ['raw'=>count($pages),'eligible'=>count($accepted),'rejected'=>$rejected,'photos'=>array_slice($accepted,0,3)];
}

function llama_photo_pilot_distance(float $lat,float $lon,float $a,float $b): float {
    $x=sin(deg2rad($a-$lat)/2)**2+cos(deg2rad($lat))*cos(deg2rad($a))*sin(deg2rad($b-$lon)/2)**2;
    return 12742000*asin(sqrt(max(0,min(1,$x))));
}

function llama_photo_pilot_karta(array $r,?callable $request=null): array {
    $request ??= 'llama_photo_pilot_json';
    $data=$request('https://api.kartaview.org/2.0/photo/',['lat'=>$r['latitude'],'lng'=>$r['longitude'],
        'radius'=>100,'zoomLevel'=>18,'itemsPerPage'=>20]);
    $rows=$data['result']['data'] ?? null;
    if (!is_array($rows)) {
        if ((string)($data['status']['apiCode'] ?? '')==='601') $rows=[];
        else throw new RuntimeException('Unexpected KartaView response; not counted as no coverage.');
    }
    $near=[];
    foreach ($rows as $photo) {
        if (!is_array($photo)||!is_numeric($photo['lat'] ?? null)||!is_numeric($photo['lng'] ?? null)) continue;
        $distance=llama_photo_pilot_distance((float)$r['latitude'],(float)$r['longitude'],(float)$photo['lat'],(float)$photo['lng']);
        if ($distance>100) continue;
        $url=(string)($photo['fileurl'] ?? '');
        $url=str_replace('[[sizeprefix]]','1280',$url);
        $url=preg_replace('~^http://~','https://',$url);
        if (!preg_match('~^https://(?:storage[0-9]*\.openstreetcam\.org|storage[0-9]*\.kartaview\.org)/~i',$url) || str_contains($url,'[')) $url='';
        $near[]=['title'=>'Street-level photo · '.round($distance).' m','preview'=>$url,'distance'=>$distance];
    }
    usort($near,static fn($a,$b)=>$a['distance']<=>$b['distance']);
    return ['raw'=>count($rows),'eligible'=>count($near),'photos'=>array_slice($near,0,3)];
}

function llama_photo_pilot_mapillary(array $r,string $token,?callable $request=null): array {
    if ($token==='') throw new RuntimeException('Mapillary token not configured.');
    $request ??= 'llama_photo_pilot_json';
    $lat=(float)$r['latitude'];$lon=(float)$r['longitude'];
    $dy=100/111320;$dx=$dy/max(0.01,cos(deg2rad($lat)));
    $data=$request('https://graph.mapillary.com/images',['bbox'=>implode(',',[$lon-$dx,$lat-$dy,$lon+$dx,$lat+$dy]),
        'fields'=>'id,computed_geometry,geometry,thumb_256_url','limit'=>20],['Authorization: OAuth '.$token]);
    if (!isset($data['data']) || !is_array($data['data'])) throw new RuntimeException('Unexpected Mapillary response.');
    $near=[];
    foreach ($data['data'] as $photo) {
        $coord=$photo['computed_geometry']['coordinates'] ?? $photo['geometry']['coordinates'] ?? [];
        if (!isset($coord[0],$coord[1])||!is_numeric($coord[0])||!is_numeric($coord[1])||!ctype_digit((string)($photo['id'] ?? ''))) continue;
        $distance=llama_photo_pilot_distance($lat,$lon,(float)$coord[1],(float)$coord[0]);
        if ($distance>100) continue;
        $preview=(string)($photo['thumb_256_url'] ?? '');
        if (!preg_match('~^https://(?:[a-z0-9-]+\.)+(?:fbcdn\.net|cdninstagram\.com|mapillary\.com)/~i',$preview)) $preview='';
        $near[]=['title'=>'Street-level photo · '.round($distance).' m','url'=>'https://www.mapillary.com/app/?pKey='.$photo['id'],'preview'=>$preview,'distance'=>$distance];
    }
    usort($near,static fn($a,$b)=>$a['distance']<=>$b['distance']);
    return ['raw'=>count($data['data']),'eligible'=>count($near),'photos'=>array_slice($near,0,3)];
}
