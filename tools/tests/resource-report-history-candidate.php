<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/resource-report-history-candidate.php';

$assert = static function (bool $ok, string $description): void {
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $description);
    }
};

$definitions = llama_resource_service_question_definitions();
$candidates = llama_resource_service_question_candidates();
$manifest = llama_resource_experience_manifest();
$occurrences = llama_resource_report_experience_field_catalog();
$schema = llama_resource_history_candidate_schema();

$assert(count($definitions) >= 20
    && array_keys($definitions) === array_keys($candidates),
    'Every original service-only definition must appear exactly once in the candidate registry.');
$assert(count($occurrences) === 118,
    'The audited Experience inventory must keep all 118 Service/group/question occurrences.');
$assert(strlen($schema['schema_version']) === 64
    && hash('sha256', $schema['definition_json']) === $schema['schema_version']
    && $schema['write_approved'] === false,
    'Versioned candidate schema must have a deterministic hash and never imply approval.');
$assert($schema === llama_resource_history_candidate_schema(),
    'Repeated catalog reads must return the same immutable schema candidate.');
$assert($schema['definition']['approval_status'] === 'existing_resource_questions_approved_writer_not_enabled'
    && $schema['write_approved'] === false,
    'Approving existing Resource questions must NOT activate immutable writes.');
$assert(count($candidates) === 26,
    'Exactly the original 26 Resource-only questions must remain present and approved.');

foreach ($candidates as $key => $candidate) {
    $assert($candidate['key'] === $key
        && $candidate['label'] === $definitions[$key][0]
        && $candidate['type'] === $definitions[$key][1]
        && $candidate['options'] === ($definitions[$key][2] ?? [])
        && $candidate['review_status'] === 'approved_existing_resource_facility',
        'Approved existing question must match the current Service form unchanged: ' . $key);
    $assert(llama_report_question($key) === null,
        'Resource-only candidate must not enter the approved Place/Site/Area catalog: ' . $key);
}
foreach ($manifest as $service => $groups) {
    foreach ($groups as $groupKey => $group) {
        foreach ($group['questions'] as $key => $field) {
            if (!isset($candidates[$key])) {
                continue;
            }
            $assert($field['label'] === $candidates[$key]['label']
                && $field['type'] === $candidates[$key]['type']
                && $field['options'] === $candidates[$key]['options'],
                'Existing Add/Update/Admin Resource controls drifted from the candidate definition: '
                    . $service . '/' . $groupKey . '/' . $key);
        }
    }
}
$assert(($candidates['service_fuel_grades']['options']['diesel'] ?? '') === 'Diesel'
    && $candidates['service_water_type']['type'] === 'select'
    && $candidates['service_access_notes']['type'] === 'textarea',
    'Original approved-in-Resource Service labels/types/choices must not change.');

$resource = [
    'id' => 14, 'parent_place_id' => null, 'name' => 'Roadside Station',
    'created_at' => '2024-01-01 01:00:00', 'created_by' => 29,
    'services' => [
        ['service_type' => 'toilet'],
        ['service_type' => 'fuel'],
        ['service_type' => 'potable_water'],
    ],
    'experience' => [
        'toilet' => [
            'men' => ['facility_cleanliness' => '3', 'facility_soap' => 'no'],
            'women' => ['facility_cleanliness' => '5', 'facility_toilet_paper' => 'unknown'],
            'all_gender' => ['facility_cleanliness' => '4'],
        ],
        'fuel' => ['general' => [
            'service_fuel_grades' => ['diesel', '85'],
            'service_fuel_pumps' => '0',
            'service_access_notes' => 'Easy entry',
        ]],
        'potable_water' => ['general' => [
            'service_water_key_required' => 'no',
            'service_access_notes' => 'Tap on north wall',
        ]],
    ],
];
$raw = serialize($resource);
$missing = llama_resource_history_candidate($resource);
$assert(serialize($resource) === $raw,
    'Creating an immutable preview must not modify Resource records.');
$assert($missing['write_approved'] === false && $missing['status'] === 'preview_only',
    'Resource question approval must not enable database writes.');
$assert($missing['target']['kind'] === 'resource'
    && $missing['target']['parent_place_id'] === null,
    'Standalone Resource must not receive a fabricated Place ID.');
$assert($missing['provenance']['contributor_id'] === null
    && $missing['provenance']['submitted_at'] === null
    && $missing['provenance']['visit_date'] === null,
    'Legacy created_by and created_at must never become inferred Scout report provenance.');
$assert(count($missing['blockers']) >= 8,
    'Existing ledger constraints and missing provenance must explicitly block history writes.');
$assert(!str_contains(implode(' ', $missing['blockers']), 'questions have not been explicitly approved'),
    'Do not reintroduce the resolved Resource question approval blocker.');

