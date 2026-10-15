<?php

declare(strict_types=1);

/**
 * Flexible Place-level pricing field definition.
 *
 * Ownership is intentionally not defined here. app/place-report/flow-scopes.php
 * is the single canonical source for Place / Area / Site ownership.
 *
 * Rate cards are serialized as JSON in place_rules.rate_cards_json. The
 * shared form progressively enhances this textarea into repeatable cards;
 * without JavaScript the JSON value remains editable instead of disappearing.
 */
function llama_place_report_add_pricing_fields(callable $add): void
{
    $add(
        'place_rate_cards_json',
        'Rate cards',
        'rules',
        'textarea',
        'rules.rate_cards_json',
        [
            'wide' => true,
            'rows' => 4,
            'maxlength' => 60000,
            'placeholder' =>
                'Rate card data is managed by the pricing controls.',
            'help' =>
                'Use one rate card for each distinct price. Multiple cards can describe site categories, individual sites, mixed rates, and seasonal pricing without forcing a single campground-wide fee.',
            'allow_unknown' => false,
            'counts_toward_completion' => false,
            'points_categories' => ['seasons_rules_services'],
        ]
    );
}
