<?php
declare(strict_types=1);
// Pure server-side eligibility tests, no live database connection.
require_once dirname(__DIR__, 2) . '/app/place-report/question-catalog.php';
$restroom = [
    'scope' => 'area', 'source' => 'map_feature', 'id' => 7,
    'feature_type' => 'facility_area', 'facility_kind' => 'restroom',
];
$water = array_replace($restroom, ['facility_kind' => 'water']);
$campingArea = array_replace($restroom, [
    'feature_type' => 'camping_area', 'facility_kind' => '',
]);
$wrongScope = array_replace($restroom, ['scope' => 'place']);
$shared = ['report_scopes' => ['place', 'area']];
$restroomOnly = ['report_scopes' => ['area'], 'facility_kinds' => ['restroom']];
$waterOnly = ['report_scopes' => ['area'], 'facility_kinds' => ['water']];
$invalid = ['report_scopes' => ['area'], 'facility_kinds' => []];
$cases = [
    [$shared, $restroom, true],
    [$shared, $campingArea, true],
    [$restroomOnly, $restroom, true],
    [$restroomOnly, $water, false],
    [$restroomOnly, $campingArea, false],
    [$waterOnly, $restroom, false],
    [$waterOnly, $water, true],
    [$invalid, $restroom, false],
    [$restroomOnly, $wrongScope, false],
];
foreach ($cases as $i => [$field, $target, $expected]) {
    if (llama_report_question_applies_to_area_target($field, $target) !== $expected) {
        throw new RuntimeException('Facility eligibility case failed: ' . $i);
    }
}
echo "Facility Area question eligibility passed.\n";
