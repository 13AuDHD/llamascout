<?php

declare(strict_types=1);

require_once __DIR__ . '/pad-us-sync.php';


/*
 * =========================================================
 * TEXT NORMALIZATION
 * =========================================================
 */

function llama_pad_us_canonical_text(
    mixed $value
): string {
    $value =
        strtolower(
            trim(
                (string) $value
            )
        );

    $value =
        preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            $value
        );

    return trim(
        (string) $value
    );
}


/*
 * =========================================================
 * PROPERTY TYPE NAME
 * =========================================================
 */

function llama_pad_us_property_type_name(
    PDOStatement $statement,
    string $slug
): string {
    $slug =
        trim($slug);

    if ($slug === '') {
        return '';
    }

    $statement->execute([
        $slug,
    ]);

    return
        trim(
            (string) (
                $statement->fetchColumn()
                ?: ''
            )
        );
}


/*
 * =========================================================
 * GENERIC -> SPECIFIC NORMALIZATION
 *
 * Generic ownership classes are fallbacks.
 *
 * Example:
 *
 * PAD-US:
 *   designation = National Public Lands
 *   manager     = Bureau of Land Management
 *   mapped type = Federal Public Land
 *
 * Llama Scout:
 *   BLM Land
 *
 * We only promote when the manager clearly identifies the
 * agency. We do not guess a more specific designation merely
 * because an agency manages the land.
 * =========================================================
 */

function llama_pad_us_normalize_property_type(
    string $propertyTypeSlug,
    string $manager,
    string $designation,
    string $localDesignation
): string {
    $slug =
        trim(
            $propertyTypeSlug
        );

    if ($slug === '') {
        return '';
    }

    $managerCanonical =
        llama_pad_us_canonical_text(
            $manager
        );

    $designationCanonical =
        llama_pad_us_canonical_text(
            $designation
        );

    $localDesignationCanonical =
        llama_pad_us_canonical_text(
            $localDesignation
        );

    /*
     * BLM is a distinct usable Land Type in Llama Scout.
     *
     * A generic federal polygon managed by BLM should never
     * surface to the form simply as Federal Public Land.
     */
    if (
        $slug === 'federal-public-land'
        && (
            str_contains(
                $managerCanonical,
                'bureau of land management'
            )
            ||
            preg_match(
                '/(^| )blm( |$)/',
                $managerCanonical
            ) === 1
        )
    ) {
        return 'blm-land';
    }

    /*
     * National Forest and National Grassland should normally
     * already arrive through their own PAD-US designation
     * mappings.
     *
     * This fallback only applies when the PAD-US designation
     * itself explicitly says what the land system is.
     *
     * We do NOT convert every generic USFS-managed parcel into
     * National Forest because Forest Service can manage other
     * federal land designations too.
     */
    if (
        $slug === 'federal-public-land'
        && (
            $designationCanonical
                === 'national forest'
            ||
            $localDesignationCanonical
                === 'national forest'
        )
    ) {
        return 'national-forest';
    }

    if (
        $slug === 'federal-public-land'
        && (
            $designationCanonical
                === 'national grassland'
            ||
            $localDesignationCanonical
                === 'national grassland'
        )
    ) {
        return 'national-grassland';
    }

    return $slug;
}


/*
 * =========================================================
 * LAND TYPE SPECIFICITY
 *
 * Lower number = more specific / preferred.
 *
 * Acreage is only used after specificity. This prevents a
 * small generic polygon from beating a meaningful specific
 * designation.
 * =========================================================
 */

