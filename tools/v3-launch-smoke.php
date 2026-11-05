<?php
declare(strict_types=1);

/** V3 launch smoke tests. No DB, network, or live data writes. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/place-report/scoped-answers.php';
require_once dirname(__DIR__) . '/app/place-report/diagnostics.php';

$checks = 0;
$failures = [];
$check = static function (string $name, callable $fn) use (&$checks, &$failures): void {
    ++$checks;
    try { $fn(); echo "PASS: $name\n"; }
    catch (Throwable $e) { $failures[] = "$name: " . $e->getMessage(); echo "FAIL: $name\n"; }
};
$assert = static function (bool $condition, string $why): void {
    if (!$condition) throw new RuntimeException($why);
};

$ruleUsesOnlyType = null;
$ruleUsesOnlyType = static function (array $rule) use (&$ruleUsesOnlyType): bool {
    if (isset($rule['rules'])) {
        foreach ((array) $rule['rules'] as $nested) {
            if (!is_array($nested) || !$ruleUsesOnlyType($nested)) {
                return false;
            }
        }
        return true;
    }

    return (($rule['field'] ?? null) === 'type');
};

$assertNoAnswerDependencies = static function (
    array $field,
    string $why
) use ($assert, &$ruleUsesOnlyType): void {
    foreach ((array) ($field['applicable_if'] ?? []) as $rule) {
        $assert(
            is_array($rule) && $ruleUsesOnlyType($rule),
            $why
        );
    }
};
$fields = llama_place_report_fields();
$check('Registry loaded', static function () use ($fields, $assert): void {
    $assert(count($fields) > 100, 'Expected canonical field inventory');
    $assert(isset($fields['daytime_noise'], $fields['nighttime_noise'], $fields['site_length_feet']), 'Missing core questions');
});
$check('Only approved questions appear in the shared catalog', static function () use ($fields, $assert): void {
    $catalog = llama_report_questions();
    $assert(isset($catalog['max_rv_length_feet']), 'Approved maximum RV length is missing');
    $assert(isset($catalog['rv_suitable'], $catalog['tent_camping_suitable']),
        'Approved Site suitability questions are missing');
    $assert(!isset($catalog['max_trailer_length_feet']), 'Retired trailer-length question is visible');
    $assert(!isset($catalog['trailer_suitable']), 'Unapproved trailer suitability is visible');
    foreach ($catalog as $key => $field) {
        $assert(!empty($field['approved_question']) && empty($field['derived']),
            "Non-approved question in catalog: $key");
    }
    foreach (llama_report_group_selected_questions($fields) as $group => $definition) {
        $assert($group !== 'additional_questions', 'Extra question group was returned');
        foreach ($definition['fields'] as $key => $_field) {
            $assert(isset($catalog[$key]), "Unapproved grouped question: $key");
        }
    }
});
$check('Normal reviewed applicability is active', static function () use ($assert): void {
    $assert(!llama_place_report_full_inventory_review_enabled(),
        'Temporary inventory review bypass is still enabled');

    $catalog = llama_report_questions();
    foreach (['heat_season_risk', 'tornado_risk', 'amenity_river_access', 'amenity_lake_access',
        'amenity_campground_host', 'site_hookups_available', 'hookup_electric_service'] as $key) {
        $assert(isset($catalog[$key]), 'Missing approved question after review mode: ' . $key);
    }

    $assert(($catalog['hookup_electric_service']['applicable_if'][0] ?? []) === [
        'field' => 'site_hookups_available',
        'operator' => 'equals',
        'value' => '1',
    ], 'Electrical Service dependency is not active');

    $assert(($catalog['tent_pad_width_feet']['applicable_if'][0] ?? []) === [
        'field' => 'tent_pad',
        'operator' => 'equals',
        'value' => '1',
    ], 'Tent Pad Width dependency is not active');

    $assert((array) ($catalog['max_rv_length_feet']['applicable_if'] ?? []) === [],
        'Maximum RV / trailer length should remain independent');

    $groups = llama_place_report_approved_question_groups();
    $seasonal = (array) $groups['seasonal_access'][1];
    $expectedSeasonal = [
        'best_months',
        'seasonal_closure',
        'season_begins',
        'season_ends',
        'heat_season_risk',
        'hurricane_risk',
        'monsoon_risk',
        'tornado_risk',
        'mud_season_risk',
        'snow_risk',
        'approach_winter_maintenance',
        'approach_snow_chains',
    ];
    $assert($seasonal === $expectedSeasonal,
        'Seasonal Access order does not match the reviewed inventory');

    $seasonFields = llama_report_questions();
    $assert(($seasonFields['best_months']['label'] ?? '') === 'Best Seasons',
        'Best Months was not renamed to Best Seasons');
    $assert(($seasonFields['season_begins']['label'] ?? '') === 'Closure Ends',
        'Season Begins was not renamed to Closure Ends');
    $assert(($seasonFields['season_ends']['label'] ?? '') === 'Closure Begins',
        'Season Ends was not renamed to Closure Begins');
    $assert(($seasonFields['monsoon_risk']['label'] ?? '') === 'Monsoon Season Risk',
        'Monsoon Risk was not renamed');
    $assert(($seasonFields['approach_winter_maintenance']['options'] ?? []) === [
        'not_applicable' => 'Not Applicable',
        'occasional' => 'Occasionally Plowed',
        'regular' => 'Frequently Plowed',
        'none' => 'Not Maintained',
    ], 'Winter Road Maintenance answers do not match the reviewed set');
    $assert(($seasonFields['approach_snow_chains']['options'] ?? []) === [
        'not_applicable' => 'Not Applicable',
        'no' => 'No',
        'conditional' => 'Recommended',
        'required' => 'Required',
    ], 'Snow Chains answers do not match the reviewed set');
    $setting = (array) $groups['setting'][1];
    $expectedSetting = [
        'landscape_primary',
        'environment_water_nearby',
        'landscape_details',
        'landscape_views',
        'environment_wildlife_activity',
        'critter_activity',
        'environment_bug_nuisance',
        'environment_open_sky',
        'environment_sun_exposure',
        'environment_shade',
        'tree_cover',
        'environment_notes',
    ];
    $assert($setting === $expectedSetting,
        'Setting and Environment order does not match the reviewed inventory');

    $settingFields = llama_report_questions();
    $expectedSettingLabels = [
        'landscape_primary' => 'Primary Setting',
        'environment_water_nearby' => 'Water Nearby',
        'landscape_details' => 'Setting Details',
        'landscape_views' => 'Views',
        'environment_wildlife_activity' => 'Wildlife Activity',
        'critter_activity' => 'Critter Activity',
        'environment_bug_nuisance' => 'Bug Nuisance',
        'environment_open_sky' => 'Open Sky',
        'environment_sun_exposure' => 'Sun Exposure',
        'environment_shade' => 'Environmental Shade',
        'tree_cover' => 'Tree Cover',
        'environment_notes' => 'Environmental Notes',
    ];
    foreach ($expectedSettingLabels as $key => $label) {
        $assert(($settingFields[$key]['label'] ?? '') === $label,
            'Unexpected Setting and Environment label for ' . $key);
    }
    foreach ([
        'environment_wildlife_activity',
        'critter_activity',
        'environment_bug_nuisance',
        'environment_open_sky',
        'environment_sun_exposure',
        'environment_shade',
        'tree_cover',
    ] as $key) {
        $assert(($settingFields[$key]['type'] ?? '') === 'rating',
            $key . ' must use the 1-5 rating scale');
    }
    $assert(!isset($settingFields['environment_wildlife']),
        'Historical Wildlife Common Yes/No question is still approved');
    $assert(!isset($settingFields['environment_bugs']),
        'Historical Bugs Significant Yes/No question is still approved');
    $assert(isset($settingFields['environment_notes']),
        'Environmental Notes is missing from Setting and Environment');

    $roadFields = llama_report_questions();
    $roadTypeOptions = (array) ($roadFields['approach_road_type']['options'] ?? []);
    $assert(($roadTypeOptions['tribal'] ?? '') === 'Native/Tribal road',
        'Access Road Type is missing Native/Tribal road');

    $hazardField = $roadFields['approach_road_hazards'] ?? [];
    $expectedHazards = [
        'approach_deep_ruts' => 'Deep ruts',
        'rocks' => 'Rocks',
        'washboards' => 'Washboards',
        'potholes' => 'Potholes',
        'mud_risk' => 'Mud',
        'steep_grades' => 'Steep grades',
        'drop_off_exposure' => 'Drop-offs',
        'water_crossings' => 'Water crossings',
        'downed_tree_risk' => 'Downed Tree Risk',
        'none' => 'None',
        'unknown' => 'Unknown',
    ];
    $assert(($hazardField['options'] ?? []) === $expectedHazards,
        'Road Hazards Present pill order or wording does not match the reviewed set');
    $assert(empty($hazardField['allow_unknown']),
        'Road Hazards Present must use only the Unknown pill, not the extra Unknown checkbox');

    $assert(!isset($roadFields['water_crossings']),
        'Duplicate Water Crossings follow-up question is still approved');
    $assert(!isset($roadFields['downed_tree_risk']),
        'Duplicate Downed Tree Risk follow-up question is still approved');

    $assert(($roadFields['approach_fee_required']['label'] ?? '') === 'Road access/Parking fee?',
        'Road access/Parking fee question label does not match');
    $assert(($roadFields['approach_road_fee']['label'] ?? '') === 'Road access/Parking fee ($)',
        'Road access/Parking fee amount label does not match');

    $amenities = (array) $groups['amenities'][1];
    $pier = array_search('amenity_fishing_pier', $amenities, true);
    $assert($amenities[$pier - 2] === 'amenity_river_access'
        && $amenities[$pier - 1] === 'amenity_lake_access',
        'River/Lake Access must appear immediately before Fishing Pier');
});

$check('Getting There parent-child rules are declared beneath inventory review', static function () use ($assert): void {
    $makeCapture = static function (array &$captured): callable {
        return static function (
            string $key,
            string $label,
            string $section,
            string $type,
            string $storage,
            array $extra = []
        ) use (&$captured): void {
            $captured[$key] = array_merge([
                'key' => $key,
                'label' => $label,
                'section' => $section,
                'type' => $type,
                'storage' => $storage,
                'applicable_if' => [],
            ], $extra);
        };
    };

    $road = [];
    llama_place_report_add_road_restriction_questions($makeCapture($road));
    $assert(($road['approach_road_fee']['applicable_if'][0] ?? []) === [
        'field' => 'approach_fee_required',
        'operator' => 'equals',
        'value' => '1',
    ], 'Road access/Parking fee amount is not a child of the fee question');
    $assert(($road['approach_gate_access_type']['applicable_if'][0] ?? []) === [
        'field' => 'approach_gate_present',
        'operator' => 'equals',
        'value' => '1',
    ], 'Approach gate type is not a child of Road gated?');
    foreach (['approach_gate_opens', 'approach_gate_closes'] as $key) {
        $assert(($road[$key]['applicable_if'][0] ?? []) === [
            'field' => 'approach_gate_access_type',
            'operator' => 'equals',
            'value' => 'scheduled',
        ], $key . ' is not a child of scheduled gate access');
    }

    $seasonRaw = [];
    $seasonAdd = $makeCapture($seasonRaw);
    llama_place_report_add_season_fields($seasonRaw, $seasonAdd);
    foreach (['season_begins', 'season_ends'] as $key) {
        $assert(($seasonRaw[$key]['applicable_if'][0] ?? []) === [
            'field' => 'seasonal_closure',
            'operator' => 'equals',
            'value' => '1',
        ], $key . ' is not a child of Seasonal Closure');
    }

    $hazards = [];
    foreach ([
        'approach_deep_ruts',
        'rocks',
        'washboards',
        'mud_risk',
        'steep_grades',
        'drop_off_exposure',
    ] as $key) {
        $hazards[$key] = ['applicable_if' => []];
    }
    llama_place_report_apply_road_hazard_dependencies($hazards);
    foreach (array_keys($hazards) as $key) {
        $assert(($hazards[$key]['applicable_if'][0]['field'] ?? '') === 'approach_road_hazards',
            $key . ' is not a child of Road Hazards Present');
        $assert(($hazards[$key]['applicable_if'][0]['value'] ?? []) === [$key],
            $key . ' does not use its matching Road Hazards Present pill');
    }

    $operating = [];
    llama_place_report_add_operating_fields($makeCapture($operating));
    $assert(($operating['place_gate_access_type']['applicable_if'][0]['field'] ?? '') === 'place_entrance_gate',
        'Place Gate Access Type is not a child of Place Gated');
    foreach (['place_gate_opening_time', 'place_gate_closing_time'] as $key) {
        $conditions = (array) ($operating[$key]['applicable_if'] ?? []);
        $assert(count($conditions) === 2
            && ($conditions[0]['field'] ?? '') === 'place_entrance_gate'
            && ($conditions[1]['field'] ?? '') === 'place_gate_access_type'
            && ($conditions[1]['value'] ?? '') === 'scheduled',
            $key . ' is not restricted to a scheduled Place gate');
    }
});

$check('Getting There cleanup keeps only supported active questions', static function () use ($assert): void {
    $fields = llama_place_report_fields();
    foreach ([
        'approach_width_restricted',
        'approach_narrowest_width_feet',
        'road_overall_difficulty',
        'road_stress',
        'access_summary',
        'approach_restriction_details',
        'approach_access_info_url',
        'approach_year_round',
        'approach_seasonal_closure',
        'approach_closure_month',
        'approach_closure_day',
        'approach_reopening_month',
        'approach_reopening_day',
        'approach_seasonal_restrictions',
        'approach_road_conditions_url',
    ] as $key) {
        $assert(!isset($fields[$key]), 'Retired Getting There question is still active: ' . $key);
    }

    $groups = llama_place_report_approved_question_groups();
    $assert((array) $groups['gate_access'][1] === [
        'approach_gate_present',
        'approach_gate_access_type',
        'approach_gate_opens',
        'approach_gate_closes',
    ], 'Getting There Gate Access still contains Place entrance-gate questions');

    foreach ([
        'place_entrance_gate',
        'place_gate_access_type',
        'place_gate_opening_time',
        'place_gate_closing_time',
    ] as $key) {
        $assert(in_array($key, (array) $groups['operating'][1], true),
            'Operating Hours is missing moved Place gate question: ' . $key);
    }

    $layouts = llama_place_report_layout_groups();
    $assert(($layouts['place']['nearest_services'] ?? '') === 'Nearest Services',
        'Nearest Services is not its own Place category');
});

$check('Place Fees and Reservations dependencies match owner review', static function () use ($assert): void {
    $raw = [];
    $add = static function (
        string $key,
        string $label,
        string $section,
        string $type,
        string $storage,
        array $extra = []
    ) use (&$raw): void {
        $raw[$key] = array_merge([
            'key' => $key,
            'label' => $label,
            'section' => $section,
            'type' => $type,
            'storage' => $storage,
            'applicable_if' => [],
        ], $extra);
    };
    llama_place_report_add_rules_service_fields($add, ['0' => 'On site']);

    foreach ([
        'reservation_url' => 'reservation_required',
        'reservation_fee' => 'reservation_required',
        'membership_url' => 'membership_required',
        'membership_fee' => 'membership_required',
        'check_in_begins' => 'check_in_required',
        'checkout_ends' => 'check_out_required',
        'parking_entrance_fee' => 'parking_entrance_required',
    ] as $child => $parent) {
        $assert(($raw[$child]['applicable_if'][0] ?? []) === [
            'field' => $parent,
            'operator' => 'equals',
            'value' => '1',
        ], $child . ' is not a Yes-child of ' . $parent);
    }

    $discounts = $raw['discounts_available'] ?? [];
    $assert(($discounts['type'] ?? '') === 'multiselect',
        'Discounts Available is not a pill multiselect');
    $assert(($discounts['exclusive_options'] ?? []) === ['none', 'unknown'],
        'Discounts Available None/Unknown pills are not exclusive');
    $assert(($discounts['options'] ?? []) === [
        'senior' => 'Senior',
        'military_veteran' => 'Military/Veteran',
        'disability_access' => 'Disability/Access',
        'interagency_pass' => 'Interagency Pass',
        'annual_pass' => 'Annual Pass',
        'resident_local' => 'Resident/Local',
        'membership' => 'Membership',
        'first_responder' => 'First Responder',
        'other' => 'Other',
        'none' => 'None',
        'unknown' => 'Unknown',
    ], 'Discounts Available pill inventory changed unexpectedly');
});

$check('Stay Limit is Basic Place info and Place accessibility is retired', static function () use ($assert): void {
    $groups = llama_place_report_approved_question_groups();
    $assert(in_array('stay_limit_days', (array) $groups['basic'][1], true),
        'Stay Limit is missing from Basic Place info');
    $assert(!in_array('stay_limit_days', (array) $groups['fees'][1], true),
        'Stay Limit is still in Fees and Reservations');

    $layouts = llama_place_report_layout_groups();
    $assert(!isset($layouts['place']['place_accessibility']),
        'Place-level Accessibility layout group still exists');

    $fields = llama_place_report_fields();
    foreach ([
        'place_accessible',
        'shared_accessible_facilities',
        'shared_accessible_facility_types',
        'amenity_accessible_showers',
    ] as $key) {
        $assert(!isset($fields[$key]),
            'Retired Place accessibility question is still active: ' . $key);
    }

    $assert(isset($fields['accessible_toilet'], $fields['accessibility_notes']),
        'Site accessibility questions were accidentally removed');
    $assert(($fields['accessible_toilet']['report_scope_group'] ?? '') === 'site',
        'Accessible Toilet is not Site-only');
    $assert(($fields['accessibility_notes']['report_scope_group'] ?? '') === 'site',
        'Accessibility Notes is not Site-only');
});

$check('Camping and Overnight Stays keeps only approved independent questions', static function () use ($assert, $assertNoAnswerDependencies): void {
    $fields = llama_place_report_fields();
    foreach ([
        'tent_camping_allowed',
        'rv_camping_allowed',
        'car_truck_camping',
        'overnight_restrictions',
        'camping_conditional_details',
        'overnight_vehicle_tent_permit_url',
    ] as $key) {
        $assert(!isset($fields[$key]),
            'Retired overnight question is still active: ' . $key);
    }

    foreach ([
        'overnight_camping_allowed',
        'dispersed_camping_allowed',
        'designated_sites_only',
    ] as $key) {
        $assert(isset($fields[$key]),
            'Approved overnight question is missing: ' . $key);
        $assertNoAnswerDependencies(
            $fields[$key],
            'Approved overnight question gained an answer dependency: ' . $key
        );
    }
});

$check('Rules and Restrictions parent-child rules match owner review', static function () use ($assert): void {
    $raw = [];
    $add = static function (
        string $key,
        string $label,
        string $section,
        string $type,
        string $storage,
        array $extra = []
    ) use (&$raw): void {
        $raw[$key] = array_merge([
            'key' => $key,
            'label' => $label,
            'section' => $section,
            'type' => $type,
            'storage' => $storage,
            'applicable_if' => [],
        ], $extra);
    };

    llama_place_report_add_rules_service_fields($add, ['0' => 'On site']);
    llama_place_report_add_flow_condition_fields($raw, $add);
    llama_place_report_add_permission_fields($raw, $add);
    llama_place_report_add_place_rules_fields($add);

    llama_place_report_apply_flow_conditions($raw);
    llama_place_report_apply_place_rules_metadata($raw);

    $assert(($raw['dogs_required_to_be_leashed']['applicable_if'][0] ?? []) === [
        'field' => 'pets_allowed',
        'operator' => 'equals',
        'value' => '1',
    ], 'Leash Required is not a child of Pets Allowed');

    $equals = static fn (string $field, string $value): array => [
        'field' => $field,
        'operator' => 'equals',
        'value' => $value,
    ];

    $assert(($raw['generator_prohibited']['applicable_if'] ?? []) === [
        $equals('generator_restrictions', '1'),
    ], 'Generators Prohibited is not a child of Generator Restrictions');

    foreach (['generator_quiet_hours', 'generator_run_restrictions'] as $key) {
        $assert(($raw[$key]['applicable_if'] ?? []) === [
            $equals('generator_restrictions', '1'),
            $equals('generator_prohibited', '0'),
        ], $key . ' does not require restrictions Yes and prohibited No');
    }

    foreach (['generator_quiet_hours_begin', 'generator_quiet_hours_end'] as $key) {
        $assert(($raw[$key]['applicable_if'] ?? []) === [
            $equals('generator_restrictions', '1'),
            $equals('generator_prohibited', '0'),
            $equals('generator_quiet_hours', '1'),
        ], $key . ' is not a child of Generator Quiet Hours');
    }

    $assert(($raw['generator_max_run_hours']['applicable_if'] ?? []) === [
        $equals('generator_restrictions', '1'),
        $equals('generator_prohibited', '0'),
        $equals('generator_run_restrictions', '1'),
    ], 'Run-Time Hours is not a child of Run-Time Restrictions');

    $assert(($raw['generator_restriction_details']['applicable_if'] ?? []) === [
        $equals('generator_restrictions', '1'),
    ], 'Restriction Details is not a child of Generator Restrictions');

    foreach (['place_quiet_hours_begin', 'place_quiet_hours_end'] as $key) {
        $assert(($raw[$key]['applicable_if'][0] ?? []) === [
            'field' => 'place_quiet_hours',
            'operator' => 'equals',
            'value' => '1',
        ], $key . ' is not a child of Quiet Hours');
    }

    $permitRules = (array) ($raw['fire_activity_permit_url']['applicable_if'][0]['rules'] ?? []);
    $permitParents = [];
    foreach ($permitRules as $rule) {
        $permitParents[(string) ($rule['field'] ?? '')] = $rule;
    }
    foreach ([
        'campfire_allowed',
        'collecting_firewood',
        'place_charcoal_grills_allowed',
        'place_propane_fire_pits_allowed',
        'drone_use_legal',
        'target_shooting_allowed',
    ] as $key) {
        $assert(($permitParents[$key]['operator'] ?? '') === 'equals'
            && ($permitParents[$key]['value'] ?? '') === '2',
            'Fire / Activity Permit URL is not tied to Permit Required for ' . $key);
    }

    $assert(($raw['current_fire_restrictions_url']['flow_independent_of'] ?? []) === [
        'campfire_allowed',
    ], 'Current Fire Restrictions URL is no longer independent of Campfires Allowed');
});

$check('Retired Rules helpers stay out of the active registry', static function () use ($assert): void {
    $fields = llama_place_report_fields();
    foreach ([
        'fire_activities_conditional_details',
        'camping_permit_url',
    ] as $key) {
        $assert(!isset($fields[$key]),
            'Retired Rules helper is still active: ' . $key);
    }
    $assert(isset($fields['fire_activity_permit_url']),
        'Approved Fire / Activity Permit URL was accidentally removed');
});

$check('Operating Hours and Place amenities match owner review', static function () use ($assert, $assertNoAnswerDependencies): void {
    $raw = [];
    $add = static function (
        string $key,
        string $label,
        string $section,
        string $type,
        string $storage,
        array $extra = []
    ) use (&$raw): void {
        $raw[$key] = array_merge([
            'key' => $key,
            'label' => $label,
            'section' => $section,
            'type' => $type,
            'storage' => $storage,
            'applicable_if' => [],
        ], $extra);
    };
    llama_place_report_add_operating_fields($add);

    foreach (['place_opening_time', 'place_closing_time'] as $key) {
        $assert(($raw[$key]['applicable_if'][0] ?? []) === [
            'field' => 'place_open_24_hours',
            'operator' => 'equals',
            'value' => '0',
        ], $key . ' is not a child of Normally open 24 hours? = No');
    }

    $assert(($raw['place_gate_access_type']['applicable_if'][0] ?? []) === [
        'field' => 'place_entrance_gate',
        'operator' => 'equals',
        'value' => '1',
    ], 'Place Gate Access Type is not a child of Place Gated');

    foreach (['place_gate_opening_time', 'place_gate_closing_time'] as $key) {
        $assert(($raw[$key]['applicable_if'] ?? []) === [
            ['field' => 'place_entrance_gate', 'operator' => 'equals', 'value' => '1'],
            ['field' => 'place_gate_access_type', 'operator' => 'equals', 'value' => 'scheduled'],
        ], $key . ' is not restricted to a scheduled Place gate');
    }

    $fields = llama_place_report_fields();
    foreach ([
        'amenity_none',
        'amenity_recycling',
        'amenity_electricity',
        'amenity_water_filling_station',
        'amenity_trailhead_parking',
    ] as $key) {
        $assert(!isset($fields[$key]), 'Retired Place amenity is still active: ' . $key);
    }

    $groups = llama_place_report_approved_question_groups();
    foreach ((array) $groups['amenities'][1] as $key) {
        $assert(isset($fields[$key]), 'Approved Place amenity is missing: ' . $key);
        $assertNoAnswerDependencies(
            $fields[$key],
            'Approved Place amenity gained an answer dependency: ' . $key
        );
    }
});

$check('Official Site accessibility question is first in Accessibility', static function () use ($assert): void {
    $groups = llama_place_report_approved_question_groups();
    $assert(!in_array('site_accessible', (array) $groups['site_info'][1], true),
        'Official Site accessibility question is still in Site Info');
    $assert(((array) $groups['site_accessibility'][1])[0] === 'site_accessible',
        'Official Site accessibility question is not first in Accessibility');

    $fields = llama_place_report_fields();
    $assert(($fields['site_accessible']['label'] ?? '') === 'Officially marked accessible Site?',
        'Official Site accessibility label changed unexpectedly');
    $assert(($fields['site_accessible']['approved_question_group'] ?? '') === 'site_accessibility',
        'Official Site accessibility question is not assigned to Accessibility');
    $assert(($fields['site_accessible']['layout_group'] ?? '') === 'site_accessibility',
        'Official Site accessibility question is not in the Site Accessibility layout');
    $assert(($fields['site_accessible']['report_scope_group'] ?? '') === 'site',
        'Official Site accessibility question is not Site-scoped');
});

$check('Site overhead clearance supports Unlimited', static function () use ($assert): void {
    $fields = llama_place_report_fields();
    $field = $fields['overhead_clearance_feet'] ?? null;

    $assert(is_array($field), 'Overhead Clearance question is missing');
    $options = (array) ($field['options'] ?? []);
    $assert(($options['30'] ?? '') === '30 ft',
        '30 ft option changed unexpectedly');
    $assert(($options['unlimited'] ?? '') === 'Unlimited',
        'Unlimited is not the final overhead clearance option');
    $assert(array_key_last($options) === 'unlimited',
        'Unlimited is not the last overhead clearance option');

    $parsed = llama_place_report_parse_answer($field, 'unlimited');
    $assert(($parsed['state'] ?? '') === 'answered'
        && ($parsed['value'] ?? null) === 'unlimited',
        'Unlimited overhead clearance does not persist distinctly');
});

$check('Parking and Maneuvering keeps only approved independent questions', static function () use ($assert, $assertNoAnswerDependencies): void {
    $fields = llama_place_report_fields();
    foreach ([
        'double_driveway',
        'hike_in_distance_feet',
        'site_access_difficulty',
        'trailer_suitable',
    ] as $key) {
        $assert(!isset($fields[$key]),
            'Retired Site parking question is still active: ' . $key);
    }

    $groups = llama_place_report_approved_question_groups();
    foreach ((array) $groups['parking'][1] as $key) {
        $assert(isset($fields[$key]), 'Approved parking question is missing: ' . $key);
        $assertNoAnswerDependencies(
            $fields[$key],
            'Approved parking question gained an answer dependency: ' . $key
        );
    }
});

$check('Site Amenities hookup dependencies match owner review', static function () use ($assert): void {
    $raw = [];
    $add = static function (
        string $key,
        string $label,
        string $section,
        string $type,
        string $storage,
        array $extra = []
    ) use (&$raw): void {
        $raw[$key] = array_merge([
            'key' => $key,
            'label' => $label,
            'section' => $section,
            'type' => $type,
            'storage' => $storage,
            'applicable_if' => [],
        ], $extra);
    };

    llama_place_report_add_site_fields($add);
    llama_place_report_apply_site_equipment_metadata($raw);

    $fields = llama_place_report_fields();
    $assert(!isset($fields['hookup_electric']),
        'Retired hidden electric hookup question is still active');

    foreach (['hookup_electric_service', 'hookup_water', 'hookup_sewer'] as $key) {
        $assert(($raw[$key]['applicable_if'][0] ?? []) === [
            'field' => 'site_hookups_available',
            'operator' => 'equals',
            'value' => '1',
        ], $key . ' is not a child of Hookups = Yes');
    }

    $assert(($raw['hookup_electric_service']['options']['none'] ?? '') === 'None',
        'Electrical Service is missing the None option');

    foreach (['tent_pad_width_feet', 'tent_pad_length_feet'] as $key) {
        $assert(($raw[$key]['applicable_if'][0] ?? []) === [
            'field' => 'tent_pad',
            'operator' => 'equals',
            'value' => '1',
        ], $key . ' is not a child of Tent Pad = Yes');
    }
});

$check('Site Accessibility dependencies match owner review', static function () use ($assert): void {
    $raw = [];

    foreach ([
        'site_accessible',
        'amenity_picnic_table',
        'amenity_toilets',
        'accessible_picnic_table',
        'accessible_toilet',
        'wheelchair_friendly',
        'mobility_device_friendly',
        'step_free_access',
        'flat_walking_surface',
        'walking_distance_from_vehicle',
        'accessibility_notes',
    ] as $key) {
        $raw[$key] = [
            'key' => $key,
            'label' => $key,
            'applicable_if' => [],
        ];
    }

    llama_place_report_apply_site_accessibility_metadata($raw);

    $assert(($raw['accessible_picnic_table']['applicable_if'][0] ?? []) === [
        'field' => 'amenity_picnic_table',
        'operator' => 'equals',
        'value' => '1',
    ], 'Accessible picnic table is not a child of Picnic Table = Yes');

    $assert(($raw['accessible_toilet']['applicable_if'][0] ?? []) === [
        'field' => 'amenity_toilets',
        'operator' => 'in',
        'value' => ['pit', 'flush', 'portable', 'present_unknown_type'],
    ], 'Accessible Toilet is not limited to Places with toilets');

    foreach ([
        'site_accessible',
        'wheelchair_friendly',
        'mobility_device_friendly',
        'step_free_access',
        'flat_walking_surface',
        'walking_distance_from_vehicle',
        'accessibility_notes',
    ] as $key) {
        $assert((array) ($raw[$key]['applicable_if'] ?? []) === [],
            'Independent accessibility question unexpectedly became dependent: ' . $key);
    }
});

$check('Connectivity stays provider-neutral and notes stay independent', static function () use ($assert): void {
    $raw = [
        'connectivity_satellite' => [
            'label' => 'Satellite',
            'help' => '',
            'applicable_if' => [],
        ],
        'connectivity_other_cell' => [
            'label' => 'Other Cellular',
            'help' => '',
            'applicable_if' => [],
        ],
        'connectivity_starlink_note' => [
            'label' => 'Starlink Note',
            'help' => '',
            'applicable_if' => [[
                'field' => 'connectivity_services_tested',
                'operator' => 'contains',
                'value' => 'starlink',
            ]],
        ],
    ];

    llama_place_report_apply_flow_metadata($raw);

    $assert(($raw['connectivity_satellite']['label'] ?? '') === 'Satellite System',
        'Satellite question is not provider-neutral');
    $assert(($raw['connectivity_other_cell']['label'] ?? '') === 'Other Cellular',
        'Other Cellular label changed unexpectedly');
    $assert(($raw['connectivity_starlink_note']['label'] ?? '') === 'Connectivity Notes',
        'Starlink Note was not renamed to Connectivity Notes');
    $assert((array) ($raw['connectivity_starlink_note']['applicable_if'] ?? []) === [],
        'Connectivity Notes is still conditional');

    $fields = llama_place_report_fields();
    $assert(($fields['connectivity_starlink_note']['label'] ?? '') === 'Connectivity Notes',
        'Final registry does not expose Connectivity Notes');
    $assert(($fields['connectivity_satellite']['label'] ?? '') === 'Satellite System',
        'Final registry does not expose Satellite System');
    $assert(isset($fields['connectivity_starlink_tested'], $fields['connectivity_amazon_leo_tested']),
        'Satellite tested-service inventory lost Starlink or Amazon Leo support');
});

$check('Connectivity is Site-only and does not inherit from Place', static function () use ($assert): void {
    $fields = llama_place_report_fields();

    foreach ([
        'connectivity_overall',
        'connectivity_att',
        'connectivity_t_mobile',
        'connectivity_verizon',
        'connectivity_other_cell',
        'connectivity_satellite',
        'connectivity_services_tested',
        'connectivity_starlink_note',
    ] as $key) {
        $assert(($fields[$key]['report_scope_group'] ?? '') === 'site',
            'Connectivity question is not Site-only: ' . $key);
        $assert(($fields[$key]['layout_section'] ?? '') === 'site',
            'Connectivity question is not laid out on the Site page: ' . $key);
        $assert(($fields[$key]['layout_group'] ?? '') === 'connectivity_services',
            'Connectivity question is not in the Site Connectivity group: ' . $key);
    }

    $groups = llama_place_report_approved_group_page_lookup();
    $assert(($groups['connectivity_public']['page'] ?? '') === 'site',
        'Approved Connectivity group is not assigned to the Site page');
});

$check('Approved sensory questions stay independent and legacy overlaps stay retired', static function () use ($assert, $assertNoAnswerDependencies): void {
    $fields = llama_place_report_fields();

    foreach (['sensory_road_noise', 'sensory_human_activity'] as $key) {
        $assert(!isset($fields[$key]),
            'Retired sensory question is still active: ' . $key);
    }

    $groups = llama_place_report_approved_question_groups();
    foreach (['daytime', 'nighttime', 'specific_sensory'] as $groupKey) {
        foreach ((array) $groups[$groupKey][1] as $key) {
            $assert(isset($fields[$key]),
                'Approved sensory question is missing: ' . $key);
            $assertNoAnswerDependencies(
                $fields[$key],
                'Approved sensory question gained an answer dependency: ' . $key
            );
        }
    }
});

$check('Safety and Hazards keeps only approved independent questions', static function () use ($assert, $assertNoAnswerDependencies): void {
    $fields = llama_place_report_fields();

    foreach (['cliff_exposure', 'warning_motorized_recreation_traffic'] as $key) {
        $assert(!isset($fields[$key]),
            'Retired safety question is still active: ' . $key);
    }

    $groups = llama_place_report_approved_question_groups();
    foreach ((array) $groups['safety_public'][1] as $key) {
        $assert(isset($fields[$key]), 'Approved safety question is missing: ' . $key);
        $assertNoAnswerDependencies(
            $fields[$key],
            'Approved safety question gained an answer dependency: ' . $key
        );
    }
});

$check('Experience and Recommendations keeps only approved independent questions', static function () use ($assert, $assertNoAnswerDependencies): void {
    $fields = llama_place_report_fields();

    foreach ([
        'experience_night_sky',
        'experience_overnight_comfort',
        'experience_extended_stay_comfort',
        'experience_remote_work',
        'recommended_quiet_evening',
        'recommended_sensory_retreat',
        'recommended_stargazing',
    ] as $key) {
        $assert(!isset($fields[$key]),
            'Retired experience question is still active: ' . $key);
    }

    $groups = llama_place_report_approved_question_groups();
    foreach ((array) $groups['experience_public'][1] as $key) {
        $assert(isset($fields[$key]), 'Approved experience question is missing: ' . $key);
        $assertNoAnswerDependencies(
            $fields[$key],
            'Approved experience question gained an answer dependency: ' . $key
        );
    }
});

$check('Scout Notes and Place History stay independent and moderator notes stay private', static function () use ($assert): void {
    $fields = llama_place_report_fields();
    $groups = llama_place_report_approved_question_groups();

    foreach (['notes_public', 'history_public'] as $groupKey) {
        foreach ((array) $groups[$groupKey][1] as $key) {
            $assert(isset($fields[$key]), 'Approved notes/history question is missing: ' . $key);
            $assert((array) ($fields[$key]['applicable_if'] ?? []) === [],
                'Approved notes/history question unexpectedly became dependent: ' . $key);
        }
    }

    $assert(isset($fields['contributor_notes']),
        'Moderator contributor_notes helper was accidentally retired');
    $assert(($fields['contributor_notes']['approved_question_group'] ?? null) === null,
        'Moderator contributor_notes leaked into the approved public questionnaire');
    $assert(!empty($fields['contributor_notes']['hide_public']),
        'Moderator contributor_notes is not hidden from public presentation');
    $assert(!empty($fields['contributor_notes']['moderation_only']),
        'Moderator contributor_notes is not explicitly marked moderation-only');
    $assert(empty($fields['contributor_notes']['counts_toward_completion']),
        'Moderator contributor_notes still counts toward Scout Report completion');
});

$check('Road Conditions approved inventory matches reviewed questions', static function () use ($assert): void {
    $fields = llama_place_report_fields();
    $groups = llama_place_report_approved_question_groups();
    $road = (array) $groups['road_conditions'][1];

    $assert(in_array('potholes', $road, true),
        'Approved Road Conditions inventory is missing Potholes');
    $assert(!in_array('approach_access_restrictions', $road, true),
        'Retired Access Restrictions remains in the approved Road Conditions inventory');
    $assert(!isset($fields['approach_access_restrictions']),
        'Retired Access Restrictions remains active');
});

$check('Fees and Reservations renders on Site with reviewed ownership preserved', static function () use ($assert): void {
    $fields = llama_place_report_fields();
    $groups = llama_place_report_approved_group_page_lookup();

    $assert(($groups['fees']['page'] ?? '') === 'site',
        'Approved Fees & Reservations group is not assigned to Site');

    foreach ((array) llama_place_report_approved_question_groups()['fees'][1] as $key) {
        $assert(($fields[$key]['layout_section'] ?? '') === 'site',
            'Fees & Reservations question is not rendered on Site: ' . $key);
        $assert(($fields[$key]['layout_group'] ?? '') === 'reservations_pricing',
            'Fees & Reservations question left the shared pricing layout group: ' . $key);
    }

    $assert(($fields['fee']['report_scope_group'] ?? '') === 'place',
        'Base Camping Fee should remain Place-only');
    foreach ([
        'extra_vehicle_fee',
        'membership_required',
        'membership_fee',
        'membership_url',
        'parking_entrance_required',
        'parking_entrance_fee',
        'discounts_available',
    ] as $key) {
        $assert(($fields[$key]['report_scope_group'] ?? '') === 'place_area',
            'Shared fee policy lost Place/Area ownership: ' . $key);
    }
    foreach ([
        'reservation_required',
        'reservation_fee',
        'reservation_url',
        'check_in_required',
        'check_in_begins',
        'check_out_required',
        'checkout_ends',
    ] as $key) {
        $assert(($fields[$key]['report_scope_group'] ?? '') === 'all',
            'Override-capable fee/reservation field lost inheritance: ' . $key);
    }
});

$check('Approved questions have shared layout metadata', static function () use ($assert): void {
    $layouts = llama_place_report_layout_groups();
    $missing = [];
    foreach (llama_report_questions() as $key => $field) {
        $section = (string) ($field['layout_section'] ?? '');
        $group = (string) ($field['layout_group'] ?? '');
        if (!isset($layouts[$section][$group])) {
            $missing[] = "$key ($section/$group)";
        }
    }
    $assert(!$missing, 'Approved questions missing layout: ' . implode(', ', $missing));
});
$check('Public questions use one approved catalog', static function () use ($assert): void {
    $catalog = llama_report_questions();
    foreach (llama_report_public_question_groups() as $definition) {
        foreach ($definition[1] as $key) {
            $assert(isset($catalog[$key]), "Public question absent from approved catalog: $key");
        }
    }
});

$check('Published Place uses latest canonical current answers', static function () use ($assert): void {
    $old = [
        'name' => 'Old name',
        'rules' => [
            'place_open_year_round' => '1',
            'season_begins' => '05-01',
        ],
    ];
    $current = [
        'name' => [
            'recorded' => true,
            'state' => 'answered',
            'value' => 'Latest approved name',
        ],
        'place_open_year_round' => [
            'recorded' => true,
            'state' => 'unknown',
            'value' => null,
        ],
        'season_begins' => [
            'recorded' => false,
            'state' => 'unanswered',
            'value' => null,
        ],
    ];
    $data = llama_place_report_project_current_place_answers($old, $current);
    $assert($data['name'] === 'Latest approved name', 'Old published name overrode current value');
    $assert(llama_place_report_get_path($data, 'rules.place_open_year_round') === null,
        'Unknown left behind the old Yes/No answer');
    $assert(in_array('place_open_year_round', $data['_answer_state'], true),
        'Explicit Unknown state was lost');
    $assert(llama_place_report_get_path($data, 'rules.season_begins') === null,
        'Unanswered question resurrected an old published value');
});

$check('Explicit unanswered is distinct from Unknown', static function () use ($fields, $assert): void {
    $u = llama_place_report_parse_answer($fields['daytime_noise'], llama_place_report_unknown_token());
    $n = llama_place_report_parse_answer($fields['daytime_noise'], llama_place_report_unanswered_token());
    $assert($u['state'] === 'unknown' && $n['state'] === 'unanswered', 'States were conflated');
});
$check('Answered rating retains value', static function () use ($fields, $assert): void {
    $a = llama_place_report_parse_answer($fields['daytime_noise'], '3');
    $assert($a['state'] === 'answered' && $a['value'] === 3, 'Rating was modified');
});
$check('Out-of-range rating rejected', static function () use ($fields, $assert): void {
    try { llama_place_report_parse_answer($fields['nighttime_noise'], '9'); }
    catch (InvalidArgumentException) { return; }
    $assert(false, 'Invalid rating was accepted');
});
$check('Reviewed observational groups are Site-only', static function () use ($assert): void {
    $fields = llama_place_report_fields();
    $groups = llama_place_report_approved_question_groups();

    foreach ([
        'setting',
        'parking',
        'daytime',
        'nighttime',
        'specific_sensory',
        'safety_public',
        'experience_public',
        'notes_public',
        'history_public',
    ] as $groupKey) {
        foreach ((array) $groups[$groupKey][1] as $key) {
            $assert(($fields[$key]['report_scope_group'] ?? '') === 'site',
                'Reviewed observational question is not Site-only: ' . $key);
        }
    }

    foreach ([
        'environment_notes',
        'emergency_access',
        'parking_surface',
        'ground_condition',
        'parking_grade',
    ] as $key) {
        $assert(($fields[$key]['report_scope_group'] ?? '') === 'site',
            'Reviewed Site observation still inherits from Place/Area: ' . $key);
    }

    $assert(($fields['contributor_notes']['report_scope_group'] ?? '') === 'all',
        'Private moderator notes unexpectedly lost multi-scope ownership');
});

$check('Quick Warnings render only in Site Safety and Hazards', static function () use ($assert): void {
    $source = file_get_contents(dirname(__DIR__) . '/partials/place-report/read-only.php');
    $assert(is_string($source), 'Unable to inspect Scout Report read-only renderer');

    $warningPos = strpos($source, 'data-report-warnings');
    $safetyGatePos = strpos($source, '(string) $sectionKey === \'safety_public\'');
    $placeWarningText = 'Important conditions reported for this Place.';
    $siteWarningText = 'Important conditions reported for this Site.';

    $assert($warningPos !== false, 'Quick Warnings container is missing');
    $assert($safetyGatePos !== false && $safetyGatePos < $warningPos,
        'Quick Warnings is not gated to Site Safety & Hazards');
    $assert(strpos($source, 'data-report-page="site"') !== false,
        'Quick Warnings is not marked as Site presentation');
    $assert(strpos($source, 'data-report-scopes="site"') !== false,
        'Quick Warnings is not marked Site-only');
    $assert(strpos($source, $placeWarningText) === false,
        'Place-level Quick Warnings summary is still present');
    $assert(strpos($source, $siteWarningText) !== false,
        'Site-level Quick Warnings summary is missing');
});

$check('Final Place Area Site ownership model matches owner review', static function () use ($assert): void {
    $fields = llama_place_report_fields();
    $pages = llama_place_report_approved_group_page_lookup();

    foreach ([
        'name',
        'type',
        'description',
        'latitude',
        'longitude',
        'elevation_feet',
        'road',
        'city',
        'county',
        'state',
        'land_type',
        'land_manager',
        'region',
        'place_open_year_round',
        'place_open_24_hours',
        'place_opening_time',
        'place_closing_time',
        'place_entrance_gate',
        'place_gate_access_type',
        'place_gate_opening_time',
        'place_gate_closing_time',
        'road_surface',
        'road_width',
        'approach_road_condition',
        'approach_road_hazards',
        'approach_road_type',
        'approach_permit_required',
        'approach_fee_required',
        'approach_road_fee',
        'approach_gate_present',
        'approach_gate_access_type',
        'approach_gate_opens',
        'approach_gate_closes',
        'nearest_town',
        'nearest_fuel',
        'nearest_ev_charging',
        'nearest_propane',
        'nearest_alcohol_sales',
        'nearest_grocery',
        'nearest_water',
        'nearest_toilet',
        'nearest_hospital',
    ] as $key) {
        $assert(($fields[$key]['report_scope_group'] ?? '') === 'place',
            'Place-only question has wrong ownership: ' . $key);
    }

    foreach ([
        'season_begins',
        'season_ends',
        'best_months',
        'seasonal_closure',
        'snow_risk',
        'mud_season_risk',
        'monsoon_risk',
        'tornado_risk',
        'hurricane_risk',
        'heat_season_risk',
        'amenity_toilets',
        'amenity_potable_water',
        'amenity_trash',
        'amenity_dump_station',
        'amenity_showers',
        'amenity_picnic_shelter',
        'amenity_wifi',
        'amenity_laundry',
        'amenity_dishwashing_station',
        'amenity_playground',
        'amenity_dog_park',
        'amenity_amphitheater',
        'amenity_campground_host',
        'amenity_camp_store',
        'amenity_trailhead',
        'amenity_river_access',
        'amenity_lake_access',
        'amenity_fishing_pier',
    ] as $key) {
        $assert(($fields[$key]['report_scope_group'] ?? '') === 'place_area',
            'Place/Area question has wrong ownership: ' . $key);
    }

    foreach ([
        'overnight_camping_allowed',
        'dispersed_camping_allowed',
        'designated_sites_only',
        'stay_limit_days',
        'pets_allowed',
        'dogs_required_to_be_leashed',
        'generator_restrictions',
        'generator_prohibited',
        'generator_quiet_hours',
        'generator_quiet_hours_begin',
        'generator_quiet_hours_end',
        'generator_run_restrictions',
        'generator_max_run_hours',
        'generator_restriction_details',
        'place_quiet_hours',
        'place_quiet_hours_begin',
        'place_quiet_hours_end',
        'campfire_allowed',
        'collecting_firewood',
        'place_charcoal_grills_allowed',
        'place_propane_fire_pits_allowed',
        'fire_activity_permit_url',
        'current_fire_restrictions_url',
        'food_storage_required',
        'pack_it_in_pack_it_out',
        'place_alcohol_permitted',
        'drone_use_legal',
        'target_shooting_allowed',
        'place_additional_rules',
    ] as $key) {
        $assert(($fields[$key]['report_scope_group'] ?? '') === 'all',
            'Place-first override-capable policy has wrong ownership: ' . $key);
    }

    $assert(($pages['rules_public']['page'] ?? '') === 'place',
        'Rules & Restrictions is not Place-first in the approved page map');

    foreach ((array) llama_place_report_approved_question_groups()['rules_public'][1] as $key) {
        $assert(in_array('place', (array) ($fields[$key]['report_scopes'] ?? []), true),
            'Rule cannot be recorded at Place scope: ' . $key);
        $assert(in_array('site', (array) ($fields[$key]['report_scopes'] ?? []), true),
            'Rule cannot provide a Site override: ' . $key);
    }

    $assert(($fields['road_exposure']['report_scope_group'] ?? '') === 'site',
        'Road Exposure should remain a Site sensory observation');
    $assert(($fields['road_exposure']['layout_section'] ?? '') === 'site',
        'Road Exposure leaked back into Getting There presentation');
});

$check('Place/Site field ownership enforced', static function () use ($assert): void {
    try { llama_scoped_report_field('site_length_feet', 'place'); }
    catch (InvalidArgumentException) { return; }
    $assert(false, 'Site field accepted as a Place scoped override');
});
$check('Invalid scoped target rejected', static function () use ($assert): void {
    try { llama_scoped_report_validate_target('site', 0); }
    catch (InvalidArgumentException) { return; }
    $assert(false, 'Zero Site ID accepted');
});
$check('Invalid scoped source rejected', static function () use ($assert): void {
    try { llama_scoped_report_validate_source('site', 'unknown'); }
    catch (InvalidArgumentException) { return; }
    $assert(false, 'Unknown source accepted');
});
$check('Unified Place map editor contract', static function () use ($assert): void {
    $page = file_get_contents(dirname(__DIR__) . '/place-map-editor.php');
    $endpoint = file_get_contents(dirname(__DIR__) . '/app/place-map-features-endpoint.php');
    $script = file_get_contents(dirname(__DIR__) . '/js/place-map-editor.js');

    $assert(is_string($page), 'Unable to inspect unified map editor page');
    $assert(is_string($endpoint), 'Unable to inspect map feature endpoint');
    $assert(is_string($script), 'Unable to inspect unified map editor script');

    foreach (['place', 'area', 'site'] as $mode) {
        $assert(
            strpos($page, 'data-map-mode-button="' . $mode . '"') !== false,
            'Unified map editor is missing mode: ' . $mode
        );
    }

    $assert(
        strpos($page, 'data-site-point-panel') !== false
        && strpos($page, 'data-save-site-point') !== false,
        'Unified map editor is missing canonical Site point controls'
    );

    $assert(
        strpos($page, 'Site pricing classes') === false
        && strpos($page, '<h2>Rates</h2>') === false
        && strpos($page, 'pricing-rates.js') === false
        && strpos($page, 'pricing-site-classes.js') === false,
        'Pricing controls are still exposed in the map editor'
    );

    $assert(
        strpos($endpoint, "if (\$action === 'save_site_point')") !== false
        && strpos($endpoint, 'llama_place_map_api_sites') !== false,
        'Map endpoint does not support canonical Site point persistence'
    );

    $assert(
        strpos($endpoint, "!array_key_exists('rates', \$input)") !== false
        && strpos($endpoint, "!array_key_exists('site_classes', \$input)") !== false,
        'Map save no longer preserves pricing that this editor does not control'
    );

    $assert(
        strpos($script, "'createPricing'") === false
        && strpos($script, "'createSiteFields'") === false,
        'Unified map editor still requires retired pricing/Site-polygon modules'
    );

    $assert(
        strpos($script, "editorMode === 'site'") !== false
        && strpos($script, "action:\n                            'save_site_point'") !== false,
        'Site mode is not wired to point persistence'
    );
});

$check('Canonical field contract', static function () use ($assert): void {
    $issues = llama_place_report_contract_issues();
    $assert(!$issues, 'Contract issues: ' . implode(' | ', array_slice($issues, 0, 12)) . ' (total ' . count($issues) . ')');
});
echo "\n$checks checks; " . count($failures) . " failures\n";
foreach ($failures as $f) echo " - $f\n";
exit($failures ? 1 : 0);
