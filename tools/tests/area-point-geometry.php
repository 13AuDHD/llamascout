<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/place-map-features.php';
if (!isset(llama_place_map_feature_types()['facility_area'])) { exit(1); }
$point = llama_place_map_feature_validate_point(['type'=>'Point','coordinates'=>[-107.88012345,37.27531234]]);
if (llama_place_map_feature_point_wkt($point) !== 'POINT(-107.8801235 37.2753123)') { exit(1); }
foreach ([['type'=>'Point','coordinates'=>[0,0]],['type'=>'Point','coordinates'=>[181,38]],['type'=>'Polygon','coordinates'=>[]]] as $bad) {
  try { llama_place_map_feature_validate_point($bad); exit(1); } catch (InvalidArgumentException) {}
}
echo "Area point validation passed.\n";
