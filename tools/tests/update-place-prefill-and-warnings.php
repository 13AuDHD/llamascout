<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-update.php';
require_once dirname(__DIR__, 2) . '/app/place-report/site-identity-update.php';

$assert = static function (bool $yes, string $message): void {
    if (!$yes) throw new RuntimeException('FAIL: ' . $message);
};
$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);

$definitions = llama_place_update_definitions();
$descriptionPath = null;
foreach ($definitions as $storage => $definition) {
    if (($definition['shared']['key'] ?? '') === 'description') {
        $descriptionPath = (string) $storage;
        break;
    }
}
$assert($descriptionPath !== null, 'The canonical Description question must exist.');
$values = llama_place_update_shared_form_values(
    [$descriptionPath => 'Existing public description'], [], [], []
);
$assert(($values['description'] ?? null) === 'Existing public description',
    'Existing Place facts must be available in the editable Place form.');
$revision = llama_place_update_shared_form_values(
    [$descriptionPath => 'Original description'], [],
    [$descriptionPath => 'Suggested edit'], []
);
$assert(($revision['description'] ?? null) === 'Suggested edit',
    'Moderator-requested revisions must take priority over current Place state.');

$page = $read('account/update-place.php');
$form = $read('partials/place-report/form.php');
$tabs = $read('partials/place-report/contribution-tabs.php');
$js = $read('js/place-update-report-target.js');
$public = $read('partials/place-report/read-only.php');
$scoped = $read('app/place-report/scoped-update-review.php');
$immutable = $read('app/place-report/immutable-history.php');
$moderate = $read('admin/moderate-update.php');

$assert(str_contains($page, "\$currentValues,\n    \$publishedUnknownFields")
    && str_contains($page, '$placeReportInitiallyTouchedKeys = [];')
    && str_contains($form, '$placeReportInitiallyTouchedKeys ?? array_keys($placeReportValues)'),
    'Prefilled published answers must not turn into automatically submitted Place changes.');
$assert(str_contains($form, "'initial_answer_keys' => array_values(")
    && str_contains($form, "\$placeReportMode === 'update-report'")
    && str_contains($form, "if (\$placeReportMode === 'update-report') {\n            return '';"),
    'Prefilled Place values must not be touched and repeated Current Answer text must be hidden in Updates.');

$unchanged = llama_place_update_build_changes(
    ['_report_partial' => '1', '_report_touched' => ['description' => '1'],
     'description' => 'Existing public description'],
    [$descriptionPath => 'Existing public description'], [], true
);
$assert(($unchanged['proposed'] ?? null) === [],
    'Reselecting or reverting an existing Place value must not create a moderation proposal.');
$changed = llama_place_update_build_changes(
    ['_report_partial' => '1', '_report_touched' => ['description' => '1'],
     'description' => 'Updated public description'],
    [$descriptionPath => 'Existing public description'], [], true
);
$assert(($changed['proposed'][$descriptionPath] ?? null) === 'Updated public description',
    'An actual edited Place value must still reach moderation.');
$assert(str_contains($tabs, 'data-contribution-mode="area" aria-pressed="false">Facility</button>')
    && str_contains($page, "'area' => 'Facilities'"),
    'Public Update navigation must say Facility while retaining legacy target IDs.');
$assert(str_contains($js, 'function prefillRecordedFact(field)')
    && str_contains($js, 'FRESH_OBSERVATION_GROUPS')
    && str_contains($js, "'sensory_environment'")
    && str_contains($js, "'connectivity_services'")
    && str_contains($js, "'experience_recommendations'")
    && str_contains($js, "scope.value === 'area'")
    && !str_contains($js, 'data.scopedExisting')
    && str_contains($js, 'identityDrafts.set(activeTarget, changes)'),
    'Site and Facility policies must be prefilled, personal observations must start fresh, and duplicated answer notes must be absent.');
$assert(str_contains($page, 'data-place-update-site-identity')
    && str_contains($page, 'data-site-identity-coordinates')
    && str_contains($page, "'site_identity' => \$siteIdentity"),
    'Site identity and verified coordinates must be available to the update report.');
$assert(str_contains($scoped, 'llama_update_site_identity_proposal(')
    && str_contains($scoped, 'llama_update_site_identity_apply(')
    && str_contains($scoped, '$oldSiteIdentity')
    && str_contains($immutable, '$allowIdentityOnly')
    && str_contains($moderate, '$siteIdentityChanges'),
    'Site identity must be validated, reviewed, shown, and approved atomically.');
$warnings = strpos($public, '&& !$quickWarningsRendered');
$safety = strpos($public, "&& (string) \$sectionKey === 'safety_public'");
$assert($warnings !== false && $safety !== false && $warnings < $safety
    && str_contains($public, 'data-report-warnings')
    && str_contains($public, 'data-report-page="site"'),
    'Derived Quick Warnings must render at the start of the Site page.');
$assert(llama_update_site_identity_coordinate_pair('37.2522200, -107.2192000')
    === [37.25222, -107.2192],
    'Site GPS correction parser must preserve latitude/longitude ordering.');
foreach (['0, 0', '91, 2', '37, -181', 'abc, 2'] as $invalid) {
    $rejected = false;
    try { llama_update_site_identity_coordinate_pair($invalid); }
    catch (InvalidArgumentException) { $rejected = true; }
    $assert($rejected, 'Invalid Site GPS should be rejected: ' . $invalid);
}
echo "PASS: Update Place prefill, fresh Site observations, reviewed Site identity and top Quick Warnings.\n";
