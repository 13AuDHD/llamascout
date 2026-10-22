<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report.php';

$placeReportData =
    is_array($placeReportData ?? null)
        ? $placeReportData
        : [];

$placeReportReadMode =
    (string) ($placeReportReadMode ?? 'scout-report');

$placeReportFields =
    llama_place_report_fields();

$placeReportSections =
    llama_place_report_sections();

/*
 * Public Scout Report groups follow the approved questionnaire.
 * The shared field registry remains authoritative for labels, answer values,
 * storage and site switching. Unlisted legacy fields are not displayed.
 */
$publicReportGroups = [
    'basic' => ['Basic', ['name','type','description']],
    'location' => ['Location', ['latitude','longitude','elevation_feet','road','city','county','state','land_type','land_manager','region','activities']],
    'amenities' => ['Amenities', ['amenity_toilets','amenity_potable_water','amenity_trash','amenity_dump_station','amenity_showers','amenity_picnic_shelter','amenity_wifi','amenity_laundry','amenity_dishwashing_station','amenity_playground','amenity_dog_park','amenity_amphitheater','amenity_campground_host','amenity_camp_store','amenity_trailhead','amenity_fishing_pier']],
    'operating' => ['Operating Hours', ['place_open_year_round','seasonal_closure','place_operating_start_month','place_operating_start_day','place_operating_end_month','place_operating_end_day','place_open_24_hours','place_opening_time','place_closing_time']],
    'road_conditions' => ['Road Conditions', ['approach_road_type','approach_permit_required','approach_fee_required','approach_road_fee','road_surface','road_width','approach_road_condition','approach_road_hazards','approach_deep_ruts','rocks','washboards','mud_risk','steep_grades','drop_off_exposure','water_crossings','downed_tree_risk','approach_access_restrictions']],
    'vehicle_access' => ['Vehicle Access', ['approach_overhead_obstacles','sedan_accessible','high_clearance_recommended','four_wheel_drive_recommended','approach_turnaround_available']],
    'seasonal_access' => ['Seasonal Access', ['best_months','approach_seasonal_closure','approach_closure_month','approach_closure_day','approach_reopening_month','approach_reopening_day','hurricane_risk','monsoon_risk','mud_season_risk','snow_risk','approach_winter_maintenance','approach_snow_chains','approach_seasonal_restrictions']],
    'gate_access' => ['Gate Access', ['approach_gate_present','approach_gate_access_type','approach_gate_opens','approach_gate_closes','place_entrance_gate','place_gate_access_type','place_gate_opening_time','place_gate_closing_time']],
    'nearest_services' => ['Nearest Services', ['nearest_town','nearest_fuel','nearest_ev_charging','nearest_propane','nearest_alcohol_sales','nearest_grocery','nearest_water','nearest_toilet','nearest_hospital']],
    'site_info' => ['Site Info', ['site_number','site_accessible','tent_camping_suitable','rv_suitable','capacity_size_rating','max_people','vehicle_capacity','site_length_feet','site_width_feet','overhead_clearance_feet','ground_condition','levelness','parking_grade']],
    'fees' => ['Fees and Reservations', ['stay_limit_days','fee','extra_vehicle_fee','reservation_required','reservation_fee','reservation_url','membership_required','membership_fee','membership_url','parking_entrance_required','parking_entrance_fee','check_in_required','check_in_begins','check_out_required','checkout_ends','discounts_available']],
    'setting' => ['Setting and Environment', ['landscape_primary','landscape_details','landscape_views','environment_water_nearby','environment_wildlife','critter_activity','environment_bugs','environment_sun_exposure','environment_shade','tree_cover','environment_open_sky','environment_notes']],
    'parking' => ['Parking and Maneuvering', ['parking_surface','back_in','pull_through','turnaround_space','parking_size','parking_length_feet','max_vehicle_length_feet','max_rv_length_feet','overhead_clearance_feet','leveling_required','parking_notes']],
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
/* Official contact data is sourced from the existing RIDB facility resolver. */
$publicFacility = [];
if ($placeReportReadMode === 'scout-report' && !empty($place['id'])) {
    require_once dirname(__DIR__, 2) . '/app/ridb-facility-details.php';
    try {
        $publicFacility = llama_place_facility_public_data(db(), (int) $place['id']);
    } catch (Throwable $exception) {
        llama_log_caught_exception($exception, 'scout-report.facility');
    }
}

/* Render only a limited set of formatting tags, never source HTML attributes. */
$renderSafeDescription = static function (string $raw): string {
    $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $raw = preg_replace('~<(script|style|iframe|object|svg|form)\b[^>]*>.*?</\1\s*>~is', '', $raw) ?? $raw;
    $raw = strip_tags($raw, '<p><br><strong><b><em><i><u><ul><ol><li><h3><h4><h5><blockquote>');
    $raw = preg_replace_callback('~<(/?)([a-z][a-z0-9]*)\b[^>]*>~i', static function (array $m): string {
        $tag = strtolower($m[2]);
        return '<' . $m[1] . $tag . ($tag === 'br' ? ' /' : '') . '>';
    }, $raw) ?? $raw;
    return $raw;
};

$publicFieldGroups = [];
if ($placeReportReadMode === 'scout-report') {
    $placeReportSections = [];
    foreach ($publicReportGroups as $groupKey => [$groupLabel, $keys]) {
        $placeReportSections[$groupKey] = ['label' => $groupLabel, 'icon' => 'info-circle'];
        foreach ($keys as $key) {
            // Keep the first occurrence of a shared field to avoid duplicate cards.
            $publicFieldGroups[$key] ??= $groupKey;
        }
    }
}


/*
 * The contributor form and completion meter evaluate applicability against
 * flattened scoring input. The public Scout Report must use that exact same
 * input and the exact same canonical applicability function so a question
 * that was never available for this Place cannot reappear as a read-only
 * "Not provided" card.
 */
$placeReportApplicabilityInput =
    llama_place_report_scoring_input_from_data(
        $placeReportData
    );

$placeReportUpdateUrl = '';

if (
    $placeReportReadMode === 'scout-report'
    && !empty($canSuggestUpdate)
    && !empty($place['slug'])
) {
    $placeReportUpdateUrl =
        'https://account.llamascout.com/update-place.php?slug='
        . rawurlencode(
            (string) $place['slug']
        );
}

?>
<link
    rel="stylesheet"
    href="/css/site/features/scout-warning-compact.css"
>
<link
    rel="stylesheet"
    href="/css/site/features/place-report-empty-state.css"
>
<?php

$e = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );


