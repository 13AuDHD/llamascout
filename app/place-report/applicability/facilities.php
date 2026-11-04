<?php

declare(strict_types=1);

/**
 * Shared-facility applicability.
 *
 * V3 owner review makes every approved Facilities & Amenities question
 * independently answerable. Place type must not hide an approved amenity.
 *
 * Activities remains context-sensitive because it is a Location/source-context
 * question rather than part of the reviewed Facilities & Amenities group.
 */
function llama_place_report_apply_facility_profiles(
    array &$fields
): void {
    $types = llama_place_report_applicability_types();

    llama_place_report_set_applicable(
        $fields,
        ['activities'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => array_values(
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
