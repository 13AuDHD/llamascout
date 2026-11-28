<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/resource-address-enrichment.php';
$assert=static function(bool $ok,string $message):void {if (!$ok) throw new RuntimeException('FAIL: '.$message);};
$r=['id'=>1,'state_region'=>'Colorado','country_code'=>'US','city'=>'','street_address'=>'','postal_code'=>'','county'=>''];
$raw=['street'=>'Main St','housenumber'=>'42','city'=>'Durango','county'=>'La Plata County','state'=>'Colorado','state_code'=>'CO','country_code'=>'us','postcode'=>'81301','distance'=>12,'result_type'=>'building','rank'=>['confidence'=>0.95]];
$c=llama_resource_address_candidate($r,$raw);
$assert($c['automatic']['street_address']==='42 Main St','Close numbered building accepted');
$assert(!isset($c['automatic']['state_region']),'Existing state preserved');
$assert($c['automatic']['city']==='Durango','City filled');
$raw['distance']=90;$c=llama_resource_address_candidate($r,$raw);
$assert(!isset($c['automatic']['street_address']) && isset($c['fields']['street_address']),'Distant street candidate review only');
$raw['state']='Utah';$raw['state_code']='UT';$c=llama_resource_address_candidate($r,$raw);
$assert($c['automatic']===[],'Conflicting state blocked');
$assert(llama_resource_address_candidate($r,[])['automatic']===[],'No result does not invent data');
echo "PASS: address candidate matching and preservation.\n";
