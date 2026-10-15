<?php

declare(strict_types=1);

/**
 * Overnight-use conditional behavior.
 *
 * The overall overnight permission is the parent rule. Once overnight use is
 * permitted, permitted-with-permit, or conditional, the form can ask which
 * overnight modes and operational requirements apply.
 *
 * Stored answers remain intact when hidden. The runtime applicability engine
 * prevents a hidden parent answer from activating its children.
 */
function llama_place_report_apply_overnight_applicability(
    array &$fields
): void {
    $overnightPositive =
        llama_place_report_overnight_positive_rule();

    /*
     * Overnight modes answer separate questions. None is inferred from the
     * Place type or from another mode.
     */
    llama_place_report_set_applicable(
        $fields,
        ['tent_camping_allowed'],
        [
            $overnightPositive,
            [
                'field' => 'type',
                'operator' => 'in',
                'value' =>
                    llama_place_report_applicability_types()['tent_overnight'],
            ],
        ]
    );

    llama_place_report_set_applicable(
        $fields,
        [
            'rv_camping_allowed',
            'car_truck_camping',
            'overnight_restrictions',
        ],
        [
            $overnightPositive,
        ]
    );

    /*
     * Overnight-only rules, nighttime observations, and experience.
     */
    llama_place_report_set_applicable(
        $fields,
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
        [
            $overnightPositive,
        ]
    );

    /*
     * Overnight logistics and costs. Entrance/day-use/facility and parking
     * fees stay independent because they can apply without an overnight stay.
     */
    llama_place_report_set_applicable(
        $fields,
        [
            'reservation_required',
            'membership_required',
            'check_in_required',
            'check_out_required',
            'fee',
        ],
        [
            $overnightPositive,
        ]
    );

    llama_place_report_set_applicable(
        $fields,
        [
            'reservation_url',
            'reservation_fee',
        ],
        [
            $overnightPositive,
            [
                'field' => 'reservation_required',
                'operator' => 'equals',
                'value' => '1',
            ],
        ]
    );

    llama_place_report_set_applicable(
        $fields,
        [
            'membership_url',
            'membership_fee',
        ],
        [
            $overnightPositive,
            [
                'field' => 'membership_required',
                'operator' => 'equals',
                'value' => '1',
            ],
        ]
    );

    llama_place_report_set_applicable(
        $fields,
        ['check_in_begins'],
        [
            $overnightPositive,
            [
                'field' => 'check_in_required',
                'operator' => 'equals',
                'value' => '1',
            ],
        ]
    );

    llama_place_report_set_applicable(
        $fields,
        ['checkout_ends'],
        [
            $overnightPositive,
            [
                'field' => 'check_out_required',
                'operator' => 'equals',
                'value' => '1',
            ],
        ]
    );

    /*
     * The combined tent/RV permit URL is specific to those two modes, not to
     * generic overnight permission.
     */
    llama_place_report_set_applicable(
        $fields,
        ['overnight_vehicle_tent_permit_url'],
        [[
            'operator' => 'any',
            'rules' => [
                [
                    'field' => 'tent_camping_allowed',
                    'operator' => 'equals',
                    'value' => '2',
                ],
                [
                    'field' => 'rv_camping_allowed',
                    'operator' => 'equals',
                    'value' => '2',
                ],
            ],
        ]]
    );
}
