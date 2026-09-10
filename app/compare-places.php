<?php

declare(strict_types=1);

/* =========================================================
   LLAMA SCOUT
   COMPARE PLACES
   ========================================================= */

require_once __DIR__ . '/places.php';


const LLAMA_COMPARE_MIN_PLACES = 2;
const LLAMA_COMPARE_MAX_PLACES = 4;


/* =========================================================
   SELECTION
   ========================================================= */

function llama_compare_requested_slugs(
    mixed $value
): array {
    if (is_string($value)) {
        $value = explode(
            ',',
            $value
        );
    }

    if (!is_array($value)) {
        return [];
    }

    $slugs = [];

    foreach ($value as $slug) {
        $slug =
            strtolower(
                trim(
                    (string) $slug
                )
            );

        if (
            $slug === ''
            || !preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slug
            )
        ) {
            continue;
        }

        if (
            !in_array(
                $slug,
                $slugs,
                true
            )
        ) {
            $slugs[] = $slug;
        }

        if (
            count($slugs)
            >= LLAMA_COMPARE_MAX_PLACES
        ) {
            break;
        }
    }

    return $slugs;
}


function llama_compare_url(
    array $slugs
): string {
    $slugs =
        llama_compare_requested_slugs(
            $slugs
        );

    if (!$slugs) {
        return
            'https://llamascout.com/compare.php';
    }

    return
        'https://llamascout.com/compare.php?places='
        . rawurlencode(
            implode(
                ',',
                $slugs
            )
        );
}


/* =========================================================
   PLACE OPTIONS
   ========================================================= */

function llama_compare_place_options(): array
{
    $places =
        places_public();

    $options = [];

    foreach ($places as $place) {
        $slug =
            trim(
                (string) (
                    $place['slug']
                    ?? ''
                )
            );

        if ($slug === '') {
            continue;
        }

        $options[] = [
            'slug' =>
                $slug,

            'name' =>
                (string) (
                    $place['name']
                    ?? 'Unnamed Place'
                ),

            'type' =>
                (string) (
                    $place['type']
                    ?? ''
                ),

            'city' =>
                (string) (
                    $place['city']
                    ?? ''
                ),

            'state' =>
                (string) (
                    $place['state']
                    ?? ''
                ),

            'elevation_feet' =>
                $place['elevation_feet']
                ?? null,
        ];
    }

    usort(
        $options,
        static function (
            array $a,
            array $b
        ): int {
            return strcasecmp(
                (string) $a['name'],
                (string) $b['name']
            );
        }
    );

    return $options;
}


/* =========================================================
   LOAD MEMBER DATA
   ========================================================= */

function llama_compare_places(
    array $slugs
): array {
    $slugs =
        llama_compare_requested_slugs(
            $slugs
        );

    $places = [];

    foreach ($slugs as $slug) {
        $place =
            place_member_by_slug(
                $slug
            );

        if (!$place) {
            continue;
        }

        $places[] =
            llama_compare_normalize_place(
                $place
            );
    }

    return $places;
}


function llama_compare_normalize_place(
    array $place
): array {
    $images =
        is_array(
            $place['images']
            ?? null
        )
            ? $place['images']
            : [];

    $featuredImage = null;

    foreach ($images as $image) {
        if (
            !empty(
                $image['is_featured']
            )
        ) {
            $featuredImage = $image;
            break;
        }
    }

    if (
        !$featuredImage
        && $images
    ) {
        $featuredImage =
            $images[0];
    }

    $place['compare_featured_image'] =
        $featuredImage;

    return $place;
}


/* =========================================================
   DISPLAY HELPERS
   ========================================================= */

function llama_compare_h(
    mixed $value
): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function llama_compare_label(
    mixed $value
): string {
    $value =
        trim(
            (string) $value
        );

    if ($value === '') {
        return 'Not reported';
    }

    return ucwords(
        str_replace(
            [
                '_',
                '-',
            ],
            ' ',
            $value
        )
    );
}


