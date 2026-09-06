<?php

declare(strict_types=1);

/* =========================================================
   LLAMA SCOUT STRIPE WEBHOOK EVENT CONTROL

   Production webhook processing uses a database-backed claim.

   A Stripe event may be delivered more than once. An event that
   previously failed is retryable. A processing event whose worker
   disappeared becomes retryable after the lease expires.
   ========================================================= */

const LLAMA_STRIPE_WEBHOOK_MAX_BYTES = 1048576;
const LLAMA_STRIPE_WEBHOOK_LEASE_SECONDS = 600;


function llama_stripe_webhook_payload(): string
{
    $contentLength =
        (int) (
            $_SERVER['CONTENT_LENGTH']
            ?? 0
        );

    if (
        $contentLength > 0
        && $contentLength
            > LLAMA_STRIPE_WEBHOOK_MAX_BYTES
    ) {
        throw new LengthException(
            'Stripe webhook payload is too large.'
        );
    }

    $payload =
        file_get_contents(
            'php://input'
        );

    if (
        !is_string($payload)
        || $payload === ''
    ) {
        throw new UnexpectedValueException(
            'Stripe webhook payload is missing.'
        );
    }

    if (
        strlen($payload)
        > LLAMA_STRIPE_WEBHOOK_MAX_BYTES
    ) {
        throw new LengthException(
            'Stripe webhook payload is too large.'
        );
    }

    return $payload;
}


function llama_stripe_webhook_signature(): string
{
    $signature =
        trim(
            (string) (
                $_SERVER[
                    'HTTP_STRIPE_SIGNATURE'
                ]
                ?? ''
            )
        );

    if ($signature === '') {
        throw new UnexpectedValueException(
            'Stripe webhook signature is missing.'
        );
    }

    /*
     * Stripe signatures are tiny. This avoids accepting an
     * arbitrarily large header into signature verification.
     */
    if (strlen($signature) > 16384) {
        throw new UnexpectedValueException(
            'Stripe webhook signature is invalid.'
        );
    }

    return $signature;
}


function llama_stripe_webhook_event_identity(
    object $event
): array {
    $eventId =
        trim(
            (string) (
                $event->id
                ?? ''
            )
        );

    $eventType =
        trim(
            (string) (
                $event->type
                ?? ''
            )
        );

    if (
        $eventId === ''
        || $eventType === ''
    ) {
        throw new UnexpectedValueException(
            'Stripe webhook event is incomplete.'
        );
    }

    if (
        strlen($eventId) > 255
        || strlen($eventType) > 120
    ) {
        throw new UnexpectedValueException(
            'Stripe webhook event identity is invalid.'
        );
    }

    return [
        'id' => $eventId,
        'type' => $eventType,
    ];
}


function llama_membership_stripe_event_claim(
    PDO $db,
    string $eventId,
    string $eventType
): bool {
    try {
        $insert =
            $db->prepare(
                'INSERT INTO membership_stripe_events
                 (
                    stripe_event_id,
                    event_type,
                    status,
                    attempt_count,
                    received_at,
                    last_attempt_at
                 )
                 VALUES
                 (
                    ?,
                    ?,
                    "processing",
                    1,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                 )'
            );

        $insert->execute([
            $eventId,
            $eventType,
        ]);

        return true;
    } catch (PDOException $exception) {
        if (
            (string) $exception->getCode()
            !== '23000'
        ) {
            throw $exception;
        }
    }

    /*
     * A failed event may retry immediately.
     *
     * A processing event may retry only after the lease expires.
     * Updating last_attempt_at atomically acquires a new lease, so
     * two retry deliveries cannot both become workers.
     */
    $retry =
        $db->prepare(
            'UPDATE membership_stripe_events
             SET
                status = "processing",
                attempt_count = attempt_count + 1,
                error_message = NULL,
                processed_at = NULL,
                last_attempt_at = UTC_TIMESTAMP()
             WHERE stripe_event_id = ?
               AND (
                    status = "failed"
                    OR (
                        status = "processing"
                        AND last_attempt_at
                            <= DATE_SUB(
                                UTC_TIMESTAMP(),
                                INTERVAL 10 MINUTE
                            )
                    )
               )'
        );

    $retry->execute([
        $eventId,
    ]);

    return
        $retry->rowCount()
        === 1;
}


