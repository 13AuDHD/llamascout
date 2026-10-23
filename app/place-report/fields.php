<?php

declare(strict_types=1);

/* Canonical field registry assembled from smaller section files. */

function llama_place_report_fields(): array
{
    $distance = llama_place_report_distance_options();
    $f = [];

    $add = static function (
        string $key,
        string $label,
        string $section,
        string $type,
        string $storage,
        array $extra = []
    ) use (&$f): void {
        $f[$key] = array_merge(
            [
                'key' => $key,
                'label' => $label,
                'section' => $section,
                'type' => $type,
                'storage' => $storage,
                'points_categories' => [],
                'allow_unknown' => false,
                'counts_toward_completion' => true,
                'completion_group' => null,
                'min_characters' => 0,
                'applicable_if' => [],
                'derived' => false,
            ],
            $extra
        );
    };

    /*
     * ---------------------------------------------------------
     * 1. Register every canonical field first.
     * ---------------------------------------------------------
     *
     * Applicability must never depend on whether a field happened to be
     * registered before or after the applicability pass.
     */
    llama_place_report_add_basic_location_fields($add);
    llama_place_report_add_site_fields($add);
    llama_place_report_add_experience_fields($f, $add, $distance);

    require_once __DIR__ . '/fields-source-context.php';
    llama_place_report_add_source_context_fields($add);

    require_once __DIR__ . '/fields-road-conditions.php';
    llama_place_report_add_road_condition_questions($add);

    require_once __DIR__ . '/fields-road-access-additions.php';
    llama_place_report_add_road_access_questions($add);

    require_once __DIR__ . '/fields-road-seasonal.php';
    llama_place_report_add_road_seasonal_questions($add);

    require_once __DIR__ . '/fields-road-restrictions.php';
    llama_place_report_add_road_restriction_questions($add);

    require_once __DIR__ . '/fields-seasons.php';
    llama_place_report_add_season_fields($f, $add);

    llama_place_report_add_summary_fields($f, $add);

    require_once __DIR__ . '/flow-conditions.php';
    llama_place_report_add_flow_condition_fields($f, $add);

    require_once __DIR__ . '/flow-permissions.php';
    llama_place_report_add_permission_fields($f, $add);

    require_once __DIR__ . '/fields-place-overnight.php';
    llama_place_report_add_overnight_permission_fields($add);

    require_once __DIR__ . '/fields-place-shared-amenities.php';
    llama_place_report_add_shared_amenity_fields($f, $add);

    require_once __DIR__ . '/fields-place-rules.php';
    llama_place_report_add_place_rules_fields($add);

    require_once __DIR__ . '/fields-place-operating.php';
    llama_place_report_add_operating_fields($add);

    /*
     * ---------------------------------------------------------
     * 2. Apply behavior only after the registry is complete.
     * ---------------------------------------------------------
     */
    llama_place_report_apply_applicability($f);

    llama_place_report_apply_flow_conditions($f);

    llama_place_report_apply_permission_applicability(
        $f
    );

    llama_place_report_apply_road_hazard_dependencies($f);

    require_once __DIR__ . '/flow-metadata.php';
    llama_place_report_apply_flow_metadata($f);

    require_once __DIR__ . '/flow-pricing.php';

    foreach (llama_place_report_pricing_semantics() as $key => $pricingMeta) {
        if (!isset($f[$key])) {
            continue;
        }

        $f[$key]['pricing'] = $pricingMeta;
    }

    require_once __DIR__ . '/flow-access-options.php';
    llama_place_report_apply_access_options($f);

    foreach (
        [
            'landscape_primary',
            'landscape_details',
            'landscape_views',
        ]
        as $key
    ) {
        if (isset($f[$key])) {
            $f[$key]['wide'] = false;
        }
    }

    foreach (
        [
            'entrance_facility_fee',
            'parking_fee',
        ]
        as $key
    ) {
        if (isset($f[$key])) {
            $f[$key]['display_section'] =
                'location';
        }
    }

    /*
     * ---------------------------------------------------------
     * 3. Apply presentation metadata.
     * -----------------------------------------------------------
     */
    require_once __DIR__ . '/flow-layout.php';
    llama_place_report_apply_layout_metadata($f);
    llama_place_report_apply_place_rules_metadata($f);
    llama_place_report_apply_operating_metadata($f);

    require_once __DIR__ . '/flow-site-selection.php';
    llama_place_report_apply_site_selection_metadata($f);

    require_once __DIR__ . '/flow-site-parking.php';
    llama_place_report_apply_site_parking_metadata($f);

    require_once __DIR__ . '/flow-site-equipment.php';
    llama_place_report_apply_site_equipment_metadata($f);

    require_once __DIR__ . '/flow-site-experience.php';
    llama_place_report_apply_site_experience_metadata($f);

    require_once __DIR__ . '/flow-site-accessibility.php';
    llama_place_report_apply_site_accessibility_metadata($f);

    require_once __DIR__ . '/flow-site-notes.php';
    llama_place_report_apply_site_notes_metadata($f);

    require_once __DIR__ . '/flow-form-cleanup.php';
    llama_place_report_apply_form_cleanup_metadata($f);

    /*
     * The shared form consumes registry order directly. Reorder only after
     * every presentation-metadata pass has finished so every field-driven
     * consumer sees the same final sequence.
     */
    llama_place_report_reorder_layout_fields(
        $f
    );

    /*
     * ----------------------------------------------------------
     * 4. Apply ownership last.
     * ---------------------------------------------------------
     */
    require_once __DIR__ . '/flow-scopes.php';
    llama_place_report_apply_flow_scopes($f);

    /*
     * Publish the approved question identity and ordering only after all
     * registration, presentation, and ownership passes have completed.
     * Consumers must use these final values rather than recomputing them.
     * Legacy fields remain registered without an approved question slot.
     */
    $approvedQuestions = llama_place_report_approved_question_lookup();
    foreach ($f as $key => &$field) {
        $approved = $approvedQuestions[$key] ?? null;
        $field['approved_question_group'] = $approved['group'] ?? null;
        $field['approved_question_group_label'] = $approved['group_label'] ?? null;
        $field['approved_question_position'] = $approved['position'] ?? null;
        $field['approved_question'] = $approved !== null;
    }
    unset($field);

    return $f;
}