function llama_pad_us_property_type_priority(
    string $slug
): int {
    $slug =
        trim($slug);

    /*
     * Very specific federal / protected land designations.
     */
    $highSpecificity = [
        'national-conservation-area',
        'national-monument',
        'national-park',
        'national-preserve-reserve',
        'national-recreation-area',
        'national-seashore-lakeshore',
        'national-river-scenic-riverway',
        'national-wildlife-refuge',
        'federal-water-project-recreation-land',

        'state-park',
        'state-recreation-area',
        'state-natural-area-preserve',
        'state-forest',
        'state-trust-land',
        'wildlife-management-game-lands',
        'water-management-district',

        'county-regional-park',
        'city-municipal-land',
        'public-utility-reservoir-land',
        'rest-area-transportation-facility',
        'fairgrounds-event-property',

        'land-trust-conservation-preserve',
        'tribal-land',

        'military-land',
    ];

    if (
        in_array(
            $slug,
            $highSpecificity,
            true
        )
    ) {
        return 10;
    }

    /*
     * Agency land systems.
     *
     * These are still meaningful Land Types, but a named
     * designation such as National Conservation Area should
     * beat generic BLM Land if both overlap.
     */
    $agencySpecific = [
        'national-forest',
        'national-grassland',
        'blm-land',
    ];

    if (
        in_array(
            $slug,
            $agencySpecific,
            true
        )
    ) {
        return 20;
    }

    /*
     * Other meaningful property categories.
     */
    $normalSpecificity = [
        'public-parking-civic-property',
        'roadside-highway-right-of-way',

        'travel-center-truck-stop-property',
        'retail-commercial-property',
        'restaurant-property',
        'medical-healthcare-property',
        'religious-community-property',
        'casino-gaming-property',
        'membership-hosted-property',
    ];

    if (
        in_array(
            $slug,
            $normalSpecificity,
            true
        )
    ) {
        return 30;
    }

    /*
     * Generic ownership buckets are deliberately last.
     */
    $generic = [
        'federal-public-land',
        'state-public-land',
        'local-public-land',
        'private-land',
        'other',
    ];

    if (
        in_array(
            $slug,
            $generic,
            true
        )
    ) {
        return 1000;
    }

    /*
     * Unknown/new mapped types should still beat a generic
     * fallback unless specifically classified otherwise.
     */
    return 100;
}


/*
 * =========================================================
 * POINT LOOKUP
 * =========================================================
 */

