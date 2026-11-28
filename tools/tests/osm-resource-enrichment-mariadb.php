<?php
declare(strict_types=1);
$dsn=(string)getenv('LLAMA_OSM_TEST_DSN');
if (!preg_match('/(?:^|;)dbname=llama_osm_import_test(?:;|$)/',$dsn)) throw new RuntimeException('Disposable test database required.');
require_once dirname(__DIR__,2).'/app/osm-resource-enrichment.php';
$db=new PDO($dsn,(string)getenv('LLAMA_OSM_TEST_USER'),(string)getenv('LLAMA_OSM_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
$assert=static function(bool $ok,string $message):void { if (!$ok) throw new RuntimeException('FAIL: '.$message); };
// Run after publication integration, which creates the native Resource schema.
$db->exec(file_get_contents(dirname(__DIR__,2).'/tools/migrations/20261128_osm_resource_enrichment.sql'));
$db->exec('ALTER TABLE resources ADD COLUMN IF NOT EXISTS county VARCHAR(120) NULL');
$db->exec('DELETE FROM osm_resource_enrichment_jobs; DELETE FROM osm_resource_enrichment_facts');
$db->exec("DELETE FROM osm_resource_publications WHERE osm_id=987654321; DELETE FROM resources WHERE source_external_id='node/987654321'");
$db->exec("INSERT INTO resources (resource_type,name,slug,latitude,longitude,city,source_provider,source_external_id,hours_mode)
    VALUES ('water','Enrichment fixture','enrichment-fixture',37,-107,'Existing city','OpenStreetMap','node/987654321','unknown')");
$id=(int)$db->lastInsertId();
$stmt=$db->prepare("INSERT INTO osm_resource_publications (osm_type,osm_id,region,source_snapshot,disposition,resource_id) VALUES ('node',987654321,'colorado',?,'published',?)");
$stmt->execute([json_encode(['latitude'=>37,'longitude'=>-107]),$id]);
function ev(int $v):string { $o=''; while ($v>=128) {$o.=chr(($v&127)|128);$v>>=7;} return $o.chr($v); }
function en(int $f,int $v):string {return ev($f*8).ev($v);}
function es(int $f,string $v):string {return ev($f*8+2).ev(strlen($v)).$v;}
function ez(int $v):int {return $v<0?-2*$v-1:2*$v;}
function ef(string $type,string $payload):string {
    $blob=en(2,strlen($payload)).es(3,gzcompress($payload));
    $header=es(1,$type).en(3,strlen($blob));
    return pack('N',strlen($header)).$header.$blob;
}
$strings='';
foreach (['','addr:city','Source city','contact:phone','555-0100','opening_hours','24/7','website','https://example.com','addr:street','Main St','addr:housenumber','42'] as $s) $strings.=es(1,$s);
$keys=ev(1).ev(3).ev(5).ev(7).ev(9).ev(11); $vals=ev(2).ev(4).ev(6).ev(8).ev(10).ev(12);
$node=en(1,ez(987654321)).es(2,$keys).es(3,$vals).en(8,ez(370000000)).en(9,ez(-1070000000));
$way=en(1,111).es(2,$keys).es(3,$vals);
$relation=en(1,222).es(2,$keys).es(3,$vals);
$dense=es(1,ev(ez(333))).es(8,ev(ez(370000000))).es(9,ev(ez(-1070000000))).es(10,ev(3).ev(4).ev(0));
$block=es(1,$strings).es(2,es(1,$node).es(3,$way).es(4,$relation).es(2,$dense));
$matched=iterator_to_array(llama_enrichment_block($block,['node'=>[987654321=>true,333=>true],'way'=>[111=>true],'relation'=>[222=>true]]),false);
$assert(count($matched)===4,'All OSM object types including dense nodes decoded');
$assert($matched[3]['tags']['contact:phone']==='555-0100','Dense node tags retained');
$assert(llama_enrichment_values(['website'=>'javascript:alert(1)'])===[],'Unsafe URL excluded');
$header=ef('OSMHeader',es(4,'OsmSchema-V0.6').es(4,'DenseNodes').en(32,time()-60));
$file=$header.ef('OSMData',$block);
$fetch=static fn($region,$offset,$length,$validator)=>['data'=>substr($file,$offset,$length),'size'=>strlen($file),'validator'=>'etag:"fixture"'];
$first=llama_enrichment_step($db,'colorado',$fetch);
$assert($first['matches']===0,'Header checkpoint');
$changed=static fn($region,$offset,$length,$validator)=>['data'=>substr($file,$offset,$length),'size'=>strlen($file),'validator'=>'etag:"changed"'];
try { llama_enrichment_step($db,'colorado',$changed); throw new LogicException('Changed source accepted'); }
catch (RuntimeException $e) { $assert(str_contains($e->getMessage(),'changed'),'Changed source rejected'); }
$second=llama_enrichment_step($db,'colorado',$fetch);
$assert($second['matches']===1 && $second['percent']===100,'Exact identity scan complete');
$row=$db->query('SELECT * FROM resources WHERE id='.$id)->fetch();
$assert($row['city']==='Existing city' && $row['street_address']==='42 Main St' && $row['phone']==='555-0100' && $row['website_url']==='https://example.com','Only blank contact fields filled');
$assert($row['hours_mode']==='24_hours' && $row['verified_at']===null,'Hours filled without verification');
$assert(llama_enrichment_step($db,'colorado',$fetch)['done']===true,'Completed state resumes without rescanning');
echo "PASS: exact PBF identities, safe autofill, source version protection and resumable completion.\n";