$evidence = [
    'submission_id' => 303,
    'contributor_id' => 13,
    'reviewed_by' => 99,
    'submitted_at' => '2026-11-18 10:00:00',
    // A verified visit date does not currently exist on Resource submissions.
];
$preview = llama_resource_history_candidate($resource, $evidence);
$assert($preview['logical_report_key'] === 'resource-submission:303:resource:resource_record:14',
    'History idempotency key must include submission and exact Resource target identity.');
$assert($preview['provenance']['contributor_id'] === 13
    && $preview['provenance']['visit_date'] === null,
    'Moderation provenance must come from explicit evidence, not current Resource fields.');
$answers = $preview['answers'];
$assert(count($answers) === 10,
    'Preview should contain only recorded Service/group observations, not unanswered fields.');
$assert($answers['toilet/men/facility_cleanliness']['value'] === '3'
    && $answers['toilet/women/facility_cleanliness']['value'] === '5'
    && $answers['toilet/all_gender/facility_cleanliness']['value'] === '4',
    'Same canonical question must remain three independent restroom observations.');
$assert($answers['toilet/men/facility_soap']['value'] === 'no'
    && $answers['fuel/general/service_fuel_pumps']['value'] === '0'
    && $answers['fuel/general/service_fuel_grades']['value'] === ['diesel', '85'],
    'Explicit No, numeric zero and multiselect data must survive intact.');
$assert($answers['toilet/women/facility_toilet_paper']['state'] === 'unknown'
    && $answers['toilet/women/facility_toilet_paper']['value'] === null
    && $answers['toilet/women/facility_toilet_paper']['legacy_value'] === 'unknown',
    'Unknown must preserve its distinct state and original Resource token.');
$assert($answers['fuel/general/service_access_notes']['value'] === 'Easy entry'
    && $answers['potable_water/general/service_access_notes']['value'] === 'Tap on north wall',
    'Service-specific notes must not be merged into one answer.');
$assert(!isset($answers['toilet/men/facility_toilet_paper']),
    'Unanswered questions must not be inserted into immutable history previews.');
$assert($preview['content_sha256'] === llama_resource_history_candidate($resource, $evidence)['content_sha256'],
    'Same Resource and provenance must produce a stable immutable content digest.');

$changed = $resource;
$changed['experience']['toilet']['men']['facility_cleanliness'] = '4';
$assert(llama_resource_history_candidate($changed, $evidence)['content_sha256'] !== $preview['content_sha256'],
    'Different approved answers must create a different content hash.');

$linked = $resource;
$linked['id'] = 22;
$linked['parent_place_id'] = 7;
$facility = llama_resource_history_candidate($linked, $evidence);
$assert($facility['target']['key'] === 'facility:resource_record:22'
    && $facility['target']['parent_place_id'] === 7
    && $facility['logical_report_key'] === 'resource-submission:303:facility:resource_record:22'
    && $facility['content_sha256'] !== $preview['content_sha256'],
    'Resource-backed Facility must remain distinct from standalone Resource and mapped Area.');
$assert(!isset($facility['target']['place_id'])
    && $facility['target']['source'] === 'resource_record',
    'A linked Facility must never be reinterpreted as an Area report.');

$invalid = $linked;
$invalid['experience']['retired_service'] = ['general' => ['obsolete' => 'yes']];
$stale = llama_resource_history_candidate($invalid, $evidence);
$assert(count($stale['unmapped']) === 1
    && str_contains(implode(' ', $stale['blockers']), 'unrecognized or inactive'),
    'Historical unknown fields must block publication and remain visible for reconciliation.');

$historyCode = (string) file_get_contents(dirname(__DIR__, 2)
    . '/app/place-report/immutable-history.php');
$historySql = (string) file_get_contents(dirname(__DIR__, 2)
    . '/tools/migrations/20261030_m2_006_scout_report_history.sql');
$assert(str_contains($historyCode, "['place', 'area', 'site']")
    && str_contains($historySql, 'place_id BIGINT UNSIGNED NOT NULL')
    && str_contains($historySql, "CHECK (target_scope IN ('place', 'area', 'site'))"),
    'Current immutable writer must remain incompatible with Resource/Facility until reviewed migration.');
$assert(!str_contains($historyCode, 'resource_record'),
    'Resource preview must not accidentally activate an unreviewed central ledger write.');

echo 'Resource-only question candidates: ' . count($candidates)
    . ', Experience instances: ' . count($occurrences)
    . ', recorded sample instances: ' . count($answers) . PHP_EOL;
echo "PASS: Versioned Resource history candidate preserves compound identity, explicit provenance, No/0/Unknown and blocks writes.\n";
