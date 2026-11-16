<?php

declare(strict_types=1);

/**
 * Use the public map's precedence when highlighting Admin Place thumbnails.
 *
 * This consumes the same closure, pricing and map-partner fields as
 * js/map.js renderCard(). It does not infer "open" from an Active DB status.
 * Partner-brand borders follow the map's use_branded_cards setting.
 */
function llama_admin_place_image_status(array $place): array
{
    $closed = filter_var($place['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $featured = strtolower(trim((string) ($place['status'] ?? ''))) === 'featured'
        || filter_var($place['is_featured'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $fee = strtolower(trim((string) ($place['camping_fee_status'] ?? 'unknown')));
    $paid = in_array($fee, ['paid', 'mixed'], true);

    $partner = is_array($place['map_partner'] ?? null)
        && (int) ($place['map_partner']['id'] ?? 0) > 0
        ? $place['map_partner'] : null;
    $branded = $partner !== null && !empty($partner['use_branded_cards']);

    // Map rendering applies these in the same effective order: verified
    // Closed > branded partner > Featured > Paid > neutral.
    $state = $closed ? 'closed'
        : ($branded ? 'partner'
        : ($featured ? 'featured'
        : ($paid ? 'paid' : 'neutral')));

    $label = match ($state) {
        'closed' => 'Closed',
        'partner' => ($partner['relationship'] ?? '') === 'affiliate' ? 'Affiliate' : 'Partner',
        'featured' => 'Featured',
        'paid' => 'Paid',
        default => '',
    };

    $statuses = [];
    if ($closed) $statuses[] = 'Verified closed';
    if ($featured) $statuses[] = 'Featured';
    if ($paid) $statuses[] = $fee === 'mixed' ? 'Mixed free/paid camping' : 'Paid camping';
    if ($partner !== null && ($partner['relationship'] ?? '') === 'affiliate') {
        $statuses[] = 'Affiliate';
    } elseif ($partner !== null && $branded) {
        $statuses[] = 'Partner branded';
    }
    if (!$statuses) $statuses[] = 'No verified closure or fee highlight';

    $primary = strtoupper(trim((string) ($partner['primary_color'] ?? '')));
    $partnerId = $branded ? (int) ($partner['id'] ?? 0) : 0;
    $partnerColor = $partnerId > 0 && preg_match('/^#[0-9A-F]{6}$/', $primary)
        ? $primary : '';

    return [
        'state' => $state,
        'badge' => $label,
        'title' => implode(' · ', $statuses),
        'partner_id' => $partnerId,
        'partner_color' => $partnerColor,
    ];
}
