<?php
 
declare(strict_types=1);

require_once __DIR__ . '/mail.php';


function llama_marketing_user_token(PDO $db, int $userId): string
{
    if ($userId < 1) {
        throw new InvalidArgumentException('A valid user is required.');
    }

    $stmt = $db->prepare(
        'SELECT marketing_unsubscribe_token
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);

    $token = trim((string) $stmt->fetchColumn());

    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        return $token;
    }

    $token = bin2hex(random_bytes(32));

    $update = $db->prepare(
        'UPDATE users
         SET marketing_unsubscribe_token = ?
         WHERE id = ?'
    );
    $update->execute([$token, $userId]);

    return $token;
}


function llama_marketing_unsubscribe_url(PDO $db, int $userId): string
{
    return 'https://account.llamascout.com/email-preferences.php?token='
        . rawurlencode(llama_marketing_user_token($db, $userId));
}


function llama_promotion_campaign_utc_timestamp(
    ?string $value
): ?int {
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    try {
        return (
            new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC')
            )
        )->getTimestamp();
    } catch (Throwable) {
        return null;
    }
}


function llama_promotion_money(int $cents): string
{
    return '$' . number_format(max(0, $cents) / 100, 2);
}


function llama_promotion_email_variable_names(): array
{
    return [
        'display_name',
        'username',
        'campaign_name',
        'campaign_label',
        'campaign_description',
        'promotion_url',
        'starts_at',
        'ends_at',
        'monthly_regular_price',
        'monthly_sale_price',
        'monthly_year_total',
        'monthly_discount',
        'monthly_offer',
        'annual_regular_price',
        'annual_sale_price',
        'annual_month_equivalent',
        'annual_discount',
        'annual_offer',
        'unsubscribe_url',
    ];
}


function llama_promotion_email_url(array $promotion): string
{
    $url = trim((string) ($promotion['landing_url'] ?? ''));

    if ($url === '') {
        return 'https://llamascout.com/membership.php';
    }

    if (str_starts_with($url, '/')) {
        return 'https://llamascout.com' . $url;
    }

    return $url;
}


function llama_promotion_email_datetime(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable(
            $value,
            new DateTimeZone('UTC')
        ))
            ->setTimezone(new DateTimeZone('America/Denver'))
            ->format('F j, Y g:i A T');
    } catch (Throwable) {
        return $value;
    }
}


function llama_promotion_email_plan_context(
    PDO $db,
    int $promotionId
): array {
    $stmt = $db->prepare(
        'SELECT
            p.id,
            p.interval_slug,
            COALESCE(pp.amount_cents, cp.amount_cents, p.base_price_cents) AS amount_cents,
            mpp.discount_type,
            mpp.discount_value
         FROM membership_plans p
         LEFT JOIN membership_plan_prices cp
           ON cp.plan_id = p.id
          AND cp.is_current = 1
         LEFT JOIN membership_promotion_plans mpp
           ON mpp.plan_id = p.id
          AND mpp.promotion_id = ?
         LEFT JOIN membership_plan_prices pp
           ON pp.id = mpp.plan_price_id
         WHERE p.interval_slug IN ("monthly", "annual")
           AND p.is_active = 1
         ORDER BY p.sort_order, p.id'
    );
    $stmt->execute([$promotionId]);

    $context = [
        'monthly_regular_price' => 'Not available',
        'monthly_sale_price' => 'Not included in this sale',
        'monthly_year_total' => '',
        'monthly_discount' => 'No sale',
        'monthly_offer' => 'Not included in this sale',
        'annual_regular_price' => 'Not available',
        'annual_sale_price' => 'Not included in this sale',
        'annual_month_equivalent' => '',
        'annual_discount' => 'No sale',
        'annual_offer' => 'Not included in this sale',
    ];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $interval = (string) ($row['interval_slug'] ?? '');

        if (!in_array($interval, ['monthly', 'annual'], true)) {
            continue;
        }

        $base = max(0, (int) ($row['amount_cents'] ?? 0));
        $context[$interval . '_regular_price'] =
            llama_promotion_money($base)
            . ($interval === 'monthly' ? ' / month' : ' / year');

        $discountType = trim((string) ($row['discount_type'] ?? ''));
        $discountValue = (int) ($row['discount_value'] ?? 0);

        if ($discountType === '' || $discountValue < 1) {
            continue;
        }

        if ($discountType === 'percent') {
            $percent = min(100, $discountValue);
            $discount = (int) round($base * ($percent / 100));
            $sale = max(0, $base - $discount);
            $discountLabel = $percent . '% off';
        } else {
            $sale = max(0, $base - $discountValue);
            $discountLabel = llama_promotion_money($discountValue) . ' off';
        }

        if ($interval === 'monthly') {
            $yearTotal = $sale * 12;
            $context['monthly_sale_price'] =
                llama_promotion_money($sale) . ' / month';
            $context['monthly_year_total'] =
                llama_promotion_money($yearTotal) . ' for 12 months';
            $context['monthly_discount'] = $discountLabel;
            $context['monthly_offer'] =
                llama_promotion_money($sale)
                . ' / month ('
                . llama_promotion_money($yearTotal)
                . ' for 12 months, '
                . $discountLabel
                . ')';
        } else {
            $monthlyEquivalent = (int) round($sale / 12);
            $context['annual_sale_price'] =
                llama_promotion_money($sale) . ' / year';
            $context['annual_month_equivalent'] =
                llama_promotion_money($monthlyEquivalent) . ' / month equivalent';
            $context['annual_discount'] = $discountLabel;
            $context['annual_offer'] =
                llama_promotion_money($sale)
                . ' / year ('
                . llama_promotion_money($monthlyEquivalent)
                . ' / month equivalent, '
                . $discountLabel
                . ')';
        }
    }

    return $context;
}


