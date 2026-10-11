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

    llama_place_report_add_basic_location_fields($add);
    llama_place_report_add_site_fields($add);
    llama_place_report_add_experience_fields($f, $add, $distance);

    require_once __DIR__ . '/fields-road-conditions.php';
    llama_place_report_add_road_condition_questions($add);
    require_once __DIR__ . '/fields-road-access-additions.php';
    llama_place_report_add_road_access_questions($add);
    require_once __DIR__ . '/fields-road-seasonal.php';
    llama_place_report_add_road_seasonal_questions($add);
    require_once __DIR__ . '/fields-road-restrictions.php';
    llama_place_report_add_road_restriction_questions($add);

    // Short categorical cards match the width of nearby ratings.
    foreach (['landscape_primary', 'landscape_details', 'landscape_views'] as $key) {
        if (isset($f[$key])) {
            $f[$key]['wide'] = false;
        }
    }

    require_once __DIR__ . '/fields-seasons.php';
    llama_place_report_add_season_fields($f, $add);

    llama_place_report_add_summary_fields($f, $add);
    require_once __DIR__ . '/flow-conditions.php';
    llama_place_report_add_flow_condition_fields($f, $add);
    llama_place_report_apply_applicability($f);
    llama_place_report_apply_flow_conditions($f);
    llama_place_report_apply_road_hazard_dependencies($f);

    require_once __DIR__ . '/flow-metadata.php';
    llama_place_report_apply_flow_metadata($f);

    // Report scopes are annotations until each caller supports scoped saving.
    require_once __DIR__ . '/flow-scopes.php';
    llama_place_report_apply_flow_scopes($f);

    foreach ([
        'approach_width_restricted', 'approach_narrowest_width_feet',
        'approach_turnaround_available', 'approach_overhead_obstacles',
        'approach_access_restrictions',
        'approach_year_round', 'approach_seasonal_closure',
        'approach_closure_month', 'approach_closure_day',
        'approach_reopening_month', 'approach_reopening_day',
        'approach_winter_maintenance', 'approach_snow_chains',
        'approach_seasonal_restrictions', 'approach_road_conditions_url',
        'approach_road_type', 'approach_permit_required',
        'approach_fee_required', 'approach_gate_present',
        'approach_gate_access_type', 'approach_gate_opens',
        'approach_gate_closes', 'approach_restriction_details',
        'approach_access_info_url',
    ] as $key) {
        $f[$key]['report_scopes'] = ['place'];
        $f[$key]['report_scope_group'] = 'place';
    }

    require_once __DIR__ . '/flow-access-options.php';
    llama_place_report_apply_access_options($f);

    require_once __DIR__ . '/flow-permissions.php';
    llama_place_report_apply_permission_flows($f, $add);

    require_once __DIR__ . '/fields-place-overnight.php';
    llama_place_report_add_overnight_permission_fields($add);

    require_once __DIR__ . '/fields-place-shared-amenities.php';
    llama_place_report_add_shared_amenity_fields($f, $add);

    require_once __DIR__ . '/fields-place-rules.php';
    llama_place_report_add_place_rules_fields($add);

    require_once __DIR__ . '/fields-place-operating.php';
    llama_place_report_add_operating_fields($add);

    // Entrance/day-use and parking charges describe the Place itself rather
    // than a camping stay, so show them with the main Location/Place facts.
    // Storage paths and points categories remain unchanged.
    foreach (['entrance_facility_fee', 'parking_fee'] as $key) {
        if (isset($f[$key])) {
            $f[$key]['display_section'] = 'location';
        }
    }

    require_once __DIR__ . '/flow-layout.php';
    llama_place_report_apply_layout_metadata($f);
    llama_place_report_apply_place_rules_metadata($f);
    llama_place_report_apply_operating_metadata($f);

    require_once __DIR__ . '/flow-site-selection.php';
    llama_place_report_apply_site_selection_metadata($f);

    // The approved Place-wide overnight questions remain in their proper
    // subsection even though their legacy storage category is "rules".
    foreach ([
        'tent_camping_allowed',
        'rv_camping_allowed',
        'trailer_camping_allowed',
        'overnight_restrictions',
        'overnight_vehicle_tent_permit_url',
    ] as $key) {
        $f[$key]['layout_section'] = 'place';
        $f[$key]['layout_group'] = 'overnight_stays';
    }

    return $f;
}
