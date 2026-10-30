<?php

declare(strict_types=1);

/**
 * Authoritative approved Scout Report question inventory.
 *
 * This module is deliberately independent from presentation, persistence,
 * applicability, and scope assignment. It provides the shared approved
 * questionnaire identity/order used by the existing reporting system.
 *
 * M1-001 is a behavior-preserving extraction from flow-layout.php. Known
 * differences between the approved PDF and the current implementation are
 * recorded below as reconciliation metadata rather than silently "fixed" here.
 */

/**
 * Approved question grouping and order from the completed Scout Report mapping.
 *
 * This inventory belongs to the shared reporting layer, not a page template.
 * Keys continue to resolve their labels, answer choices, scope and storage
 * through llama_place_report_fields().
 */
function llama_place_report_approved_question_groups(): array
{
    return [
    'basic' => ['Basic', ['name','type','description']],
    'location' => ['Location', ['latitude','longitude','elevation_feet','road','city','county','state','land_type','land_manager','region','activities']],
    'amenities' => ['Amenities', ['amenity_toilets','amenity_potable_water','amenity_trash','amenity_dump_station','amenity_showers','amenity_picnic_shelter','amenity_wifi','amenity_laundry','amenity_dishwashing_station','amenity_playground','amenity_dog_park','amenity_amphitheater','amenity_campground_host','amenity_camp_store','amenity_trailhead','amenity_fishing_pier']],
    'operating' => ['Operating Hours', ['place_open_year_round','seasonal_closure','season_begins','season_ends','place_open_24_hours','place_opening_time','place_closing_time']],
    'road_conditions' => ['Road Conditions', ['approach_road_type','approach_permit_required','approach_fee_required','approach_road_fee','road_surface','road_width','approach_road_condition','approach_road_hazards','approach_deep_ruts','rocks','washboards','mud_risk','steep_grades','drop_off_exposure','water_crossings','downed_tree_risk','approach_access_restrictions']],
    'vehicle_access' => ['Vehicle Access', ['approach_overhead_obstacles','sedan_accessible','high_clearance_recommended','four_wheel_drive_recommended','approach_turnaround_available']],
    'seasonal_access' => ['Seasonal Access', ['best_months','approach_seasonal_closure','approach_closure_month','approach_closure_day','approach_reopening_month','approach_reopening_day','hurricane_risk','monsoon_risk','mud_season_risk','snow_risk','approach_winter_maintenance','approach_snow_chains','approach_seasonal_restrictions']],
    'gate_access' => ['Gate Access', ['approach_gate_present','approach_gate_access_type','approach_gate_opens','approach_gate_closes','place_entrance_gate','place_gate_access_type','place_gate_opening_time','place_gate_closing_time']],
    'nearest_services' => ['Nearest Services', ['nearest_town','nearest_fuel','nearest_ev_charging','nearest_propane','nearest_alcohol_sales','nearest_grocery','nearest_water','nearest_toilet','nearest_hospital']],
    'site_info' => ['Site Info', ['site_number','site_accessible','tent_camping_suitable','rv_suitable','capacity_size_rating','max_people','vehicle_capacity','site_length_feet','site_width_feet','overhead_clearance_feet','ground_condition','levelness','parking_grade']],
    'fees' => ['Fees and Reservations', ['stay_limit_days','fee','extra_vehicle_fee','reservation_required','reservation_fee','reservation_url','membership_required','membership_fee','membership_url','parking_entrance_required','parking_entrance_fee','check_in_required','check_in_begins','check_out_required','checkout_ends','discounts_available']],
    'setting' => ['Setting and Environment', ['landscape_primary','landscape_details','landscape_views','environment_water_nearby','environment_wildlife','critter_activity','environment_bugs','environment_sun_exposure','environment_shade','tree_cover','environment_open_sky','environment_notes']],
    'parking' => ['Parking and Maneuvering', ['parking_surface','back_in','pull_through','turnaround_space','parking_size','parking_length_feet','max_vehicle_length_feet','max_rv_length_feet','parking_clearance_feet','leveling_required','parking_notes']],
    'site_accessibility' => ['Accessibility', ['accessible_picnic_table','accessible_toilet','wheelchair_friendly','mobility_device_friendly','step_free_access','flat_walking_surface','walking_distance_from_vehicle','accessibility_notes']],
    'site_amenities' => ['Site Amenities', ['amenity_fire_ring','amenity_grill','amenity_picnic_table','amenity_lantern_post','site_potable_water','site_hookups_available','hookup_electric_service','hookup_water','hookup_sewer','tent_pad','tent_pad_width_feet','tent_pad_length_feet','amenity_bear_box','site_lake_access','site_river_access','site_trail_access','site_wifi_rating','site_amenities_notes']],
    'connectivity_public' => ['Connectivity', ['connectivity_overall','connectivity_att','connectivity_t_mobile','connectivity_verizon','connectivity_other_cell','connectivity_satellite','connectivity_services_tested','connectivity_starlink_note']],
    'daytime' => ['Daytime', ['daytime_social_interaction','daytime_noise','daytime_traffic','daytime_crowds','daytime_privacy','daytime_light_pollution','daytime_sensory_comfort','felt_safe_daytime_rating']],
    'nighttime' => ['Nighttime', ['nighttime_social_interaction','nighttime_noise','nighttime_traffic','nighttime_crowds','nighttime_privacy','nighttime_light_pollution','nighttime_sensory_comfort','felt_safe_nighttime_rating']],
    'specific_sensory' => ['Specific Sensory', ['sensory_dust_from_traffic','sensory_generator_noise','sensory_aircraft_noise','sensory_train_noise','sensory_dog_barking','sensory_wildlife_noise','sensory_wind_noise','sensory_strong_odors','sensory_smoke_risk','road_exposure','sensory_visual_exposure','sensory_predictability','sensory_summary']],
    'safety_public' => ['Safety and Hazards', ['warning_possible_downed_trees','fall_hazard','wildlife_risk','wildfire_risk','flash_flood_risk','rockfall_risk','traffic_hazard','warning_blind_turn_traffic_nearby','emergency_access']],
    'rules_public' => ['Rules and Restrictions', ['overnight_camping_allowed','dispersed_camping_allowed','designated_sites_only','pets_allowed','dogs_required_to_be_leashed','generator_restrictions','generator_prohibited','generator_quiet_hours','generator_quiet_hours_begin','generator_quiet_hours_end','generator_run_restrictions','generator_max_run_hours','generator_restriction_details','place_quiet_hours','place_quiet_hours_begin','place_quiet_hours_end','campfire_allowed','collecting_firewood','place_charcoal_grills_allowed','place_propane_fire_pits_allowed','fire_activity_permit_url','current_fire_restrictions_url','food_storage_required','pack_it_in_pack_it_out','place_alcohol_permitted','drone_use_legal','target_shooting_allowed','place_additional_rules']],
    'experience_public' => ['Experience and Recommendations', ['site_rating','condition_rating','location_rating','experience_sunrise_view','experience_sunset_view','experience_overall_scenery','experience_stargazing','experience_sensory_retreat','experience_quiet_evening','recommended_remote_work','recommended_overnight_stop','recommended_extended_stay','recommended_solo_travel','recommended_families','recommended_large_groups','not_recommended_for','experience_summary']],
    'notes_public' => ['Scout Notes', ['scout_note_1','scout_note_2','scout_note_3']],
    'history_public' => ['Place History', ['visited_at','visit_length_of_stay','visit_setup_types']],
];
}