function llama_promotion_email_context(
    PDO $db,
    array $promotion,
    array $user = [],
    ?string $unsubscribeUrl = null
): array {
    $displayName = trim((string) ($user['display_name'] ?? ''));
    $username = trim((string) ($user['username'] ?? ''));

    if ($displayName === '') {
        $displayName = $username !== '' ? $username : 'Trail Tester';
    }

    if ($username === '') {
        $username = 'trailtester';
    }

    $promotionId = (int) ($promotion['id'] ?? 0);

    return array_merge(
        [
            'display_name' => $displayName,
            'username' => $username,
            'campaign_name' => (string) ($promotion['name'] ?? 'Membership promotion'),
            'campaign_label' => (string) (
                $promotion['public_label']
                ?? $promotion['name']
                ?? 'Membership promotion'
            ),
            'campaign_description' => (string) ($promotion['public_description'] ?? ''),
            'promotion_url' => llama_promotion_email_url($promotion),
            'starts_at' => llama_promotion_email_datetime($promotion['starts_at'] ?? null),
            'ends_at' => llama_promotion_email_datetime($promotion['ends_at'] ?? null),
            'unsubscribe_url' =>
                $unsubscribeUrl
                ?? 'https://account.llamascout.com/email-preferences.php?token=TEST',
        ],
        $promotionId > 0
            ? llama_promotion_email_plan_context($db, $promotionId)
            : []
    );
}


function llama_promotion_email_sample_context(
    PDO $db,
    array $promotion
): array {
    return llama_promotion_email_context(
        $db,
        $promotion,
        [
            'display_name' => 'Trail Tester',
            'username' => 'trailtester',
        ],
        'https://account.llamascout.com/email-preferences.php?token=TEST'
    );
}


