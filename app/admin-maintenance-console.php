<?php

declare(strict_types=1);

/*
 * Basecamp emergency maintenance console.
 *
 * Automatic maintenance remains traffic-triggered because this hosting
 * plan does not provide cron. This file adds an Owner-only manual fallback
 * that runs the same production workers, with their existing locks and
 * duplicate-delivery protections intact.
 */

function admin_maintenance_console_scout_interval(
    PDO $db
): int {
    try {
        require_once __DIR__ . '/scout-policy.php';

        $interval =
            llama_scout_policy_int(
                $db,
                'maintenance_interval_seconds'
            );

        return max(
            60,
            $interval
        );
    } catch (Throwable) {
        return 86400;
    }
}


function admin_maintenance_console_workers(
    PDO $db
): array {
    $scoutInterval =
        admin_maintenance_console_scout_interval(
            $db
        );

    return [
        'scout_renewals' => [
            'id' =>
                'scout_renewals',
            'key' =>
                'scout_renewals',
            'storage' =>
                'app_maintenance',
            'label' =>
                'Scout renewal maintenance',
            'description' =>
                'Keeps Scout periods, renewals, reactivation windows, extensions, roles, and expiration state current.',
            'icon' =>
                'binoculars',
            'interval_seconds' =>
                $scoutInterval,
            'warning_seconds' =>
                max(
                    3600,
                    $scoutInterval * 2
                ),
            'critical_seconds' =>
                max(
                    86400,
                    $scoutInterval * 7
                ),
        ],

        'membership_promotion_email' => [
            'id' =>
                'membership_promotion_email',
            'key' =>
                'membership_promotion_email',
            'storage' =>
                'app_maintenance',
            'label' =>
                'Promotion campaign emails',
            'description' =>
                'Sends due membership campaign announcements and reminders.',
            'icon' =>
                'mail',
            'interval_seconds' =>
                60,
        ],

        'newsletter_delivery' => [
            'id' =>
                'newsletter_delivery',
            'key' =>
                'newsletter_delivery',
            'storage' =>
                'app_maintenance',
            'label' =>
                'Newsletter delivery',
            'description' =>
                'Advances due Llama Scout Monthly and Member Dispatch delivery queues.',
            'icon' =>
                'news',
            'interval_seconds' =>
                60,
        ],

        'support_email_notifications' => [
            'id' =>
                'support_email_notifications',
            'key' =>
                'support_email_notifications',
            'storage' =>
                'app_maintenance',
            'label' =>
                'Support email notifications',
            'description' =>
                'Retries unsent support ticket confirmations and Basecamp notices.',
            'icon' =>
                'headset',
            'interval_seconds' =>
                600,
        ],

        'membership_lifecycle_email' => [
            'id' =>
                'membership_lifecycle_email',
            'key' =>
                'membership_lifecycle_email',
            'storage' =>
                'app_maintenance',
            'label' =>
                'Membership lifecycle emails',
            'description' =>
                'Sends membership-started, renewal, ending, and related lifecycle messages from local membership state.',
            'icon' =>
                'mail-cog',
            'interval_seconds' =>
                60,
        ],

        'promotion_code_sync' => [
            'id' =>
                'promotion_code_sync',
            'key' =>
                'promotion_code_sync',
            'storage' =>
                'app_maintenance',
            'label' =>
                'Promotion Code sync',
            'description' =>
                'Keeps Stripe Promotion Code active states aligned with scheduled promotions.',
            'icon' =>
                'refresh',
            'interval_seconds' =>
                300,
        ],

        'shop_notification_email' => [
            'id' =>
                'shop_notification_email',
            'key' =>
                'shop_notification_email',
            'storage' =>
                'app_maintenance',
            'label' =>
                'Shop notification email',
            'description' =>
                'Sends and retries shipment, delivery, and refund notifications.',
            'icon' =>
                'mail',
            'interval_seconds' =>
                300,
        ],

        'shop_expired_checkouts' => [
            'id' =>
                'shop_expired_checkouts',
            'key' =>
                'shop_expired_checkouts',
            'storage' =>
                'app_maintenance',
            'label' =>
                'Expired Shop checkouts',
            'description' =>
                'Cancels abandoned pending checkouts and releases inventory reservations.',
            'icon' =>
                'shopping-cart',
            'interval_seconds' =>
                300,
        ],


    ];
}


