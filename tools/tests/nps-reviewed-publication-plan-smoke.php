<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/nps-reviewed-publication-plan.php';

$assert = static function (bool $okay, string $message): void {
    if (!$okay) {
        throw new RuntimeException($message);
    }
};

$expected = [
    'site_total' => ['canonical_question', 'campsite_count'],
    'reservable_sites' => ['site_inventory', null],
    'regular_nightly_fee_usd' => ['rate_cards', 'place_rate_cards_json'],
    'location' => ['coordinates', null],
    'place_rv_max_feet' => ['canonical_question', 'place_rv_max_feet'],
];
foreach ($expected as $field => [$route, $key]) {
    $plan = llama_nps_review_publication_destination($field);
    $assert($plan['route'] === $route && $plan['question_key'] === $key,
        'Review decision routed to the wrong canonical destination: ' . $field);
}
$assert(llama_nps_review_publication_destination('unrecognized')['route'] === 'unmapped',
    'Unknown official fields must never automatically publish');

$base = [
    'field_key' => 'site_total',
    'status' => 'reviewed_not_published',
    'value' => '88',
    'place_id' => 14,
];
$ready = llama_nps_review_publication_preflight($base);
$assert($ready['review_usable'] === true
    && $ready['publication_allowed'] === false,
    'Reviewed field may enter comparison but must not be auto-published');

foreach ([
    ['status' => 'stale_sources', 'place_id' => 14, 'value' => '88'],
    ['status' => 'waiting_for_place', 'place_id' => null, 'value' => '88'],
    ['status' => 'unresolved', 'place_id' => 14, 'value' => ''],
    ['status' => 'reviewed_not_published', 'place_id' => null, 'value' => '88'],
] as $change) {
    $plan = llama_nps_review_publication_preflight(array_merge($base, $change));
    $assert($plan['review_usable'] === false
        && $plan['publication_allowed'] === false,
        'Blocked or stale review unexpectedly passed publication preflight');
}
$summary = llama_nps_review_publication_summary([
    $base,
    array_merge($base, ['status' => 'stale_sources']),
    array_merge($base, ['status' => 'waiting_for_place', 'place_id' => null]),
    array_merge($base, ['status' => 'unresolved', 'value' => '']),
]);
$assert($summary['total'] === 4
    && $summary['ready_to_compare'] === 1
    && $summary['stale'] === 1
    && $summary['waiting_for_place'] === 1
    && $summary['unresolved'] === 1,
    'NPS publication preflight does not correctly classify decisions');

$admin = file_get_contents(dirname(__DIR__, 2) . '/admin/nps-place-fill.php');
$service = file_get_contents(dirname(__DIR__, 2) . '/app/nps-reviewed-publication-plan.php');
$assert(is_string($admin) && is_string($service)
    && str_contains($admin, 'llama_nps_review_publication_preflight($review)')
    && str_contains($admin, 'llama_nps_review_publication_summary('),
    'Admin review table does not display canonical destination and blockers');
$assert(!str_contains($service, 'INSERT INTO')
    && !str_contains($service, 'UPDATE places')
    && !str_contains($service, 'DELETE FROM'),
    'Publication readiness helper must remain read-only');
echo "PASS: Reviewed NPS field decisions map to canonical destinations, block stale identities, and never auto-publish\n";
