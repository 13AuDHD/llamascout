<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report.php';

$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$root = dirname(__DIR__, 2);
$get = static fn (string $file): string => (string) file_get_contents($root . '/' . $file);

$siteLocation = $get('partials/place-report/site-location.php');
$sharedForm = $get('partials/place-report/form.php');
$admin = $get('admin/place.php');
$adminTabs = $get('partials/place-report/contribution-tabs.php');
$adminSummary = $get('admin/partials/place-summary-report.php');
$saveService = $get('app/admin-place-sites.php');
$saveController = $get('app/admin-place-actions.php');
$css = $get('css/admin/pages/place.css');
$mainSave = $get('js/admin/place-report-save.js');

$assert(str_contains($adminTabs, 'contributionAdminPlaceId')
    && str_contains($adminTabs, 'data-contribution-facility-link')
    && str_contains($adminTabs, 'data-contribution-resource-link'),
    'Admin navigation must link separately to Facility and Resource.');
$assert(str_contains($adminSummary, 'Facilities at this Place')
    && str_contains($adminSummary, 'Edit Facility')
    && str_contains($adminSummary, 'data-admin-save-report-label'),
    'Admin must show linked Facility records and a target-aware save action.');
$assert(str_contains($css, '@import url("../../site/features/contribution-tabs.css")')
    && str_contains($css, 'data-report-main-section="site"')
    && str_contains($css, '--admin-report-accent'),
    'Admin must load shared tab styling and Site orange headings.');
$assert(str_contains($sharedForm, "\$approvedKey === 'site_info'")
    && str_contains($sharedForm, "\$placeReportMode === 'moderator' && \$hasAdminSitePicker"),
    'Admin Site Location must follow Site Info only when the Site picker exists.');
$assert(str_contains($siteLocation, 'name="admin_site_coordinates_changed"')
    && str_contains($admin, '/js/admin/place-site-location.js'),
    'Admin Site Location needs a touched marker and target-aware controller.');
$assert(str_contains($saveService, 'admin_place_save_selected_site_coordinates(')
    && str_contains($saveService, 'UPDATE place_campsites SET latitude = ?, longitude = ?')
    && str_contains($saveService, "['map_feature_id']"),
    'Admin Site GPS must write existing child Site coordinates and respect mapped Sites.');
$assert(str_contains($saveController, 'admin_report_mode')
    && str_contains($mainSave, "saveData.delete('admin_area_answers_json')")
    && str_contains($mainSave, "saveData.delete('site_coordinates')")
    && str_contains($mainSave, "saveData.set('admin_report_mode', mode)"),
    'Save routing must keep Site and Area payloads out of unrelated Place saves.');
$assert(!str_contains($admin, 'ALTER TABLE'), 'Admin page must not mutate the database schema.');

$good = llama_place_report_parse_first_site_coordinates('37.2522200, -107.2192000');
$assert($good === ['latitude' => 37.25222, 'longitude' => -107.2192],
    'Admin Site coordinates must use the canonical Site parser.');
foreach (['999, -107', '0, 0', '37, -181', '37', '<script>'] as $bad) {
    try {
        llama_place_report_parse_first_site_coordinates($bad);
        throw new RuntimeException('Invalid coordinates were accepted: ' . $bad);
    } catch (InvalidArgumentException) {
        // Correct input rejection.
    }
}
echo "PASS: Admin Place/Area/Site editor styling, terminology, targeted saves, and Site GPS contract.\n";
