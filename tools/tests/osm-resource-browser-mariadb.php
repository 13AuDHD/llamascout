<?php
declare(strict_types=1);
$dsn=(string)getenv('LLAMA_OSM_TEST_DSN');
if (!preg_match('/(?:^|;)dbname=llama_osm_import_test(?:;|$)/',$dsn)) throw new RuntimeException('Disposable test DB required.');
require_once dirname(__DIR__,2) . '/app/osm-resource-browser.php';
$db=new PDO($dsn,(string)getenv('LLAMA_OSM_TEST_USER'),(string)getenv('LLAMA_OSM_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
$assert=static function(bool $ok,string $message):void { if (!$ok) throw new RuntimeException('FAIL: '.$message); };
$root=dirname(__DIR__,2);
foreach (['20261127_osm_nearest_services.sql','20261127_osm_resource_import.sql','20261127_osm_browser_import.sql'] as $migration) {
    $db->exec(file_get_contents($root.'/tools/migrations/'.$migration));
}
$db->exec('DELETE FROM osm_resource_import_stage; DELETE FROM osm_resource_import_jobs; DELETE FROM osm_nearest_services');
function v(int $value):string {
    $out='';
    while ($value>=128) { $out.=chr(($value&127)|128); $value>>=7; }
    return $out.chr($value);
}
function n(int $field,int $value):string { return v($field*8).v($value); }
function s(int $field,string $value):string { return v($field*8+2).v(strlen($value)).$value; }
function zz(int $value):int { return $value<0 ? -2*$value-1 : 2*$value; }
function frame(string $type,string $payload):string {
    $blob=n(2,strlen($payload)).s(3,gzcompress($payload));
    $header=s(1,$type).n(3,strlen($blob));
    return pack('N',strlen($header)).$header.$blob;
}
$stamp=time()-3600;
$date=gmdate('Y-m-d',$stamp);
$old=gmdate('Y-m-d',$stamp-86400);
$header=frame('OSMHeader',s(4,'OsmSchema-V0.6').s(4,'DenseNodes').n(32,$stamp));
$strings=s(1,'').s(1,'amenity').s(1,'drinking_water').s(1,'name').s(1,'Example Water').s(1,'access').s(1,'private');
$ids=$lats=$lons=$pairs='';
for ($i=1;$i<=102;$i++) {
    $ids.=v(zz(1));
    $lats.=v(zz($i===1?371000000:1));
    $lons.=v(zz($i===1?-1071000000:1));
    $pairs.=v(1).v(2).v(3).v(4).($i===102?v(5).v(6):'').v(0);
}
$dense=s(1,$ids).s(8,$lats).s(9,$lons).s(10,$pairs);
$data=frame('OSMData',s(1,$strings).s(2,s(2,$dense)));
$file=$header.$data;
$fetch=static fn($region,$offset,$length,$validator)=>['data'=>substr($file,$offset,$length),'size'=>strlen($file),'validator'=>'etag:"fixture"'];
$insert=$db->prepare('INSERT INTO osm_nearest_services VALUES (?,?,?,?,?,?,?,?)');
$insert->execute(['colorado','node',800,'fuel','Old point',37,-107,$old]);
$insert->execute(['colorado','way',900,'fuel','Old mapped area',37,-107,$old]);
$assert(llama_osm_resource_queue($db,['colorado'])===['colorado'],'Admin action queues without credentials');
$live=static fn()=> $db->query("SELECT name FROM osm_nearest_services WHERE region='colorado' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$r=llama_osm_browser_step($db,$fetch,50000);
$assert($r['job']['status']==='importing' && $r['job']['offset']===strlen($header),'First step reads header only');
$r=llama_osm_browser_step($db,$fetch,50000);
$assert($r['job']['rows']===100 && $r['job']['offset']===strlen($header),'Exactly 100 rows; same-block checkpoint saved');
$assert($live()===['Old mapped area','Old point'],'Live snapshot untouched during staging');
$r=llama_osm_browser_step($db,$fetch,50000);
$assert($r['job']['rows']===101 && $r['job']['offset']===strlen($file),'Resume within block; private point excluded');
$r=llama_osm_browser_step($db,$fetch,50000);
$assert($r['job']['status']==='complete' && count($live())===102,'Atomic point refresh preserves mapped area');
$assert($db->query("SELECT name FROM osm_nearest_services WHERE osm_type='way'")->fetchColumn()==='Old mapped area','Old area retained');
$assert((int)$db->query("SELECT COUNT(*) FROM osm_nearest_services WHERE osm_type='node' AND name='Example Water'")->fetchColumn()===101,'All point identities imported');
$assert(llama_osm_browser_step($db,$fetch,50000)['done']===true,'Queue drained');
llama_osm_resource_queue($db,['colorado']);
llama_osm_browser_step($db,$fetch,50000);
$changed=static fn($region,$offset,$length,$validator)=>['data'=>substr($file,$offset,$length),'size'=>strlen($file),'validator'=>'etag:"changed"'];
try { llama_osm_browser_step($db,$changed,50000); throw new LogicException('Changed source accepted'); }
catch (RuntimeException $e) { $assert(str_contains($e->getMessage(),'changed'),'Changed source rejected'); }
$assert(count($live())===102,'Changed file preserves previous snapshot');
$assert(llama_osm_resource_inventory($db)['colorado']['job']['status']==='error','Changed snapshot can be restarted');
$assert(llama_pbf_services(['amenity'=>'sanitary_dump_station'])===['dump_station'],'Dump station');
$assert(llama_pbf_services(['shop'=>'laundry','self_service'=>'no'])===[],'Non-self-service laundry exclusion');
$assert(llama_pbf_services(['amenity'=>'fuel','fuel:propane'=>'yes'])===['fuel','propane'],'Multiple tagged services');
echo "PASS: browser SQL queue, bounded row cursor, staged refresh, preservation of mapped areas, source-version protection.\n";