function llama_membership_stripe_event_finish(
    PDO $db,
    string $eventId,
    string $status,
    ?string $error = null
): void {
    if (
        !in_array(
            $status,
            [
                'processed',
                'failed',
                'ignored',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid membership Stripe event status.'
        );
    }

    $error =
        $error !== null
            ? mb_substr(
                trim($error),
                0,
                4000
            )
            : null;

    $update =
        $db->prepare(
            'UPDATE membership_stripe_events
             SET
                status = ?,
                error_message = ?,
                processed_at = UTC_TIMESTAMP()
             WHERE stripe_event_id = ?'
        );

    $update->execute([
        $status,
        $error !== ''
            ? $error
            : null,
        $eventId,
    ]);
}


function llama_shop_stripe_event_claim(
    PDO $db,
    string $eventId,
    string $eventType,
    int $orderId
): bool {
    try {
        $insert =
            $db->prepare(
                'INSERT INTO shop_stripe_events
                 (
                    stripe_event_id,
                    event_type,
                    order_id,
                    status,
                    attempt_count,
                    received_at,
                    last_attempt_at
                 )
                 VALUES
                 (
                    ?,
                    ?,
                    ?,
                    "processing",
                    1,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                 )'
            );

        $insert->execute([
            $eventId,
            $eventType,
            $orderId > 0
                ? $orderId
                : null,
        ]);

        return true;
    } catch (PDOException $exception) {
        if (
            (string) $exception->getCode()
            !== '23000'
        ) {
            throw $exception;
        }
    }

    $retry =
        $db->prepare(
            'UPDATE shop_stripe_events
             SET
                status = "processing",
                attempt_count = attempt_count + 1,
                error_message = NULL,
                processed_at = NULL,
                last_attempt_at = UTC_TIMESTAMP()
             WHERE stripe_event_id = ?
               AND (
                    status = "failed"
                    OR (
                        status = "processing"
                        AND last_attempt_at
                            <= DATE_SUB(
                                UTC_TIMESTAMP(),
                                INTERVAL 10 MINUTE
                            )
                    )
               )'
        );

    $retry->execute([
        $eventId,
    ]);

    return
        $retry->rowCount()
        === 1;
}


function llama_shop_stripe_event_finish(
    PDO $db,
    string $eventId,
    string $status,
    ?string $error = null
): void {
    if (
        !in_array(
            $status,
            [
                'processed',
                'failed',
                'ignored',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid Shop Stripe event status.'
        );
    }

    $error =
        $error !== null
            ? mb_substr(
                trim($error),
                0,
                4000
            )
            : null;

    $update =
        $db->prepare(
            'UPDATE shop_stripe_events
             SET
                status = ?,
                error_message = ?,
                processed_at = UTC_TIMESTAMP()
             WHERE stripe_event_id = ?'
        );

    $update->execute([
        $status,
        $error !== ''
            ? $error
            : null,
        $eventId,
    ]);
}


function llama_stripe_subscription_is_llama_scout(
    PDO $db,
    object $subscription
): bool {
    $metadataUserId =
        (int) (
            $subscription
                ->metadata
                ->llama_user_id
            ?? 0
        );

    if ($metadataUserId > 0) {
        return true;
    }

    $subscriptionId =
        trim(
            (string) (
                $subscription->id
                ?? ''
            )
        );

    $customer =
        $subscription->customer
        ?? null;

    $customerId =
        is_string($customer)
            ? trim($customer)
            : (
                is_object($customer)
                    ? trim(
                        (string) (
                            $customer->id
                            ?? ''
                        )
                    )
                    : ''
            );

    if (
        $subscriptionId === ''
        && $customerId === ''
    ) {
        return false;
    }

    $where = [];
    $params = [];

    if ($subscriptionId !== '') {
        $where[] =
            'stripe_subscription_id = ?';

        $params[] =
            $subscriptionId;
    }

    if ($customerId !== '') {
        $where[] =
            'stripe_customer_id = ?';

        $params[] =
            $customerId;
    }

    if (!$where) {
        return false;
    }

    $lookup =
        $db->prepare(
            'SELECT id
             FROM users
             WHERE ' .
             implode(
                 ' OR ',
                 $where
             ) .
             '
             LIMIT 1'
        );

    $lookup->execute(
        $params
    );

    return
        (bool)
        $lookup->fetchColumn();
}
