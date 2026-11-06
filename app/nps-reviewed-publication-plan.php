<?php

declare(strict_types=1);

/**
 * Field-level preflight for NPS/RIDB reviewed decisions.
 *
 * A source review is NOT a published fact. Some fields target canonical
 * questions, others require rate-card or location validation. This service
 * never writes to a Place, report, map, or source database.
 */
function llama_nps_review_publication_destination(string $field): array
{
    return match ($field) {
        'site_total' => [
            'name' => 'Scout Report: Number of campsites',
            'question_key' => 'campsite_count',
            'route' => 'canonical_question',
            'note' => 'Compare with imported Site records and any approved manual count before publishing.',
        ],
        'reservable_sites' => [
            'name' => 'Reservable Site inventory',
            'question_key' => null,
            'route' => 'site_inventory',
            'note' => 'This is not the total physical Site count. Compare with the RIDB reservation inventory.',
        ],
        'regular_nightly_fee_usd' => [
            'name' => 'Place rate cards',
            'question_key' => 'place_rate_cards_json',
            'route' => 'rate_cards',
            'note' => 'Confirm season and Site category before adding a nightly rate. Do not overwrite existing cards.',
        ],
        'location' => [
            'name' => 'Place GPS / map editor',
            'question_key' => null,
            'route' => 'coordinates',
            'note' => 'Confirm point versus campground boundary. Do not alter stored coordinates automatically.',
        ],
        'place_rv_max_feet' => [
            'name' => 'Place RV length question',
            'question_key' => 'place_rv_max_feet',
            'route' => 'canonical_question',
            'note' => 'Check the existing published and manual maximum before updating.',
        ],
        default => [
            'name' => 'Unmapped official field',
            'question_key' => null,
            'route' => 'unmapped',
            'note' => 'Define a canonical question or destination before any publication.',
        ],
    };
}

/**
 * A single readiness state controls whether a decision may enter a future
 * write workflow. The current version intentionally never approves a write.
 */
function llama_nps_review_publication_preflight(array $review): array
{
    $dest = llama_nps_review_publication_destination(
        (string) ($review['field_key'] ?? '')
    );
    $state = (string) ($review['status'] ?? '');
    $value = trim((string) ($review['value'] ?? ''));
    $reason = '';
    if ($state === 'stale_sources') {
        $reason = 'Source records changed. Re-review NPS and RIDB evidence.';
    } elseif ($state === 'waiting_for_place') {
        $reason = 'No published matching RIDB Place. Import/link the existing facility first.';
    } elseif ($state === 'unresolved' || $value === '') {
        $reason = 'No current verified value. Resolve the source disagreement first.';
    } elseif ($state !== 'reviewed_not_published'
        || (int) ($review['place_id'] ?? 0) < 1) {
        $reason = 'Identity or publication state is not confirmed.';
    } elseif ($dest['route'] === 'unmapped') {
        $reason = 'No canonical publication destination is defined.';
    } else {
        $reason = 'Ready for destination-specific comparison; not yet published.';
    }

    return $dest + [
        'state' => $state,
        'value' => $value,
        'review_usable' => $reason === 'Ready for destination-specific comparison; not yet published.',
        'publication_allowed' => false,
        'reason' => $reason,
    ];
}

/** Aggregate queues without implying a reviewed candidate is already live. */
function llama_nps_review_publication_summary(array $reviews): array
{
    $summary = [
        'total' => 0,
        'ready_to_compare' => 0,
        'stale' => 0,
        'waiting_for_place' => 0,
        'unresolved' => 0,
        'other_blocked' => 0,
    ];
    foreach ($reviews as $review) {
        $summary['total']++;
        $plan = llama_nps_review_publication_preflight((array) $review);
        if ($plan['review_usable']) {
            $summary['ready_to_compare']++;
        } elseif ($plan['state'] === 'stale_sources') {
            $summary['stale']++;
        } elseif ($plan['state'] === 'waiting_for_place') {
            $summary['waiting_for_place']++;
        } elseif ($plan['state'] === 'unresolved') {
            $summary['unresolved']++;
        } else {
            $summary['other_blocked']++;
        }
    }
    return $summary;
}