/**
 * Canonical question-level visibility.
 *
 * Visibility is independent from Place/Site discoverability and operational
 * permissions. Restricted dispersed Sites remain restricted even when a field
 * is classified Public.
 *
 * Legacy group keys ending in "_public" do not determine V3 access.
 */
function llama_place_report_approved_question_visibility(): array
{
    $visibility = [];

    foreach (llama_place_report_approved_question_groups() as $definition) {
        foreach ((array) ($definition[1] ?? []) as $key) {
            $visibility[(string) $key] = 'community';
        }
    }

    $publicKeys = [
        'name',
        'type',
        'elevation_feet',
        'city',
        'county',
        'state',
        'land_type',
        'land_manager',
        'region',
    ];

    $groups =
        llama_place_report_approved_question_groups();

    foreach ((array) ($groups['amenities'][1] ?? []) as $amenityKey) {
        $publicKeys[] =
            (string) $amenityKey;
    }

    foreach (array_values(array_unique($publicKeys)) as $key) {
        if (array_key_exists($key, $visibility)) {
            $visibility[$key] =
                'public';
        }
    }

    return $visibility;
}

/**
 * Shared authorization decision for canonical question visibility.
 *
 * This helper answers only question-level visibility. It does not grant access
 * to a Place or Site and must not bypass dispersed-Site discoverability rules.
 */
