<?php

declare(strict_types=1);

function llama_ridb_normalization_key(
    string $value
): string {
    $value =
        strtoupper(
            trim($value)
        );

    $value =
        preg_replace(
            '/[^A-Z0-9]+/',
            ' ',
            $value
        )
        ?? $value;

    return
        trim(
            preg_replace(
                '/\s+/',
                ' ',
                $value
            )
            ?? $value
        );
}

function llama_ridb_normalization_definitions(): array
{
    return [
        'facility_name' => [
            'label' => 'Facility name',
            'category' => 'Facility identity',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB Facility schema',
            'aliases' => [
                'FacilityName',
            ],
        ],

        'facility_description' => [
            'label' => 'Facility description',
            'category' => 'Facility identity',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB Facility schema',
            'aliases' => [
                'FacilityDescription',
            ],
        ],

        'facility_directions' => [
            'label' => 'Directions',
            'category' => 'Access',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB Facility schema',
            'aliases' => [
                'FacilityDirections',
            ],
        ],

        'facility_latitude' => [
            'label' => 'Latitude',
            'category' => 'Location',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB Facility schema',
            'aliases' => [
                'FacilityLatitude',
            ],
        ],

        'facility_longitude' => [
            'label' => 'Longitude',
            'category' => 'Location',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB Facility schema',
            'aliases' => [
                'FacilityLongitude',
            ],
        ],

        'facility_geojson' => [
            'label' => 'GeoJSON',
            'category' => 'Location',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB Facility schema',
            'aliases' => [
                'GEOJSON',
            ],
        ],

        'facility_phone' => [
            'label' => 'Facility phone',
            'category' => 'Contact',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB Facility schema',
            'aliases' => [
                'FacilityPhone',
            ],
        ],

        'facility_email' => [
            'label' => 'Facility email',
            'category' => 'Contact',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB Facility schema',
            'aliases' => [
                'FacilityEmail',
            ],
        ],

        'facility_reservation_url' => [
            'label' => 'Reservation URL',
            'category' => 'Reservations',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB Facility schema',
            'aliases' => [
                'FacilityReservationURL',
            ],
        ],

        'facility_use_fee_description' => [
            'label' => 'Use / fee description',
            'category' => 'Pricing',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB Facility schema',
            'aliases' => [
                'FacilityUseFeeDescription',
            ],
        ],

        'facility_last_updated' => [
            'label' => 'RIDB last updated',
            'category' => 'Source metadata',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB Facility schema',
            'aliases' => [
                'LastUpdatedDate',
            ],
        ],

        'site_access' => [
            'label' => 'Site access',
            'category' => 'Access',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard',
            'aliases' => [
                'Site Access',
            ],
        ],

        'parking_type' => [
            'label' => 'Parking / driveway type',
            'category' => 'Parking',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: Parking Site Feature',
            'aliases' => [
                'Driveway Entry',
                'Parking Type',
                'RV Parking',
            ],
        ],

        'parking_surface' => [
            'label' => 'Parking / driveway surface',
            'category' => 'Parking',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: Parking Site Feature',
            'aliases' => [
                'Driveway Surface',
                'Parking Surface',
            ],
        ],

        'parking_grade' => [
            'label' => 'Parking / driveway grade',
            'category' => 'Parking',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: Parking Site Feature',
            'aliases' => [
                'Driveway Grade',
            ],
        ],

        'parking_length' => [
            'label' => 'Parking / driveway length',
            'category' => 'Parking',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: Parking Site Feature',
            'aliases' => [
                'Driveway Length',
                'Parking Length',
            ],
        ],

        'double_driveway' => [
            'label' => 'Double driveway',
            'category' => 'Parking',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB legacy attribute',
            'aliases' => [
                'Double Driveway',
            ],
        ],

        'site_length' => [
            'label' => 'Site length',
            'category' => 'Site dimensions',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard',
            'aliases' => [
                'Site Length',
            ],
        ],

        'site_width' => [
            'label' => 'Site width',
            'category' => 'Site dimensions',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard',
            'aliases' => [
                'Site Width',
            ],
        ],

        'overhead_clearance' => [
            'label' => 'Overhead clearance',
            'category' => 'Vehicle fit',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard accessibility detail',
            'aliases' => [
                'Site Height/Overhead Clearance',
                'Overhead Clearance',
            ],
        ],

        'max_vehicle_length' => [
            'label' => 'Maximum vehicle length',
            'category' => 'Vehicle fit',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Max Vehicle Length',
            ],
        ],

        'max_vehicles' => [
            'label' => 'Maximum vehicles',
            'category' => 'Capacity',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: Parking Site Feature',
            'aliases' => [
                'Max Num of Vehicles',
                'Maximum Number of Vehicles',
            ],
        ],

        'min_vehicles' => [
            'label' => 'Minimum vehicles',
            'category' => 'Capacity',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Min Num of Vehicles',
            ],
        ],

        'base_vehicles' => [
            'label' => 'Base vehicles included',
            'category' => 'Capacity',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Base Number of Vehicles',
            ],
        ],

        'max_people' => [
            'label' => 'Maximum people',
            'category' => 'Capacity',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Max Num of People',
                'Maximum Number of People',
            ],
        ],

        'min_people' => [
            'label' => 'Minimum people',
            'category' => 'Capacity',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Min Num of People',
            ],
        ],

        'base_people' => [
            'label' => 'Base people included',
            'category' => 'Capacity',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Base Number of People',
            ],
        ],

        'tent_pad_present' => [
            'label' => 'Tent pad / platform',
            'category' => 'Tent setup',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: Tent Pad / Platform',
            'aliases' => [
                'Tent Pad',
                'Tent Pads',
            ],
        ],

        'tent_pad_length' => [
            'label' => 'Tent pad length',
            'category' => 'Tent setup',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: Tent Pad / Platform',
            'aliases' => [
                'Tent Pad Length',
            ],
        ],

        'tent_pad_width' => [
            'label' => 'Tent pad width',
            'category' => 'Tent setup',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: Tent Pad / Platform',
            'aliases' => [
                'Tent Pad Width',
            ],
        ],

        'electric_hookup' => [
            'label' => 'Electric hookup',
            'category' => 'Hookups',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: ELECTRIC',
            'aliases' => [
                'Electricity Hookup',
                'Electric Hookup',
                'Electric Hookups',
                'ELECTRIC HOOKUPS',
            ],
        ],

        'water_hookup' => [
            'label' => 'Water hookup',
            'category' => 'Hookups',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: WATER',
            'aliases' => [
                'Water Hookup',
                'Water Hookups',
                'WATER HOOKUPS',
            ],
        ],

        'sewer_hookup' => [
            'label' => 'Sewer hookup',
            'category' => 'Hookups',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: SEWER',
            'aliases' => [
                'Sewer Hookup',
                'Sewer Hookups',
                'SEWER HOOKUPS',
            ],
        ],

        'potable_water' => [
            'label' => 'Water availability',
            'category' => 'Water',
            'treatment' => 'source_attributed',
            'standard_basis' => 'Federal Camping Data Standard: WATER TYPE / LOCATION / ACCESS',
            'aliases' => [
                'Water',
                'Water (Seasonal)',
                'WATER (SEASONAL)',
                'Potable Water',
            ],
        ],

        'campfire_allowed' => [
            'label' => 'Campfires allowed',
            'category' => 'Fire',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: FIRES',
            'aliases' => [
                'Campfire Allowed',
                'Fires Allowed',
            ],
        ],

        'fire_ring' => [
            'label' => 'Fire ring / fire pit',
            'category' => 'Fire',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: CAMPFIRE TYPE / Fire Ring',
            'aliases' => [
                'Fire Pit',
                'Fire Ring',
                'Fire Rings',
                'Campfire Rings',
                'CAMPFIRE RINGS',
            ],
        ],

        'grill' => [
            'label' => 'Grill / barbecue',
            'category' => 'Cooking',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: GRILL',
            'aliases' => [
                'Grill',
                'Grills',
                'GRILLS',
                'BBQ',
                'Campfire Grills',
                'Campsite Grills',
            ],
        ],

        'picnic_table' => [
            'label' => 'Picnic table',
            'category' => 'Cooking',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: OUTDOOR DINING',
            'aliases' => [
                'Picnic Table',
                'Picnic Tables',
                'PICNIC TABLES',
            ],
        ],

        'food_storage' => [
            'label' => 'Animal-proof food storage',
            'category' => 'Cooking',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: ANIMAL-PROOF STORAGE',
            'aliases' => [
                'Food Locker',
                'Food Storage Locker',
                'FOOD STORAGE LOCKER',
                'Bear Box',
            ],
        ],

        'toilet' => [
            'label' => 'Toilet',
            'category' => 'Sanitation',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: TOILET',
            'aliases' => [
                'Pit Toilets',
                'PIT TOILETS',
                'Accessible Pit Toilets',
                'ACCESSIBLE PIT TOILETS',
                'Vault Toilets',
                'Flush Toilets',
            ],
        ],

        'trash_collection' => [
            'label' => 'Trash collection',
            'category' => 'Sanitation',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB / agency amenity',
            'aliases' => [
                'Trash Collection',
                'TRASH COLLECTION',
            ],
        ],

        'pets_allowed' => [
            'label' => 'Pets allowed',
            'category' => 'Rules',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard: PETS',
            'aliases' => [
                'Pets Allowed',
            ],
        ],

        'checkin_time' => [
            'label' => 'Check-in time',
            'category' => 'Operations',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Checkin Time',
                'Check-in Time',
            ],
        ],

        'checkout_time' => [
            'label' => 'Checkout time',
            'category' => 'Operations',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Checkout Time',
                'Check-out Time',
            ],
        ],

        'equipment_mandatory' => [
            'label' => 'Equipment mandatory',
            'category' => 'Rules',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'IS EQUIPMENT MANDATORY',
                'Is Equipment Mandatory',
            ],
        ],

        'proximity_to_water' => [
            'label' => 'Proximity to water',
            'category' => 'Environment',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Proximity to Water',
            ],
        ],

        'shade' => [
            'label' => 'Shade',
            'category' => 'Environment',
            'treatment' => 'source_attributed',
            'standard_basis' => 'Federal Camping Data Standard: Campsite Environment Setting',
            'aliases' => [
                'Shade',
            ],
        ],

        'privacy' => [
            'label' => 'Privacy',
            'category' => 'Environment',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Privacy',
            ],
        ],

        'quiet_area' => [
            'label' => 'Quiet area',
            'category' => 'Environment',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Quiet Area',
            ],
        ],

        'site_rating' => [
            'label' => 'Site rating',
            'category' => 'Source assessment',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Site Rating',
            ],
        ],

        'condition_rating' => [
            'label' => 'Condition rating',
            'category' => 'Source assessment',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Condition Rating',
            ],
        ],

        'location_rating' => [
            'label' => 'Location rating',
            'category' => 'Source assessment',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Location Rating',
            ],
        ],

        'capacity_size_rating' => [
            'label' => 'Capacity / size rating',
            'category' => 'Capacity',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB campsite attribute',
            'aliases' => [
                'Capacity/Size Rating',
            ],
        ],

        'hike_in_distance' => [
            'label' => 'Hike-in distance',
            'category' => 'Access',
            'treatment' => 'direct',
            'standard_basis' => 'Federal Camping Data Standard accessibility detail',
            'aliases' => [
                'Hike In Distance to Site',
                'Hike-In Distance',
            ],
        ],

        'lantern_post' => [
            'label' => 'Lantern post / hanging feature',
            'category' => 'Site amenity',
            'treatment' => 'direct',
            'standard_basis' => 'RIDB / agency amenity',
            'aliases' => [
                'Lantern Pole',
                'Lantern Post',
                'Lantern Posts',
                'LANTERN POSTS',
                'Hanging Feature',
            ],
        ],

        'lake_access' => [
            'label' => 'Lake access',
            'category' => 'Water access',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB / agency amenity',
            'aliases' => [
                'Lake Access',
                'LAKE ACCESS',
            ],
        ],

        'trailhead' => [
            'label' => 'Trailhead',
            'category' => 'Access',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB / agency amenity',
            'aliases' => [
                'Trailheads',
                'TRAILHEADS',
            ],
        ],

        'trailhead_parking' => [
            'label' => 'Trailhead parking',
            'category' => 'Parking',
            'treatment' => 'source_attributed',
            'standard_basis' => 'RIDB / agency amenity',
            'aliases' => [
                'Trailhead Parking',
                'TRAILHEAD PARKING',
            ],
        ],

        'internal_map_x' => [
            'label' => 'Internal map X',
            'category' => 'Internal campground map',
            'treatment' => 'layout_only',
            'standard_basis' => 'RIDB campground layout coordinate',
            'aliases' => [
                'Map X Coordinate',
            ],
        ],

        'internal_map_y' => [
            'label' => 'Internal map Y',
            'category' => 'Internal campground map',
            'treatment' => 'layout_only',
            'standard_basis' => 'RIDB campground layout coordinate',
            'aliases' => [
                'Map Y Coordinate',
            ],
        ],

        'placed_on_map' => [
            'label' => 'Placed on internal map',
            'category' => 'Internal campground map',
            'treatment' => 'layout_only',
            'standard_basis' => 'RIDB campground layout coordinate',
            'aliases' => [
                'Placed on Map',
            ],
        ],
    ];
}