/**
 * Resolve a canonical question from its published storage path.
 *
 * Preserve the first registered definition when legacy keys share a path,
 * matching the existing Suggest an Update lookup behavior.
 */
function llama_place_report_field_by_storage(string $storage): ?array
{
    static $index = null;
    if ($index === null) {
        $index = [];
        foreach (llama_place_report_fields() as $field) {
            $path = (string) ($field['storage'] ?? '');
            if ($path !== '' && !array_key_exists($path, $index)) {
                $index[$path] = $field;
            }
        }
    }
    return $index[$storage] ?? null;
}


/**
 * Resolve canonical report storage to its published Place table/column.
 * Submission-only metadata intentionally has no published Place target.
 * This is shared mapping metadata, not a database write.
 */
function llama_place_report_storage_definition(
    array $field
): ?array {
    $storage =
        trim(
            (string) (
                $field['storage']
                ?? ''
            )
        );

    if ($storage === '') {
        return null;
    }


    /*
     * Update submission metadata, not published Place data.
     */
    if (
        in_array(
            $storage,
            [
                'visited_at',
                'contributor_notes',
            ],
            true
        )
    ) {
        return null;
    }


    $placeColumns = [
        'name',
        'type',
        'description',
        'latitude',
        'longitude',
        'elevation_feet',
        'road',
        'city',
        'county',
        'state',
        'region',
        'land_manager',
        'land_type',
        'access_summary',
        'sensory_summary',
    ];


    if (
        in_array(
            $storage,
            $placeColumns,
            true
        )
    ) {
        return [
            'table' => 'places',
            'column' => $storage,
        ];
    }


    if (
        str_starts_with(
            $storage,
            'details.'
        )
    ) {
        return [
            'table' =>
                'place_details',

            'column' =>
                substr(
                    $storage,
                    strlen('details.')
                ),
        ];
    }


    if (
        str_starts_with(
            $storage,
            'amenities.'
        )
    ) {
        return [
            'table' =>
                'place_amenities',

            'column' =>
                substr(
                    $storage,
                    strlen('amenities.')
                ),
        ];
    }


    if (
        str_starts_with(
            $storage,
            'connectivity.'
        )
    ) {
        return [
            'table' =>
                'place_connectivity',

            'column' =>
                substr(
                    $storage,
                    strlen('connectivity.')
                ),
        ];
    }


    foreach (
        [
            'daytime',
            'nighttime',
        ]
        as $period
    ) {
        $prefix =
            'sensory.'
            . $period
            . '.';

        if (
            str_starts_with(
                $storage,
                $prefix
            )
        ) {
            return [
                'table' =>
                    'place_sensory',

                'column' =>
                    substr(
                        $storage,
                        strlen($prefix)
                    ),

                'period' =>
                    $period,
            ];
        }
    }


    if (
        str_starts_with(
            $storage,
            'sensory.details.'
        )
    ) {
        return [
            'table' =>
                'place_sensory_details',

            'column' =>
                substr(
                    $storage,
                    strlen(
                        'sensory.details.'
                    )
                ),
        ];
    }


    if (
        str_starts_with(
            $storage,
            'rules.'
        )
    ) {
        return [
            'table' =>
                'place_rules',

            'column' =>
                substr(
                    $storage,
                    strlen('rules.')
                ),
        ];
    }


    if (
        str_starts_with(
            $storage,
            'experience.'
        )
    ) {
        return [
            'table' =>
                'place_experience',

            'column' =>
                substr(
                    $storage,
                    strlen('experience.')
                ),
        ];
    }


    return null;
}


/** Canonical index of publishable Place answer definitions. */
function llama_place_report_published_definitions(): array
{
    $definitions = [];

    foreach (
        llama_place_report_fields()
        as $key => $field
    ) {
        $storageDefinition =
            llama_place_report_storage_definition(
                $field
            );

        if (!$storageDefinition) {
            continue;
        }


        $storage =
            (string) $field['storage'];


        // Preserve the first definition for shared legacy storage paths.
        if (array_key_exists($storage, $definitions)) {
            continue;
        }

        $definitions[$storage] =
            array_merge(
                $storageDefinition,
                [
                    'shared' =>
                        $field,

                    'key' =>
                        (string) $key,

                    'label' =>
                        (string) (
                            $field['label']
                            ?? $key
                        ),

                    'section' =>
                        (string) (
                            $field['section']
                            ?? 'other'
                        ),

                    'type' =>
                        (string) (
                            $field['type']
                            ?? 'text'
                        ),
                ]
            );
    }


    return $definitions;
}