function llama_place_report_visibility_allows(
    string $visibility,
    bool $hasCommunityAccess
): bool {
    if ($visibility === 'public') {
        return true;
    }

    if ($visibility === 'community') {
        return $hasCommunityAccess;
    }

    return false;
}

function llama_place_report_field_visibility_allows(
    array $field,
    bool $hasCommunityAccess
): bool {
    return llama_place_report_visibility_allows(
        (string) ($field['approved_visibility'] ?? ''),
        $hasCommunityAccess
    );
}

/**
 * Approved two-page Scout Report presentation.
 *
 * The data foundation remains shared, but the full Scout Report must not be
 * flattened into one overwhelming page. Place-wide information is presented
 * on the Place page and Site-specific information is presented on the Site page.
 *
 * Accent roles are semantic. Presentation code maps "place" to the existing
 * Place green treatment and "site" to the existing Site orange treatment.
 * Hex values do not belong in canonical reporting metadata.
 */
function llama_place_report_approved_pages(): array
{
    return [
        'place' => [
            'label' => 'Place details',
            'accent_role' => 'place',
            'groups' => [
                'basic',
                'location',
                'amenities',
                'operating',
                'road_conditions',
                'vehicle_access',
                'seasonal_access',
                'gate_access',
                'nearest_services',
            ],
        ],
        'site' => [
            'label' => 'Site details',
            'accent_role' => 'site',
            'groups' => [
                'site_info',
                'fees',
                'setting',
                'parking',
                'site_accessibility',
                'site_amenities',
                'connectivity_public',
                'daytime',
                'nighttime',
                'specific_sensory',
                'safety_public',
                'rules_public',
                'experience_public',
                'notes_public',
                'history_public',
            ],
        ],
    ];
}

/**
 * Approved question groups ordered by their canonical report pages.
 *
 * Page ownership and page-local group order come only from approved_pages().
 * The group registry still owns each group's label and question order.
 */
function llama_place_report_approved_groups_in_page_order(): array
{
    $groups =
        llama_place_report_approved_question_groups();

    $ordered = [];

    foreach (llama_place_report_approved_pages() as $page) {
        foreach ((array) ($page['groups'] ?? []) as $groupKey) {
            $groupKey =
                (string) $groupKey;

            if (isset($groups[$groupKey])) {
                $ordered[$groupKey] =
                    $groups[$groupKey];
            }
        }
    }

    return $ordered;
}

/**
 * Flatten approved page metadata to one lookup per approved group.
 */
function llama_place_report_approved_group_page_lookup(): array
{
    $lookup = [];

    foreach (llama_place_report_approved_pages() as $pageKey => $page) {
        foreach ((array) ($page['groups'] ?? []) as $groupKey) {
            $lookup[(string) $groupKey] = [
                'page' => $pageKey,
                'page_label' => (string) ($page['label'] ?? ''),
                'accent_role' => (string) ($page['accent_role'] ?? ''),
            ];
        }
    }

    return $lookup;
}

/**
 * Approved read-only source fields shown with the Place Basic information.
 *
 * These are canonical Scout Report definitions, but they are not ordinary
 * community-editable questions. Their values come from the existing official
 * facility/source bundle and retain provider provenance outside the editable
 * question registry.
 */
