<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/resource-kartaview.php';
$assert=static function(bool $ok,string $message):void {if (!$ok) throw new RuntimeException('FAIL: '.$message);};
$src='https://storage13.openstreetcam.org/files/photo/2022/10/6/proc/example.jpg';
$cdn='https://cdn.kartaview.org/pr:sharp/'.rtrim(strtr(base64_encode($src),'+/','-_'),'=');
$assert(llama_kartaview_processed_url(['fileurlProc'=>$src])===$cdn,'CDN preferred to legacy processed storage');
$assert(llama_kartaview_processed_urls(['fileurlProc'=>$src])===[$cdn,$src],'Legacy storage is fallback');
$assert(llama_kartaview_processed_url(['imageProcUrl'=>$cdn])===$cdn,'API CDN field supported');
$assert(llama_kartaview_cdn_url('https://cdn.kartaview.org/pr:sharp/'.base64_encode('https://example.com/image.jpg'))===null,'Invalid embedded host blocked');
$assert(llama_kartaview_processed_url(['fileurl'=>str_replace('/proc/','/[[sizeprefix]]/',$src) ])===$cdn,'Processed template resolved');
$assert(llama_kartaview_processed_url(['fileurl'=>str_replace('/proc/','/raw/',$src)])===null,'Raw image rejected');
$assert(llama_kartaview_processed_url(['fileurlProc'=>'https://example.com/files/photo/2022/10/6/proc/example.jpg'])===null,'Untrusted host rejected');
$r=['latitude'=>37,'longitude'=>-107];
$photo=static fn(float $lat)=>['lat'=>$lat,'lng'=>-107,'fileurlProc'=>$src,'id'=>'123','dateAdded'=>'2022-10-06'];
$request=static fn($url,$params)=>['status'=>['apiCode'=>600],'result'=>['data'=>[$photo(37.0007),$photo(37.0001),$photo(37.01)]]];
$result=llama_kartaview_candidates($r,$request);
$assert($result['raw']===3 && count($result['photos'])===2,'Distance filtering');
$assert($result['photos'][0]['distance']<$result['photos'][1]['distance'],'Closest image first');
$assert(str_contains($result['photos'][0]['credit'],'© Grab and KartaView Contributors'),'Required attribution');
$assert(llama_kartaview_candidates($r,static fn()=>['status'=>['apiCode'=>601]])['raw']===0,'No coverage handled');
try {llama_kartaview_candidates($r,static fn()=>['status'=>['apiCode'=>401]]);throw new LogicException('Error accepted as no coverage');}
catch (RuntimeException $e) {$assert(str_contains($e->getMessage(),'401'),'Authentication failure not saved as no coverage');}
$attempts=[];
$picked=llama_kartaview_pick($result['photos'],static function($url) use (&$attempts):array {
    $attempts[]=$url;
    return count($attempts)===1?['ok'=>false,'status'=>503,'detail'=>'HTTP 503']:['ok'=>true,'status'=>206,'detail'=>'Image bytes received'];
});
$assert($picked['photo']['src']===$src && count($picked['failures'])===1,'Unavailable CDN falls back without throwing');
$picked=llama_kartaview_pick($result['photos'],static fn()=>['ok'=>false,'status'=>0,'detail'=>'DNS error']);
$assert($picked['photo']===null && count($picked['failures'])===2,'Failed host is reported and not fatal');
$picked=llama_kartaview_pick($result['photos'],static fn()=>['ok'=>false,'status'=>429,'detail'=>'Rate limited']);
$assert(!empty($picked['throttled']),'429 triggers backoff');
echo "PASS: KartaView processed URL, distance, attribution and API status handling.\n";
