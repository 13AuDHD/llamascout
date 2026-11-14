<?php
declare(strict_types=1);

/** No DB needed. Protect canonical Place questions from Area presentation edits. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once dirname(__DIR__, 2) . '/app/place-report.php';

$fields = llama_report_questions();
$groups = llama_report_public_question_groups();

foreach ([
    'amenity_toilets', 'amenity_potable_water',
    'amenity_showers', 'amenity_trash',
] as $key) {
    if (!isset($fields[$key])
        || !in_array('place', (array) $fields[$key]['report_scopes'], true)) {
        throw new RuntimeException('Missing Place-scoped Amenities question: ' . $key);
    }
}

foreach ([
    'facility_toilet_count', 'facility_shower_cost',
    'facility_pool_heated', 'facility_operational_status',
] as $key) {
    if (!isset($fields[$key])
        || ($fields[$key]['report_scopes'] ?? []) !== ['area']
        || (string) ($fields[$key]['report_scope_group'] ?? '') !== 'area'
        || empty($fields[$key]['scoped_only_storage'])) {
        throw new RuntimeException('Area-only facility question leaked to Place: ' . $key);
    }
}

$placeGroups = 0;
$placeQuestions = 0;
$areaQuestions = 0;
foreach ($groups as $group) {
    foreach ((array) ($group[1] ?? []) as $key) {
        if (!isset($fields[$key])) continue;
        $scopes = (array) $fields[$key]['report_scopes'];
        if (in_array('place', $scopes, true)) {
            ++$placeQuestions;
            ++$placeGroups;
        }
        if ($scopes === ['area']) ++$areaQuestions;
    }
}
if ($placeQuestions < 35 || $areaQuestions < 20) {
    throw new RuntimeException('Place or Area canonical question inventory is missing.');
}
echo "PASS: canonical Place questions preserved and Area-only facility questions separately scoped\n";
