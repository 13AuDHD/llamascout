<?php

declare(strict_types=1);

// Run with: php tools/tests/site-visibility.php
// No production database or login required.
require_once dirname(__DIR__, 2) . '/app/site-visibility.php';

$developedPlace = ['type' => 'developed-campground'];
$dispersedPlace = ['type' => 'dispersed-camping'];
$rvPlace = ['type' => 'rv-park-resort'];
$developedSite = ['id' => 42, 'site_type' => 'tent_site', 'raw_site_type' => ''];
$dispersedSite = ['id' => 42, 'site_type' => 'tent_site', 'raw_site_type' => 'dispersed'];
$publicIds = [42 => true];

$cases = [
    'public developed campground Site' =>
        llama_site_can_read($developedPlace, $developedSite, false, $publicIds),
    'public RV park Site' =>
        llama_site_can_read($rvPlace, $developedSite, false, $publicIds),
    'dispersed Place cannot publish a Site' =>
        !llama_site_can_read($dispersedPlace, $developedSite, false, $publicIds),
    'explicitly dispersed Site stays private' =>
        !llama_site_can_read($developedPlace, $dispersedSite, false, $publicIds),
    'unreviewed Site stays private' =>
        !llama_site_can_read($developedPlace, $developedSite, false, []),
    'member access unlocks dispersed Site' =>
        llama_site_can_read($dispersedPlace, $dispersedSite, true, []),
    'member access unlocks unreviewed Site' =>
        llama_site_can_read($developedPlace, $developedSite, true, []),
    'invalid Site ID cannot be published' =>
        !llama_site_can_read($developedPlace, ['id' => 0], false, [0 => true]),
];

foreach ($cases as $name => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$name}\n");
        exit(1);
    }
    echo "PASS: {$name}\n";
}
