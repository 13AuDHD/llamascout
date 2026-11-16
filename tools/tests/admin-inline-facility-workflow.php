<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string =>
    (string) file_get_contents($root . '/' . $path);
$expect = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
};

$page = $read('admin/place.php');
$summary = $read('admin/partials/place-summary-report.php');
$picker = $read('admin/partials/place-report-area-selector.php');
$editor = $read('admin/partials/place-linked-facility-editor.php');
$save = $read('app/admin-place-actions.php');
$css = $read('css/admin/pages/place.css');
$siteJs = $read('js/contribution-tabs.js');
$areaJs = $read('js/admin/place-report-area.js');
$map = $read('admin/partials/place-map.php');
$mapJs = $read('js/admin/place-map.js');
$fields = $read('partials/resources/fields.php');

$expect(str_contains($page, "? 'new'")
    && str_contains($page, "\$creatingFacility = \$selectedFacility === 'new'"),
    'No Facility catalog must select Add Facility instead of an empty state.');
$expect(!str_contains($summary, 'No Facilities to edit')
    && !str_contains($summary, 'data-contribution-area-empty')
    && str_contains($summary, "|| \$creatingFacility ? 'resource' : 'map_feature'"),
    'The empty-state map-editor dead end must be removed.');
$expect(str_contains($picker, 'data-admin-add-facility')
    && str_contains($picker, '<option value="new"')
    && !str_contains($picker, 'if (empty($adminFacilityCatalog))'),
    'Add Facility and the selector must exist with zero legacy Areas.');
$expect(str_contains($editor, "\$creatingFacility ? 'Add Facility' : 'Edit Facility'")
    && str_contains($editor, "name=\"selected_facility_id\"")
    && str_contains($editor, "name=\"facility_resource_id\"")
    && str_contains($editor, "name=\"parent_place_id\"")
    && str_contains($editor, "'/partials/resources/fields.php'")
    && str_contains($editor, "Create Facility' : 'Save Facility'"),
    'New Facilities must use the same form and save ownership as existing ones.');
$expect(str_contains($save, '$resourceId = admin_resource_save(')
    && str_contains($save, '$resourceId > 0')
    && str_contains($save, "\$facilityData['parent_place_id'] = \$placeId")
    && str_contains($save, "if (!empty(\$existing))"),
    'Facility creation must use existing transactional save, server-owned Place and no empty gallery rewrite.');
$expect(str_contains($fields, 'name="coordinates" required')
    && str_contains($fields, 'data-resource-locate')
    && str_contains($fields, "if (!\$facilityForm)"),
    'Facility creation must use GPS location without address fields.');
$expect(str_contains($css, 'grid-template-columns: repeat(3, minmax(0, 1fr))')
    && !str_contains($css, 'grid-template-columns: repeat(5, minmax(0, 1fr));')
    && str_contains($css, '.admin-place-shared-page .contribution-scope-picker'),
    'Admin tabs must have exactly three equal columns, not two empty slots.');
$expect(str_contains($siteJs, "modernFacilitySelected")
    && str_contains($areaJs, "String(value) === 'new'")
    && str_contains($areaJs, 'if (!controls.size) {'),
    'Switching Facilities must work with no legacy Area answers.');
$expect(str_contains($map, "'facilities' => \$mapFacilities")
    && str_contains($mapJs, "for (const facility of linkedFacilities)")
    && str_contains($mapJs, "const value = 'resource:' + id"),
    'Coordinates created in Admin must display as selectable purple Facility markers.');

echo "PASS: Admin in-place Add/Edit Facility, GPS marker and three-column tabs.\n";
