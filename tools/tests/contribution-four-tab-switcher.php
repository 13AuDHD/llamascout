<?php
declare(strict_types=1);

// Regression: four matching tabs on both Add Place and Resource,
// without turning the Resource editor into a nested Place form.
function tabs_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function tabs_render(string $context, int $draft = 0): string
{
    if ($context === 'resource') {
        $contributionPickerResourcePage = true;
        $contributionReturnDraft = $draft;
    } elseif ($context === 'new-place') {
        $areaInitialTab = 'place';
        $draftId = $draft;
    }
    ob_start();
    require dirname(__DIR__, 2) . '/partials/place-report/contribution-tabs.php';
    return (string) ob_get_clean();
}

$place = tabs_render('new-place', 17);
$resource = tabs_render('resource', 17);
$admin = tabs_render('admin');

foreach (['Place', 'Site', 'Facility', 'Resource'] as $name) {
    tabs_check(str_contains($place, '>' . $name . '</'),
        'Add Place switcher missing ' . $name);
    tabs_check(str_contains($resource, '>' . $name . '</'),
        'Resource switcher missing ' . $name);
}
tabs_check(str_contains($place, 'data-contribution-mode="place"'),
    'Place/Site/Facility need interactive local tabs.');
tabs_check(str_contains($place, 'data-contribution-mode="site"'),
    'Site tab missing from Add Place.');
tabs_check(str_contains($place, 'data-contribution-mode="area"'),
    'Facility tab must retain canonical Area mode.');
tabs_check(str_contains($place, '/resource-contribution.php?return_draft=17'),
    'Linking to Resource must retain an existing Place draft ID.');
tabs_check(str_contains($resource, '/add-place.php?tab=place&draft=17'),
    'Resource to Place navigation lost draft ID.');
tabs_check(str_contains($resource, '/add-place.php?tab=site&draft=17'),
    'Resource to Site navigation must load Site directly.');
tabs_check(str_contains($resource, '/add-place.php?tab=area&draft=17'),
    'Resource to Facility navigation must load Facility directly.');
tabs_check(str_contains($resource, 'aria-current="page">Resource</span>'),
    'Resource tab must be clearly active.');
tabs_check(!str_contains($resource, 'data-contribution-picker\n    hidden'),
    'Resource page navigation must be visible without local-tab JavaScript.');
tabs_check(!str_contains($resource, 'data-contribution-mode'),
    'Resource page must not have inactive local-tab buttons.');
tabs_check(!str_contains($admin, 'Resource</'),
    'Existing admin report switcher should retain its original scope only.');

$addPlace = file_get_contents(dirname(__DIR__, 2) . '/add-place.php');
$resourcePage = file_get_contents(dirname(__DIR__, 2) . '/resource-contribution.php');
$css = file_get_contents(dirname(__DIR__, 2) . '/css/site/features/contribution-tabs.css');
tabs_check(str_contains($addPlace, "'place', 'site', 'area'"),
    'Add Place must accept direct Site and Facility links.');
tabs_check(str_contains($resourcePage, "partials/place-report/contribution-tabs.php"),
    'Resource editor must render the shared navigation partial.');
tabs_check(str_contains($resourcePage, "'site/features/contribution-tabs.css'"),
    'Resource page must load the same navigation stylesheet.');
tabs_check(str_contains($resourcePage, "\$_GET['return_draft']"),
    'Resource editor must preserve a Place draft across page navigation.');
tabs_check(str_contains($css, '.contribution-current-resource[aria-current="page"]'),
    'Resource selected state must use the same segmented-control style.');
echo "PASS: shared Place/Site/Facility/Resource switcher, current state, admin isolation and draft-safe cross-navigation\n";