function llama_promotion_email_recipients(
    PDO $db,
    int $promotionId,
    string $deliveryType,
    int $limit = 25
): array {
    $limit = max(1, min(100, $limit));

    /*
     * Free members only:
     * - verified email
     * - promotional email still enabled
     * - no current paid/complimentary membership access
     * - no active Scout access
     * - no active complimentary grant
     * - no successful delivery already recorded for this campaign/type
     * - failed delivery may retry after a one-hour cooldown
     *
     * This mirrors user_has_member_access() without making
     * multiple database calls for every queued recipient.
     */
    $sql =
        'SELECT
            u.id,
            u.email,
            u.username,
            u.display_name,
            u.marketing_unsubscribe_token
         FROM users u
         LEFT JOIN membership_promotion_deliveries d
           ON d.promotion_id = ?
          AND d.user_id = u.id
          AND d.delivery_type = ?
         WHERE u.email_verified_at IS NOT NULL
           AND u.marketing_email_enabled = 1

           AND NOT (
                LOWER(COALESCE(u.membership_status, \'none\'))
                    IN (\'active\', \'trialing\', \'past_due\', \'complimentary\')
                AND (
                    u.membership_ends_at IS NULL
                    OR u.membership_ends_at >= UTC_TIMESTAMP()
                )
           )

           AND NOT EXISTS (
                SELECT 1
                FROM scout_profiles sp
                INNER JOIN user_roles ur
                    ON ur.user_id = sp.user_id
                INNER JOIN roles r
                    ON r.id = ur.role_id
                WHERE sp.user_id = u.id
                  AND sp.status = \'active\'
                  AND (
                        sp.active_through IS NULL
                        OR sp.active_through >= UTC_TIMESTAMP()
                  )
                  AND r.slug IN (
                        \'scout\',
                        \'master-scout\',
                        \'master_scout\'
                  )
           )

           AND NOT EXISTS (
                SELECT 1
                FROM membership_grants mg
                WHERE mg.user_id = u.id
                  AND mg.grant_type = \'complimentary\'
                  AND mg.revoked_at IS NULL
                  AND mg.starts_at <= UTC_TIMESTAMP()
                  AND mg.ends_at >= UTC_TIMESTAMP()
           )

           AND (
                d.id IS NULL
                OR (
                    d.status = \'failed\'
                    AND d.failed_at IS NOT NULL
                    AND d.failed_at <= DATE_SUB(
                        UTC_TIMESTAMP(),
                        INTERVAL 1 HOUR
                    )
                )
           )
         ORDER BY u.id ASC
         LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute([$promotionId, $deliveryType]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function llama_promotion_record_delivery(
    PDO $db,
    int $promotionId,
    array $user,
    string $deliveryType,
    string $status,
    ?string $failureMessage = null
): void {
    $sentAt = $status === 'sent' ? gmdate('Y-m-d H:i:s') : null;
    $failedAt = $status === 'failed' ? gmdate('Y-m-d H:i:s') : null;

    $stmt = $db->prepare(
        'INSERT INTO membership_promotion_deliveries
         (
            promotion_id,
            user_id,
            email,
            delivery_type,
            status,
            sent_at,
            failed_at,
            failure_message
         )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            sent_at = VALUES(sent_at),
            failed_at = VALUES(failed_at),
            failure_message = VALUES(failure_message),
            updated_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        $promotionId,
        (int) $user['id'],
        (string) $user['email'],
        $deliveryType,
        $status,
        $sentAt,
        $failedAt,
        $failureMessage !== null ? mb_substr($failureMessage, 0, 500) : null,
    ]);
}


function llama_promotion_send_batch(
    PDO $db,
    array $promotion,
    string $deliveryType,
    int $limit = 25
): array {
    $promotionId = (int) ($promotion['id'] ?? 0);

    if ($promotionId < 1) {
        throw new InvalidArgumentException('Promotion is missing an ID.');
    }

    $deliveryType = $deliveryType === 'reminder'
        ? 'reminder'
        : 'announcement';

    if (
        !llama_promotion_campaign_email_enabled(
            $db,
            $deliveryType
        )
    ) {
        return [
            'attempted' => 0,
            'sent' => 0,
            'failed' => 0,
            'remaining' => 0,
        ];
    }

    $recipients = llama_promotion_email_recipients(
        $db,
        $promotionId,
        $deliveryType,
        $limit
    );

    $stats = [
        'attempted' => 0,
        'sent' => 0,
        'failed' => 0,
        'remaining' => count($recipients),
    ];

    foreach ($recipients as $user) {
        $stats['attempted']++;

        try {
            $unsubscribeUrl = llama_marketing_unsubscribe_url(
                $db,
                (int) $user['id']
            );

            $context = llama_promotion_email_context(
                $db,
                $promotion,
                $user,
                $unsubscribeUrl
            );

            $sent = send_promotion_campaign_email(
                $db,
                $user,
                $deliveryType,
                $context
            );

            if (!$sent) {
                throw new RuntimeException('Mail server rejected the message.');
            }

            llama_promotion_record_delivery(
                $db,
                $promotionId,
                $user,
                $deliveryType,
                'sent'
            );

            $stats['sent']++;
        } catch (Throwable $exception) {
            llama_promotion_record_delivery(
                $db,
                $promotionId,
                $user,
                $deliveryType,
                'failed',
                $exception->getMessage()
            );

            $stats['failed']++;
        }
    }

    $remainingStmt = $db->prepare(
        'SELECT COUNT(*)
         FROM users u
         LEFT JOIN membership_promotion_deliveries d
           ON d.promotion_id = ?
          AND d.user_id = u.id
          AND d.delivery_type = ?
         WHERE u.email_verified_at IS NOT NULL
           AND u.marketing_email_enabled = 1

           AND NOT (
                LOWER(COALESCE(u.membership_status, \'none\'))
                    IN (\'active\', \'trialing\', \'past_due\', \'complimentary\')
                AND (
                    u.membership_ends_at IS NULL
                    OR u.membership_ends_at >= UTC_TIMESTAMP()
                )
           )

           AND NOT EXISTS (
                SELECT 1
                FROM scout_profiles sp
                INNER JOIN user_roles ur
                    ON ur.user_id = sp.user_id
                INNER JOIN roles r
                    ON r.id = ur.role_id
                WHERE sp.user_id = u.id
                  AND sp.status = \'active\'
                  AND (
                        sp.active_through IS NULL
                        OR sp.active_through >= UTC_TIMESTAMP()
                  )
                  AND r.slug IN (
                        \'scout\',
                        \'master-scout\',
                        \'master_scout\'
                  )
           )

           AND NOT EXISTS (
                SELECT 1
                FROM membership_grants mg
                WHERE mg.user_id = u.id
                  AND mg.grant_type = \'complimentary\'
                  AND mg.revoked_at IS NULL
                  AND mg.starts_at <= UTC_TIMESTAMP()
                  AND mg.ends_at >= UTC_TIMESTAMP()
           )

           AND (
                d.id IS NULL
                OR d.status = \'failed\'
           )'
    );
    $remainingStmt->execute([$promotionId, $deliveryType]);

    $stats['remaining'] = (int) $remainingStmt->fetchColumn();

    return $stats;
}


function llama_due_promotion_email_jobs(PDO $db): array
{
    $now = gmdate('Y-m-d H:i:s');

    $stmt = $db->prepare(
        'SELECT *
         FROM membership_promotions
         WHERE is_enabled = 1
           AND
           (
                (
                    email_enabled = 1
                    AND email_send_at IS NOT NULL
                    AND email_send_at <= ?
                    AND email_sent_at IS NULL
                )
                OR
                (
                    reminder_enabled = 1
                    AND reminder_send_at IS NOT NULL
                    AND reminder_send_at <= ?
                    AND reminder_sent_at IS NULL
                )
           )
         ORDER BY starts_at ASC, id ASC'
    );
    $stmt->execute([$now, $now]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function llama_promotion_finish_delivery_if_complete(
    PDO $db,
    int $promotionId,
    string $deliveryType
): void {
    $recipients = llama_promotion_email_recipients(
        $db,
        $promotionId,
        $deliveryType,
        1
    );

    if ($recipients) {
        return;
    }

    $sentCountStmt = $db->prepare(
        'SELECT COUNT(*)
         FROM membership_promotion_deliveries
         WHERE promotion_id = ?
           AND delivery_type = ?
           AND status = \'sent\''
    );
    $sentCountStmt->execute([$promotionId, $deliveryType]);
    $sentCount = (int) $sentCountStmt->fetchColumn();

    if ($deliveryType === 'reminder') {
        $stmt = $db->prepare(
            'UPDATE membership_promotions
             SET reminder_sent_at = UTC_TIMESTAMP(),
                 reminder_sent_count = ?
             WHERE id = ?'
        );
    } else {
        $stmt = $db->prepare(
            'UPDATE membership_promotions
             SET email_sent_at = UTC_TIMESTAMP(),
                 email_sent_count = ?
             WHERE id = ?'
        );
    }

    $stmt->execute([$sentCount, $promotionId]);
}


/* =========================================================
   OPPORTUNISTIC PROMOTION EMAIL MAINTENANCE

   Porkbun does not provide cron on this hosting plan.

   Like Scout maintenance, campaign email is advanced by
   ordinary authenticated site activity. The database-backed
   throttle keeps this from running on every request.

   Each maintenance pass sends only a small number of messages
   so a member page load is not turned into a large mail job.
   ========================================================= */

function llama_promotion_email_maintenance_storage_available(
    PDO $db
): bool {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );

    $stmt->execute([
        'app_maintenance',
    ]);

    return (bool) $stmt->fetchColumn();
}


function llama_promotion_email_maintenance_is_due(
    PDO $db,
    int $intervalSeconds = 60
): bool {
    $intervalSeconds = max(30, $intervalSeconds);

    if (
        !llama_promotion_email_maintenance_storage_available(
            $db
        )
    ) {
        throw new RuntimeException(
            'Promotion email maintenance storage is not initialized. Missing table: app_maintenance'
        );
    }

    $stmt = $db->prepare(
        'SELECT last_run_at
         FROM app_maintenance
         WHERE maintenance_key = ?
         LIMIT 1'
    );
    $stmt->execute(['membership_promotion_email']);

    $lastRun = $stmt->fetchColumn();

    if (!$lastRun) {
        return true;
    }

    $timestamp =
        llama_promotion_campaign_utc_timestamp(
            (string) $lastRun
        );

    if ($timestamp === null) {
        return true;
    }

    return (time() - $timestamp) >= $intervalSeconds;
}


function llama_mark_promotion_email_maintenance_run(
    PDO $db
): void {
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
        'membership_promotion_email',
    ]);
}


function llama_run_promotion_email_maintenance(
    PDO $db,
    int $batchSize = 2
): array {
    $summary = [
        'ran' => false,
        'campaigns' => 0,
        'attempted' => 0,
        'sent' => 0,
        'failed' => 0,
    ];

    if (!llama_mail_delivery_enabled()) {
        return $summary;
    }

    if (!llama_promotion_email_maintenance_is_due($db)) {
        return $summary;
    }

    /*
     * Prevent two simultaneous page requests from both becoming
     * mail workers. The lock is connection-scoped and released
     * automatically if this request ends unexpectedly.
     */
    $lockStmt = $db->query(
        "SELECT GET_LOCK('llamascout_promotion_email', 0)"
    );

    if (!$lockStmt || (int) $lockStmt->fetchColumn() !== 1) {
        return $summary;
    }

    try {
        /*
         * Check the throttle again after acquiring the lock in
         * case another request completed maintenance while this
         * request was waiting.
         */
        if (!llama_promotion_email_maintenance_is_due($db)) {
            return $summary;
        }

        $summary['ran'] = true;
        $batchSize = max(1, min(5, $batchSize));

        $jobs = llama_due_promotion_email_jobs($db);

        foreach ($jobs as $promotion) {
            $promotionId = (int) ($promotion['id'] ?? 0);

            if ($promotionId < 1) {
                continue;
            }

            $emailSendAt =
                llama_promotion_campaign_utc_timestamp(
                    (string) (
                        $promotion['email_send_at']
                        ?? ''
                    )
                );

            if (
                !empty($promotion['email_enabled'])
                && $emailSendAt !== null
                && empty($promotion['email_sent_at'])
                && $emailSendAt <= time()
            ) {
                $stats = llama_promotion_send_batch(
                    $db,
                    $promotion,
                    'announcement',
                    $batchSize
                );

                llama_promotion_finish_delivery_if_complete(
                    $db,
                    $promotionId,
                    'announcement'
                );

                $summary['campaigns']++;
                $summary['attempted'] += (int) $stats['attempted'];
                $summary['sent'] += (int) $stats['sent'];
                $summary['failed'] += (int) $stats['failed'];
            }

            $reminderSendAt =
                llama_promotion_campaign_utc_timestamp(
                    (string) (
                        $promotion['reminder_send_at']
                        ?? ''
                    )
                );

            if (
                !empty($promotion['reminder_enabled'])
                && $reminderSendAt !== null
                && empty($promotion['reminder_sent_at'])
                && $reminderSendAt <= time()
            ) {
                $stats = llama_promotion_send_batch(
                    $db,
                    $promotion,
                    'reminder',
                    $batchSize
                );

                llama_promotion_finish_delivery_if_complete(
                    $db,
                    $promotionId,
                    'reminder'
                );

                $summary['campaigns']++;
                $summary['attempted'] += (int) $stats['attempted'];
                $summary['sent'] += (int) $stats['sent'];
                $summary['failed'] += (int) $stats['failed'];
            }
        }

        llama_mark_promotion_email_maintenance_run($db);

        return $summary;
    } finally {
        try {
            $db->query(
                "SELECT RELEASE_LOCK('llamascout_promotion_email')"
            );
        } catch (Throwable) {
            // Connection cleanup will release the lock.
        }
    }
}