$iconRoot = dirname(__DIR__, 2) . '/assets/icons/';

$localIcon =
    static function (
        string $legacyIcon,
        string $key = ''
    ) use (
        $iconRoot
    ): string {
        if ($key === 'warning_no_cell_service') {
            return 'antenna-bars-off';
        }

        $prefix = 'fa' . '-';
        $name = trim($legacyIcon);

        if (str_starts_with($name, $prefix)) {
            $name = substr($name, strlen($prefix));
        }

        $aliases = [
            'align-left' => 'file-text',
            'arrows-left-right' => 'arrows-diff',
            'arrows-rotate' => 'refresh',
            'box' => 'package',
            'box-archive' => 'packages',
            'building-columns' => 'building-factory',
            'calendar-day' => 'calendar',
            'calendar-xmark' => 'calendar-off',
            'campground' => 'tent',
            'car-burst' => 'alert-triangle',
            'car-side' => 'car',
            'cart-shopping' => 'shopping-cart',
            'circle-info' => 'info-circle',
            'circle-xmark' => 'xbox-x',
            'city' => 'building-store',
            'cloud-sun' => 'temperature-sun',
            'dollar-sign' => 'receipt',
            'ear-listen' => 'ear',
            'faucet-drip' => 'droplet',
            'file-signature' => 'signature',
            'fire' => 'campfire',
            'fire-flame-curved' => 'flame',
            'gas-pump' => 'gas-station',
            'hill-rockslide' => 'mountain',
            'hospital' => 'medical-cross',
            'house-circle-xmark' => 'door-off',
            'location-crosshairs' => 'current-location',
            'location-dot' => 'map-pin',
            'map-location-dot' => 'map-pin',
            'moon' => 'moon-stars',
            'motorcycle' => 'motorbike',
            'mountain-sun' => 'mountain',
            'note-sticky' => 'file-text',
            'pen' => 'edit',
            'person-falling' => 'cliff-jumping',
            'person-walking' => 'walk',
            'person-walking-arrow-right' => 'walk',
            'restroom' => 'at-toilet',
            'rotate' => 'refresh',
            'ruler-horizontal' => 'scale',
            'satellite-dish' => 'satellite',
            'scale-balanced' => 'scale',
            'shield-halved' => 'shield',
            'signal' => 'antenna-bars-5',
            'signs-post' => 'sign-right',
            'smog' => 'at-misty-cloud',
            'snowflake' => 'snowflake',
            'square-parking' => 'parking',
            'table-picnic' => 'picnic-table',
            'tent-arrow-turn-left' => 'tent-off',
            'trailer' => 'caravan',
            'trash-arrow-up' => 'trash',
            'trash-can' => 'trash',
            'triangle-exclamation' => 'alert-triangle',
            'truck-droplet' => 'caravan',
            'truck-medical' => 'medical-cross',
            'truck-monster' => 'car-4wd',
            'truck-pickup' => 'car-suv',
            'utensils' => 'picnic-table',
            'water' => 'ripple',
            'tree' => 'trees',
        ];

        $candidate = $aliases[$name] ?? $name;

        if (
            $candidate !== ''
            && is_file($iconRoot . $candidate . '.svg')
        ) {
            return $candidate;
        }

        return 'info-circle';
    };