function llama_place_report_approved_source_fields(): array
{
    return [
        'official_address' => [
            'label' => 'Official Address',
            'group' => 'basic',
            'page' => 'place',
            'page_label' => 'Place details',
            'accent_role' => 'place',
            'answer_type' => 'text',
            'source_bundle' => 'facility_public',
            'source_key' => 'address',
            'visibility' => 'public',
            'editable' => false,
            'status' => 'active',
        ],
        'official_phone' => [
            'label' => 'Phone Number',
            'group' => 'basic',
            'page' => 'place',
            'page_label' => 'Place details',
            'accent_role' => 'place',
            'answer_type' => 'phone',
            'source_bundle' => 'facility_public',
            'source_key' => 'phone',
            'visibility' => 'public',
            'editable' => false,
            'status' => 'active',
        ],
        'official_email' => [
            'label' => 'Email Address',
            'group' => 'basic',
            'page' => 'place',
            'page_label' => 'Place details',
            'accent_role' => 'place',
            'answer_type' => 'email',
            'source_bundle' => 'facility_public',
            'source_key' => 'email',
            'visibility' => 'public',
            'editable' => false,
            'status' => 'active',
        ],
        'official_links' => [
            'label' => 'Official Links',
            'group' => 'basic',
            'page' => 'place',
            'page_label' => 'Place details',
            'accent_role' => 'place',
            'answer_type' => 'links',
            'source_bundle' => 'facility_public',
            'source_key' => 'links',
            'visibility' => 'public',
            'editable' => false,
            'status' => 'active',
        ],
    ];
}

/**
 * Approved compound questions.
 *
 * A compound question has one user-facing identity but preserves multiple
 * existing component keys for storage, validation, and historical data.
 */
function llama_place_report_approved_compound_questions(): array
{
    return [
        'season_begins' => [
            'label' => 'Season Begins',
            'group' => 'seasonal_access',
            'page' => 'place',
            'page_label' => 'Place details',
            'accent_role' => 'place',
            'display_format' => 'month_day',
            'components' => [
                'approach_closure_month' => [
                    'role' => 'month',
                    'label' => 'Month',
                ],
                'approach_closure_day' => [
                    'role' => 'day',
                    'label' => 'Day',
                ],
            ],
            'status' => 'active',
        ],
        'season_ends' => [
            'label' => 'Season Ends',
            'group' => 'seasonal_access',
            'page' => 'place',
            'page_label' => 'Place details',
            'accent_role' => 'place',
            'display_format' => 'month_day',
            'components' => [
                'approach_reopening_month' => [
                    'role' => 'month',
                    'label' => 'Month',
                ],
                'approach_reopening_day' => [
                    'role' => 'day',
                    'label' => 'Day',
                ],
            ],
            'status' => 'active',
        ],
    ];
}

/**
 * Map persisted component keys back to their user-facing compound question.
 */
function llama_place_report_approved_compound_component_lookup(): array
{
    $lookup = [];

    foreach (llama_place_report_approved_compound_questions() as $compoundKey => $compound) {
        foreach ((array) ($compound['components'] ?? []) as $componentKey => $component) {
            $lookup[(string) $componentKey] = [
                'compound_key' => $compoundKey,
                'compound_label' => (string) ($compound['label'] ?? $compoundKey),
                'group' => (string) ($compound['group'] ?? ''),
                'page' => (string) ($compound['page'] ?? ''),
                'accent_role' => (string) ($compound['accent_role'] ?? ''),
                'role' => (string) ($component['role'] ?? ''),
                'component_label' => (string) ($component['label'] ?? $componentKey),
            ];
        }
    }

    return $lookup;
}

/**
 * Known differences between the approved Scout Report PDF and the current
 * implementation. These records make the migration work machine-readable
 * without changing current storage or rendering behavior.
 */
