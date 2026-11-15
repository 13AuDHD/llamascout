<?php
declare(strict_types=1);

// New Place and Site are local tabs; Facility and Resource have independent forms.
function tabs_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function tabs_render(string $context, int $draft = 0): string
{
    if (in_array($context, ['resource', 'facility'], true)) {
        $contributionPickerResourcePage = true;
        $contributionIsFacility = $context === 'facility';
        $contributionReturnDraft = $draft;
    } elseif (in_array($context, ['new-place', 'site'], true)) {
        $areaInitialTab = $context === 'new-place' ? 'place' : $context;
        $draftId = $draft;
    }
    ob_start();
    require dirname(__DIR__, 2) . '/partials/place-report/contribution-tabs.php';
    return (string) ob_get_clean();
}

$place = tabs_render('new-place', 17);
$site = tabs_render('site', 17);
$facility = tabs_render('facility', 17);
$resource = tabs_render('resource', 17);
$admin = tabs_render('admin');

foreach (['Place', 'Site', 'Facility', 'Resource'] as $label) {
    foreach ([$place, $site, $facility, $resource] as $html) {
        tabs_check(str_contains($html, '>' . $label . '<'),
            'Missing ' . $label . ' contribution choice.');
    }
}
tabs_check(str_contains($place, 'data-contribution-mode="place"')
    && str_contains($place, 'data-contribution-mode="site"'),
    'Place and Site must remain local report tabs.');
tabs_check(!str_contains($place, 'data-contribution-mode="area"'),
    'Facility must no longer be an Area panel in Add Place.');
tabs_check(str_contains($place, '/resource-contribution.php?kind=facility&return_draft=17'),
    'Facility must have an independent submission route and preserve Place draft.');
tabs_check(str_contains($place, '/resource-contribution.php?return_draft=17'),
    'Resource must have an independent submission route and preserve Place draft.');
tabs_check(str_contains($facility, 'aria-current="page">Facility</span>'),
    'Facility tab should be selected in the independent Facility form.');
tabs_check(str_contains($resource, 'aria-current="page">Resource</span>'),
    'Resource tab should be selected in the independent Resource form.');
tabs_check(str_contains($resource, '/resource-contribution.php?kind=facility&return_draft=17'),
    'Resource to Facility navigation must retain draft ID.');
tabs_check(str_contains($facility, '/add-place.php?tab=site&draft=17'),
    'Facility to Site navigation must retain draft ID.');
tabs_check(!str_contains($facility, 'data-contribution-mode'),
    'Facility must not accidentally render an embedded Place form.');
tabs_check(!str_contains($admin, 'Resource</'),
    'Admin switcher should keep its existing scope.');

$intros = [
    'place' => [$place, 'Add a Place', 'Describe a Place'],
    'site' => [$site, 'Add a Site', 'Describe a Site'],
    'area' => [$facility, 'Add a Facility', 'Describe an on-site Facility'],
    'resource' => [$resource, 'Add a Resource', 'Describe an off-site Resource'],
];
foreach ($intros as $mode => [$html, $name, $text]) {
    tabs_check(str_contains($html, 'data-contribution-intro-mode="' . $mode . '"')
        && str_contains($html, $name) && str_contains($html, $text),
        'Wrong intro for ' . $name);
}
tabs_check(!str_contains($admin, 'contribution-type-intro'),
    'Admin reports must not show new-contribution intro cards.');

$root = dirname(__DIR__, 2);
$add = file_get_contents($root . '/add-place.php');
$form = file_get_contents($root . '/partials/resources/fields.php');
$editor = file_get_contents($root . '/resource-contribution.php');
tabs_check(!str_contains($add, "require __DIR__ . '/partials/place-report/add-area-form.php'"),
    'Old bundled Facility form must not render on Add Place.');
tabs_check(str_contains($add, "http_build_query(\$query)"),
    'Legacy Facility URL must redirect to standalone Facility.');
tabs_check(str_contains($editor, "partials/place-report/contribution-tabs.php"),
    'Facility and Resource must share navigation.');
tabs_check(str_contains($form, 'data-facility-parent-picker')
    && str_contains($form, 'name="parent_place_id"'),
    'Facility must require an explicit Parent Place.');
tabs_check(str_contains($form, 'if (!$facilityForm)'),
    'Facility must omit Resource address inputs.');
echo "PASS: independent Facility/Resource forms, Place/Site tabs, Parent Place search and draft-safe navigation\n";
