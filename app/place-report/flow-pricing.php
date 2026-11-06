<?php

declare(strict_types=1);

/**
 * Canonical pricing semantics for Scout Report fields.
 *
 * The versioned Place card is authoritative. Old scalar and mapped prices
 * are read only by its legacy adapter until the Place saves a version 2 card.
 */
function llama_place_report_pricing_semantics(): array
{
    return [
        'place_rate_cards_json' => [
            'kind' => 'camping_rate_schedule',
            'scope' => 'place',
            'precedence' => 'authoritative',
            'mapped_override' => false,
        ],
        'fee' => [
            'kind' => 'base_overnight',
            'unit' => 'night',
            'scope' => 'place',
            'precedence' => 'legacy_adapter_only',
            'mapped_override' => true,
        ],

        'reservation_fee' => [
            'kind' => 'booking_surcharge',
            'unit' => 'reservation',
            'scope' => 'report_target',
            'precedence' => 'additive',
            'mapped_override' => false,
        ],

        'membership_fee' => [
            'kind' => 'membership_cost',
            'unit' => 'membership',
            'scope' => 'place_area',
            'precedence' => 'separate',
            'mapped_override' => false,
        ],

        'entrance_facility_fee' => [
            'kind' => 'entrance_facility',
            'unit' => 'entry_or_use',
            'scope' => 'place_area',
            'precedence' => 'separate',
            'mapped_override' => false,
        ],

        'parking_fee' => [
            'kind' => 'parking',
            'unit' => 'parking_or_pass',
            'scope' => 'place_area',
            'precedence' => 'separate',
            'mapped_override' => false,
        ],
    ];
}

/** Public prices resolve exclusively through app/place-pricing.php. */
function llama_place_report_pricing_precedence(): array
{
    return [
        'individual_site_selector',
        'preserved_area_selector',
        'most_specific_site_attributes',
        'place_wide_row',
    ];
}