function llama_compare_yes_no(
    mixed $value
): string {
    if (
        $value === null
        || $value === ''
    ) {
        return 'Not reported';
    }

    return
        (int) $value === 1
            ? 'Yes'
            : 'No';
}


function llama_compare_number(
    mixed $value,
    string $suffix = ''
): string {
    if (
        $value === null
        || $value === ''
        || !is_numeric($value)
    ) {
        return 'Not reported';
    }

    return
        number_format(
            (float) $value,
            floor((float) $value)
                === (float) $value
                ? 0
                : 1
        )
        . $suffix;
}


function llama_compare_rating(
    mixed $value
): string {
    if (
        $value === null
        || $value === ''
        || !is_numeric($value)
    ) {
        return 'Not reported';
    }

    $rating =
        max(
            1,
            min(
                5,
                (int) round(
                    (float) $value
                )
            )
        );

    return
        $rating
        . ' / 5';
}


function llama_compare_coordinates(
    array $place
): string {
    $latitude =
        $place['latitude']
        ?? null;

    $longitude =
        $place['longitude']
        ?? null;

    if (
        !is_numeric($latitude)
        || !is_numeric($longitude)
    ) {
        return 'Not reported';
    }

    return
        number_format(
            (float) $latitude,
            5,
            '.',
            ''
        )
        . ', '
        . number_format(
            (float) $longitude,
            5,
            '.',
            ''
        );
}


function llama_compare_location(
    array $place
): string {
    $parts = [];

    foreach (
        [
            'city',
            'county',
            'state',
        ]
        as $field
    ) {
        $value =
            trim(
                (string) (
                    $place[$field]
                    ?? ''
                )
            );

        if (
            $value !== ''
            && !in_array(
                $value,
                $parts,
                true
            )
        ) {
            $parts[] = $value;
        }
    }

    return
        $parts
            ? implode(
                ', ',
                $parts
            )
            : 'Not reported';
}


function llama_compare_image_url(
    array $place
): string {
    $image =
        $place[
            'compare_featured_image'
        ]
        ?? null;

    if (!is_array($image)) {
        return '';
    }

    $src =
        trim(
            (string) (
                $image['src']
                ?? ''
            )
        );

    if ($src === '') {
        return '';
    }

    if (
        preg_match(
            '#^https?://#i',
            $src
        )
    ) {
        return $src;
    }

    return
        'https://llamascout.com/'
        . ltrim(
            $src,
            '/'
        );
}


/* =========================================================
   COMPARISON SCHEMA
   ========================================================= */

function llama_compare_value(
    array $place,
    array $path
): mixed {
    $value = $place;

    foreach ($path as $key) {
        if (
            !is_array($value)
            || !array_key_exists(
                $key,
                $value
            )
        ) {
            return null;
        }

        $value =
            $value[$key];
    }

    return $value;
}