function llama_ridb_normalization_alias_index(): array
{
    $index = [];

    foreach (
        llama_ridb_normalization_definitions()
        as $canonical => $definition
    ) {
        foreach (
            (array) (
                $definition['aliases']
                ?? []
            )
            as $alias
        ) {
            $index[
                llama_ridb_normalization_key(
                    (string) $alias
                )
            ] = $canonical;
        }
    }

    return $index;
}

function llama_ridb_normalization_match(
    string $attributeName
): ?array {
    $definitions =
        llama_ridb_normalization_definitions();

    $index =
        llama_ridb_normalization_alias_index();

    $key =
        llama_ridb_normalization_key(
            $attributeName
        );

    $canonical =
        $index[$key]
        ?? null;

    if (
        $canonical === null
        || !isset(
            $definitions[$canonical]
        )
    ) {
        return null;
    }

    return
        array_merge(
            [
                'canonical' =>
                    $canonical,
            ],
            $definitions[$canonical]
        );
}

function llama_ridb_normalization_report(
    PDO $ridbDb
): array {
    if (
        !function_exists(
            'llama_ridb_schema_attribute_catalog'
        )
    ) {
        throw new RuntimeException(
            'RIDB schema helper is required.'
        );
    }

    $catalog =
        llama_ridb_schema_attribute_catalog(
            $ridbDb
        );

    $mapped = [];
    $unmapped = [];
    $mappedSiteOccurrences = 0;
    $unmappedSiteOccurrences = 0;

    foreach (
        (array) (
            $catalog['attributes']
            ?? []
        )
        as $attribute
    ) {
        $name =
            (string) (
                $attribute[
                    'attribute_name'
                ]
                ?? ''
            );

        $match =
            llama_ridb_normalization_match(
                $name
            );

        if ($match === null) {
            $unmapped[] =
                $attribute;

            $unmappedSiteOccurrences +=
                (int) (
                    $attribute[
                        'sites_seen'
                    ]
                    ?? 0
                );

            continue;
        }

        $canonical =
            (string) $match[
                'canonical'
            ];

        if (!isset($mapped[$canonical])) {
            $mapped[$canonical] = [
                'canonical' =>
                    $canonical,

                'label' =>
                    (string) $match[
                        'label'
                    ],

                'category' =>
                    (string) $match[
                        'category'
                    ],

                'treatment' =>
                    (string) $match[
                        'treatment'
                    ],

                'standard_basis' =>
                    (string) $match[
                        'standard_basis'
                    ],

                'aliases_seen' =>
                    [],

                'sites_seen' =>
                    0,

                'sites_nonblank' =>
                    0,

                'examples' =>
                    [],
            ];
        }

        $mapped[$canonical][
            'aliases_seen'
        ][] =
            $name;

        $mapped[$canonical][
            'sites_seen'
        ] +=
            (int) (
                $attribute[
                    'sites_seen'
                ]
                ?? 0
            );

        $mapped[$canonical][
            'sites_nonblank'
        ] +=
            (int) (
                $attribute[
                    'sites_nonblank'
                ]
                ?? 0
            );

        foreach (
            (array) (
                $attribute['examples']
                ?? []
            )
            as $example
        ) {
            if (
                !in_array(
                    $example,
                    $mapped[$canonical][
                        'examples'
                    ],
                    true
                )
                && count(
                    $mapped[$canonical][
                        'examples'
                    ]
                ) < 8
            ) {
                $mapped[$canonical][
                    'examples'
                ][] =
                    $example;
            }
        }

        $mappedSiteOccurrences +=
            (int) (
                $attribute[
                    'sites_seen'
                ]
                ?? 0
            );
    }

    $mapped =
        array_values($mapped);

    usort(
        $mapped,
        static function (
            array $a,
            array $b
        ): int {
            $category =
                strnatcasecmp(
                    (string) $a['category'],
                    (string) $b['category']
                );

            if ($category !== 0) {
                return $category;
            }

            return
                strnatcasecmp(
                    (string) $a['label'],
                    (string) $b['label']
                );
        }
    );

    usort(
        $unmapped,
        static fn (
            array $a,
            array $b
        ): int =>
            (int) (
                $b['sites_seen']
                ?? 0
            )
            <=>
            (int) (
                $a['sites_seen']
                ?? 0
            )
    );

    return [
        'site_count' =>
            (int) (
                $catalog['site_count']
                ?? 0
            ),

        'source_attribute_count' =>
            (int) (
                $catalog[
                    'attribute_count'
                ]
                ?? 0
            ),

        'canonical_count' =>
            count($mapped),

        'mapped_source_attribute_count' =>
            (int) (
                $catalog[
                    'attribute_count'
                ]
                ?? 0
            )
            - count($unmapped),

        'unmapped_source_attribute_count' =>
            count($unmapped),

        'mapped_site_occurrences' =>
            $mappedSiteOccurrences,

        'unmapped_site_occurrences' =>
            $unmappedSiteOccurrences,

        'mapped' =>
            $mapped,

        'unmapped' =>
            $unmapped,
    ];
}

function llama_ridb_normalization_treatment_label(
    string $treatment
): string {
    return match ($treatment) {
        'direct' =>
            'Direct structured import',

        'source_attributed' =>
            'Import with RIDB attribution',

        'layout_only' =>
            'Keep for campground layout',

        default =>
            'Review before import',
    };
}
