<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/resource-photo-discovery.php';
$assert=static function(bool $ok,string $message):void {if (!$ok) throw new RuntimeException('FAIL: '.$message);};
$meta=[
    'LicenseShortName'=>['value'=>'CC BY-SA 4.0'],
    'LicenseUrl'=>['value'=>'https://creativecommons.org/licenses/by-sa/4.0/'],
    'Artist'=>['value'=>'<a href="https://example.com">Example photographer</a>'],
];
$page=['title'=>'File:Example.jpg','imageinfo'=>[[
    'mime'=>'image/jpeg','thumburl'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Example.jpg/1280px-Example.jpg',
    'descriptionurl'=>'https://commons.wikimedia.org/wiki/File:Example.jpg','extmetadata'=>$meta,
]]];
$photo=llama_commons_photo($page);
$assert($photo!==null && $photo['credit']==='Example photographer | CC BY-SA 4.0','Licensed photo attribution preserved');
$bad=$page;$bad['imageinfo'][0]['extmetadata']['LicenseShortName']['value']='All rights reserved';
$assert(llama_commons_photo($bad)===null,'Unknown or restrictive license rejected');
$bad=$page;$bad['imageinfo'][0]['thumburl']='https://example.com/image.jpg';
$assert(llama_commons_photo($bad)===null,'Untrusted image host rejected');
$assert(llama_commons_file_title('https://commons.wikimedia.org/wiki/File:Example.jpg')==='File:Example.jpg','Explicit photo link recognized');
$assert(llama_commons_file_title('Category:Example')===null,'Categories are not exact photos');
$r=['id'=>1,'name'=>'Example Resource','latitude'=>37,'longitude'=>-107,'source_external_id'=>'node/1'];
$api=static function($host,$params) use ($page):array {return ['query'=>['pages'=>[$page]]];};
$found=llama_resource_photo_candidates($r,['wikimedia_commons'=>'File:Example.jpg'],$api);
$assert(count($found)===1 && $found[0]['match']==='linked','Explicit source matched');
$found=llama_resource_photo_candidates($r,[],$api);
$assert(count($found)===1 && $found[0]['match']==='nearby','Proximity is review-only');
echo "PASS: Commons licensing, URL allowlist, exact/nearby distinction.\n";