function llama_compare_sections(): array
{
    return [
        [
            'key' =>
                'location',

            'title' =>
                'Location',

            'icon' =>
                'fa-location-dot',

            'rows' => [
                [
                    'label' =>
                        'Area',

                    'callback' =>
                        static fn (
                            array $place
                        ): string =>
                            llama_compare_location(
                                $place
                            ),
                ],
                [
                    'label' =>
                        'Exact coordinates',

                    'callback' =>
                        static fn (
                            array $place
                        ): string =>
                            llama_compare_coordinates(
                                $place
                            ),
                ],
                [
                    'label' =>
                        'Elevation',

                    'path' =>
                        ['elevation_feet'],

                    'format' =>
                        'feet',
                ],
                [
                    'label' =>
                        'Land manager',

                    'path' =>
                        ['land_manager'],

                    'format' =>
                        'label',
                ],
                [
                    'label' =>
                        'Land type',

                    'path' =>
                        ['land_type'],

                    'format' =>
                        'label',
                ],
                [
                    'label' =>
                        'Road / approach',

                    'path' =>
                        ['road'],

                    'format' =>
                        'text',
                ],
            ],
        ],

        [
            'key' =>
                'site',

            'title' =>
                'Site & Vehicle',

            'icon' =>
                'fa-campground',

            'rows' => [
                [
                    'label' =>
                        'Vehicle capacity',

                    'path' =>
                        [
                            'details',
                            'vehicle_capacity',
                        ],

                    'format' =>
                        'number',
                ],
                [
                    'label' =>
                        'Maximum vehicle length',

                    'path' =>
                        [
                            'details',
                            'max_vehicle_length_feet',
                        ],

                    'format' =>
                        'feet',
                ],
                [
                    'label' =>
                        'Tent camping',

                    'path' =>
                        [
                            'details',
                            'tent_camping_suitable',
                        ],

                    'format' =>
                        'yes_no',
                ],
                [
                    'label' =>
                        'RV suitable',

                    'path' =>
                        [
                            'details',
                            'rv_suitable',
                        ],

                    'format' =>
                        'yes_no',
                ],
                [
                    'label' =>
                        'Trailer suitable',

                    'path' =>
                        [
                            'details',
                            'trailer_suitable',
                        ],

                    'format' =>
                        'yes_no',
                ],
                [
                    'label' =>
                        'Parking surface',

                    'path' =>
                        [
                            'details',
                            'parking_surface',
                        ],

                    'format' =>
                        'label',
                ],
                [
                    'label' =>
                        'Ground condition',

                    'path' =>
                        [
                            'details',
                            'ground_condition',
                        ],

                    'format' =>
                        'label',
                ],
                [
                    'label' =>
                        'Levelness',

                    'path' =>
                        [
                            'details',
                            'levelness',
                        ],

                    'format' =>
                        'rating',
                ],
                [
                    'label' =>
                        'Open sky',

                    'path' =>
                        [
                            'details',
                            'site_open_sky',
                        ],

                    'format' =>
                        'rating',
                ],
                [
                    'label' =>
                        'Shade',

                    'path' =>
                        [
                            'details',
                            'site_shade',
                        ],

                    'format' =>
                        'rating',
                ],
                [
                    'label' =>
                        'Turnaround space',

                    'path' =>
                        [
                            'details',
                            'turnaround_space',
                        ],

                    'format' =>
                        'yes_no',
                ],
                [
                    'label' =>
                        'Pull-through',

                    'path' =>
                        [
                            'details',
                            'pull_through',
                        ],

                    'format' =>
                        'yes_no',
                ],
            ],
        ],

        [
            'key' =>
                'access',

            'title' =>
                'Road & Access',

            'icon' =>
                'fa-road',

            'rows' => [
                [
                    'label' =>
                        'Site access difficulty',

                    'path' =>
                        [
                            'details',
                            'site_access_difficulty',
                        ],

                    'format' =>
                        'rating',
                ],
                [
                    'label' =>
                        'Road difficulty',

                    'path' =>
                        [
                            'details',
                            'road_overall_difficulty',
                        ],

                    'format' =>
                        'rating',
                ],
                [
                    'label' =>
                        'Road stress',

                    'path' =>
                        [
                            'details',
                            'road_stress',
                        ],

                    'format' =>
                        'rating',
                ],
                [
                    'label' =>
                        'Road surface',

                    'path' =>
                        [
                            'details',
                            'road_surface',
                        ],

                    'format' =>
                        'label',
                ],
                [
                    'label' =>
                        'Road width',

                    'path' =>
                        [
                            'details',
                            'road_width',
                        ],

                    'format' =>
                        'label',
                ],
                [
                    'label' =>
                        'Sedan accessible',

                    'path' =>
                        [
                            'details',
                            'sedan_accessible',
                        ],

                    'format' =>
                        'yes_no',
                ],
                [
                    'label' =>
                        'High clearance recommended',

                    'path' =>
                        [
                            'details',
                            'high_clearance_recommended',
                        ],

                    'format' =>
                        'yes_no',
                ],
                [
                    'label' =>
                        '4WD recommended',

                    'path' =>
                        [
                            'details',
                            'four_wheel_drive_recommended',
                        ],

                    'format' =>
                        'yes_no',
                ],
                [
                    'label' =>
                        'Water crossings',

                    'path' =>
                        [
                            'details',
                            'water_crossings',
                        ],

                    'format' =>
                        'yes_no',
                ],
                [
                    'label' =>
                        'Seasonal closure',

                    'path' =>
                        [
                            'details',
                            'seasonal_closure',
                        ],

                    'format' =>
                        'yes_no',
                ],
                [
                    'label' =>
                        'Mud risk',

                    'path' =>
                        [
                            'details',
                            'mud_risk',
                        ],

                    'format' =>
                        'rating',
                ],
                [
                    'label' =>
                        'Drop-off exposure',

                    'path' =>
                        [
                            'details',
                            'drop_off_exposure',
                        ],

                    'format' =>
                        'rating',
                ],
            ],
        ],

        [
            'key' =>
                'sensory-day',

            'title' =>
                'Sensory, Daytime',

            'icon' =>
                'fa-sun',

            'rows' =>
                llama_compare_sensory_rows(
                    'daytime'
                ),
        ],

        [
            'key' =>
                'sensory-night',

            'title' =>
                'Sensory, Nighttime',

            'icon' =>
                'fa-moon',

            'rows' =>
                llama_compare_sensory_rows(
                    'nighttime'
                ),
        ],

        [
            'key' =>
                'sensory-other',

            'title' =>
                'Other Sensory Conditions',

            'icon' =>
                'fa-ear-listen',

            'rows' => [
                llama_compare_rating_row(
                    'Traffic dust',
                    [
                        'sensory_details',
                        'dust_from_traffic',
                    ]
                ),

                llama_compare_rating_row(
                    'Generator noise',
                    [
                        'sensory_details',
                        'generator_noise',
                    ]
                ),

                llama_compare_rating_row(
                    'Road noise',
                    [
                        'sensory_details',
                        'road_noise',
                    ]
                ),

                llama_compare_rating_row(
                    'Human activity',
                    [
                        'sensory_details',
                        'human_activity',
                    ]
                ),

                llama_compare_rating_row(
                    'Wildlife noise',
                    [
                        'sensory_details',
                        'wildlife_noise',
                    ]
                ),

                llama_compare_rating_row(
                    'Wind noise',
                    [
                        'sensory_details',
                        'wind_noise',
                    ]
                ),

                llama_compare_rating_row(
                    'Smoke risk',
                    [
                        'sensory_details',
                        'smoke_risk',
                    ]
                ),

                llama_compare_rating_row(
                    'Strong odors',
                    [
                        'sensory_details',
                        'strong_odors',
                    ]
                ),

                llama_compare_rating_row(
                    'Visual exposure',
                    [
                        'sensory_details',
                        'visual_exposure',
                    ]
                ),

                llama_compare_rating_row(
                    'Predictability',
                    [
                        'sensory_details',
                        'predictability',
                    ]
                ),
            ],
        ],

        [
            'key' =>
                'connectivity',

            'title' =>
                'Connectivity',

            'icon' =>
                'fa-signal',

            'rows' => [
                llama_compare_rating_row(
                    'Overall',
                    [
                        'connectivity',
                        'overall',
                    ]
                ),

                llama_compare_rating_row(
                    'T-Mobile',
                    [
                        'connectivity',
                        't_mobile',
                    ]
                ),

                llama_compare_rating_row(
                    'Verizon',
                    [
                        'connectivity',
                        'verizon',
                    ]
                ),

                llama_compare_rating_row(
                    'AT&T',
                    [
                        'connectivity',
                        'att',
                    ]
                ),

                llama_compare_rating_row(
                    'Other cell',
                    [
                        'connectivity',
                        'other_cell',
                    ]
                ),

                llama_compare_rating_row(
                    'Starlink',
                    [
                        'connectivity',
                        'starlink',
                    ]
                ),

                [
                    'label' =>
                        'Starlink tested',

                    'path' =>
                        [
                            'connectivity',
                            'starlink_tested',
                        ],

                    'format' =>
                        'yes_no',
                ],
            ],
        ],

        [
            'key' =>
                'amenities',

            'title' =>
                'Amenities',

            'icon' =>
                'fa-list-check',

            'rows' => [
                llama_compare_yes_no_row(
                    'Toilets',
                    'toilets'
                ),

                llama_compare_yes_no_row(
                    'Potable water',
                    'potable_water'
                ),

                llama_compare_yes_no_row(
                    'Trash',
                    'trash'
                ),

                llama_compare_yes_no_row(
                    'Fire ring',
                    'fire_ring'
                ),

                llama_compare_yes_no_row(
                    'Picnic table',
                    'picnic_table'
                ),

                llama_compare_yes_no_row(
                    'Bear box',
                    'bear_box'
                ),

                llama_compare_yes_no_row(
                    'Showers',
                    'showers'
                ),

                llama_compare_yes_no_row(
                    'Electricity',
                    'electricity'
                ),

                llama_compare_yes_no_row(
                    'Dump station',
                    'dump_station'
                ),

                llama_compare_yes_no_row(
                    'Food storage required',
                    'food_storage_required'
                ),
            ],
        ],
    ];
}


