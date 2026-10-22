<?php

declare(strict_types=1);

/**
 * Seasons, rules, fees, booking, and nearby-service fields.
 *
 * This file is registration only. Applicability, ownership, terminology
 * overrides, and pricing precedence are applied elsewhere.
 */
function llama_place_report_add_rules_service_fields(
    callable $add,
    array $distance
): void {
    $add('best_months', 'Best months', 'rules', 'select', 'rules.best_months', [
        'allow_unknown' => true,
        'options' => [
            'year-round' => 'Year-round',
            'spring' => 'Spring',
            'summer' => 'Summer',
            'fall' => 'Fall',
            'winter' => 'Winter',
            'spring-summer' => 'Spring through Summer',
            'summer-fall' => 'Summer through Fall',
            'late-spring-fall' => 'Late Spring through Fall',
            'snow-free-months' => 'Generally snow-free months',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('winter_access', 'Winter access?', 'rules', 'tri', 'rules.winter_access', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('seasonal_closure', 'Seasonal closure?', 'rules', 'tri', 'details.seasonal_closure', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);

    foreach ([
        'snow_risk' => ['Snow risk', 'Low', 'High'],
        'mud_season_risk' => ['Mud-season risk', 'Low', 'High'],
        'monsoon_risk' => ['Monsoon risk', 'Low', 'High'],
        'hurricane_risk' => ['Hurricane risk', 'Low', 'High'],
        'heat_season_risk' => ['Heat-season risk', 'Low', 'High'],
    ] as $key => [$label, $low, $high]) {
        $add($key, $label, 'rules', 'rating', 'rules.' . $key, [
            'allow_unknown' => true,
            'low' => $low,
            'high' => $high,
            'points_categories' => ['seasons_rules_services'],
        ]);
    }

    $add('seasonal_access_note', 'Seasonal access notes', 'rules', 'textarea', 'rules.seasonal_access_note', [
        'wide' => true,
        'rows' => 3,
        'placeholder' => 'Gated in winter, impassable after heavy rain, snow usually lingers until June, etc.',
        'points_categories' => ['seasons_rules_services'],
        'counts_toward_completion' => false,
    ]);

    foreach ([
        'overnight_camping_allowed' => 'Overnight camping allowed?',
        'dispersed_camping_allowed' => 'Dispersed camping allowed?',
        'collecting_firewood' => 'Collecting firewood?',
        'campfire_allowed' => 'Campfire allowed?',
        'drone_use_legal' => 'Drone use allowed?',
        'target_shooting_allowed' => 'Target shooting allowed?',
    ] as $key => $label) {
        $add($key, $label, 'rules', 'permission', 'rules.' . $key, [
            'allow_unknown' => true,
            'points_categories' => ['seasons_rules_services'],
        ]);
    }

    foreach ([
        'designated_sites_only' => 'Designated sites only?',
        'pets_allowed' => 'Pets allowed?',
        'dogs_required_to_be_leashed' => 'Dogs require leash?',
        'food_storage_required' => 'Food storage required?',
        'existing_sites_encouraged' => 'Existing sites encouraged?',
        'pack_it_in_pack_it_out' => 'Pack it in / pack it out?',
        'residential_use_prohibited' => 'Residential use prohibited?',
    ] as $key => $label) {
        $add($key, $label, 'rules', 'tri', 'rules.' . $key, [
            'allow_unknown' => true,
            'points_categories' => ['seasons_rules_services'],
        ]);
    }

    $add('generator_restrictions', 'Generator restrictions?', 'rules', 'tri', 'rules.generator_restrictions', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('generator_quiet_hours', 'Generator quiet hours?', 'rules', 'tri', 'rules.generator_quiet_hours', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('generator_quiet_hours_begin', 'Generator quiet hours begin', 'rules', 'select', 'rules.generator_quiet_hours_begin', [
        'allow_unknown' => true,
        'options' => [
            'sunset' => 'Sunset',
            '19:00' => '7 PM',
            '20:00' => '8 PM',
            '21:00' => '9 PM',
            '22:00' => '10 PM',
            '23:00' => '11 PM',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('generator_quiet_hours_end', 'Generator quiet hours end', 'rules', 'select', 'rules.generator_quiet_hours_end', [
        'allow_unknown' => true,
        'options' => [
            'sunrise' => 'Sunrise',
            '06:00' => '6 AM',
            '07:00' => '7 AM',
            '08:00' => '8 AM',
            '09:00' => '9 AM',
            '10:00' => '10 AM',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('stay_limit_days', 'Stay limit', 'rules', 'select', 'rules.stay_limit_days', [
        'allow_unknown' => true,
        'options' => [
            '1' => '1 day',
            '3' => '3 days',
            '5' => '5 days',
            '7' => '7 days',
            '10' => '10 days',
            '14' => '14 days',
            '16' => '16 days',
            '21' => '21 days',
            '28' => '28 days',
            '30' => '30 days',
            'permit-limit' => 'Permit Limit',
            'varies-by-season' => 'Varies by season',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('reservation_required', 'Reservation required?', 'rules', 'tri', 'rules.reservation_required', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('reservation_url', 'Reservation URL', 'rules', 'url', 'rules.reservation_url', [
        'wide' => true,
        'placeholder' => 'https://...',
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('reservation_fee', 'Reservation / booking fee', 'rules', 'number', 'rules.reservation_fee', [
        'step' => '.01',
        'min' => '0',
        'max' => '100000',
        'placeholder' => '0.00',
        'format' => 'currency',
        'points_categories' => ['seasons_rules_services'],
        'help' =>
            'A separate reservation or booking charge added to the stay. Do not enter the nightly camping price here.',
    ]);

    $add('membership_required', 'Membership required?', 'rules', 'tri', 'rules.membership_required', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('membership_url', 'Membership URL', 'rules', 'url', 'rules.membership_url', [
        'wide' => true,
        'placeholder' => 'https://...',
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('membership_fee', 'Membership fee', 'rules', 'number', 'rules.membership_fee', [
        'step' => '.01',
        'min' => '0',
        'max' => '100000',
        'placeholder' => '0.00',
        'format' => 'currency',
        'points_categories' => ['seasons_rules_services'],
        'help' =>
            'The cost of the required membership itself. Do not enter a nightly camping rate, reservation fee, entrance fee, or parking fee here.',
    ]);

    $add('check_in_required', 'Check-in required?', 'rules', 'tri', 'rules.check_in_required', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('check_in_begins', 'Check-in begins', 'rules', 'select', 'rules.check_in_begins', [
        'allow_unknown' => true,
        'options' => [
            'anytime' => 'Anytime',
            '10:00' => '10 AM',
            '11:00' => '11 AM',
            '12:00' => '12 PM',
            '13:00' => '1 PM',
            '14:00' => '2 PM',
            '15:00' => '3 PM',
            '16:00' => '4 PM',
            '17:00' => '5 PM',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('check_out_required', 'Check-out required?', 'rules', 'tri', 'rules.check_out_required', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('checkout_ends', 'Check-out ends', 'rules', 'select', 'rules.checkout_ends', [
        'allow_unknown' => true,
        'options' => [
            'anytime' => 'Anytime',
            '06:00' => '6 AM',
            '07:00' => '7 AM',
            '08:00' => '8 AM',
            '09:00' => '9 AM',
            '10:00' => '10 AM',
            '11:00' => '11 AM',
            '12:00' => '12 PM',
            '13:00' => '1 PM',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('fee', 'Base camping fee', 'rules', 'number', 'rules.fee', [
        'step' => '.01',
        'min' => '0',
        'max' => '100000',
        'placeholder' => '0.00',
        'format' => 'currency',
        'points_categories' => ['seasons_rules_services'],
        'help' =>
            'The normal base overnight camping price for the Place when one simple amount accurately describes it. Use 0 for free camping. Detailed Area, Site, weekday, weekend, holiday, or seasonal nightly rates belong in the mapped rate system and take precedence for those mapped targets.',
    ]);

    $add('entrance_facility_fee', 'Entrance / day-use / facility fee', 'rules', 'number', 'rules.entrance_facility_fee', [
        'step' => '.01',
        'min' => '0',
        'max' => '100000',
        'placeholder' => '0.00',
        'format' => 'currency',
        'points_categories' => ['seasons_rules_services'],
        'help' =>
            'A separate entrance, day-use, recreation, or facility charge. Do not combine this with the overnight camping price or parking charge.',
    ]);

    $add('parking_fee', 'Parking fee / pass', 'rules', 'number', 'rules.parking_fee', [
        'step' => '.01',
        'min' => '0',
        'max' => '100000',
        'placeholder' => '0.00',
        'format' => 'currency',
        'points_categories' => ['seasons_rules_services'],
        'help' =>
            'A separate charge required to park a vehicle or hold a parking pass. Do not use this for the nightly camping price unless the documented charge is specifically a parking charge.',
    ]);

    $add('current_fire_restrictions_url', 'Current fire restrictions URL', 'rules', 'url', 'rules.current_fire_restrictions_url', [
        'wide' => true,
        'placeholder' => 'https://...',
        'points_categories' => ['seasons_rules_services'],
    ]);

    foreach ([
        'nearest_town' => 'Nearest town',
        'nearest_fuel' => 'Nearest fuel',
        'nearest_ev_charging' => 'Nearest EV charging',
        'nearest_alcohol_sales' => 'Nearest alcohol sales',
        'nearest_propane' => 'Nearest propane',
        'nearest_grocery' => 'Nearest grocery',
        'nearest_water' => 'Nearest potable water',
        'nearest_toilet' => 'Nearest public toilet',
        'nearest_hospital' => 'Nearest hospital / emergency care',
    ] as $key => $label) {
        $add($key, $label, 'rules', 'select', 'rules.' . $key, [
            'allow_unknown' => true,
            'options' => $distance,
            'points_categories' => ['seasons_rules_services'],
        ]);
    }
}