$campsiteSwitchableFields =
    array_keys(
        array_filter(
            $placeReportFields,
            static function (
                array $field
            ): bool {
                return (string) (
                        $field[
                            'report_scope_group'
                        ]
                        ?? 'place'
                    )
                    !== 'place';
            }
        )
    );

$renderValue =
    static function (
        string $key,
        array $field
    ) use (
        $placeReportData,
        $placeReportReadMode,
        $e,
        $localIcon,
        $campsiteSwitchableFields,
        $renderSafeDescription
    ): void {
        $state =
            llama_place_report_answer_state(
                $placeReportData,
                $key
            );

        $value =
            llama_place_report_display_value(
                $placeReportData,
                $key
            );

        /*
         * During Issue #5 reconciliation, unanswered values stay visible in
         * every read-only flow so they can be compared against editable input.
         */
        if ($state === 'unanswered') {
            $value = 'Not provided';
        }

        $type =
            (string) $field['type'];

        $isRating =
            $type === 'rating';

        $class =
            'scout-report-item '
            . (
                $isRating
                    ? 'scout-report-rating-item'
                    : 'scout-report-value-item'
            )
            . (
                !empty($field['wide'])
                    ? ' scout-report-item-wide'
                    : ''
            )
            . (
                $type === 'textarea'
                    ? ' scout-report-long-text'
                    : ''
            )
            . (
                $state === 'unknown'
                    ? ' is-explicit-unknown'
                    : ''
            )
            . (
                $state === 'unanswered'
                    ? ' is-unanswered'
                    : ''
            );
        ?>

        <?php
        $isCampsiteSwitchable =
            $placeReportReadMode === 'scout-report'
            && in_array(
                $key,
                $campsiteSwitchableFields,
                true
            );

        /*
         * Reconciliation mode keeps unanswered Site/Area-capable questions
         * visible instead of waiting for a Site selection.
         */
        $hideUntilCampsite =
            false;
        ?>

        <div
            class="<?= $e($class) ?>"
            <?php if ($isCampsiteSwitchable): ?>
                data-campsite-report-field="<?= $e($key) ?>"
                data-campsite-report-type="<?= $e($type) ?>"
            <?php endif; ?>
            <?= $hideUntilCampsite ? 'hidden' : '' ?>
        >

            <?php if ($isRating): ?>
                <div class="scout-rating-content">
                    <span>
                        <?= $e(
                            rtrim(
                                (string) $field['label'],
                                '*'
                            )
                        ) ?>
                    </span>

                    <strong><?= $e($value) ?></strong>
                </div>

                <?php
                $rating =
                    $state === 'answered'
                        ? (int) llama_place_report_get_path(
                            $placeReportData,
                            (string) $field['storage']
                        )
                        : 0;
                ?>

                <div
                    class="scout-rating-dots"
                    aria-label="<?= $state === 'answered' ? $rating . ' out of 5' : $e((string) $value) ?>"
                >
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span
                            class="scout-rating-dot<?= $i <= $rating ? ' is-filled' : '' ?>"
                            aria-hidden="true"
                        ></span>
                    <?php endfor; ?>
                </div>

            <?php else: ?>
                <?php
                $icon =
                    $localIcon(
                        llama_place_report_field_icon(
                            $key,
                            $field
                        ),
                        $key
                    );
                ?>

                <?php
                $safeUrl = null;

                if (
                    $type === 'url'
                    && $state === 'answered'
                    && is_string($value)
                    && $value !== ''
                ) {
                    $urlParts = parse_url($value);
                    $urlScheme = strtolower(
                        (string) ($urlParts['scheme'] ?? '')
                    );

                    if (
                        in_array(
                            $urlScheme,
                            ['http', 'https'],
                            true
                        )
                    ) {
                        $safeUrl = $value;
                    }
                }
                ?>

                <div class="scout-report-value-content">
                    <span>
                        <?= $e(
                            rtrim(
                                (string) $field['label'],
                                '*'
                            )
                        ) ?>
                    </span>

                    <strong>
                        <?php if ($safeUrl !== null): ?>
                            <a
                                href="<?= $e($safeUrl) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            ><?= $e($value) ?></a>
                        <?php elseif ($key === 'description' && $state === 'answered'): ?>
                            <?= $renderSafeDescription((string) $value) ?>
                        <?php else: ?>
                            <?= $e($value) ?>
                        <?php endif; ?>
                    </strong>
                </div>

                <?= llama_icon(
                    $icon,
                    [
                        'class' => 'scout-report-value-icon',
                    ]
                ) ?>
            <?php endif; ?>
        </div>
        <?php
    };

