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
$adminSiteRules = $get('admin/partials/place-report-site-shared-questions.php');
$siteAdd = $get('add-site.php');
$tabsCss = $get('css/site/features/contribution-tabs.css');
$tabs = $get('partials/place-report/contribution-tabs.php');
$saveService = $get('app/admin-place-sites.php');
$saveController = $get('app/admin-place-actions.php');
$css = $get('css/admin/pages/place.css');
$mainSave = $get('js/admin/place-report-save.js');

$adminFragment = explode('<?php elseif ($isNewContribution): ?>',
    explode('<?php if ($isAdminPlacePage): ?>', $adminTabs, 2)[1] ?? '', 2)[0];
$assert(str_contains($adminTabs, 'contributionAdminPlaceId')
    && str_contains($adminFragment, 'data-contribution-mode="facility"')
    && !str_contains($adminFragment, 'data-contribution-resource-link')
    && !str_contains($adminFragment, 'data-contribution-mode="area"'),
    'Admin must show only Place, Site, and Facility tabs.');
$assert(str_contains($adminSummary, "place-report-area-selector.php")
    && str_contains($adminSummary, "place-linked-facility-editor.php")
    && str_contains($adminSummary, 'data-admin-save-report-label'),
    'Admin must use one Facility selector for both mapped and linked Facility editors.');
$assert(str_contains($css, '@import url("../../site/features/contribution-tabs.css")')
    && str_contains($css, 'data-report-main-section="site"')
    && str_contains($css, '--admin-report-accent'),
    'Admin must load shared tab styling and Site orange headings.');
$assert(str_contains($sharedForm, "\$approvedKey === 'site_info'")
    && str_contains($sharedForm, "\$placeReportMode === 'moderator' && \$hasAdminSitePicker"),
    'Admin Site Location must follow Site Info only when the Site picker exists.');
$siteRules = llama_report_questions_for_scope('site', true);
$assert(isset($siteRules['pets_allowed'], $siteRules['generator_restrictions'])
    && !isset($siteRules['latitude'], $siteRules['longitude'])
    && str_contains($sharedForm, "\$placeReportOnlyScope === 'site' && \$reportMainKey !== 'site'")
    && str_contains($sharedForm, "\$placeReportOnlyScope !== 'site'")
    && str_contains($siteAdd, "\$placeReportOnlyScope = 'site';"),
    'Standalone Add Site must display Site-owned rules, not parent Place Location.');
$assert(str_contains($adminSummary, "place-report-site-shared-questions.php")
    && !str_contains($adminSummary, 'data-admin-site-shared-questions hidden disabled')
    && str_contains($sharedForm, 'require $placeReportSiteSharedPartial;')
    && str_contains($adminSiteRules, 'data-admin-site-shared-questions hidden disabled')
    && str_contains($adminSiteRules, 'admin_site_')
    && str_contains($adminSiteRules, "llama_report_questions_for_scope('site', true)"),
    'Admin Site shared policy controls must render inside Site section and remain separate from Place fields.');
$assert(str_contains($tabs, 'contribution-current-resource is-site')
    && str_contains($tabsCss, '.contribution-current-resource.is-site[aria-current="page"]')
    && str_contains($tabsCss, '--mode-color: var(--place-accent)')
    && str_contains($css, '--admin-report-accent: var(--place-accent)'),
    'Place styling must use the forest-green token, and standalone Site amber rather than Resource teal.');
$approvedPages = llama_place_report_approved_pages();
$assert(!in_array('rules_public', $approvedPages['place']['groups'], true)
    && in_array('rules_public', $approvedPages['site']['groups'], true)
    && (llama_place_report_approved_group_page_lookup()['rules_public']['page'] ?? '') === 'site',
    'Public Rules and Restrictions must belong to Site, not Place.');
$assert(str_contains($sharedForm, "\$placeReportOnlyScope !== 'site'")
    && str_contains($sharedForm, "!== 'rules_public'")
    && str_contains($adminSiteRules, 'data-admin-site-shared-questions hidden disabled'),
    'Hide parent Place rules in the shared Place form while retaining Site Admin controls.');
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
    'Save routing must keep Site and mapped Facility payloads out of unrelated Place saves.');
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
echo "PASS: Admin Place/Facility/Site editor styling, terminology, targeted saves, and Site GPS contract.\n";
