<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report.php';

function site_location_test_assert(bool $valid, string $message): void
{
    if (!$valid) {
        throw new RuntimeException($message);
    }
}

$valid = llama_place_report_parse_first_site_coordinates(' 37.2522200, -107.2192000 ');
site_location_test_assert($valid === [
    'latitude' => 37.25222,
    'longitude' => -107.2192,
], 'Site coordinates must be parsed independently from Place answers.');
site_location_test_assert(
    llama_place_report_parse_first_site_coordinates('') === null,
    'Unspecified Site coordinates must stay optional.'
);

foreach (['91, -107', '37, -181', '0, 0', '37', '37, -107, 5', '<script>bad</script>'] as $invalid) {
    $rejected = false;
    try {
        llama_place_report_parse_first_site_coordinates($invalid);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    site_location_test_assert($rejected, 'Invalid Site coordinate input accepted: ' . $invalid);
}

$root = dirname(__DIR__, 2);
$form = file_get_contents($root . '/partials/place-report/form.php');
$site = file_get_contents($root . '/partials/place-report/site-location.php');
$moderation = file_get_contents($root . '/app/moderation.php');
$runtime = file_get_contents($root . '/app/place-report/runtime.php');
$css = file_get_contents($root . '/css/site/pages/add-place.css');
$facilityCss = file_get_contents($root . '/css/site/features/resource-form.css');

site_location_test_assert(str_contains($form, "\$approvedKey === 'site_info'"),
    'Site Location must follow Site Info without moving any questions.');
site_location_test_assert(str_contains($site, 'name="site_coordinates"'),
    'Site Location must not reuse canonical Place latitude/longitude names.');
site_location_test_assert(str_contains($moderation, "\$data['_first_site_coordinates'] ?? null"),
    'Approved Site coordinates must be published to the Site record.');
site_location_test_assert(str_contains($runtime, "\$input['site_coordinates']"),
    'Site coordinates must be persisted across submissions and revisions.');
site_location_test_assert(str_contains($css, 'data-report-main-section="site"'),
    'Site category headings need the orange accent.');
site_location_test_assert(str_contains($css, 'gap: 12px;'),
    'Place and Site subsection spacing must be explicit.');
site_location_test_assert(
    !str_contains($facilityCss, '.resource-facility-theme > .contribution-resource-panel'),
    'Duplicate outer Facility purple border must stay removed.'
);
echo "PASS: Site GPS isolation, form placement, Place/Site styling, and Facility border cleanup.\n";