$renderContributionPrompt =
    static function (
        string $sectionLabel,
        int $missingCount,
        bool $empty
    ) use (
        $placeReportUpdateUrl,
        $e
    ): void {
        if ($empty) {
            $message =
                'No one has reported '
                . strtolower($sectionLabel)
                . ' information here yet.';
        } else {
            $message =
                $missingCount
                . ' applicable detail'
                . ($missingCount === 1 ? '' : 's')
                . ' in this section '
                . ($missingCount === 1 ? 'has' : 'have')
                . ' not been reported yet.';
        }
        ?>

        <div
            class="scout-report-contribution-prompt<?= $empty ? ' is-empty' : ' is-partial' ?>"
        >
            <div class="scout-report-contribution-copy">
                <strong>
                    <?= $empty
                        ? 'Be the first to add this'
                        : 'Know something we are missing?' ?>
                </strong>

                <p><?= $e($message) ?></p>
            </div>

            <?php if ($placeReportUpdateUrl !== ''): ?>
                <a
                    href="<?= $e($placeReportUpdateUrl) ?>"
                    class="scout-report-contribution-action"
                >
                    <?= llama_icon('edit') ?>
                    Update this Place
                </a>
            <?php endif; ?>
        </div>
        <?php
    };

/*
 * Warning rules come from the shared Scout Report runtime. Keep a stable
 * container in the page even when the Place itself has no warnings so an Area
 * or Site selection can reveal its own warning set without rebuilding layout.
 */
$warnings =
    llama_place_report_quick_warnings(
        $placeReportData
    );
?>
    <section
        class="scout-report-section scout-report-warning-section"
        data-report-warnings
        <?= $warnings ? '' : 'hidden' ?>
    >
        <h3>
            <?= llama_icon('alert-triangle') ?>
            Quick warnings
        </h3>

        <p
            class="scout-report-summary"
            data-report-warning-summary
        >
            Important conditions reported for this Place.
        </p>

        <div
            class="scout-report-grid"
            data-report-warning-grid
        >
            <?php foreach ($warnings as $key => $warning): ?>
                <?php
                $warningIcon =
                    $localIcon(
                        (string) (
                            $warning['icon']
                            ?? 'alert-triangle'
                        ),
                        $key
                    );
                ?>

                <div
                    class="scout-report-item scout-report-value-item scout-report-warning-item"
                >
                    <div class="scout-report-value-content">
                        <span>
                            <?= $e(
                                (string) (
                                    $warning['label']
                                    ?? 'Warning'
                                )
                            ) ?>
                        </span>

                        <strong>Warning</strong>
                    </div>

                    <?= llama_icon(
                        $warningIcon,
                        [
                            'class' => 'scout-report-value-icon',
                        ]
                    ) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

