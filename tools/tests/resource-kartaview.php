<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/resource-kartaview.php';
$assert=static function(bool $ok,string $message):void {if (!$ok) throw new RuntimeException('FAIL: '.$message);};
$src='https://storage13.openstreetcam.org/files/photo/2022/10/6/proc/example.jpg';
$assert(llama_kartaview_processed_url(['fileurlProc'=>$src])===$src,'Processed image allowed');
$assert(llama_kartaview_processed_url(['fileurl'=>str_replace('/proc/','/[[sizeprefix]]/',$src)])===$src,'Processed template resolved');
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
echo "PASS: KartaView processed URL, distance, attribution and API status handling.\n";