function admin_maintenance_console_last_run(
    PDO $db,
    array $worker
): ?string {
    try {
        if (
            ($worker['storage'] ?? '')
            === 'site_settings'
        ) {
            $stmt =
                $db->prepare(
                    'SELECT setting_value
                     FROM site_settings
                     WHERE setting_key = ?
                     LIMIT 1'
                );
        } else {
            $stmt =
                $db->prepare(
                    'SELECT last_run_at
                     FROM app_maintenance
                     WHERE maintenance_key = ?
                     LIMIT 1'
                );
        }

        $stmt->execute([
            (string) $worker['key'],
        ]);

        $value =
            $stmt->fetchColumn();

        return
            is_string($value)
            && trim($value) !== ''
                ? trim($value)
                : null;
    } catch (Throwable) {
        return null;
    }
}


function admin_maintenance_console_timestamp(
    ?string $value
): ?int {
    $value =
        trim(
            (string) $value
        );

    if ($value === '') {
        return null;
    }

    try {
        return
            (
                new DateTimeImmutable(
                    $value,
                    new DateTimeZone('UTC')
                )
            )->getTimestamp();
    } catch (Throwable) {
        return null;
    }
}


function admin_maintenance_console_interval_label(
    int $seconds
): string {
    $seconds =
        max(
            1,
            $seconds
        );

    if ($seconds < 60) {
        return
            'Every '
            . number_format($seconds)
            . ' second'
            . ($seconds === 1 ? '' : 's');
    }

    if ($seconds < 3600) {
        $minutes =
            max(
                1,
                (int) round(
                    $seconds / 60
                )
            );

        return
            'Every '
            . number_format($minutes)
            . ' minute'
            . ($minutes === 1 ? '' : 's');
    }

    if ($seconds < 86400) {
        $hours =
            max(
                1,
                (int) round(
                    $seconds / 3600
                )
            );

        return
            'Every '
            . number_format($hours)
            . ' hour'
            . ($hours === 1 ? '' : 's');
    }

    $days =
        max(
            1,
            (int) round(
                $seconds / 86400
            )
        );

    return
        'Every '
        . number_format($days)
        . ' day'
        . ($days === 1 ? '' : 's');
}


function admin_maintenance_console_age_label(
    ?int $timestamp
): string {
    if ($timestamp === null) {
        return 'No successful run recorded';
    }

    $age =
        max(
            0,
            time() - $timestamp
        );

    if ($age < 60) {
        return 'Less than a minute ago';
    }

    if ($age < 3600) {
        $minutes =
            max(
                1,
                (int) floor(
                    $age / 60
                )
            );

        return
            number_format($minutes)
            . ' minute'
            . ($minutes === 1 ? '' : 's')
            . ' ago';
    }

    if ($age < 86400) {
        $hours =
            max(
                1,
                (int) floor(
                    $age / 3600
                )
            );

        return
            number_format($hours)
            . ' hour'
            . ($hours === 1 ? '' : 's')
            . ' ago';
    }

    $days =
        max(
            1,
            (int) floor(
                $age / 86400
            )
        );

    return
        number_format($days)
        . ' day'
        . ($days === 1 ? '' : 's')
        . ' ago';
}


function admin_maintenance_console_statuses(
    PDO $db
): array {
    $workers =
        admin_maintenance_console_workers(
            $db
        );

    foreach ($workers as &$worker) {
        $interval =
            max(
                60,
                (int) (
                    $worker[
                        'interval_seconds'
                    ]
                    ?? 300
                )
            );

        /*
         * Short workers should not turn yellow simply because there was
         * no authenticated request for a few minutes. Give every short
         * worker at least a one-hour healthy window and one full day
         * before declaring it red.
         *
         * Scout maintenance scales with its configured policy interval.
         */
        $warning =
            (int) (
                $worker[
                    'warning_seconds'
                ]
                ?? max(
                    3600,
                    $interval * 2
                )
            );

        $critical =
            (int) (
                $worker[
                    'critical_seconds'
                ]
                ?? max(
                    86400,
                    $interval * 7
                )
            );

        $lastRun =
            admin_maintenance_console_last_run(
                $db,
                $worker
            );

        $timestamp =
            admin_maintenance_console_timestamp(
                $lastRun
            );

        if ($timestamp === null) {
            $status = 'down';
            $statusLabel =
                'No run recorded';
        } else {
            $age =
                max(
                    0,
                    time() - $timestamp
                );

            if ($age <= $warning) {
                $status = 'good';
                $statusLabel =
                    'Healthy';
            } elseif ($age <= $critical) {
                $status = 'attention';
                $statusLabel =
                    'Overdue';
            } else {
                $status = 'down';
                $statusLabel =
                    'Stale';
            }
        }

        $worker['last_run'] =
            $lastRun;

        $worker['last_timestamp'] =
            $timestamp;

        $worker['status'] =
            $status;

        $worker['status_label'] =
            $statusLabel;

        $worker['age_label'] =
            admin_maintenance_console_age_label(
                $timestamp
            );

        $worker['cadence_label'] =
            admin_maintenance_console_interval_label(
                $interval
            );

        $worker['warning_seconds'] =
            $warning;

        $worker['critical_seconds'] =
            $critical;
    }

    unset($worker);

    return
        array_values(
            $workers
        );
}


