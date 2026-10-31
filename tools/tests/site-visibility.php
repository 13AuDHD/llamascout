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

foreach ($cases as $name => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$name}\n");
        exit(1);
    }
    echo "PASS: {$name}\n";
}