function llama_pad_us_point_lookup(
    PDO $referenceDb,
    PDO $mainDb,
    float $latitude,
    float $longitude
): array {
    $source =
        llama_pad_us_source(
            $referenceDb
        );

    $domains =
        llama_pad_us_domain_maps(
            $referenceDb
        );

    $response =
        llama_pad_us_http_json(
            rtrim(
                (string) $source['base_url'],
                '/'
            )
            . '/query',
            [
                'f' =>
                    'json',

                'where' =>
                    '1=1',

                'geometry' =>
                    number_format(
                        $longitude,
                        7,
                        '.',
                        ''
                    )
                    . ','
                    . number_format(
                        $latitude,
                        7,
                        '.',
                        ''
                    ),

                'geometryType' =>
                    'esriGeometryPoint',

                'inSR' =>
                    '4326',

                'spatialRel' =>
                    'esriSpatialRelIntersects',

                'outFields' =>
                    implode(
                        ',',
                        [
                            'OBJECTID',
                            'Unit_Nm',
                            'Loc_Nm',
                            'Loc_Ds',
                            'Loc_Mang',
                            'Mang_Name',
                            'Mang_Type',
                            'Des_Tp',
                            'Category',
                            'State_Nm',
                            'Source_PAID',
                            'Pub_Access',
                            'GIS_Src',
                            'Src_Date',
                            'GIS_Acres',
                        ]
                    ),

                'returnGeometry' =>
                    'false',
            ]
        );

    $features =
        is_array(
            $response['features']
            ?? null
        )
            ? $response['features']
            : [];

    $sourceId =
        (int) $source['id'];

    $matches = [];

    /*
     * PAD-US designation -> Llama Scout property type mapping.
     */
    $mappingStatement =
        $referenceDb->prepare(
            'SELECT
                property_type_slug,
                surface_in_place_form,
                reviewed
             FROM reference_property_type_mappings
             WHERE source_id = ?
               AND source_designation = ?
               AND active = 1
             LIMIT 1'
        );

    /*
     * Resolve the current display name from the main database.
     */
    $propertyTypeStatement =
        $mainDb->prepare(
            'SELECT name
             FROM place_property_types
             WHERE slug = ?
               AND active = 1
             LIMIT 1'
        );


    foreach ($features as $feature) {
        if (!is_array($feature)) {
            continue;
        }

        $attributes =
            is_array(
                $feature['attributes']
                ?? null
            )
                ? $feature['attributes']
                : [];


        /*
         * -----------------------------------------------------
         * DESIGNATION
         * -----------------------------------------------------
         */

        $designation =
            llama_pad_us_decode(
                $domains,
                'Des_Tp',
                $attributes['Des_Tp']
                    ?? ''
            );

        if ($designation === '') {
            continue;
        }


        /*
         * -----------------------------------------------------
         * REFERENCE MAPPING
         * -----------------------------------------------------
         */

        $mappingStatement->execute([
            $sourceId,
            $designation,
        ]);

        $mapping =
            $mappingStatement->fetch(
                PDO::FETCH_ASSOC
            );


        $propertyTypeSlug =
            trim(
                (string) (
                    $mapping[
                        'property_type_slug'
                    ]
                    ?? ''
                )
            );


        /*
         * -----------------------------------------------------
         * MANAGER
         * -----------------------------------------------------
         */

        $localManager =
            trim(
                (string) (
                    $attributes[
                        'Loc_Mang'
                    ]
                    ?? ''
                )
            );

        $sourceManager =
            llama_pad_us_decode(
                $domains,
                'Mang_Name',
                $attributes[
                    'Mang_Name'
                ]
                ?? ''
            );

        $manager =
            $localManager !== ''
                ? $localManager
                : $sourceManager;


        /*
         * -----------------------------------------------------
         * LOCAL DESIGNATION
         * -----------------------------------------------------
         */

        $localDesignation =
            trim(
                (string) (
                    $attributes[
                        'Loc_Ds'
                    ]
                    ?? ''
                )
            );


        /*
         * -----------------------------------------------------
         * NORMALIZE LAND TYPE
         *
         * This is where Federal Public Land + BLM becomes
         * BLM Land before anything reaches best_match.
         * -----------------------------------------------------
         */

        $originalPropertyTypeSlug =
            $propertyTypeSlug;

        $propertyTypeSlug =
            llama_pad_us_normalize_property_type(
                $propertyTypeSlug,
                $manager,
                $designation,
                $localDesignation
            );


        $propertyTypeName =
            llama_pad_us_property_type_name(
                $propertyTypeStatement,
                $propertyTypeSlug
            );


        /*
         * If normalization pointed to a type that somehow does
         * not exist in the active taxonomy, fall back safely to
         * the original mapped type rather than producing a blank.
         */
        if (
            $propertyTypeSlug !== ''
            && $propertyTypeName === ''
            && $originalPropertyTypeSlug !== ''
        ) {
            $propertyTypeSlug =
                $originalPropertyTypeSlug;

            $propertyTypeName =
                llama_pad_us_property_type_name(
                    $propertyTypeStatement,
                    $propertyTypeSlug
                );
        }


        /*
         * -----------------------------------------------------
         * NAME / ACREAGE
         * -----------------------------------------------------
         */

        $name =
            llama_pad_us_location_name(
                $attributes
            );

        $acreage =
            is_numeric(
                $attributes[
                    'GIS_Acres'
                ]
                ?? null
            )
                ? (float) $attributes[
                    'GIS_Acres'
                ]
                : null;


        /*
         * -----------------------------------------------------
         * MATCH RECORD
         * -----------------------------------------------------
         */

        $matches[] = [
            'object_id' =>
                (int) (
                    $attributes[
                        'OBJECTID'
                    ]
                    ?? 0
                ),

            'name' =>
                $name,

            'designation' =>
                $designation,

            'local_designation' =>
                $localDesignation,

            'category' =>
                llama_pad_us_decode(
                    $domains,
                    'Category',
                    $attributes[
                        'Category'
                    ]
                        ?? ''
                ),

            'manager' =>
                $manager,

            'manager_type' =>
                llama_pad_us_decode(
                    $domains,
                    'Mang_Type',
                    $attributes[
                        'Mang_Type'
                    ]
                        ?? ''
                ),

            'public_access' =>
                llama_pad_us_decode(
                    $domains,
                    'Pub_Access',
                    $attributes[
                        'Pub_Access'
                    ]
                        ?? ''
                ),

            'acreage' =>
                $acreage,

            'property_type_slug' =>
                $propertyTypeSlug,

            'property_type' =>
                $propertyTypeName,

            /*
             * Preserve original mapping for debugging/admin
             * inspection. The form uses the normalized fields
             * above.
             */
            'original_property_type_slug' =>
                $originalPropertyTypeSlug,

            'property_type_normalized' =>
                $propertyTypeSlug !==
                $originalPropertyTypeSlug,

            'mapped' =>
                $propertyTypeSlug !== ''
                && $propertyTypeName !== '',

            'reference_only' =>
                $mapping
                && $originalPropertyTypeSlug === ''
                && (int) (
                    $mapping[
                        'reviewed'
                    ]
                    ?? 0
                ) === 1,

            'surface_in_place_form' =>
                (int) (
                    $mapping[
                        'surface_in_place_form'
                    ]
                    ?? 0
                ) === 1,

            'specificity_priority' =>
                $propertyTypeSlug !== ''
                    ? llama_pad_us_property_type_priority(
                        $propertyTypeSlug
                    )
                    : PHP_INT_MAX,
        ];
    }


    /*
     * =========================================================
     * CHOOSE ONE CANONICAL LAND TYPE
     *
     * Only mapped records compete.
     *
     * Sorting order:
     *
     * 1. Specificity
     * 2. Smaller polygon
     *
     * Therefore:
     *
     * National Conservation Area beats BLM Land
     * BLM Land beats Federal Public Land
     * National Forest beats Federal Public Land
     *
     * Acreage only breaks ties among similarly specific types.
     * =========================================================
     */

    $mapped =
        array_values(
            array_filter(
                $matches,
                static fn (
                    array $match
                ): bool =>
                    !empty(
                        $match['mapped']
                    )
            )
        );


    usort(
        $mapped,
        static function (
            array $a,
            array $b
        ): int {
            $aPriority =
                (int) (
                    $a[
                        'specificity_priority'
                    ]
                    ?? PHP_INT_MAX
                );

            $bPriority =
                (int) (
                    $b[
                        'specificity_priority'
                    ]
                    ?? PHP_INT_MAX
                );

            if (
                $aPriority !==
                $bPriority
            ) {
                return
                    $aPriority
                    <=>
                    $bPriority;
            }


            $aAcres =
                is_numeric(
                    $a['acreage']
                    ?? null
                )
                    ? (float) $a[
                        'acreage'
                    ]
                    : PHP_FLOAT_MAX;

            $bAcres =
                is_numeric(
                    $b['acreage']
                    ?? null
                )
                    ? (float) $b[
                        'acreage'
                    ]
                    : PHP_FLOAT_MAX;


            return
                $aAcres
                <=>
                $bAcres;
        }
    );


    $bestMatch =
        $mapped[0]
        ?? null;


    /*
     * Do not expose the internal ranking helper as part of
     * best_match. Keep it in matches for troubleshooting.
     */
    if (is_array($bestMatch)) {
        unset(
            $bestMatch[
                'specificity_priority'
            ]
        );
    }


    return [
        'best_match' =>
            $bestMatch,

        'matches' =>
            $matches,
    ];
}
