<?php

declare(strict_types=1);

/**
 * Shared-facility and recreation-context applicability.
 *
 * Generic facilities can exist at many kinds of Places, including rest areas,
 * travel centers, parking properties, campgrounds, and day-use facilities.
 * Only questions whose meaning is inherently campground/recreation-specific
 * are restricted here.
 */
function llama_place_report_apply_facility_profiles(
    array &$fields
): void {
    $types =
        llama_place_report_applicability_types();

    $campgroundFacilityTypes = [
        'developed-campground',
        'camping-area',
        'rv-park-resort',
    ];

    $recreationFacilityTypes =
        array_values(
            array_unique(
                array_merge(
                    $types['camping'],
                    $types['natural_day_use'],
                    [
                        'rest-area',
                    ]
                )
            )
        );

    /*
     * "Campground host" and "camp store" have campground-specific meaning.
     * A staffed travel center or retail store is not a campground host/store.
     */
    llama_place_report_set_applicable(
        $fields,
        [
            'amenity_campground_host',
            'amenity_camp_store',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $campgroundFacilityTypes,
        ]]
    );

    /*
     * These recreation facilities should not turn an ordinary commercial
     * parking/travel-center report into a campground/day-use inventory.
     */
    llama_place_report_set_applicable(
        $fields,
        [
            'amenity_amphitheater',
            'amenity_playground',
            'amenity_picnic_shelter',
            'amenity_fishing_pier',
            'amenity_lake_access',
            'amenity_river_access',
            'amenity_trailhead',
            'amenity_trailhead_parking',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $recreationFacilityTypes,
        ]]
    );

    /*
     * Dishwashing stations are a camping/picnic facility rather than a
     * generic property amenity.
     */
    llama_place_report_set_applicable(
        $fields,
        ['amenity_dishwashing_station'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' =>
                array_values(
                    array_unique(
                        array_merge(
                            $types['camping'],
                            [
                                'day-use',
                                'rest-area',
                            ]
                        )
                    )
                ),
        ]]
    );

    /*
     * Activities describe recreational use of the Place. Do not ask this at
     * ordinary commercial/civic parking, travel centers, truck stops, or
     * street parking.
     */
    llama_place_report_set_applicable(
        $fields,
        ['activities'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' =>
                array_values(
                    array_unique(
                        array_merge(
                            $types['camping'],
                            $types['natural_day_use']
                        )
                    )
                ),
        ]]
    );
}
