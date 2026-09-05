<?php

declare(strict_types=1);

/* =========================================================
   LLAMA SCOUT SHOP MAINTENANCE

   Porkbun does not provide cron on this hosting plan.

   This worker performs lightweight cleanup during ordinary
   authenticated activity. Stripe webhooks remain the primary
   source of truth. This is a safety net for stale pending Shop
   checkouts whose expiration webhook was delayed or missed.
   ========================================================= */


function shop_cleanup_expired_pending_orders(
    PDO $db,
    int $limit = 50
): array {
    $limit = max(1, min(500, $limit));

    $stats = [
        'orders_cancelled' => 0,
        'reservations_released' => 0,
    ];

    /*
     * Lock candidate rows so two requests cannot attempt to clean
     * the same expired checkout at the same time.
     */
    $db->beginTransaction();

    try {
        $stmt = $db->query(
            'SELECT id
             FROM shop_orders
             WHERE payment_status = "pending"
               AND order_status = "pending"
               AND checkout_expires_at IS NOT NULL
               AND checkout_expires_at <= UTC_TIMESTAMP()
             ORDER BY checkout_expires_at ASC, id ASC
             LIMIT ' . $limit . '
             FOR UPDATE'
        );

        $orderIds = array_map(
            'intval',
            $stmt
                ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])
                : []
        );

        if (!$orderIds) {
            $db->commit();

            return $stats;
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($orderIds), '?')
        );

        $cancel = $db->prepare(
            'UPDATE shop_orders
             SET
                order_status = "cancelled",
                payment_status = "cancelled",
                canceled_at = COALESCE(
                    canceled_at,
                    UTC_TIMESTAMP()
                ),
                updated_at = UTC_TIMESTAMP()
             WHERE id IN (' . $placeholders . ')
               AND payment_status = "pending"
               AND order_status = "pending"'
        );

        $cancel->execute($orderIds);

        $stats['orders_cancelled'] =
            $cancel->rowCount();

        $release = $db->prepare(
            'UPDATE shop_inventory_reservations
             SET
                status = "released",
                released_at = COALESCE(
                    released_at,
                    UTC_TIMESTAMP()
                )
             WHERE order_id IN (' . $placeholders . ')
               AND status = "active"'
        );

        $release->execute($orderIds);

        $stats['reservations_released'] =
            $release->rowCount();

        $db->commit();

        return $stats;

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}


function shop_maintenance_table_available(
    PDO $db
): bool {
    $stmt = $db->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'app_maintenance'"
    );

    return $stmt
        && (int) $stmt->fetchColumn() > 0;
}


function shop_cleanup_maintenance_is_due(
    PDO $db,
    int $intervalSeconds = 300
): bool {
    $intervalSeconds = max(60, $intervalSeconds);

    if (!shop_maintenance_table_available($db)) {
        /*
         * Cleanup is inexpensive and safe to run without the
         * throttle table if that shared table is unavailable.
         */
        return true;
    }

    $stmt = $db->prepare(
        'SELECT last_run_at
         FROM app_maintenance
         WHERE maintenance_key = ?
         LIMIT 1'
    );

    $stmt->execute([
        'shop_expired_checkouts',
    ]);

    $lastRun = $stmt->fetchColumn();

    if (!$lastRun) {
        return true;
    }

    $timestamp = strtotime((string) $lastRun);

    return $timestamp === false
        || (time() - $timestamp) >= $intervalSeconds;
}


function shop_mark_cleanup_maintenance_run(
    PDO $db
): void {
    if (!shop_maintenance_table_available($db)) {
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO app_maintenance
         (
            maintenance_key,
            last_run_at
         )
         VALUES (?, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            last_run_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        'shop_expired_checkouts',
    ]);
}


function shop_run_checkout_cleanup_maintenance(
    PDO $db,
    int $limit = 50,
    int $intervalSeconds = 300
): array {
    $summary = [
        'ran' => false,
        'orders_cancelled' => 0,
        'reservations_released' => 0,
    ];

    if (
        !shop_cleanup_maintenance_is_due(
            $db,
            $intervalSeconds
        )
    ) {
        return $summary;
    }

    $lockStmt = $db->query(
        "SELECT GET_LOCK(
            'llamascout_shop_checkout_cleanup',
            0
         )"
    );

    if (
        !$lockStmt
        || (int) $lockStmt->fetchColumn() !== 1
    ) {
        return $summary;
    }

    try {
        if (
            !shop_cleanup_maintenance_is_due(
                $db,
                $intervalSeconds
            )
        ) {
            return $summary;
        }

        $stats =
            shop_cleanup_expired_pending_orders(
                $db,
                $limit
            );

        shop_mark_cleanup_maintenance_run(
            $db
        );

        return [
            'ran' => true,
            'orders_cancelled' =>
                (int) $stats['orders_cancelled'],
            'reservations_released' =>
                (int) $stats['reservations_released'],
        ];

    } finally {
        $db->query(
            "SELECT RELEASE_LOCK(
                'llamascout_shop_checkout_cleanup'
             )"
        );
    }
}
