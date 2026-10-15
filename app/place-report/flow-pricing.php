<?php

declare(strict_types=1);

/**
 * Canonical pricing semantics for Scout Report fields.
 *
 * This does not introduce a new persistence model. It documents how existing
 * fee fields relate to the normalized mapped-rate tables.
 */
function llama_place_report_pricing_semantics(): array
{
    return [
        'fee' => [
            'kind' => 'base_overnight',
            'unit' => 'night',
            'scope' => 'place',
            'precedence' => 'fallback',
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

/**
 * Detailed mapped nightly rates have precedence for their mapped target.
 *
 * Site rates override matching Site pricing-class rates, and Site pricing
 * classes override matching parent Area rates. Unmatched rates continue to
 * inherit from the less-specific layer. If no mapped rate applies, consumers
 * may fall back to the Place-level base camping fee.
 */
function llama_place_report_pricing_precedence(): array
{
    return [
        'site_mapped_rate',
        'site_pricing_class_rate',
        'parent_area_mapped_rate',
        'place_base_fee',
    ];
}
