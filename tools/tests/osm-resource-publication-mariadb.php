<?php
declare(strict_types=1);
$dsn=(string)getenv('LLAMA_OSM_TEST_DSN');
if (!preg_match('/(?:^|;)dbname=llama_osm_import_test(?:;|$)/',$dsn)) throw new RuntimeException('Disposable test database required.');
require_once dirname(__DIR__,2).'/app/osm-resource-publication.php';
$db=new PDO($dsn,(string)getenv('LLAMA_OSM_TEST_USER'),(string)getenv('LLAMA_OSM_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
$assert=static function(bool $ok,string $message):void { if (!$ok) throw new RuntimeException('FAIL: '.$message); };
$root=dirname(__DIR__,2);
foreach (['20261101_v3_003_resources.sql','20261101_v3_004_resource_pages.sql','20261101_v3_005_resource_marker_service.sql','20261101_v3_006_resource_hours.sql','20261127_osm_nearest_services.sql','20261127_osm_resource_publication.sql'] as $migration) {
    $db->exec(file_get_contents($root.'/tools/migrations/'.$migration));
}
$db->exec('CREATE TABLE IF NOT EXISTS places (id BIGINT PRIMARY KEY,latitude DECIMAL(10,7),longitude DECIMAL(11,7),KEY(latitude,longitude))');
$db->exec('DELETE FROM osm_nearest_services; DELETE FROM osm_resource_publications; DELETE FROM resource_services; DELETE FROM resources; DELETE FROM places');
$db->exec("INSERT INTO places VALUES (1,38,-108)");
$date=gmdate('Y-m-d');
$add=$db->prepare('INSERT INTO osm_nearest_services VALUES (?,?,?,?,?,?,?,?)');
foreach ([
    ['colorado','node',10,'potable_water','Water',37,-107],
    ['colorado','node',10,'dump_station','Water',37,-107],
    ['colorado','node',11,'potable_water','Water',37.2,-107],
    ['colorado','node',12,'toilet','Camp toilet',38,-108],
    ['colorado','node',13,'trash','Trash disposal',39,-109],
    ['colorado','node',14,'fuel','Fuel',40,-110],
    ['colorado','node',15,'potable_water','Possible duplicate',37.0001,-107],
    ['utah','node',10,'potable_water','Water',37,-107],
] as $row) $add->execute([...$row,$date]);
$rows=llama_osm_publication_candidates($db,'colorado','');
$assert(count($rows)===5,'Priority only, group services by source feature');
$assert($rows[0]['services']===['potable_water','dump_station'],'Camper priority order preserved');
$result=llama_osm_publication_publish($db,'colorado','',array_map(static fn($r)=>$r['osm_type'].'/'.$r['osm_id'],$rows),42);
$assert($result===['published'=>3,'review'=>2],'Eligible records published, nearby Place and Resource held');
$assert(llama_osm_publication_counts($db)===['published'=>3,'review'=>2],'Persistent decisions');
$assert(llama_osm_publication_candidates($db,'utah','')===[],'Cross-state OSM identity cannot duplicate');
$assert(llama_osm_publication_candidates($db,'colorado','')===[],'Repeated publish cannot duplicate');
$resource=$db->query("SELECT * FROM resources WHERE source_external_id='node/10'")->fetch(PDO::FETCH_ASSOC);
$assert($resource['status']==='unknown' && $resource['hours_mode']==='unknown' && $resource['verified_at']===null && $resource['created_by']===null,'No invented verification or hours');
$services=$db->prepare('SELECT service_type,status,fee_status FROM resource_services WHERE resource_id=? ORDER BY sort_order');
$services->execute([$resource['id']]);
$assert($services->fetchAll(PDO::FETCH_ASSOC)===[
    ['service_type'=>'potable_water','status'=>'unknown','fee_status'=>'unknown'],
    ['service_type'=>'dump_station','status'=>'unknown','fee_status'=>'unknown'],
],'Native services and unknown fees');
$assert((int)$db->query("SELECT COUNT(*) FROM resources WHERE resource_type='waste_disposal'")->fetchColumn()===1,'Waste disposal type');
$assert((int)$db->query('SELECT COUNT(DISTINCT slug) FROM resources')->fetchColumn()===3,'Repeated source names have unique URLs');
$baseline=json_decode($db->query("SELECT source_snapshot FROM osm_resource_publications WHERE osm_id=10")->fetchColumn(),true,32,JSON_THROW_ON_ERROR);
$db->exec("DELETE FROM osm_nearest_services WHERE osm_id=10");
$assert($baseline['name']==='Water' && $baseline['services']===['potable_water','dump_station'],'Retained source baseline independent of refresh');
// Simulate a database that has already reached the former nationwide cap.
$ledger=$db->prepare("INSERT INTO osm_resource_publications (osm_type,osm_id,region,source_snapshot,disposition) VALUES ('node',?,'utah','{}','published')");
for ($i=1000;$i<1250;$i++) $ledger->execute([$i]);
$add->execute(['colorado','node',20,'shower','Example Shower',41,-105,$date]);
$add->execute(['colorado','node',21,'potable_water','Another Water',42,-105,$date]);
$showerRows=llama_osm_publication_candidates($db,'colorado','shower');
$assert(count($showerRows)===1 && $showerRows[0]['services']===['shower'],'Selected shower filter retained beyond old cap');
$assert(llama_osm_publication_publish($db,'colorado','shower',[],42,true)===['published'=>1,'review'=>0],'Scoped bulk publication continues beyond old cap');
$assert(llama_osm_publication_publish($db,'colorado','shower',[],42,true)===['published'=>0,'review'=>0],'Bulk resume is idempotent');
$assert(count(llama_osm_publication_candidates($db,'colorado','potable_water'))===1,'Bulk shower publication did not publish water');
$assert(llama_osm_publication_counts($db)['published']===254,'Global count may exceed former pilot cap');
echo "PASS: priority publication, native services, unknown facts, duplicate review, source identity and provenance.\n";
