<?php

declare(strict_types=1);

/**
 * Canonical pricing semantics for Scout Report fields.
 *
 * Nightly Site prices belong to Admin > Campsite Pricing, not the
 * Scout Report. Only independent access, booking, membership and parking
 * fees remain here as non-nightly reporting facts.
 */
function llama_place_report_pricing_semantics(): array
{
    return [
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
        'campsite_pricing_site',
        'campsite_pricing_season',
        'place_seasonal_closure_override',
    ];
}