function llama_place_report_approved_reconciliation(): array
{
    return [
        'official_address' => [
            'approved_label' => 'Official Address',
            'approved_group' => 'basic',
            'canonical_key' => 'official_address',
            'current_keys' => [],
            'status' => 'canonical_source_field',
            'compatibility' => 'facility_public_projection',
        ],
        'official_phone' => [
            'approved_label' => 'Phone Number',
            'approved_group' => 'basic',
            'canonical_key' => 'official_phone',
            'current_keys' => [],
            'status' => 'canonical_source_field',
            'compatibility' => 'facility_public_projection',
        ],
        'official_email' => [
            'approved_label' => 'Email Address',
            'approved_group' => 'basic',
            'canonical_key' => 'official_email',
            'current_keys' => [],
            'status' => 'canonical_source_field',
            'compatibility' => 'facility_public_projection',
        ],
        'official_links' => [
            'approved_label' => 'Official Links',
            'approved_group' => 'basic',
            'canonical_key' => 'official_links',
            'current_keys' => [],
            'status' => 'canonical_source_field',
            'compatibility' => 'facility_public_projection',
        ],
        'seasonal_access_begin' => [
            'approved_label' => 'Season Begins',
            'approved_group' => 'seasonal_access',
            'current_keys' => [
                'approach_closure_month',
                'approach_closure_day',
            ],
            'status' => 'canonical_compound_question',
            'compatibility' => 'preserve_existing_month_day_storage',
        ],
        'seasonal_access_end' => [
            'approved_label' => 'Season Ends',
            'approved_group' => 'seasonal_access',
            'current_keys' => [
                'approach_reopening_month',
                'approach_reopening_day',
            ],
            'status' => 'canonical_compound_question',
            'compatibility' => 'preserve_existing_month_day_storage',
        ],
        'site_overhead_clearance' => [
            'approved_label' => 'Site Overhead Clearance',
            'approved_group' => 'site_info',
            'current_keys' => [
                'overhead_clearance_feet',
            ],
            'status' => 'resolved_existing_key',
            'compatibility' => 'preserve_existing_overhead_clearance',
        ],
        'parking_clearance' => [
            'approved_label' => 'Parking Clearance',
            'approved_group' => 'parking',
            'current_keys' => [
                'parking_clearance_feet',
            ],
            'status' => 'resolved_distinct_key',
            'compatibility' => 'new_field_preserves_legacy_site_clearance',
        ],
        'flat_walking_surface' => [
            'approved_label' => 'Flat Walking Surface',
            'approved_group' => 'site_accessibility',
            'current_keys' => [
                'flat_walking_surface',
            ],
            'status' => 'approved_v3_override',
            'compatibility' => 'retain_as_active_v3_question',
        ],
        'moderator_notes' => [
            'approved_label' => 'Notes for Moderator',
            'approved_group' => 'questionnaire_only',
            'current_keys' => [
                'contributor_notes',
            ],
            'status' => 'workflow_metadata',
            'compatibility' => 'never_publish_as_scout_report_card',
        ],
        'toilet_type' => [
            'approved_label' => 'Toilets',
            'approved_group' => 'amenities',
            'current_keys' => [
                'amenity_toilets',
            ],
            'status' => 'resolved_typed_storage_with_presence_mirror',
            'compatibility' => 'backfill_legacy_yes_no_and_mirror_presence',
        ],
        'camp_host' => [
            'approved_label' => 'Camp Host',
            'approved_group' => 'amenities',
            'current_keys' => [
                'amenity_campground_host',
            ],
            'status' => 'resolved_yes_no_with_legacy_value',
            'compatibility' => 'preserve_existing_seasonal_value',
        ],
        'parking_to_site_distance' => [
            'approved_label' => 'Distance parking to site',
            'approved_group' => 'site_accessibility',
            'current_keys' => [
                'walking_distance_from_vehicle',
            ],
            'status' => 'approved_v3_bucket_override',
            'compatibility' => 'retain_existing_distance_buckets',
        ],
        'permission_answer_vocabulary' => [
            'approved_label' => null,
            'approved_group' => 'rules_public',
            'current_keys' => [
                'overnight_camping_allowed',
                'dispersed_camping_allowed',
                'campfire_allowed',
                'collecting_firewood',
                'place_charcoal_grills_allowed',
                'place_propane_fire_pits_allowed',
                'place_alcohol_permitted',
                'drone_use_legal',
                'target_shooting_allowed',
            ],
            'status' => 'resolved_field_specific_vocabularies',
            'compatibility' => 'preserve_legacy_conditional_values',
        ],
    ];
}

/**
 * Static integrity checks for the approved inventory itself.
 *
 * This intentionally does not call llama_place_report_fields(), so it can be
 * used while the field registry is being assembled without creating a cycle.
 */
