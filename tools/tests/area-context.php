<?php

declare(strict_types=1);

// Run with: php tools/tests/area-context.php
// No database or login required.
require_once dirname(__DIR__, 2) . '/app/place-report.php';

function area_context_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

$areaQuestions =
    llama_report_questions_for_scope(
        'area',
        true
    );

area_context_assert(
    isset($areaQuestions['amenity_toilets']),
    'Area includes Place/Area shared amenities.'
);

area_context_assert(
    isset($areaQuestions['daytime_noise']),
    'Area includes all-scope sensory observations.'
);

area_context_assert(
    !isset($areaQuestions['site_number']),
    'Area excludes Site identity fields.'
);

area_context_assert(
    !isset($areaQuestions['site_length_feet']),
    'Area excludes Site-only physical dimensions.'
);

$amenityField =
    llama_report_question(
        'amenity_toilets'
    );

area_context_assert(
    is_array($amenityField),
    'Shared amenity question exists in the canonical catalog.'
);

$directArea =
    llama_place_report_resolve_scoped_answer(
        $amenityField,
        ['amenity_toilets' => 'place'],
        ['amenity_toilets' => 'area'],
        [],
        'area'
    );

area_context_assert(
    ($directArea['value'] ?? null) === 'area'
        && ($directArea['source_scope'] ?? null) === 'area'
        && ($directArea['inherited'] ?? null) === false,
    'Direct Area answer overrides the Place answer.'
);

$inheritedPlace =
    llama_place_report_resolve_scoped_answer(
        $amenityField,
        ['amenity_toilets' => 'place'],
        [],
        [],
        'area'
    );

area_context_assert(
    ($inheritedPlace['value'] ?? null) === 'place'
        && ($inheritedPlace['source_scope'] ?? null) === 'place'
        && ($inheritedPlace['inherited'] ?? null) === true,
    'Area inherits an eligible Place answer when no Area override exists.'
);

$siteField =
    llama_report_question(
        'site_length_feet'
    );

$siteOnlyAtArea =
    llama_place_report_resolve_scoped_answer(
        $siteField,
        ['site_length_feet' => 35],
        ['site_length_feet' => 40],
        [],
        'area'
    );

area_context_assert(
    ($siteOnlyAtArea['recorded'] ?? true) === false,
    'Site-only fields cannot resolve into Area context.'
);

echo "Area context checks passed.\n";