<script
    src="/js/place-report-warnings.js"
    defer
></script>
<?php

foreach (
    $placeReportSections
    as $sectionKey => $section
):
    $fields =
        array_filter(
            $placeReportFields,
            static function (
                array $field,
                string|int $fieldKey
            ) use (
                $sectionKey,
                $placeReportReadMode,
                $placeReportApplicabilityInput,
                $publicFieldGroups
            ): bool {
                if ($placeReportReadMode === 'scout-report') {
                    return ($publicFieldGroups[(string) $fieldKey] ?? null) === $sectionKey
                        && empty($field['hide_public']);
                }

                if (
                    (string) (
                        $field['display_section']
                        ?? $field['section']
                        ?? ''
                    ) !== $sectionKey
                ) {
                    return false;
                }

                if (
                    $placeReportReadMode !== 'moderation'
                    && !empty($field['hide_public'])
                ) {
                    return false;
                }

                return true;
            },
            ARRAY_FILTER_USE_BOTH
        );

    if (!$fields) {
        continue;
    }

    $applicableFields =
        $fields;

    /*
     * Issue #5 reconciliation mode:
     *
     * Do not remove unanswered questions from the Scout Report. The public
     * read-only view now exposes the same question inventory and sequence as
     * the editable flows so answer-state mismatches can be found directly.
     */
    $answeredFields =
        $applicableFields;

    $reportedFields =
        $answeredFields;

    if ($placeReportReadMode === 'scout-report') {
        foreach (
            $applicableFields
            as $fieldKey => $field
        ) {
            if (
                in_array(
                    (string) $fieldKey,
                    $campsiteSwitchableFields,
                    true
                )
            ) {
                $reportedFields[$fieldKey] =
                    $field;
            }
        }
    }

    $missingApplicableCount =
        max(
            0,
            count($applicableFields)
            - count($answeredFields)
        );

    $switchableApplicableCount = 0;
    $switchableAnsweredCount = 0;

    if ($placeReportReadMode === 'scout-report') {
        foreach (
            $applicableFields
            as $fieldKey => $field
        ) {
            if (
                !in_array(
                    (string) $fieldKey,
                    $campsiteSwitchableFields,
                    true
                )
            ) {
                continue;
            }

            $switchableApplicableCount++;

            if (
                llama_place_report_answer_state(
                    $placeReportData,
                    (string) $fieldKey
                ) !== 'unanswered'
            ) {
                $switchableAnsweredCount++;
            }
        }
    }

    $fixedMissingCount =
        max(
            0,
            $missingApplicableCount
            - (
                $switchableApplicableCount
                - $switchableAnsweredCount
            )
        );

    $sectionHasReportedData =
        count($reportedFields) > 0;
