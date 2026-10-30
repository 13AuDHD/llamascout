<?php
declare(strict_types=1);

/**
 * M2-008L: READ-ONLY legacy field / V3 registry comparison.
 *
 * Run from the V3 repository root:
 *   php tools/m2-008l-legacy-mapping-audit.php
 *
 * Does not query the database or write files.
 * Output: CSV to stdout. Redirect to a file if desired.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/place-report/approved-questions.php';

$inventory = json_decode(<<<'INVENTORY'
[{"path":"access.downedTreeRisk","uses":2},{"path":"access.dropOffExposure","uses":2},{"path":"access.fourWheelDriveRecommended","uses":2},{"path":"access.highClearanceRecommended","uses":2},{"path":"access.mudRisk","uses":2},{"path":"access.potholes","uses":2},{"path":"access.roadDifficulty","uses":2},{"path":"access.roadOverallDifficulty","uses":2},{"path":"access.roadStress","uses":2},{"path":"access.roadSurface","uses":2},{"path":"access.roadWidth","uses":2},{"path":"access.rocks","uses":2},{"path":"access.seasonalClosure","uses":2},{"path":"access.sedanAccessible","uses":2},{"path":"access.siteAccessDifficulty","uses":2},{"path":"access.steepGrades","uses":2},{"path":"access.washboards","uses":2},{"path":"access.waterCrossings","uses":2},{"path":"amenities.bear_box","uses":8},{"path":"amenities.bearBox","uses":2},{"path":"amenities.dump_station","uses":8},{"path":"amenities.dumpStation","uses":2},{"path":"amenities.electricity","uses":10},{"path":"amenities.fire_ring","uses":8},{"path":"amenities.fireRing","uses":2},{"path":"amenities.foodStorageRequired","uses":2},{"path":"amenities.laundry","uses":8},{"path":"amenities.picnic_table","uses":8},{"path":"amenities.picnicTable","uses":2},{"path":"amenities.potable_water","uses":8},{"path":"amenities.potableWater","uses":2},{"path":"amenities.showers","uses":10},{"path":"amenities.toilets","uses":10},{"path":"amenities.trash","uses":10},{"path":"amenities.wifi","uses":8},{"path":"connectivity.att","uses":10},{"path":"connectivity.other","uses":2},{"path":"connectivity.other_cell","uses":8},{"path":"connectivity.overall","uses":10},{"path":"connectivity.starlink","uses":10},{"path":"connectivity.starlink_note","uses":8},{"path":"connectivity.starlink_tested","uses":8},{"path":"connectivity.starlinkNote","uses":2},{"path":"connectivity.starlinkTested","uses":2},{"path":"connectivity.t_mobile","uses":8},{"path":"connectivity.tMobile","uses":2},{"path":"connectivity.verizon","uses":10},{"path":"details.accessible_picnic_table","uses":8},{"path":"details.accessible_toilet","uses":8},{"path":"details.back_in","uses":8},{"path":"details.bugs","uses":8},{"path":"details.campsite_count","uses":1},{"path":"details.cliff_exposure","uses":8},{"path":"details.critter_activity","uses":4},{"path":"details.desert","uses":5},{"path":"details.desert_view","uses":5},{"path":"details.downed_tree_risk","uses":8},{"path":"details.drop_off_exposure","uses":8},{"path":"details.emergency_access","uses":8},{"path":"details.environment_open_sky","uses":8},{"path":"details.environment_shade","uses":8},{"path":"details.fall_hazard","uses":8},{"path":"details.felt_safe_daytime","uses":8},{"path":"details.felt_safe_nighttime","uses":8},{"path":"details.flash_flood_risk","uses":8},{"path":"details.flat_walking_surface","uses":8},{"path":"details.forest","uses":5},{"path":"details.forest_view","uses":5},{"path":"details.four_wheel_drive_recommended","uses":8},{"path":"details.ground_condition","uses":8},{"path":"details.high_clearance_recommended","uses":8},{"path":"details.hookup_electric","uses":1},{"path":"details.hookup_electric_service","uses":1},{"path":"details.hookup_sewer","uses":1},{"path":"details.hookup_water","uses":1},{"path":"details.landscape_details","uses":4},{"path":"details.landscape_primary","uses":4},{"path":"details.landscape_views","uses":4},{"path":"details.leveling_required","uses":8},{"path":"details.levelness","uses":8},{"path":"details.max_rv_length_feet","uses":1},{"path":"details.max_trailer_length_feet","uses":8},{"path":"details.max_vehicle_length_feet","uses":8},{"path":"details.mobility_device_friendly","uses":8},{"path":"details.mountain_view","uses":5},{"path":"details.mountains","uses":5},{"path":"details.mud_risk","uses":8},{"path":"details.parking_surface","uses":8},{"path":"details.potholes","uses":8},{"path":"details.pull_through","uses":8},{"path":"details.road_difficulty","uses":8},{"path":"details.road_exposure","uses":8},{"path":"details.road_overall_difficulty","uses":8},{"path":"details.road_stress","uses":8},{"path":"details.road_surface","uses":8},{"path":"details.road_width","uses":8},{"path":"details.rockfall_risk","uses":8},{"path":"details.rocks","uses":8},{"path":"details.rv_suitable","uses":8},{"path":"details.seasonal_closure","uses":8},{"path":"details.sedan_accessible","uses":8},{"path":"details.site_access_difficulty","uses":8},{"path":"details.site_hookups_available","uses":1},{"path":"details.site_number","uses":1},{"path":"details.site_open_sky","uses":8},{"path":"details.site_shade","uses":8},{"path":"details.steep_grades","uses":8},{"path":"details.step_free_access","uses":8},{"path":"details.sun_exposure","uses":8},{"path":"details.tent_camping_suitable","uses":8},{"path":"details.traffic_hazard","uses":8},{"path":"details.trailer_suitable","uses":8},{"path":"details.tree_cover","uses":8},{"path":"details.turnaround_space","uses":8},{"path":"details.vehicle_capacity","uses":8},{"path":"details.walking_distance_from_vehicle","uses":8},{"path":"details.warning_blind_turn_traffic_nearby","uses":8},{"path":"details.warning_motorized_recreation_traffic","uses":8},{"path":"details.warning_no_amenities","uses":8},{"path":"details.warning_passing_vehicle_dust","uses":8},{"path":"details.warning_possible_downed_trees","uses":8},{"path":"details.washboards","uses":8},{"path":"details.water_crossings","uses":8},{"path":"details.water_nearby","uses":8},{"path":"details.water_view","uses":5},{"path":"details.wheelchair_friendly","uses":8},{"path":"details.wildfire_risk","uses":8},{"path":"details.wildlife","uses":8},{"path":"details.wildlife_risk","uses":8},{"path":"details.wind_exposure","uses":8},{"path":"environment.bugs","uses":2},{"path":"environment.forest","uses":2},{"path":"environment.forestView","uses":2},{"path":"environment.mountains","uses":2},{"path":"environment.mountainView","uses":2},{"path":"environment.openSky","uses":2},{"path":"environment.shade","uses":2},{"path":"environment.sunExposure","uses":2},{"path":"environment.waterNearby","uses":2},{"path":"environment.waterView","uses":2},{"path":"environment.wildlife","uses":2},{"path":"environment.windExposure","uses":2},{"path":"rules.best_months","uses":8},{"path":"rules.campfire_allowed","uses":8},{"path":"rules.check_in_begins","uses":1},{"path":"rules.check_in_required","uses":1},{"path":"rules.check_out_required","uses":1},{"path":"rules.checkout_ends","uses":1},{"path":"rules.collecting_firewood","uses":8},{"path":"rules.current_fire_restrictions_url","uses":8},{"path":"rules.designated_sites_only","uses":8},{"path":"rules.dispersed_camping_allowed","uses":8},{"path":"rules.dogs_required_to_be_leashed","uses":8},{"path":"rules.drone_use_legal","uses":8},{"path":"rules.entrance_facility_fee","uses":1},{"path":"rules.existing_sites_encouraged","uses":8},{"path":"rules.fee","uses":8},{"path":"rules.food_storage_required","uses":8},{"path":"rules.generator_quiet_hours","uses":1},{"path":"rules.generator_quiet_hours_begin","uses":1},{"path":"rules.generator_quiet_hours_end","uses":1},{"path":"rules.generator_restrictions","uses":8},{"path":"rules.heat_season_risk","uses":1},{"path":"rules.hurricane_risk","uses":4},{"path":"rules.membership_fee","uses":1},{"path":"rules.membership_required","uses":1},{"path":"rules.membership_url","uses":1},{"path":"rules.monsoon_risk","uses":8},{"path":"rules.mud_season_risk","uses":8},{"path":"rules.nearest_alcohol_sales","uses":8},{"path":"rules.nearest_ev_charging","uses":8},{"path":"rules.nearest_fuel","uses":8},{"path":"rules.nearest_grocery","uses":8},{"path":"rules.nearest_hospital","uses":8},{"path":"rules.nearest_propane","uses":8},{"path":"rules.nearest_toilet","uses":8},{"path":"rules.nearest_town","uses":8},{"path":"rules.nearest_water","uses":8},{"path":"rules.overnight_camping_allowed","uses":8},{"path":"rules.pack_it_in_pack_it_out","uses":8},{"path":"rules.parking_fee","uses":1},{"path":"rules.pets_allowed","uses":8},{"path":"rules.recommended_travel_season","uses":8},{"path":"rules.reservation_fee","uses":1},{"path":"rules.reservation_required","uses":1},{"path":"rules.reservation_url","uses":1},{"path":"rules.residential_use_prohibited","uses":8},{"path":"rules.seasonal_access_note","uses":8},{"path":"rules.snow_risk","uses":8},{"path":"rules.stay_limit_days","uses":8},{"path":"rules.target_shooting_allowed","uses":8},{"path":"rules.winter_access","uses":8},{"path":"safety.cliffExposure","uses":2},{"path":"safety.emergencyAccess","uses":2},{"path":"safety.fallHazard","uses":2},{"path":"safety.feltSafeDaytime","uses":2},{"path":"safety.feltSafeNighttime","uses":2},{"path":"safety.flashFloodRisk","uses":2},{"path":"safety.rockfallRisk","uses":2},{"path":"safety.trafficHazard","uses":2},{"path":"safety.wildfireRisk","uses":2},{"path":"safety.wildlifeRisk","uses":2},{"path":"sensory.aircraftNoise","uses":2},{"path":"sensory.daytime","uses":10},{"path":"sensory.details","uses":8},{"path":"sensory.dustFromTraffic","uses":2},{"path":"sensory.generatorNoise","uses":2},{"path":"sensory.humanActivity","uses":2},{"path":"sensory.nighttime","uses":10},{"path":"sensory.predictability","uses":2},{"path":"sensory.roadNoise","uses":2},{"path":"sensory.smokeRisk","uses":2},{"path":"sensory.strongOdors","uses":2},{"path":"sensory.visualExposure","uses":2},{"path":"sensory.wildlifeNoise","uses":2},{"path":"sensory.windNoise","uses":2},{"path":"site.backIn","uses":2},{"path":"site.groundCondition","uses":2},{"path":"site.levelingRequired","uses":2},{"path":"site.levelness","uses":2},{"path":"site.maxVehicleLengthFeet","uses":2},{"path":"site.openSky","uses":2},{"path":"site.parkingSurface","uses":2},{"path":"site.pullThrough","uses":2},{"path":"site.rvSuitable","uses":2},{"path":"site.shade","uses":2},{"path":"site.tentCampingSuitable","uses":2},{"path":"site.trailerSuitable","uses":2},{"path":"site.treeCover","uses":2},{"path":"site.turnaroundSpace","uses":2},{"path":"site.vehicleCapacity","uses":2}]
INVENTORY, true, 512, JSON_THROW_ON_ERROR);

$registry = llama_place_report_fields();
$approved = llama_place_report_approved_question_groups();
$approvedKeys = [];
foreach ($approved as $group) {
    foreach (($group[1] ?? []) as $key) {
        $approvedKeys[(string)$key] = true;
    }
}
function m2008l_normalize(string $path): string
{
    // Compare names cautiously: this only produces candidates, never an automatic mapping.
    $path = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $path);
    return strtolower((string)$path);
}
$byStorage = [];
$byNormalizedStorage = [];
foreach ($registry as $key => $field) {
    $storage = (string)($field['storage'] ?? '');
    if ($storage === '') continue;
    $byStorage[$storage][] = (string)$key;
    $byNormalizedStorage[m2008l_normalize($storage)][] = (string)$key;
}
$out = fopen('php://output', 'w');
fputcsv($out, ['legacy_path','submission_count','classification','candidate_v3_keys','approved_inventory','notes']);
foreach ($inventory as $record) {
    $path = $record['path'];
    $keys = $byStorage[$path] ?? [];
    $kind = 'unresolved';
    $notes = 'No storage-path match; preserve legacy value';
    if (count($keys) === 1) {
        $kind = 'exact_storage_path';
        $notes = 'Identity candidate only; validate values, scope and answer state';
    } elseif (count($keys) > 1) {
        $kind = 'ambiguous_exact_storage';
        $notes = 'Multiple registry fields share this storage path';
    } else {
        $keys = array_values(array_unique($byNormalizedStorage[m2008l_normalize($path)] ?? []));
        if ($keys) {
            $kind = count($keys) === 1 ? 'normalized_name_candidate' : 'ambiguous_name_candidate';
            $notes = 'Case/underscore conversion candidate; verify manually';
        }
    }
    $approval = !$keys ? 'not_matched' : implode('|', array_map(
        static fn(string $k): string => $k . ':' . (isset($approvedKeys[$k]) ? 'approved' : 'not_in_inventory'),
        $keys
    ));
    fputcsv($out, [$path, $record['uses'], $kind, implode('|', $keys), $approval, $notes]);
}
foreach ($registry as $key => $_field) {
    if (!isset($approvedKeys[(string)$key])) {
        fputcsv($out, ['', 0, 'registry_not_in_approved_inventory', (string)$key, 'not_in_inventory',
            'Review definition vs approved PDF; do not automatically add question']);
    }
}
fclose($out);