function llama_place_report_approved_registry_issues(): array
{
    $issues = [];
    $groups = llama_place_report_approved_question_groups();
    $seen = [];
    $count = 0;

    foreach ($groups as $groupKey => $definition) {
        $label = (string) ($definition[0] ?? '');
        $keys = (array) ($definition[1] ?? []);

        if ($groupKey === '' || $label === '') {
            $issues[] = 'Approved question group has a missing key or label.';
        }

        foreach ($keys as $position => $key) {
            $key = (string) $key;
            $count++;

            if ($key === '') {
                $issues[] =
                    'Approved question group '
                    . $groupKey
                    . ' contains an empty key at position '
                    . $position
                    . '.';
                continue;
            }

            if (isset($seen[$key])) {
                $issues[] =
                    'Approved question key '
                    . $key
                    . ' appears in both '
                    . $seen[$key]
                    . ' and '
                    . $groupKey
                    . '.';
                continue;
            }

            $seen[$key] = $groupKey;
        }
    }

    if ($count !== 264) {
        $issues[] =
            'Current approved-question projection contains '
            . $count
            . ' slots; expected 264 after separating Site Overhead Clearance from Parking Clearance.';
    }

    $assignedGroups = [];

    foreach (llama_place_report_approved_pages() as $pageKey => $page) {
        $accentRole = (string) ($page['accent_role'] ?? '');

        if (!in_array($pageKey, ['place', 'site'], true)) {
            $issues[] =
                'Unknown approved report page '
                . $pageKey
                . '.';
        }

        if ($accentRole !== $pageKey) {
            $issues[] =
                'Approved report page '
                . $pageKey
                . ' must use the matching semantic accent role.';
        }

        foreach ((array) ($page['groups'] ?? []) as $groupKey) {
            $groupKey = (string) $groupKey;

            if (!array_key_exists($groupKey, $groups)) {
                $issues[] =
                    'Approved report page '
                    . $pageKey
                    . ' references unknown group '
                    . $groupKey
                    . '.';
                continue;
            }

            if (isset($assignedGroups[$groupKey])) {
                $issues[] =
                    'Approved question group '
                    . $groupKey
                    . ' is assigned to both '
                    . $assignedGroups[$groupKey]
                    . ' and '
                    . $pageKey
                    . ' pages.';
                continue;
            }

            $assignedGroups[$groupKey] = $pageKey;
        }
    }

    foreach (array_keys($groups) as $groupKey) {
        if (!isset($assignedGroups[$groupKey])) {
            $issues[] =
                'Approved question group '
                . $groupKey
                . ' is not assigned to a report page.';
        }
    }

    $compoundComponents = [];

    foreach (llama_place_report_approved_compound_questions() as $compoundKey => $compound) {
        $groupKey = (string) ($compound['group'] ?? '');
        $pageKey = (string) ($compound['page'] ?? '');
        $components = (array) ($compound['components'] ?? []);

        if ($compoundKey === '' || $groupKey === '' || $pageKey === '') {
            $issues[] =
                'Approved compound question has a missing key, group, or page.';
            continue;
        }

        if (!isset($groups[$groupKey])) {
            $issues[] =
                'Approved compound question '
                . $compoundKey
                . ' references unknown group '
                . $groupKey
                . '.';
        }

        if (($assignedGroups[$groupKey] ?? null) !== $pageKey) {
            $issues[] =
                'Approved compound question '
                . $compoundKey
                . ' does not match its group page assignment.';
        }

        if (count($components) < 2) {
            $issues[] =
                'Approved compound question '
                . $compoundKey
                . ' must contain at least two component fields.';
        }

        foreach ($components as $componentKey => $component) {
            $componentKey = (string) $componentKey;

            if (isset($compoundComponents[$componentKey])) {
                $issues[] =
                    'Compound component '
                    . $componentKey
                    . ' is assigned to both '
                    . $compoundComponents[$componentKey]
                    . ' and '
                    . $compoundKey
                    . '.';
                continue;
            }

            $compoundComponents[$componentKey] =
                $compoundKey;

            $componentGroup =
                null;

            foreach ($groups as $candidateGroupKey => $definition) {
                if (in_array($componentKey, (array) ($definition[1] ?? []), true)) {
                    $componentGroup =
                        $candidateGroupKey;
                    break;
                }
            }

            if ($componentGroup !== $groupKey) {
                $issues[] =
                    'Compound component '
                    . $componentKey
                    . ' does not belong to approved group '
                    . $groupKey
                    . '.';
            }

            if (trim((string) ($component['role'] ?? '')) === '') {
                $issues[] =
                    'Compound component '
                    . $componentKey
                    . ' is missing its component role.';
            }
        }
    }

    $questionVisibility =
        llama_place_report_approved_question_visibility();

    foreach ($groups as $groupKey => $definition) {
        foreach ((array) ($definition[1] ?? []) as $key) {
            $key =
                (string) $key;

            if (!array_key_exists($key, $questionVisibility)) {
                $issues[] =
                    'Approved question '
                    . $key
                    . ' is missing canonical visibility.';
                continue;
            }

            if (
                !in_array(
                    $questionVisibility[$key],
                    ['public', 'community'],
                    true
                )
            ) {
                $issues[] =
                    'Approved question '
                    . $key
                    . ' has invalid canonical visibility.';
            }
        }
    }

    foreach (llama_place_report_approved_source_fields() as $key => $field) {
        $groupKey = (string) ($field['group'] ?? '');
        $pageKey = (string) ($field['page'] ?? '');
        $sourceKey = (string) ($field['source_key'] ?? '');

        if ($key === '' || $groupKey === '' || $pageKey === '') {
            $issues[] =
                'Approved source field has a missing key, group, or page.';
            continue;
        }

        if (!isset($groups[$groupKey])) {
            $issues[] =
                'Approved source field '
                . $key
                . ' references unknown group '
                . $groupKey
                . '.';
        }

        if (($assignedGroups[$groupKey] ?? null) !== $pageKey) {
            $issues[] =
                'Approved source field '
                . $key
                . ' does not match its group page assignment.';
        }

        if (!empty($field['editable'])) {
            $issues[] =
                'Approved source field '
                . $key
                . ' must remain read-only.';
        }

        if ($sourceKey === '') {
            $issues[] =
                'Approved source field '
                . $key
                . ' is missing its source key.';
        }
    }

    $resolvedReconciliationStatuses = [
        'canonical_source_field',
        'canonical_compound_question',
        'resolved_existing_key',
        'resolved_distinct_key',
        'approved_v3_override',
        'workflow_metadata',
        'resolved_typed_storage_with_presence_mirror',
        'resolved_yes_no_with_legacy_value',
        'approved_v3_bucket_override',
        'resolved_field_specific_vocabularies',
    ];

    foreach (llama_place_report_approved_reconciliation() as $itemKey => $item) {
        $status =
            (string) (
                $item['status']
                ?? ''
            );

        if (!in_array($status, $resolvedReconciliationStatuses, true)) {
            $issues[] =
                'Reconciliation item '
                . $itemKey
                . ' remains unresolved with status '
                . ($status !== '' ? $status : '(missing)')
                . '.';
        }
    }

    $visibilityKeys =
        array_keys(
            llama_place_report_approved_question_visibility()
        );

    $approvedKeys =
        array_keys($seen);

    sort($visibilityKeys);
    sort($approvedKeys);

    if ($visibilityKeys !== $approvedKeys) {
        $issues[] =
            'Canonical visibility keys do not exactly match the approved question inventory.';
    }

    return $issues;
}

/**
 * Small read-only snapshot useful to diagnostics and future tests.
 */
function llama_place_report_approved_registry_summary(): array
{
    $groups = llama_place_report_approved_question_groups();
    $questions = 0;

    foreach ($groups as $definition) {
        $questions += count((array) ($definition[1] ?? []));
    }

    return [
        'source' => 'Scout Report Final.pdf',
        'approved_pdf_visible_entries' => 266,
        'current_projection_groups' => count($groups),
        'current_projection_questions' => $questions,
        'approved_source_fields' => count(llama_place_report_approved_source_fields()),
        'approved_compound_questions' => count(llama_place_report_approved_compound_questions()),
        'approved_visibility_questions' => count(llama_place_report_approved_question_visibility()),
        'approved_pages' => count(llama_place_report_approved_pages()),
        'approved_page_groups' => count(llama_place_report_approved_group_page_lookup()),
        'known_reconciliation_items' =>
            count(llama_place_report_approved_reconciliation()),
        'issues' => llama_place_report_approved_registry_issues(),
    ];
}
