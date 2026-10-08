<?php

declare(strict_types=1);

/* Completion applicability rules for canonical Place Report fields. */

function llama_place_report_apply_applicability(
    array &$f
): void {
    /*
     * =========================================================
     * COMPLETION APPLICABILITY RULES
     * =========================================================
     *
     * These rules affect only whether a question belongs in the
     * current completion denominator. They do not delete stored
     * answers and they do not control Scout contribution points.
     */

    $setApplicable =
        static function (
            array &$fields,
            array $fieldKeys,
            array $rules
        ): void {
            foreach ($fieldKeys as $fieldKey) {
                if (!isset($fields[$fieldKey])) {
                    continue;
                }

                $fields[$fieldKey]['applicable_if'] =
                    $rules;
            }
        };

    $appendApplicable =
        static function (
            array &$fields,
            array $fieldKeys,
            array $rules
        ): void {
            foreach ($fieldKeys as $fieldKey) {
                if (!isset($fields[$fieldKey])) {
                    continue;
                }

                $existing =
                    array_values(
                        (array) (
                            $fields[$fieldKey]['applicable_if']
                            ?? []
                        )
                    );

                $fields[$fieldKey]['applicable_if'] =
                    array_values(
                        array_merge(
                            $existing,
                            $rules
                        )
                    );
            }
        };


    $campingPlaceTypes = [
        'dispersed-camping',
        'developed-campground',
        'camping-area',
        'rv-park-resort',
        'membership-host',
        'private-property',
        'fairgrounds',
    ];

    $dispersedRelevantPlaceTypes = [
        'dispersed-camping',
        'camping-area',
        'trailhead',
        'day-use',
        'scenic-overlook',
        'vehicle-pulloff',
    ];

    $campfireRelevantPlaceTypes = [
        'dispersed-camping',
        'developed-campground',
        'camping-area',
        'rv-park-resort',
        'trailhead',
        'day-use',
        'membership-host',
        'private-property',
        'fairgrounds',
    ];

    $naturalUsePlaceTypes = [
        'dispersed-camping',
        'developed-campground',
        'camping-area',
        'trailhead',
        'day-use',
        'scenic-overlook',
        'vehicle-pulloff',
    ];

    /*
     * Rough-road questions are useful for outdoor/camping access but are
     * noise on ordinary commercial and civic parking Places such as a
     * Love's Travel Stop, Walmart, hospital lot, or street parking.
     */
    $roughAccessPlaceTypes =
        array_values(
            array_unique(
                array_merge(
                    $campingPlaceTypes,
                    $naturalUsePlaceTypes
                )
            )
        );

    $setApplicable(
        $f,
        [
            'ground_condition',
            'sedan_accessible',
            'high_clearance_recommended',
            'four_wheel_drive_recommended',
            'water_crossings',
            'downed_tree_risk',
            'seasonal_closure',
            'site_access_difficulty',
            'road_overall_difficulty',
            'road_stress',
            'rocks',
            'washboards',
            'potholes',
            'mud_risk',
            'steep_grades',
            'drop_off_exposure',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $roughAccessPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        ['tent_camping_suitable'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $campingPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        [
            'max_people',
            'overhead_clearance_feet',
            'parking_length_feet',
            'parking_grade',
            'site_length_feet',
            'site_width_feet',
            'tent_pad',
            'double_driveway',
            'hike_in_distance_feet',
            'site_rating',
            'condition_rating',
            'location_rating',
            'capacity_size_rating',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $campingPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        [
            'tent_pad_length_feet',
            'tent_pad_width_feet',
        ],
        [[
            'field' => 'tent_pad',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['max_trailer_length_feet'],
        [[
            'field' => 'trailer_suitable',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['max_rv_length_feet'],
        [[
            'field' => 'rv_suitable',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['accessible_toilet'],
        [[
            'field' => 'amenity_toilets',
            'operator' => 'truthy',
        ]]
    );

    $setApplicable(
        $f,
        ['accessible_picnic_table'],
        [[
            'field' => 'amenity_picnic_table',
            'operator' => 'truthy',
        ]]
    );

    $setApplicable(
        $f,
        ['dogs_required_to_be_leashed'],
        [[
            'field' => 'pets_allowed',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['dispersed_camping_allowed'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $dispersedRelevantPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        [
            'collecting_firewood',
            'target_shooting_allowed',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $naturalUsePlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        ['campfire_allowed'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $campfireRelevantPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        ['current_fire_restrictions_url'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $campfireRelevantPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        [
            'amenity_fire_ring',
            'amenity_bear_box',
            'pack_it_in_pack_it_out',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => array_values(
                array_unique(
                    array_merge(
                        $campingPlaceTypes,
                        $naturalUsePlaceTypes
                    )
                )
            ),
        ]]
    );

    $setApplicable(
        $f,
        [
            'designated_sites_only',
            'food_storage_required',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $campingPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        ['existing_sites_encouraged'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => [
                'dispersed-camping',
                'camping-area',
            ],
        ]]
    );

    $setApplicable(
        $f,
        ['check_in_begins'],
        [[
            'field' => 'check_in_required',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['checkout_ends'],
        [[
            'field' => 'check_out_required',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        [
            'reservation_url',
            'reservation_fee',
        ],
        [[
            'field' => 'reservation_required',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        [
            'membership_url',
            'membership_fee',
        ],
        [[
            'field' => 'membership_required',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $intrinsicallyOvernightPlaceTypes = [
        'dispersed-camping',
        'developed-campground',
        'camping-area',
        'rv-park-resort',
        'membership-host',
    ];

    $setApplicable(
        $f,
        [
            'stay_limit_days',
            'generator_restrictions',
            'residential_use_prohibited',
            'nighttime_noise',
            'nighttime_traffic',
            'nighttime_crowds',
            'nighttime_privacy',
            'nighttime_light_pollution',
            'nighttime_sensory_comfort',
            'nighttime_social_interaction',
            'experience_night_sky',
            'experience_stargazing',
            'experience_quiet_evening',
            'experience_extended_stay_comfort',
        ],
        [[
            'operator' => 'any',
            'rules' => [
                [
                    'field' => 'type',
                    'operator' => 'in',
                    'value' => $intrinsicallyOvernightPlaceTypes,
                ],
                [
                    'field' => 'overnight_camping_allowed',
                    'operator' => 'in',
                    'value' => ['1', '2'],
                ],
            ],
        ]]
    );


    $setApplicable(
        $f,
        ['generator_quiet_hours'],
        [[
            'field' => 'generator_restrictions',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        [
            'generator_quiet_hours_begin',
            'generator_quiet_hours_end',
        ],
        [[
            'field' => 'generator_quiet_hours',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['connectivity_starlink_note'],
        [[
            'field' => 'connectivity_starlink_tested',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['warning_possible_downed_trees'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $campingPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        ['seasonal_access_note'],
        [[
            'field' => 'seasonal_closure',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );


    /*
     * =========================================================
     * DEVELOPED CAMPGROUND PROFILE
     * =========================================================
     *
     * Formal campsite/property questions belong on campground-style
     * Places, not dispersed camping or ordinary parking Places.
     */
    /*
     * These questions describe camping / overnight spaces and their actual
     * on-site utility hookups, not generic property-wide amenities. Hosted
     * stays, private camping property and fairgrounds may have them too.
     */
    $developedCampgroundPlaceTypes = [
        'developed-campground',
        'camping-area',
        'rv-park-resort',
        'membership-host',
        'private-property',
        'fairgrounds',
    ];

    $setApplicable(
        $f,
        [
            'campsite_count',
            'site_number',
            'site_hookups_available',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $developedCampgroundPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        [
            'hookup_electric',
            'hookup_water',
            'hookup_sewer',
        ],
        [
            [
                'field' => 'type',
                'operator' => 'in',
                'value' => $developedCampgroundPlaceTypes,
            ],
            [
                'field' => 'site_hookups_available',
                'operator' => 'equals',
                'value' => '1',
            ],
        ]
    );

    $setApplicable(
        $f,
        ['hookup_electric_service'],
        [
            [
                'field' => 'type',
                'operator' => 'in',
                'value' => $developedCampgroundPlaceTypes,
            ],
            [
                'field' => 'site_hookups_available',
                'operator' => 'equals',
                'value' => '1',
            ],
            [
                'field' => 'hookup_electric',
                'operator' => 'equals',
                'value' => '1',
            ],
        ]
    );

    /*
     * These either restate "developed campground" or describe a
     * dispersed/public-land use pattern rather than a formal campground.
     */
    $appendApplicable(
        $f,
        [
            'overnight_camping_allowed',
            'designated_sites_only',
            'target_shooting_allowed',
            'residential_use_prohibited',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'developed-campground',
        ]]
    );

    /*
     * =========================================================
     * RV PARK / RESORT PROFILE
     * =========================================================
     * Overnight use and individually designated RV spaces are implied
     * by this Place type. Do not assume that every park offers hookups,
     * accepts tents, allows long-term residents, or is safe from natural
     * hazards: those answers still need to be collected.
     *
     * Camping Area deliberately gets NO new hard exclusions in this
     * pass. That type can describe anything from an undeveloped area
     * with unnumbered spots to a fee-based managed recreation site.
     */
    $appendApplicable(
        $f,
        [
            'overnight_camping_allowed',
            'designated_sites_only',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'rv-park-resort',
        ]]
    );

    /*
     * =========================================================
     * HOSTED STAYS, PRIVATE PROPERTY & FAIRGROUNDS
     * =========================================================
     * Avoid assuming any of these properties are free, members-only,
     * hook-up equipped, fully developed, or exempt from natural hazards.
     * Campgrounds and event grounds may contain primitive or improved
     * areas, and hosted properties may have just one overnight space.
     * Keep the reservation, permission, membership, fees, seasonal,
     * sensory, surface, accessibility and emergency questions.
     *
     * Region / ranger district identifies public-land management units;
     * it is not a meaningful required answer for these property types.
     * Fairgrounds are whole venues rather than a single overnight
     * campsite, so a simple 'vehicle capacity' count would mislead;
     * the separate number of camping/overnight spaces remains available.
     */
    $hostedPrivateFairgroundTypes = [
        'membership-host',
        'private-property',
        'fairgrounds',
    ];

    $appendApplicable(
        $f,
        ['region'],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $hostedPrivateFairgroundTypes,
        ]]
    );

    $appendApplicable(
        $f,
        ['vehicle_capacity'],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'fairgrounds',
        ]]
    );

    /*
     * =========================================================
     * DISPERSED CAMPING PROFILE
     * =========================================================
     *
     * Be conservative here. Managed dispersed camping can still
     * require reservations, permits, check-in, camping/day-use fees,
     * designated sites, food storage, generator limits, and even
     * centralized amenities. Hide only questions that are truly
     * redundant with the Place type itself.
     */
    $appendApplicable(
        $f,
        ['dispersed_camping_allowed'],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'dispersed-camping',
        ]]
    );

    /*
     * =========================================================
     * TRAVEL CENTER / TRUCK STOP PROFILE
     * =========================================================
     *
     * A commercial travel center should not read like a public-land
     * campsite inspection. Keep developed-property, overnight,
     * sensory, accessibility, vehicle-fit, connectivity, and
     * operational questions; suppress backcountry/public-land noise.
     */
    $travelCenterPlaceTypes = [
        'travel-center',
        'truck-stop',
    ];

    $travelCenterNotApplicable = [
        /*
         * Location metadata that only makes sense for managed
         * recreation land.
         */
        'region',

        /*
         * Large commercial lots are not campsite-capacity questions.
         * Parking geometry and vehicle fit remain available.
         */
        'vehicle_capacity',
        'tree_cover',

        /*
         * Ordinary paved-property access does not need a road/trail
         * condition inventory.
         */
        'road_surface',
        'road_width',

        /*
         * Backcountry/natural-setting observations that add little
         * value to a commercial overnight stop.
         */
        'environment_water_nearby',
        'environment_wildlife',
        'environment_bugs',
        'critter_activity',
        'sensory_dust_from_traffic',
        'sensory_wildlife_noise',

        /*
         * Natural-terrain / emergency-access warnings that are not
         * useful for an ordinary developed travel-center property.
         * Keep Trip/Fall Hazard and Traffic Hazard because those can
         * absolutely matter in a parking lot.
         */
        'flash_flood_risk',
        'wildfire_risk',
        'cliff_exposure',
        'rockfall_risk',
        'wildlife_risk',
        'emergency_access',
        'warning_possible_downed_trees',
        'warning_passing_vehicle_dust',
        'warning_motorized_recreation_traffic',
        'warning_blind_turn_traffic_nearby',
        'road_exposure',

        /*
         * Travel centers are normally year-round developed
         * businesses. Temporary storm closures belong to live status,
         * not a Scout's seasonal campsite profile.
         */
        'best_months',
        'winter_access',
        'hurricane_risk',
        'snow_risk',
        'mud_season_risk',
        'monsoon_risk',
        'seasonal_access_note',

        /*
         * Public-land / campsite-rule questions.
         */
        'dispersed_camping_allowed',
        'collecting_firewood',
        'campfire_allowed',
        'drone_use_legal',
        'target_shooting_allowed',
        'designated_sites_only',
        'food_storage_required',
        'existing_sites_encouraged',
        'pack_it_in_pack_it_out',
        'current_fire_restrictions_url',

        /*
         * Fuel is intrinsic to the Travel Center / Truck Stop type.
         * Other nearby services remain useful.
         */
        'nearest_fuel',

        /*
         * Scenic/backcountry experience ratings are noise at a
         * commercial stop. Keep Quiet Evening, Overnight Comfort,
         * and Remote Work because those directly affect overnight use.
         */
        'experience_sunrise_view',
        'experience_sunset_view',
        'experience_overall_scenery',
        'experience_night_sky',
        'experience_stargazing',
        'experience_extended_stay_comfort',
        'experience_sensory_retreat',
    ];

    $appendApplicable(
        $f,
        $travelCenterNotApplicable,
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $travelCenterPlaceTypes,
        ]]
    );

    /*
     * =========================================================
     * PARKING AND ROADSIDE PROFILES
     * =========================================================
     * Differentiate commercial parking from rest areas, which can
     * have real picnic facilities, seasonal closures, hazards and
     * occasionally dump stations. Never infer that amenities are absent.
     * The site/road surface and RV/trailer fit questions stay available.
     */
    $commercialParkingPlaceTypes = [
        'retail-parking',
        'restaurant-parking',
        'casino-parking',
        'medical-office-parking',
        'hospital-parking',
        'church-parking',
        'other-parking',
        'public-parking',
        'street-parking',
    ];

    $roadsideParkingPlaceTypes = [
        'rest-area',
        'vehicle-pulloff',
    ];

    /* Parking lots and roadside stops are not numbered campgrounds. */
    $appendApplicable(
        $f,
        [
            'vehicle_capacity',
            'designated_sites_only',
            'existing_sites_encouraged',
            'dispersed_camping_allowed',
            'collecting_firewood',
            'target_shooting_allowed',
            'food_storage_required',
            'warning_possible_downed_trees',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => array_merge(
                $commercialParkingPlaceTypes,
                $roadsideParkingPlaceTypes
            ),
        ]]
    );

    /*
     * Commercial and civic parking is not a campsite or natural-use
     * inventory. Keep traffic, surfaces, hazards that affect vehicles,
     * overnight comfort, fees, restroom/accessibility, and services.
     */
    $appendApplicable(
        $f,
        [
            'region',
            'tree_cover',
            'environment_wildlife',
            'environment_bugs',
            'critter_activity',
            'sensory_wildlife_noise',
            'cliff_exposure',
            'rockfall_risk',
            'wildlife_risk',
            'warning_motorized_recreation_traffic',
            'warning_passing_vehicle_dust',
            'current_fire_restrictions_url',
            'campfire_allowed',
            'drone_use_legal',
            'experience_sensory_retreat',
            'experience_extended_stay_comfort',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $commercialParkingPlaceTypes,
        ]]
    );

    /*
     * Seasonal campground-style ratings are normally irrelevant to
     * an operating retail/business lot. A closure still belongs in
     * the Place status and Scout notes when exceptional.
     * Rest areas retain these questions: some close seasonally.
     */
    $appendApplicable(
        $f,
        [
            'best_months',
            'winter_access',
            'mud_season_risk',
            'snow_risk',
            'monsoon_risk',
            'hurricane_risk',
            'heat_season_risk',
            'seasonal_access_note',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $commercialParkingPlaceTypes,
        ]]
    );

    /* Street parking has no private internal driveway/turnaround. */
    $appendApplicable(
        $f,
        ['turnaround_space', 'pull_through', 'back_in'],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'street-parking',
        ]]
    );

    /*
     * =========================================================
     * NATURAL DAY-USE AND ROADSIDE PROFILES
     * =========================================================
     * Trailheads, overlooks, road pull-offs and day-use facilities can
     * legitimately permit overnight use, require reservations/fees,
     * have rough entrances, or contain developed amenities. Do not
     * assume overnight camping is forbidden or that facilities are
     * absent. This profile only removes questions describing the
     * geometry of an individual campsite rather than a public parking
     * location, and makes overnight-specific items conditional.
     */
    $naturalDayUsePlaceTypes = [
        'trailhead',
        'scenic-overlook',
        'vehicle-pulloff',
        'day-use',
    ];

    $appendApplicable(
        $f,
        [
            'vehicle_capacity',
            'pull_through',
            'back_in',
            'target_shooting_allowed',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $naturalDayUsePlaceTypes,
        ]]
    );

    /*
     * The road/pull-off surface, shade, views and access information
     * remain meaningful. Firewood collection is not a characteristic
     * of an overlook itself, though it can matter at a trailhead or
     * a managed day-use recreation area, so avoid broad exclusions.
     */
    $appendApplicable(
        $f,
        ['collecting_firewood'],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'scenic-overlook',
        ]]
    );

    /*
     * Explicit overnight permission (Yes or Permit) unlocks camping
     * fee and overnight comfort. Day-use/parking/entrance fees remain
     * visible because they may apply to visitors staying only by day.
     */
    $overnightUseForNaturalDayUse = [[
        'operator' => 'any',
        'rules' => [
            [
                'field' => 'type',
                'operator' => 'not_in',
                'value' => $naturalDayUsePlaceTypes,
            ],
            [
                'field' => 'overnight_camping_allowed',
                'operator' => 'in',
                'value' => ['1', '2'],
            ],
        ],
    ]];

    $appendApplicable(
        $f,
        [
            'fee',
            'experience_overnight_comfort',
        ],
        $overnightUseForNaturalDayUse
    );

}
