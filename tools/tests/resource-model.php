<?php

declare(strict_types=1);

// Run with: php tools/tests/resource-model.php
require_once dirname(__DIR__, 2) . '/app/resources.php';

function resource_model_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

$types = llama_resource_types();
$services = llama_resource_service_types();

foreach ([
    'convenience_store',
    'water_station',
    'dump_station',
    'restroom_facility',
    'propane_fill',
] as $type) {
    resource_model_assert(
        isset($types[$type]),
        "Resource type {$type} is registered."
    );
}

foreach ([
    'potable_water',
    'dump_station',
    'toilet',
    'propane',
    'ev_charging',
] as $service) {
    resource_model_assert(
        isset($services[$service]),
        "Resource service {$service} is registered."
    );
}

resource_model_assert(
    llama_resource_coordinates_valid(
        37.2753,
        -107.8801
    ),
    'Valid Resource coordinates are accepted.'
);

resource_model_assert(
    !llama_resource_coordinates_valid(
        0,
        0
    ),
    'Zero/zero Resource coordinates are rejected.'
);

$public =
    llama_resource_public_view([
        'id' => 7,
        'resource_type' => 'convenience_store',
        'resource_type_label' => 'Convenience store',
        'name' => 'Highway Market',
        'latitude' => 37.2753,
        'longitude' => -107.8801,
        'has_coordinates' => true,
        'street_address' => '100 Main St',
        'city' => 'Example',
        'state_region' => 'CO',
        'postal_code' => '81301',
        'country_code' => 'US',
        'phone' => '555-0100',
        'website_url' => 'https://example.com',
        'hours_text' => '24 hours',
        'status' => 'available',
        'notes' => '',
        'source_provider' => 'Official website',
        'source_external_id' => 'private-id',
        'source_url' => 'https://example.com/source',
        'source_updated_at' => '2026-10-20 12:00:00',
        'verified_at' => '2026-10-21 12:00:00',
        'created_by' => 13,
        'updated_by' => 13,
        'services' => [
            [
                'service_type' => 'dump_station',
                'service_type_label' => 'Dump station',
                'status' => 'available',
                'fee_status' => 'paid',
                'fee_description' => '$10',
                'access_notes' => '',
                'notes' => '',
            ],
            [
                'service_type' => 'potable_water',
                'service_type_label' => 'Potable water',
                'status' => 'available',
                'fee_status' => 'free',
                'fee_description' => '',
                'access_notes' => '',
                'notes' => '',
            ],
        ],
    ]);

resource_model_assert(
    ($public['name'] ?? '') === 'Highway Market'
        && count($public['services'] ?? []) === 2,
    'One standalone Resource can expose multiple services.'
);

resource_model_assert(
    !array_key_exists('place_id', $public)
        && !array_key_exists('area_feature_id', $public),
    'Resource public identity has no Place or Area parent.'
);

resource_model_assert(
    !array_key_exists('created_by', $public)
        && !array_key_exists('updated_by', $public)
        && !array_key_exists('source_external_id', $public)
        && !array_key_exists('source_url', $public),
    'Public Resource view strips internal ownership/source identifiers.'
);

echo "Resource model checks passed.\n";
