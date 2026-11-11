<?php

declare(strict_types=1);

/*
 * Area-only question ownership regression.
 *
 * Uses the canonical registry's existing scope projection. No database
 * connection, schema migration, or test Place is required.
 */
require_once dirname(__DIR__, 2) . '/app/place-report/flow-scopes.php';

$groups = llama_place_report_scope_groups();
if (($groups['area'] ?? null) !== ['area']) {
    throw new RuntimeException('Area scope must not include Place or Site.');
}
if (($groups['place_area'] ?? null) !== ['place', 'area']) {
    throw new RuntimeException('Existing shared Place/Area scope changed.');
}
$fields = [
    'facility_cleanliness' => [
        'key' => 'facility_cleanliness',
        'section' => 'amenities',
        'scope_group' => 'area',
    ],
    'existing_place_area' => [
        'key' => 'existing_place_area',
        'section' => 'amenities',
        'scope_group' => 'place_area',
    ],
    'existing_site' => [
        'key' => 'existing_site',
        'section' => 'site',
        'scope_group' => 'site',
    ],
];
llama_place_report_apply_flow_scopes($fields);
foreach ([
    'facility_cleanliness' => ['area'],
    'existing_place_area' => ['place', 'area'],
    'existing_site' => ['site'],
] as $key => $expected) {
    if ($fields[$key]['report_scopes'] !== $expected) {
        throw new RuntimeException('Unexpected report scope for ' . $key);
    }
}
if (($fields['facility_cleanliness']['scope_inheritance'] ?? '') !== 'none') {
    throw new RuntimeException('Area-only values must not inherit.');
}
echo "Area-only scope ownership passed.\n";
