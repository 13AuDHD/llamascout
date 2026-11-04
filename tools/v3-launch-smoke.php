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
$check('Full inventory review shows approved questions across Place types', static function () use ($assert): void {
    $assert(llama_place_report_full_inventory_review_enabled(), 'Inventory review is not enabled');
    $catalog = llama_report_questions();
    foreach (['heat_season_risk', 'tornado_risk', 'amenity_river_access', 'amenity_lake_access',
        'amenity_campground_host', 'site_hookups_available', 'hookup_electric_service'] as $key) {
        $assert(isset($catalog[$key]), 'Missing approved inventory question: ' . $key);
    }
    foreach ($catalog as $key => $field) {
        $assert((array) ($field['applicable_if'] ?? []) === [],
            'Condition still hides a question: ' . $key);
        $assert(llama_place_report_question_applicable(['type' => 'dispersed-camping'], $key),
            'Dispersed Place hides question: ' . $key);
        $assert(llama_place_report_question_applicable(['type' => 'campground'], $key),
            'Campground hides question: ' . $key);
    }
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

$check('Camping and Overnight Stays keeps only approved independent questions', static function () use ($assert): void {
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
        $assert((array) ($fields[$key]['applicable_if'] ?? []) === [],
            'Approved overnight question unexpectedly became dependent: ' . $key);
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
$check('Canonical field contract', static function () use ($assert): void {
    $issues = llama_place_report_contract_issues();
    $assert(!$issues, 'Contract issues: ' . implode(' | ', array_slice($issues, 0, 12)) . ' (total ' . count($issues) . ')');
});
echo "\n$checks checks; " . count($failures) . " failures\n";
foreach ($failures as $f) echo " - $f\n";
exit($failures ? 1 : 0);