function llama_compare_sensory_rows(
    string $period
): array {
    return [
        llama_compare_rating_row(
            'Noise',
            [
                'sensory',
                $period,
                'noise',
            ]
        ),

        llama_compare_rating_row(
            'Traffic',
            [
                'sensory',
                $period,
                'traffic',
            ]
        ),

        llama_compare_rating_row(
            'Crowds',
            [
                'sensory',
                $period,
                'crowds',
            ]
        ),

        llama_compare_rating_row(
            'Privacy',
            [
                'sensory',
                $period,
                'privacy',
            ]
        ),

        llama_compare_rating_row(
            'Light pollution',
            [
                'sensory',
                $period,
                'light_pollution',
            ]
        ),

        llama_compare_rating_row(
            'Sensory comfort',
            [
                'sensory',
                $period,
                'sensory_comfort',
            ]
        ),

        llama_compare_rating_row(
            'Social interaction',
            [
                'sensory',
                $period,
                'social_interaction_likelihood',
            ]
        ),
    ];
}


function llama_compare_rating_row(
    string $label,
    array $path
): array {
    return [
        'label' =>
            $label,

        'path' =>
            $path,

        'format' =>
            'rating',
    ];
}


function llama_compare_yes_no_row(
    string $label,
    string $field
): array {
    return [
        'label' =>
            $label,

        'path' => [
            'amenities',
            $field,
        ],

        'format' =>
            'yes_no',
    ];
}


function llama_compare_format_row_value(
    array $place,
    array $row
): string {
    if (
        isset(
            $row['callback']
        )
        && is_callable(
            $row['callback']
        )
    ) {
        return
            (string) $row['callback'](
                $place
            );
    }

    $value =
        llama_compare_value(
            $place,
            (array) (
                $row['path']
                ?? []
            )
        );

    $format =
        (string) (
            $row['format']
            ?? 'text'
        );

    return match ($format) {
        'yes_no' =>
            llama_compare_yes_no(
                $value
            ),

        'rating' =>
            llama_compare_rating(
                $value
            ),

        'feet' =>
            llama_compare_number(
                $value,
                ' ft'
            ),

        'number' =>
            llama_compare_number(
                $value
            ),

        'label' =>
            llama_compare_label(
                $value
            ),

        default =>
            trim(
                (string) $value
            ) !== ''
                ? trim(
                    (string) $value
                )
                : 'Not reported',
    };
}


function llama_compare_row_has_data(
    array $places,
    array $row
): bool {
    foreach ($places as $place) {
        if (
            llama_compare_format_row_value(
                $place,
                $row
            )
            !== 'Not reported'
        ) {
            return true;
        }
    }

    return false;
}
