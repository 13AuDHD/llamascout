<?php

declare(strict_types=1);

// Run with: php tools/tests/resource-model.php
require_once dirname(__DIR__, 2) . '/app/place-resources.php';

function resource_model_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

$types =
    llama_place_resource_types();

foreach ([
    'potable_water',
    'water_fill',
    'dump_station',
    'toilet',
    'shower',
    'trash',
    'recycling',
    'ev_charging',
    'propane',
] as $type) {
    resource_model_assert(
        isset($types[$type]),
        "Resource type {$type} is registered."
    );
}

resource_model_assert(
    llama_place_resource_coordinates_valid(
        37.2753,
        -107.8801
    ),
    'Valid Resource coordinates are accepted.'
);

resource_model_assert(
    !llama_place_resource_coordinates_valid(
        0,
        0
    ),
    'Zero/zero Resource coordinates are rejected.'
);

resource_model_assert(
    !llama_place_resource_coordinates_valid(
        100,
        -107
    ),
    'Out-of-range Resource latitude is rejected.'
);

$public =
    llama_place_resource_public_view([
        'id' => 7,
        'place_id' => 12,
        'area_feature_id' => 44,
        'area_label' => 'Loop A',
        'resource_type' => 'potable_water',
        'resource_type_label' => 'Potable water',
        'resource_subtype' => 'spigot',
        'display_name' => 'Water spigot',
        'latitude' => 37.2753,
        'longitude' => -107.8801,
        'has_coordinates' => true,
        'status' => 'available',
        'fee_status' => 'free',
        'fee_description' => '',
        'hours_text' => '24 hours',
        'access_type' => 'public',
        'access_notes' => '',
        'notes' => 'Turn handle slowly.',
        'source_provider' => 'USFS',
        'source_external_id' => 'private-id',
        'source_url' => 'https://example.com/internal',
        'source_updated_at' => '2026-10-20 12:00:00',
        'verified_at' => '2026-10-21 12:00:00',
        'created_by' => 13,
        'updated_by' => 13,
    ]);

resource_model_assert(
    ($public['display_name'] ?? '') === 'Water spigot'
        && ($public['area_label'] ?? '') === 'Loop A',
    'Public Resource view preserves identity and optional Area context.'
);

resource_model_assert(
    !array_key_exists(
        'created_by',
        $public
    )
    && !array_key_exists(
        'updated_by',
        $public
    )
    && !array_key_exists(
        'source_external_id',
        $public
    )
    && !array_key_exists(
        'source_url',
        $public
    ),
    'Public Resource view strips internal ownership and source identifiers.'
);

echo "Resource model checks passed.\n";
