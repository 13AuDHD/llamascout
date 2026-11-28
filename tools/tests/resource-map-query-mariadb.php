<?php
declare(strict_types=1);
$dsn=(string)getenv('LLAMA_OSM_TEST_DSN');
if (!preg_match('/(?:^|;)dbname=llama_osm_import_test(?:;|$)/',$dsn)) throw new RuntimeException('Disposable test database required.');
require_once dirname(__DIR__,2).'/app/resource-map-query.php';
$db=new PDO($dsn,(string)getenv('LLAMA_OSM_TEST_USER'),(string)getenv('LLAMA_OSM_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
$assert=static function(bool $ok,string $message):void { if (!$ok) throw new RuntimeException('FAIL: '.$message); };
// Runs after the publication fixture, which installs the native Resource tables.
$db->exec('ALTER TABLE resources ADD COLUMN IF NOT EXISTS parent_place_id BIGINT UNSIGNED NULL');
$db->exec('DELETE FROM resource_services; DELETE FROM resources');
$add=$db->prepare("INSERT INTO resources (resource_type,marker_service_type,name,slug,latitude,longitude,status,is_active,parent_place_id) VALUES ('water_station','potable_water',?,?,?,?, 'unknown',?,?)");
$service=$db->prepare("INSERT INTO resource_services (resource_id,service_type,status,fee_status,is_active) VALUES (?,?,'unknown','unknown',?)");
$insert=static function(string $name,float $lat,float $lon,array $types,int $active=1,?int $parent=null) use ($db,$add,$service):int {
    $add->execute([$name,llama_resource_slug($name),$lat,$lon,$active,$parent]);
    $id=(int)$db->lastInsertId();
    foreach ($types as $type) $service->execute([$id,$type,1]);
    return $id;
};
for ($i=1;$i<=260;$i++) $insert('Water '.$i,37.0+$i/10000,-107.0,['potable_water','dump_station']);
$insert('Outside viewport',40,-110,['potable_water']);
$insert('Facility',37.01,-107,['potable_water'],1,1);
$insert('Inactive',37.01,-107,['potable_water'],0);
$insert('Laundry',37.01,-107,['laundry']);
$insert('East dateline',60,179.8,['potable_water']);
$insert('West dateline',60,-179.8,['potable_water']);
$q=['south'=>36.9,'north'=>37.1,'west'=>-107.1,'east'=>-106.9,'zoom'=>10,'services'=>'potable_water,dump_station'];
$r=llama_resource_map_query($db,$q);
$assert($r['mode']==='markers' && count($r['resources'])===250 && $r['truncated'],'Bounded individual marker query');
$assert(count(array_unique(array_column($r['resources'],'id')))===250,'Multi-service Resource appears once');
$assert($r['resources'][0]['name']==='Water 1','Nearest viewport center first');
$assert(count($r['resources'][0]['services'])===2,'All services returned in bounded batch');
$r=llama_resource_map_query($db,array_replace($q,['zoom'=>8]));
$assert($r['mode']==='clusters' && $r['count']===260 && array_sum(array_column($r['clusters'],'count'))===260,'Clusters count unique active standalone Resources only');
$assert($r['resources']===[],'Cluster payload has no individual records');
$r=llama_resource_map_query($db,array_replace($q,['zoom'=>6]));
$assert($r['mode']==='hidden' && $r['count']===0,'Low zoom hides Resources');
$r=llama_resource_map_query($db,array_replace($q,['services'=>'']));
$assert($r['mode']==='hidden','Disabled categories do not query Resources');
$r=llama_resource_map_query($db,array_replace($q,['search'=>'Water 260']));
$assert($r['count']===1 && $r['resources'][0]['name']==='Water 260' && !$r['truncated'],'Search precedes result cap');
$r=llama_resource_map_query($db,array_replace($q,['services'=>'laundry']));
$assert($r['count']===1 && $r['resources'][0]['name']==='Laundry','Category filtering');
$r=llama_resource_map_query($db,['south'=>59,'north'=>61,'west'=>179,'east'=>181,'zoom'=>10,'services'=>'potable_water']);
$assert($r['count']===2,'Dateline crossing');
$r=llama_resource_map_query($db,['south'=>59,'north'=>61,'west'=>539,'east'=>541,'zoom'=>10,'services'=>'potable_water']);
$assert($r['count']===2,'Wrapped world bounds');
try { llama_resource_map_query($db,array_replace($q,['services'=>'bad'])); throw new LogicException('Invalid type accepted'); }
catch (InvalidArgumentException $e) {}
echo "PASS: viewport filtering, limit, unique clusters, search, dateline and Facility exclusion.\n";
