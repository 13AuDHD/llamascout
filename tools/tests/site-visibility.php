<?php

declare(strict_types=1);

// Run with: php tools/tests/site-visibility.php
// No production database or login required.
require_once dirname(__DIR__, 2) . '/app/site-visibility.php';

$developedPlace = ['type' => 'developed-campground'];
$dispersedPlace = ['type' => 'dispersed-camping'];
$rvPlace = ['type' => 'rv-park-resort'];
$otherPlace = ['type' => 'camping-area'];
$developedSite = ['id' => 42, 'site_type' => 'tent_site', 'raw_site_type' => ''];
$dispersedSite = ['id' => 42, 'site_type' => 'tent_site', 'raw_site_type' => 'dispersed'];
$restrictedIds = [42 => true];

$cases = [
    'developed campground Site is public by default' =>
        llama_site_can_read($developedPlace, $developedSite, false, []),
    'RV park Site is public by default' =>
        llama_site_can_read($rvPlace, $developedSite, false, []),
    'dispersed Place stays private without overrides' =>
        !llama_site_can_read($dispersedPlace, $developedSite, false, []),
    'dispersed Place cannot publish a Site by omission' =>
        !llama_site_can_read($dispersedPlace, $developedSite, false, $restrictedIds),
    'explicitly dispersed Site stays private in developed Place' =>
        !llama_site_can_read($developedPlace, $dispersedSite, false, []),
    'imported primitive Site stays private' =>
        !llama_site_can_read(
            $developedPlace,
            ['id' => 43, 'site_type' => 'tent_site', 'raw_site_type' => 'primitive'],
            false,
            []
        ),
    'backcountry Site stays private' =>
        !llama_site_can_read(
            $developedPlace,
            ['id' => 44, 'site_type' => 'backcountry_site', 'raw_site_type' => ''],
            false,
            []
        ),
    'mixed developed Site restriction stays private' =>
        !llama_site_can_read($developedPlace, $developedSite, false, $restrictedIds),
    'other Place type stays private' =>
        !llama_site_can_read($otherPlace, $developedSite, false, []),
    'member access unlocks dispersed Site' =>
        llama_site_can_read($dispersedPlace, $dispersedSite, true, []),
    'member access unlocks restricted Site' =>
        llama_site_can_read($developedPlace, $developedSite, true, $restrictedIds),
    'invalid Site ID cannot be published' =>
        !llama_site_can_read($developedPlace, ['id' => 0], false, []),
];

$officialSite = [
    'source_provider' => 'Recreation.gov / RIDB',
    'site_length_ft' => '35',
    'site_width_ft' => '12',
    'max_people' => '6',
    'max_vehicles' => '2',
    'water_hookup' => '1',
    'latitude' => '37.12345',
    'longitude' => '-107.12345',
    'source_url' => 'https://example.com/private',
    'features' => [['feature_key' => 'scout_note', 'feature_value' => 'private']],
];
$publicDetails = llama_site_public_catalog_details($officialSite);
$publicLabels = array_column($publicDetails, 'label');
$publicSerialized = json_encode($publicDetails);
$cases['official Site dimensions are public'] =
    in_array('Site length', $publicLabels, true)
    && in_array('Maximum people', $publicLabels, true);
$cases['public details exclude GPS, source URLs and reports'] =
    !str_contains((string) $publicSerialized, '37.12345')
    && !str_contains((string) $publicSerialized, 'example.com')
    && !str_contains((string) $publicSerialized, 'private');
$cases['unverified source cannot publish extra Site facts'] =
    llama_site_public_catalog_details(
        array_replace($officialSite, ['source_provider' => 'Scout report'])
    ) === [];
$cases['missing source cannot publish extra Site facts'] =
    llama_site_public_catalog_details(
        array_replace($officialSite, ['source_provider' => ''])
    ) === [];

foreach ($cases as $name => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$name}\n");
        exit(1);
    }
    echo "PASS: {$name}\n";
}
