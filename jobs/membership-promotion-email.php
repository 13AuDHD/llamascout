<?php

declare(strict_types=1);

/*
 * Intended for cPanel Cron / CLI only.
 *
 * Each run sends at most 25 messages for each due campaign
 * email type. Repeated runs continue where the last one left
 * off because membership_promotion_deliveries is idempotent.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/database.php';
require_once dirname(__DIR__) . '/app/error-logging.php';
require_once dirname(__DIR__) . '/app/promotion-campaigns.php';

$db = db();
$jobs = llama_due_promotion_email_jobs($db);

foreach ($jobs as $promotion) {
    $promotionId = (int) ($promotion['id'] ?? 0);

    if (
        !empty($promotion['email_enabled'])
        && !empty($promotion['email_send_at'])
        && empty($promotion['email_sent_at'])
        && strtotime((string) $promotion['email_send_at']) <= time()
    ) {
        $stats = llama_promotion_send_batch(
            $db,
            $promotion,
            'announcement',
            25
        );

        llama_promotion_finish_delivery_if_complete(
            $db,
            $promotionId,
            'announcement'
        );

        fwrite(
            STDOUT,
            sprintf(
                "[promotion %d announcement] attempted=%d sent=%d failed=%d remaining=%d\n",
                $promotionId,
                $stats['attempted'],
                $stats['sent'],
                $stats['failed'],
                $stats['remaining']
            )
        );
    }

    if (
        !empty($promotion['reminder_enabled'])
        && !empty($promotion['reminder_send_at'])
        && empty($promotion['reminder_sent_at'])
        && strtotime((string) $promotion['reminder_send_at']) <= time()
    ) {
        $stats = llama_promotion_send_batch(
            $db,
            $promotion,
            'reminder',
            25
        );

        llama_promotion_finish_delivery_if_complete(
            $db,
            $promotionId,
            'reminder'
        );

        fwrite(
            STDOUT,
            sprintf(
                "[promotion %d reminder] attempted=%d sent=%d failed=%d remaining=%d\n",
                $promotionId,
                $stats['attempted'],
                $stats['sent'],
                $stats['failed'],
                $stats['remaining']
            )
        );
    }
}
