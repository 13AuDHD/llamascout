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
    $amenities = (array) $groups['amenities'][1];
    $pier = array_search('amenity_fishing_pier', $amenities, true);
    $assert($amenities[$pier - 2] === 'amenity_river_access'
        && $amenities[$pier - 1] === 'amenity_lake_access',
        'River/Lake Access must appear immediately before Fishing Pier');
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