function admin_maintenance_console_marker_snapshot(
    PDO $db,
    string $key
): array {
    $stmt =
        $db->prepare(
            'SELECT last_run_at
             FROM app_maintenance
             WHERE maintenance_key = ?
             LIMIT 1'
        );

    $stmt->execute([
        $key,
    ]);

    $value =
        $stmt->fetchColumn();

    return [
        'exists' =>
            $value !== false,
        'value' =>
            $value !== false
                ? (string) $value
                : null,
    ];
}


function admin_maintenance_console_force_due(
    PDO $db,
    string $key,
    string $sentinel
): void {
    $stmt =
        $db->prepare(
            'INSERT INTO app_maintenance
             (
                maintenance_key,
                last_run_at
             )
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                last_run_at = VALUES(last_run_at)'
        );

    $stmt->execute([
        $key,
        $sentinel,
    ]);
}


function admin_maintenance_console_restore_marker(
    PDO $db,
    string $key,
    array $snapshot,
    string $sentinel
): void {
    if (!empty($snapshot['exists'])) {
        $stmt =
            $db->prepare(
                'UPDATE app_maintenance
                 SET last_run_at = ?
                 WHERE maintenance_key = ?
                   AND last_run_at = ?'
            );

        $stmt->execute([
            $snapshot['value'],
            $key,
            $sentinel,
        ]);

        return;
    }

    $stmt =
        $db->prepare(
            'DELETE FROM app_maintenance
             WHERE maintenance_key = ?
               AND last_run_at = ?'
        );

    $stmt->execute([
        $key,
        $sentinel,
    ]);
}


function admin_maintenance_console_run_callback(
    PDO $db,
    string $workerId
): array {
    return match ($workerId) {
        'scout_renewals' =>
            (function () use ($db): array {
                require_once
                    __DIR__
                    . '/scout-maintenance.php';

                return
                    llama_run_scout_renewal_maintenance(
                        $db
                    );
            })(),

        'membership_promotion_email' =>
            (function () use ($db): array {
                require_once
                    __DIR__
                    . '/promotion-campaigns.php';

                return
                    llama_run_promotion_email_maintenance(
                        $db,
                        2
                    );
            })(),

        'newsletter_delivery' =>
            (function () use ($db): array {
                require_once
                    __DIR__
                    . '/newsletters.php';

                return
                    llama_run_newsletter_maintenance(
                        $db,
                        2
                    );
            })(),

        'support_email_notifications' =>
            (function () use ($db): array {
                require_once
                    __DIR__
                    . '/support.php';

                return
                    llama_run_support_email_maintenance(
                        $db,
                        10
                    );
            })(),

        'membership_lifecycle_email' =>
            llama_run_membership_email_maintenance(
                $db,
                10
            ),

        'promotion_code_sync' =>
            llama_run_promotion_code_maintenance(
                $db,
                300
            ),

        'shop_notification_email' =>
            shop_run_shipment_email_maintenance(
                $db,
                5
            ),

        'shop_expired_checkouts' =>
            (function () use ($db): array {
                require_once
                    __DIR__
                    . '/shop-maintenance.php';

                return
                    shop_run_checkout_cleanup_maintenance(
                        $db,
                        50,
                        300
                    );
            })(),

        default =>
            throw new InvalidArgumentException(
                'Choose a valid maintenance worker.'
            ),
    };
}


