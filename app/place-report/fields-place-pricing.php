<?php

declare(strict_types=1);

/**
 * Flexible Place-level pricing field definition.
 *
 * Place owns the complete schedule. Individual Site selectors are part of
 * that schedule, never a separate editable price source.
 *
 * Rate cards are serialized as JSON in place_rules.rate_cards_json. The
 * shared form progressively enhances this textarea into repeatable cards;
 * without JavaScript the JSON value remains editable instead of disappearing.
 */
function llama_place_report_add_pricing_fields(callable $add): void
{
    $add(
        'place_rate_cards_json',
        'Campsite rates',
        'rules',
        'textarea',
        'rules.rate_cards_json',
        [
            'wide' => true,
            'scope_group' => 'place',
            'report_group' => 'pricing',
            'report_visibility' => 'community',
            'rows' => 4,
            'maxlength' => 60000,
            'placeholder' =>
                'Rate card data is managed by the pricing controls.',
            'help' =>
                'Set base, weekend and holiday prices for matching Sites. Seasonal schedules and individual Site exceptions use this same editor.',
            'allow_unknown' => false,
            'counts_toward_completion' => false,
            'points_categories' => ['seasons_rules_services'],
        ]
    );
}
