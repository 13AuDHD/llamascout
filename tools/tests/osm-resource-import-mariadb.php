<?php
declare(strict_types=1);
$dsn = (string)getenv('LLAMA_OSM_TEST_DSN');
if (!preg_match('/(?:^|;)dbname=llama_osm_import_test(?:;|$)/',$dsn)) throw new RuntimeException('Disposable test DB required.');
require_once dirname(__DIR__,2) . '/app/osm-resource-import.php';
$db = new PDO($dsn,(string)getenv('LLAMA_OSM_TEST_USER'),(string)getenv('LLAMA_OSM_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
$assert = static function(bool $ok,string $message): void { if (!$ok) throw new RuntimeException('FAIL: ' . $message); };
$root = dirname(__DIR__,2);
$db->exec(file_get_contents($root . '/tools/migrations/20261127_osm_nearest_services.sql'));
$db->exec(file_get_contents($root . '/tools/migrations/20261127_osm_resource_import.sql'));
$date = gmdate('Y-m-d');
$old = gmdate('Y-m-d',time()-86400);
$db->prepare("INSERT INTO osm_nearest_services VALUES ('colorado','node',1,'fuel','Original',37,-107,?)")->execute([$old]);
$batch = str_repeat('a',32);
$db->prepare("INSERT INTO osm_resource_import_jobs (region,batch_id,status) VALUES ('colorado',?,'queued')")->execute([$batch]);
$run = static fn(string $action,array $input=[])=>llama_osm_resource_worker($db,['action'=>$action,'region'=>'colorado','batch_id'=>$batch]+$input);
$row = static fn(int $id,string $service,string $name)=>['osm_type'=>'node','osm_id'=>$id,'service_type'=>$service,'name'=>$name,'latitude'=>37.1,'longitude'=>-107.1];
$live = static fn()=> $db->query("SELECT name FROM osm_nearest_services WHERE region='colorado' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$reject = static function(callable $call) use ($assert): void {
    try { $call(); } catch (InvalidArgumentException | RuntimeException $e) { return; }
    $assert(false,'Expected invalid import to fail');
};
$assert(count(llama_osm_resource_states())===51,'All states and DC');
$assert(array_keys(llama_osm_resource_priorities())[0]==='potable_water','Water first');
$assert(llama_osm_resource_inventory($db)['colorado']['snapshot']['snapshot_date']===$old,'Recognize manually imported states');
$run('begin',['snapshot_date'=>$date,'count'=>3]);
$run('chunk',['seq'=>0,'rows'=>[$row(2,'potable_water','Water'),$row(2,'dump_station','Water')]]);
$run('chunk',['seq'=>0,'rows'=>[$row(2,'potable_water','Water'),$row(2,'dump_station','Water')]]);
$assert($live()===['Original'],'Old snapshot remains live during transfer');
$reject(static fn()=> $run('finish'));
$reject(static fn()=> $run('chunk',['seq'=>3,'rows'=>[$row(3,'shower','Shower')]]));
$run('chunk',['seq'=>1,'rows'=>[$row(3,'shower','Shower')]]);
$result = $run('finish');
$assert($result['status']==='complete' && (int)$result['received_rows']===3,'All rows committed');
$assert($live()===['Shower','Water','Water'],'Complete replacement only');
$assert($run('finish')['status']==='complete','Finish retry is idempotent');
$assert((int)$db->query('SELECT COUNT(*) FROM osm_resource_import_stage')->fetchColumn()===0,'Staging cleaned');
$batch2 = str_repeat('b',32);
$db->prepare("UPDATE osm_resource_import_jobs SET batch_id=?,status='queued',snapshot_date=NULL,expected_rows=0,received_rows=0,next_chunk=0 WHERE region='colorado'")->execute([$batch2]);
$reject(static fn()=> $run('begin',['snapshot_date'=>$date,'count'=>1]));
$run2 = static fn(string $action,array $input=[])=>llama_osm_resource_worker($db,['action'=>$action,'region'=>'colorado','batch_id'=>$batch2]+$input);
$run2('begin',['snapshot_date'=>$old,'count'=>1]);
$run2('chunk',['seq'=>0,'rows'=>[$row(4,'laundry','Laundry')]]);
$reject(static fn()=> $run2('finish'));
$assert($live()===['Shower','Water','Water'],'Older snapshot cannot replace newer data');
$run2('fail');
$assert($live()===['Shower','Water','Water'],'Failure preserves live data');
$assert((int)$db->query('SELECT COUNT(*) FROM osm_resource_import_stage')->fetchColumn()===0,'Failed stage cleaned');
// No oversized snapshot can even enter staging under the default limit.
$batch3 = str_repeat('c',32);
$db->prepare("UPDATE osm_resource_import_jobs SET batch_id=?,status='queued',snapshot_date=NULL,expected_rows=0,received_rows=0,next_chunk=0 WHERE region='colorado'")->execute([$batch3]);
$command = ['action'=>'begin','region'=>'colorado','batch_id'=>$batch3,'snapshot_date'=>$date,'count'=>LLAMA_OSM_IMPORT_DEFAULT_MAX_ROWS+1];
$reject(static fn()=>llama_osm_resource_worker($db,$command));
$assert($live()===['Shower','Water','Water'],'Oversized refresh preserves live data');
$command['count']=1;
try {
    llama_osm_resource_paced_worker($db,$command);
    $assert(false,'Recent write must enforce cooldown');
} catch (LlamaOsmImportBusy $e) {}
$db->exec("UPDATE osm_resource_import_jobs SET updated_at=UTC_TIMESTAMP()-INTERVAL 5 SECOND");
$second = new PDO($dsn,(string)getenv('LLAMA_OSM_TEST_USER'),(string)getenv('LLAMA_OSM_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$globalLock = 'llama-osm-writes-' . substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
$hold = $second->prepare('SELECT GET_LOCK(?,0)');
$hold->execute([$globalLock]);
$assert((int)$hold->fetchColumn()===1,'Second worker holds global lock');
try {
    llama_osm_resource_paced_worker($db,$command);
    $assert(false,'Concurrent state write must be rejected');
} catch (LlamaOsmImportBusy $e) {}
$second->prepare('SELECT RELEASE_LOCK(?)')->execute([$globalLock]);
$assert(llama_osm_resource_paced_worker($db,$command)['status']==='importing','Resume after global cooldown');
$run3 = static fn(string $action,array $input=[])=>llama_osm_resource_worker($db,['action'=>$action,'region'=>'colorado','batch_id'=>$batch3]+$input);
$reject(static fn()=> $run3('chunk',['seq'=>0,'rows'=>array_fill(0,101,$row(5,'fuel','Fuel'))]));
$run3('fail');
$reject(static fn()=>llama_osm_resource_validate_regions(['../../private']));
echo "PASS: state inventory, staged transfer, idempotent retries, atomic refresh, stale worker and older data protection.\n";
