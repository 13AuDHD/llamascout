<?php

declare(strict_types=1);

require_once __DIR__ . '/pad-us-sync.php';


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
                    $mapping['property_type_slug']
                    ?? ''
                )
            );

        $propertyTypeName = '';

        if ($propertyTypeSlug !== '') {
            $propertyTypeStatement->execute([
                $propertyTypeSlug,
            ]);

            $propertyTypeName =
                trim(
                    (string) (
                        $propertyTypeStatement
                            ->fetchColumn()
                        ?: ''
                    )
                );
        }

        $localManager =
            trim(
                (string) (
                    $attributes['Loc_Mang']
                    ?? ''
                )
            );

        $sourceManager =
            llama_pad_us_decode(
                $domains,
                'Mang_Name',
                $attributes['Mang_Name']
                    ?? ''
            );

        $manager =
            $localManager !== ''
                ? $localManager
                : $sourceManager;

        $name =
            llama_pad_us_location_name(
                $attributes
            );

        $acreage =
            is_numeric(
                $attributes['GIS_Acres']
                ?? null
            )
                ? (float) $attributes['GIS_Acres']
                : null;

        $matches[] = [
            'object_id' =>
                (int) (
                    $attributes['OBJECTID']
                    ?? 0
                ),

            'name' =>
                $name,

            'designation' =>
                $designation,

            'local_designation' =>
                trim(
                    (string) (
                        $attributes['Loc_Ds']
                        ?? ''
                    )
                ),

            'category' =>
                llama_pad_us_decode(
                    $domains,
                    'Category',
                    $attributes['Category']
                        ?? ''
                ),

            'manager' =>
                $manager,

            'manager_type' =>
                llama_pad_us_decode(
                    $domains,
                    'Mang_Type',
                    $attributes['Mang_Type']
                        ?? ''
                ),

            'public_access' =>
                llama_pad_us_decode(
                    $domains,
                    'Pub_Access',
                    $attributes['Pub_Access']
                        ?? ''
                ),

            'acreage' =>
                $acreage,

            'property_type_slug' =>
                $propertyTypeSlug,

            'property_type' =>
                $propertyTypeName,

            'mapped' =>
                $propertyTypeSlug !== '',

            'reference_only' =>
                $mapping
                && $propertyTypeSlug === ''
                && (int) (
                    $mapping['reviewed']
                    ?? 0
                ) === 1,

            'surface_in_place_form' =>
                (int) (
                    $mapping[
                        'surface_in_place_form'
                    ]
                    ?? 0
                ) === 1,
        ];
    }

    /*
     * Prefer actual mapped property records.
     *
     * When multiple mapped polygons overlap the point,
     * the smaller polygon is generally the more specific
     * description of the exact location.
     */
    $mapped =
        array_values(
            array_filter(
                $matches,
                static fn (array $match): bool =>
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
            $aAcres =
                $a['acreage']
                ?? PHP_FLOAT_MAX;

            $bAcres =
                $b['acreage']
                ?? PHP_FLOAT_MAX;

            return
                $aAcres
                <=> $bAcres;
        }
    );

    return [
        'best_match' =>
            $mapped[0]
            ?? null,

        'matches' =>
            $matches,
    ];
}