?>
    <?php
    $sectionIcon =
        $localIcon(
            (string) (
                $section['icon']
                ?? ''
            ),
            'section:'
            . (string) $sectionKey
        );
    ?>

    <section
        class="scout-report-section"
        <?php if (
            $placeReportReadMode === 'scout-report'
            && $switchableApplicableCount > 0
        ): ?>
            data-report-target-section="1"
            data-report-section-label="<?= $e(
                (string) $section['label']
            ) ?>"
            data-report-applicable-count="<?= count(
                $applicableFields
            ) ?>"
            data-report-fixed-missing="<?= $fixedMissingCount ?>"
        <?php endif; ?>
    >
        <h3>
            <?= llama_icon($sectionIcon) ?>

            <?= $e(
                (string) $section['label']
            ) ?>
        </h3>

        <?php if ($placeReportReadMode === 'scout-report' && $sectionKey === 'basic'): ?>
            <?php
            $officialContact = [
                'Official Address' => ['value' => trim((string) ($publicFacility['address'] ?? '')), 'icon' => 'map-pin'],
                'Phone Number' => ['value' => trim((string) ($publicFacility['phone'] ?? '')), 'icon' => 'phone'],
                'Email Address' => ['value' => trim((string) ($publicFacility['email'] ?? '')), 'icon' => 'mail'],
            ];
            ?>
            <div class="scout-report-grid">
                <?php foreach ($officialContact as $contactLabel => $contact): ?>
                    <?php if ($contact['value'] === '') continue; ?>
                    <div class="scout-report-item scout-report-value-item">
                        <div class="scout-report-value-content">
                            <span><?= $e($contactLabel) ?></span>
                            <strong>
                                <?php if ($contactLabel === 'Phone Number'): ?>
                                    <a href="tel:<?= $e(preg_replace('/[^0-9+]/', '', $contact['value'])) ?>"><?= $e($contact['value']) ?></a>
                                <?php elseif ($contactLabel === 'Email Address'): ?>
                                    <a href="mailto:<?= $e($contact['value']) ?>"><?= $e($contact['value']) ?></a>
                                <?php else: ?>
                                    <?= $e($contact['value']) ?>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <?= llama_icon($contact['icon'], ['class' => 'scout-report-value-icon']) ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach ((array) ($publicFacility['links'] ?? []) as $officialLink): ?>
                    <?php
                    $linkUrl = trim((string) ($officialLink['url'] ?? ''));
                    if (!preg_match('~^https?://~i', $linkUrl)) continue;
                    $linkTitle = trim((string) ($officialLink['title'] ?? ''));
                    ?>
                    <div class="scout-report-item scout-report-value-item">
                        <div class="scout-report-value-content">
                            <span>Official Links</span>
                            <strong><a href="<?= $e($linkUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $e($linkTitle !== '' ? $linkTitle : $linkUrl) ?></a></strong>
                        </div>
                        <?= llama_icon('external-link', ['class' => 'scout-report-value-icon']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (
            $placeReportReadMode === 'scout-report'
            && !$sectionHasReportedData
        ): ?>
            <?php if ($switchableApplicableCount < 1): ?>
                <?php
                $renderContributionPrompt(
                    (string) $section['label'],
                    count($applicableFields),
                    true
                );
                ?>
            <?php endif; ?>

        <?php elseif ($sectionKey === 'sensory'): ?>
            <?php foreach (
                [
                    'Daytime',
                    'Nighttime',
                    'Specific sensory conditions',
                ]
                as $subsection
            ): ?>
                <?php
                $subsectionFields =
                    array_filter(
                        $reportedFields,
                        static fn (
                            array $field
                        ): bool =>
                            (string) (
                                $field['subsection']
                                ?? ''
                            )
                            === $subsection
                    );
                ?>

                <?php if (!$subsectionFields): ?>
                    <?php continue; ?>
                <?php endif; ?>

                <div class="scout-report-subsection">
                    <h4><?= $e($subsection) ?></h4>

                    <div class="scout-report-grid">
                        <?php foreach (
                            $subsectionFields
                            as $key => $field
                        ): ?>
                            <?php
                            $renderValue(
                                (string) $key,
                                $field
                            );
                            ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php
            $sensorySummaryFields =
                array_filter(
                    $reportedFields,
                    static fn (
                        array $field
                    ): bool =>
                        trim(
                            (string) (
                                $field['subsection']
                                ?? ''
                            )
                        ) === ''
                );
            ?>

            <?php if ($sensorySummaryFields): ?>
                <div class="scout-report-grid">
                    <?php foreach (
                        $sensorySummaryFields
                        as $key => $field
                    ): ?>
                        <?php
                        $renderValue(
                            (string) $key,
                            $field
                        );
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (
                $placeReportReadMode === 'scout-report'
                && $missingApplicableCount > 0
                && $switchableApplicableCount < 1
            ): ?>
                <?php
                $renderContributionPrompt(
                    (string) $section['label'],
                    $missingApplicableCount,
                    false
                );
                ?>
            <?php endif; ?>

        <?php elseif ($sectionKey === 'scout_notes'): ?>
            <ul class="scout-report-notes-list">
                <?php foreach (
                    $reportedFields
                    as $key => $field
                ): ?>
                    <?php
                    $note =
                        llama_place_report_display_value(
                            $placeReportData,
                            (string) $key
                        );

                    /* Issue #6: unanswered notes remain visible for review. */
                    if ($note === null || trim((string) $note) === '') {
                        $note = 'Not provided';
                    }
                    ?>

                    <li>
                        <?= $e(
                            $note
                            ?? 'Not provided'
                        ) ?>
                    </li>
                <?php endforeach; ?>
            </ul>

        <?php elseif ($placeReportReadMode === 'scout-report'): ?>
            <div class="scout-report-grid">
                <?php foreach ($publicReportGroups[$sectionKey][1] as $key): ?>
                    <?php if (isset($reportedFields[$key])): ?>
                        <?php $renderValue($key, $reportedFields[$key]); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <?php
            /* Canonical layout-group order within the existing report section.
             * Keep the surrounding section and Site-switching hooks intact.
             */
            $layoutGroups = llama_place_report_layout_groups();
            $fieldBuckets = [];
            foreach ($reportedFields as $fieldKey => $fieldDefinition) {
                $layoutSection = (string) ($fieldDefinition['layout_section'] ?? '');
                $layoutGroup = (string) ($fieldDefinition['layout_group'] ?? '');
                $bucketKey = $layoutSection . ':' . $layoutGroup;
                $fieldBuckets[$bucketKey][$fieldKey] = $fieldDefinition;
            }
            $orderedBuckets = [];
            foreach ($layoutGroups as $layoutSection => $groups) {
                foreach ($groups as $groupKey => $groupLabel) {
                    $bucketKey = $layoutSection . ':' . $groupKey;
                    if (isset($fieldBuckets[$bucketKey])) {
                        $orderedBuckets[$bucketKey] = [
                            'label' => $groupLabel,
                            'fields' => $fieldBuckets[$bucketKey],
                        ];
                        unset($fieldBuckets[$bucketKey]);
                    }
                }
            }
            foreach ($fieldBuckets as $bucketKey => $remainingFields) {
                $orderedBuckets[$bucketKey] = [
                    'label' => 'Legacy Review',
                    'fields' => $remainingFields,
                ];
            }
            ?>
            <?php foreach ($orderedBuckets as $bucket): ?>
                <div class="scout-report-subsection">
                    <h4><?= $e($bucket['label']) ?></h4>
                    <div class="scout-report-grid">
                        <?php foreach ($bucket['fields'] as $key => $field): ?>
                            <?php $renderValue((string) $key, $field); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (
                $placeReportReadMode === 'scout-report'
                && $missingApplicableCount > 0
                && $switchableApplicableCount < 1
            ): ?>
                <?php
                $renderContributionPrompt(
                    (string) $section['label'],
                    $missingApplicableCount,
                    false
                );
                ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (
            $placeReportReadMode === 'scout-report'
            && $switchableApplicableCount > 0
            && $missingApplicableCount > 0
        ): ?>
            <?php
            $targetPromptEmpty =
                count($applicableFields) > 0
                && $missingApplicableCount >= count($applicableFields);

            $targetPromptMessage =
                $targetPromptEmpty
                    ? (
                        'No one has reported '
                        . strtolower(
                            (string) $section['label']
                        )
                        . ' information here yet.'
                    )
                    : (
                        $missingApplicableCount
                        . ' applicable detail'
                        . (
                            $missingApplicableCount === 1
                                ? ''
                                : 's'
                        )
                        . ' in this section '
                        . (
                            $missingApplicableCount === 1
                                ? 'has'
                                : 'have'
                        )
                        . ' not been reported yet.'
                    );
            ?>

            <div
                class="scout-report-contribution-prompt<?= $targetPromptEmpty ? ' is-empty' : ' is-partial' ?>"
                data-target-report-prompt
            >
                <div class="scout-report-contribution-copy">
                    <strong>
                        <?= $targetPromptEmpty
                            ? 'Be the first to add this'
                            : 'Know something we are missing?' ?>
                    </strong>

                    <p><?= $e($targetPromptMessage) ?></p>
                </div>

                <?php if ($placeReportUpdateUrl !== ''): ?>
                    <a
                        href="<?= $e($placeReportUpdateUrl) ?>"
                        class="scout-report-contribution-action"
                    >
                        <?= llama_icon('edit') ?>
                        Update this Place
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
