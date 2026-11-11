<?php
declare(strict_types=1);

/* Canonical facility definitions and type-restricted Area ownership. */
require_once dirname(__DIR__, 2) . '/app/place-report.php';

$expected = [
    'facility_condition' => ['restroom', 'shower', 'water', 'dump', 'laundry', 'pool', 'playground', 'trash', 'firewood', 'propane', 'recreation', 'other'],
    'facility_accessible' => ['restroom', 'shower', 'water', 'dump', 'laundry', 'pool', 'playground', 'trash', 'firewood', 'propane', 'recreation', 'other'],
    'facility_cleanliness' => ['restroom', 'shower', 'laundry', 'pool'],
    'facility_toilet_type' => ['restroom'],
    'facility_toilet_paper' => ['restroom'],
    'facility_hot_water' => ['shower'],
    'facility_shower_privacy' => ['shower'],
    'facility_water_potable' => ['water'],
    'facility_water_threaded' => ['water'],
    'facility_water_flow' => ['water'],
    'facility_washers_operational' => ['laundry'],
    'facility_dryers_operational' => ['laundry'],
];
foreach ($expected as $key => $kinds) {
    $field = llama_report_question($key);
    if (!$field || ($field['report_scopes'] ?? []) !== ['area']
        || ($field['facility_kinds'] ?? []) !== $kinds
        || ($field['scope_inheritance'] ?? '') !== 'none'
        || empty($field['scoped_only_storage'])) {
        throw new RuntimeException('Invalid canonical Area facility question: ' . $key);
    }
    if (isset(llama_report_questions_for_scope('place', true)[$key])
        || isset(llama_report_questions_for_scope('site', true)[$key])) {
        throw new RuntimeException('Facility question leaked into Place/Site scope: ' . $key);
    }
    foreach (['restroom', 'shower', 'water', 'laundry', 'camping_area'] as $kind) {
        $target = [
            'scope' => 'area', 'source' => 'map_feature',
            'feature_type' => $kind === 'camping_area' ? 'camping_area' : 'facility_area',
            'facility_kind' => $kind === 'camping_area' ? '' : $kind,
        ];
        $shouldApply = in_array($kind, $kinds, true);
        if (llama_report_question_applies_to_area_target($field, $target) !== $shouldApply) {
            throw new RuntimeException('Facility eligibility mismatch: ' . $key . ' / ' . $kind);
        }
    }
}
echo "PASS: All 12 canonical facility questions have correct Area ownership and eligibility.\n";
