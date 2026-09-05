<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT
   MEMBERSHIP PROMOTION EVENT TRACKING
   ========================================================= */


function llama_membership_promotion_event(
    PDO $db,
    int $promotionId,
    string $eventType,
    ?int $userId = null,
    ?string $interval = null,
    ?string $checkoutSessionId = null,
    ?string $subscriptionId = null,
    ?int $amountCents = null,
    array $metadata = []
): void {
    if ($promotionId < 1) {
        return;
    }

    $eventType = trim($eventType);

    if ($eventType === '') {
        return;
    }

    $checkoutSessionId = trim((string) $checkoutSessionId);
    $subscriptionId = trim((string) $subscriptionId);
    $interval = trim((string) $interval);

    /*
     * Stripe may retry webhooks. A completed checkout should
     * therefore be recorded only once for the same Session.
     */
    if ($checkoutSessionId !== '') {
        $duplicate = $db->prepare(
            'SELECT id
             FROM membership_promotion_events
             WHERE promotion_id = ?
               AND event_type = ?
               AND stripe_checkout_session_id = ?
             LIMIT 1'
        );

        $duplicate->execute([
            $promotionId,
            $eventType,
            $checkoutSessionId,
        ]);

        if ($duplicate->fetchColumn()) {
            return;
        }
    }

    $metadataJson = $metadata
        ? json_encode(
            $metadata,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )
        : null;

    if ($metadataJson === false) {
        $metadataJson = null;
    }

    $stmt = $db->prepare(
        'INSERT INTO membership_promotion_events
         (
            promotion_id,
            user_id,
            event_type,
            membership_interval,
            stripe_checkout_session_id,
            stripe_subscription_id,
            amount_cents,
            metadata_json
         )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $promotionId,
        $userId && $userId > 0 ? $userId : null,
        $eventType,
        $interval !== '' ? $interval : null,
        $checkoutSessionId !== '' ? $checkoutSessionId : null,
        $subscriptionId !== '' ? $subscriptionId : null,
        $amountCents !== null ? max(0, $amountCents) : null,
        $metadataJson,
    ]);
}


function llama_membership_promotion_stats(
    PDO $db,
    int $promotionId
): array {
    $stats = [
        'checkout_started' => 0,
        'membership_purchased' => 0,
        'revenue_cents' => 0,
    ];

    if ($promotionId < 1) {
        return $stats;
    }

    $stmt = $db->prepare(
        'SELECT
            event_type,
            COUNT(*) AS event_count,
            COALESCE(SUM(amount_cents), 0) AS amount_total
         FROM membership_promotion_events
         WHERE promotion_id = ?
         GROUP BY event_type'
    );

    $stmt->execute([$promotionId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = (string) ($row['event_type'] ?? '');
        $count = (int) ($row['event_count'] ?? 0);
        $amount = (int) ($row['amount_total'] ?? 0);

        if ($type === 'checkout_started') {
            $stats['checkout_started'] = $count;
        }

        if ($type === 'membership_purchased') {
            $stats['membership_purchased'] = $count;
            $stats['revenue_cents'] = $amount;
        }
    }

    return $stats;
}