function admin_maintenance_console_summary_message(
    string $workerId,
    string $label,
    array $summary
): string {
    $prefix =
        $label
        . ' completed. ';

    return match ($workerId) {
        'scout_renewals' =>
            $prefix
            . number_format(
                (int) (
                    $summary['processed']
                    ?? 0
                )
            )
            . ' Scout profile(s) processed, '
            . number_format(
                (int) (
                    $summary['renewed']
                    ?? 0
                )
            )
            . ' renewed, '
            . number_format(
                (int) (
                    $summary['inactive']
                    ?? 0
                )
            )
            . ' made inactive, '
            . number_format(
                (int) (
                    $summary['errors']
                    ?? 0
                )
            )
            . ' error(s).',

        'membership_promotion_email' =>
            $prefix
            . number_format(
                (int) (
                    $summary['campaigns']
                    ?? 0
                )
            )
            . ' campaign job(s), '
            . number_format(
                (int) (
                    $summary['sent']
                    ?? 0
                )
            )
            . ' email(s) sent, '
            . number_format(
                (int) (
                    $summary['failed']
                    ?? 0
                )
            )
            . ' failed.',

        'newsletter_delivery' =>
            $prefix
            . number_format(
                (int) (
                    $summary['issues']
                    ?? 0
                )
            )
            . ' issue(s) checked, '
            . number_format(
                (int) (
                    $summary['sent']
                    ?? 0
                )
            )
            . ' email(s) sent, '
            . number_format(
                (int) (
                    $summary['failed']
                    ?? 0
                )
            )
            . ' failed.',

        'support_email_notifications' =>
            $prefix
            . number_format(
                (int) (
                    $summary['tickets']
                    ?? 0
                )
            )
            . ' support ticket(s) checked.',

        'membership_lifecycle_email' =>
            $prefix
            . number_format(
                (int) (
                    $summary['sent']
                    ?? 0
                )
            )
            . ' email(s) sent, '
            . number_format(
                (int) (
                    $summary['failed']
                    ?? 0
                )
            )
            . ' failed.',

        'promotion_code_sync' =>
            $prefix
            . number_format(
                (int) (
                    $summary['checked']
                    ?? 0
                )
            )
            . ' code(s) checked, '
            . number_format(
                (int) (
                    $summary['changed']
                    ?? 0
                )
            )
            . ' changed.',

        'shop_notification_email' =>
            $prefix
            . number_format(
                (int) (
                    $summary['sent']
                    ?? 0
                )
            )
            . ' notification(s) sent, '
            . number_format(
                (int) (
                    $summary['failed']
                    ?? 0
                )
            )
            . ' failed.',

        'shop_expired_checkouts' =>
            $prefix
            . number_format(
                (int) (
                    $summary[
                        'orders_cancelled'
                    ]
                    ?? 0
                )
            )
            . ' checkout(s) cancelled, '
            . number_format(
                (int) (
                    $summary[
                        'reservations_released'
                    ]
                    ?? 0
                )
            )
            . ' reservation(s) released.',

        default =>
            $prefix
            . 'Worker finished.',
    };
}


function admin_maintenance_console_run_worker(
    PDO $db,
    int $actorUserId,
    string $workerId
): array {
    if (
        !admin_users_current_is_owner(
            $db,
            $actorUserId
        )
    ) {
        throw new RuntimeException(
            'Only an Owner can manually run maintenance workers.'
        );
    }

    $workers =
        admin_maintenance_console_workers(
            $db
        );

    if (
        !isset(
            $workers[$workerId]
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid maintenance worker.'
        );
    }

    $worker =
        $workers[$workerId];

    /*
     * The normal worker functions deliberately skip work until their
     * throttle interval is due. For an explicit Owner action, make the
     * existing marker temporarily old, then invoke the same worker.
     *
     * Locks, row-level transactions, send-once checks, and other worker
     * safeguards remain in force.
     */
    $sentinel =
        '1970-01-01 00:00:01';

    $snapshot =
        admin_maintenance_console_marker_snapshot(
            $db,
            (string) $worker['key']
        );

    admin_maintenance_console_force_due(
        $db,
        (string) $worker['key'],
        $sentinel
    );

    try {
        $summary =
            admin_maintenance_console_run_callback(
                $db,
                $workerId
            );

        $after =
            admin_maintenance_console_last_run(
                $db,
                $worker
            );

        if (
            $after === null
            || $after === $sentinel
        ) {
            admin_maintenance_console_restore_marker(
                $db,
                (string) $worker['key'],
                $snapshot,
                $sentinel
            );

            throw new RuntimeException(
                'The maintenance worker did not record completion. It may already be running, or its required storage is unavailable.'
            );
        }
    } catch (Throwable $exception) {
        admin_maintenance_console_restore_marker(
            $db,
            (string) $worker['key'],
            $snapshot,
            $sentinel
        );

        throw $exception;
    }

    $message =
        admin_maintenance_console_summary_message(
            $workerId,
            (string) $worker['label'],
            $summary
        );

    try {
        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'system.maintenance_worker_run',
            'Manually ran maintenance worker: '
                . (string) $worker['label']
                . '.',
            [
                'worker_id' =>
                    $workerId,
                'maintenance_key' =>
                    (string) $worker['key'],
                'summary' =>
                    $summary,
            ]
        );
    } catch (Throwable $auditException) {
        if (
            function_exists(
                'llama_log_caught_exception'
            )
        ) {
            llama_log_caught_exception(
                $auditException,
                'admin.system.maintenance_worker_audit',
                [
                    'worker_id' =>
                        $workerId,
                ]
            );
        }
    }

    return [
        'worker' =>
            $workerId,
        'summary' =>
            $summary,
        'message' =>
            $message,
    ];
}
