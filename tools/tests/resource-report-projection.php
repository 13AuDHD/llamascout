<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/resource-report-projection.php';

$check = static function (bool $true, string $message): void {
    if (!$true) {
        throw new RuntimeException('FAIL: ' . $message);
    }
};
$getAnswer = static function (array $report, string $answerKey): ?array {
    foreach ($report['groups'] as $group) {
        foreach ($group['answers'] as $answer) {
            if ($answer['answer_key'] === $answerKey) {
                return $answer;
            }
        }
    }
    return null;
};

$resource = [
    'id' => 7,
    'parent_place_id' => null,
    'name' => 'Roadside Resource',
    'resource_type' => 'restroom_facility',
    'status' => 'available',
    'notes' => 'Existing Resource notes',
    'phone' => '555-0100',
    'created_by' => 13,
    'source_external_id' => 'private-reference',
    'services' => [
        ['service_type' => 'toilet'],
        ['service_type' => 'fuel'],
        ['service_type' => 'potable_water'],
    ],
    'experience' => [
        'toilet' => [
            'men' => ['facility_cleanliness' => '3', 'facility_soap' => 'no'],
            'women' => ['facility_cleanliness' => '5', 'service_toilet_stalls' => '0'],
            'all_gender' => ['facility_cleanliness' => 'unknown'],
        ],
        'fuel' => ['general' => ['service_fuel_grades' => ['diesel', '85']]],
        'potable_water' => ['general' => [
            'facility_water_year_round' => 'no',
            'service_water_type' => 'unmarked',
        ]],
        // Historical inactive/retired observations are preserved for audit,
        // never presented as answers to an unrelated selected service.
        'ice' => ['general' => ['service_ice_types' => ['cubed']]],
        'toilet_missing' => ['men' => ['facility_cleanliness' => '5']],
        'toilet_extra' => 'old data',
    ],
];
$original = serialize($resource);
$report = llama_resource_report_projection($resource);
$check(serialize($resource) === $original, 'The projection must not modify the persisted Resource.');
$check($report['target'] === [
    'kind' => 'resource', 'source' => 'resource_record',
    'id' => 7, 'key' => 'resource:resource_record:7',
    'parent_place_id' => null,
], 'Standalone Resource must retain independent identity.');
$check(($report['legacy_details']['name'] ?? '') === 'Roadside Resource'
    && ($report['legacy_details']['phone'] ?? '') === '555-0100'
    && !isset($report['legacy_details']['created_by'])
    && !isset($report['legacy_details']['source_external_id']),
    'Legacy details must retain their values without leaking private ownership.');
$men = $getAnswer($report, 'toilet/men/facility_cleanliness');
$women = $getAnswer($report, 'toilet/women/facility_cleanliness');
$gender = $getAnswer($report, 'toilet/all_gender/facility_cleanliness');
$check($men['value'] === '3' && $women['value'] === '5'
    && $men['answer_key'] !== $women['answer_key'],
    'Independent restroom groups must not overwrite answers with the same question key.');
$check($gender['state'] === 'unknown'
    && $gender['display_value'] === 'Unknown',
    'Explicit Unknown must not be treated as missing.');
$zero = $getAnswer($report, 'toilet/women/service_toilet_stalls');
$check($zero['state'] === 'answered' && $zero['value'] === '0',
    'A numeric zero is an answered value.');
$no = $getAnswer($report, 'toilet/men/facility_soap');
$check($no['state'] === 'answered' && $no['display_value'] === 'No',
    'Explicit No must be an answer, not absence.');
$unset = $getAnswer($report, 'toilet/women/facility_soap');
$check($unset['state'] === 'unanswered' && !$unset['recorded'],
    'An omitted question must remain Unanswered.');
$multi = $getAnswer($report, 'fuel/general/service_fuel_grades');
$check($multi['state'] === 'answered' && $multi['value'] === ['diesel', '85']
    && $multi['options'] === llama_resource_experience_manifest()['fuel']['general']['questions']['service_fuel_grades']['options'],
    'Multiple choices must keep their original order and canonical choices.');
$canonical = llama_report_question('facility_water_year_round');
$water = $getAnswer($report, 'potable_water/general/facility_water_year_round');
$check($water['label'] === $canonical['label']
    && $water['type'] === $canonical['type'],
    'Reused Facility questions must remain identical to the Place catalog.');
$check(count($report['unmapped']) === 3,
    'Old Experience from unselected or unknown services must be audit-visible, not discarded.');
$unmappedPaths = array_column($report['unmapped'], 'path');
$check(in_array('ice/general/service_ice_types', $unmappedPaths, true),
    'Inactive service Experience must not become a published answer.');

$facility = $resource;
$facility['id'] = 9;
$facility['parent_place_id'] = 42;
$facility['name'] = 'Inside Campground Restroom';
$facilityProjection = llama_resource_report_projection($facility);
$check($facilityProjection['target'] === [
    'kind' => 'facility', 'source' => 'resource_record',
    'id' => 9, 'key' => 'facility:resource_record:9',
    'parent_place_id' => 42,
], 'Linked Facility keeps its own Resource ID and parent Place relationship.');
$check($getAnswer($facilityProjection, 'toilet/women/facility_cleanliness')['value'] === '5',
    'Linked Facility reads its own stored Experience; no parent Place overlay.');
$check($resource['parent_place_id'] === null,
    'Projecting a linked Facility cannot change another Resource or Place.');
$emptyAnswer = $facility;
$emptyAnswer['experience']['fuel']['general']['service_fuel_grades'] = [];
$emptyProjection = llama_resource_report_projection($emptyAnswer);
$check($getAnswer($emptyProjection, 'fuel/general/service_fuel_grades')['state'] === 'answered',
    'A legacy explicitly empty set is distinct from absent data.');

$invalid = false;
try {
    llama_resource_report_projection(['id' => 0]);
} catch (InvalidArgumentException) {
    $invalid = true;
}
$check($invalid, 'An unpersisted Resource cannot impersonate a real report target.');

$page = (string) file_get_contents(dirname(__DIR__, 2) . '/resource.php');
$readBridge = (string) file_get_contents(dirname(__DIR__, 2)
    . '/app/resource-report-current-read.php');
$check(str_contains($page, 'llama_resource_report_read(db(), $resource)')
    && str_contains($readBridge, 'llama_resource_report_projection($resource)')
    && !str_contains($page, 'llama_resource_experience_manifest()'),
    'Public Resource/Facility display must use the guarded shared read projection.');

echo "PASS: Target-scoped Resource/Facility projection keeps No, 0, Unknown, missing, options and historical data intact.\n";
