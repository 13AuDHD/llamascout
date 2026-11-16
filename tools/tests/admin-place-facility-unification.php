<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/admin-place-facilities.php';

$assert = static function (bool $yes, string $message): void {
    if (!$yes) throw new RuntimeException('FAIL: ' . $message);
};
$areas = [
    ['scope_id'=>20,'label'=>'Shower House','feature_type'=>'facility_area'],
    ['scope_id'=>21,'label'=>'Parking Loop','feature_type'=>'parking_area'],
    ['scope_id'=>22,'label'=>'Campsite Area','feature_type'=>'camping_area'],
];
$resources = [
    ['id'=>7,'name'=>'Laundry Room','parent_place_id'=>1],
    ['id'=>8,'name'=>'Water Fill','parent_place_id'=>1],
];
$list = admin_place_facility_catalog($areas, $resources);
$assert(count($list) === 5, 'Historical Areas and linked Facilities must appear together.');
$keys = array_column($list, 'id');
foreach (['map_feature:20','map_feature:21','map_feature:22','resource:7','resource:8'] as $key) {
    $assert(in_array($key,$keys,true),'Missing Facility identity: '.$key);
}
$assert(count(array_unique($keys)) === count($keys),
    'Source-qualified IDs prevent collisions without migrating stored data.');
$map = admin_place_facility_map_details([
    'id'=>20, 'label'=>'Shower House', 'feature_type'=>'facility_area',
    'geometry_type'=>'point', 'geometry'=>['type'=>'Point',
        'coordinates'=>[-107.2192,37.25222]],
    'metadata'=>['facility_kind'=>'shower','description'=>'Open seasonally'],
]);
$assert($map['coordinates'] === '37.2522200, -107.2192000'
    && $map['coordinates_editable'] === true
    && $map['facility_kind'] === 'shower'
    && $map['notes'] === 'Open seasonally',
    'Mapped Facility location and description are projected correctly.');
$polygon = admin_place_facility_map_details([
    'id'=>21, 'feature_type'=>'parking_area', 'geometry_type'=>'polygon',
    'geometry'=>['type'=>'Polygon', 'coordinates'=>[[[1,1],[1,2],[2,2],[1,1]]]],
]);
$assert($polygon['coordinates_editable'] === false,
    'Mapped boundary must be edited through Edit Map, not converted to a point.');
$root = dirname(__DIR__, 2);
$source = static fn (string $p): string => (string) file_get_contents($root . '/' . $p);
$tabs = $source('partials/place-report/contribution-tabs.php');
$adminFragment = explode('<?php elseif ($isNewContribution): ?>',
    explode('<?php if ($isAdminPlacePage): ?>', $tabs, 2)[1] ?? '', 2)[0];
$assert(str_contains($adminFragment, 'data-contribution-mode="facility"')
    && !str_contains($adminFragment, 'data-contribution-mode="area"')
    && !str_contains($adminFragment, 'data-contribution-resource-link'),
    'Admin tabs should not expose old Areas or Resource controls.');
$assert(str_contains($source('admin/partials/place-summary-report.php'),
    "'/place-linked-facility-editor.php'"), 'Newer Facilities need in-place Admin editor.');
$assert(str_contains($source('admin/partials/place-report-area-selector.php'),
    'adminFacilityCatalog'), 'The Facility dropdown must include both underlying record types.');
$assert(str_contains($source('app/admin-place-actions.php'), 'save-linked-facility')
    && str_contains($source('app/admin-place-actions.php'), 'admin_place_save_map_facility(')
    && str_contains($source('app/admin-place-actions.php'), 'admin_resource_save('),
    'Both original Facility storage adapters must save directly through Admin Place.');
$assert(str_contains($source('app/admin-place-actions.php'), 'admin_resource_apply_gallery_edits(')
    && str_contains($source('app/admin-place-actions.php'), 'admin_resource_attach_staged_photos('),
    'Modern Facility gallery must save with Facility edits.');
$assert(str_contains($source('app/admin-place-actions.php'), 'admin_place_save_area_answers('),
    'All existing Area-scope canonical answers must keep their storage path.');
echo "PASS: Unified Facility selector, existing Area IDs and canonical answers, direct Admin saves.\n";
