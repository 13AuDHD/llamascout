<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-users.php';
require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/scout-maintenance.php';
require_once __DIR__ . '/scout-ranks.php';

function admin_testing_accounts(PDO $db): array
{
    $sql = 'SELECT
                u.id,
                u.username,
                u.display_name,
                u.email,
                u.membership_status,
                u.stripe_customer_id,
                u.stripe_subscription_id,
                sp.status AS scout_status
            FROM users u
            LEFT JOIN scout_profiles sp ON sp.user_id = u.id
            WHERE u.anonymized_at IS NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM user_roles ur
                  INNER JOIN roles r ON r.id = ur.role_id
                  WHERE ur.user_id = u.id
                    AND r.slug IN ("owner", "admin")
              )
            ORDER BY
                COALESCE(NULLIF(u.username, ""), NULLIF(u.display_name, ""), u.email) ASC,
                u.id ASC';

    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_testing_is_stripe_test_mode(): bool
{
    try {
        $config = llama_stripe_config();
        $key = trim((string) ($config['secret_key'] ?? ''));
        return str_starts_with($key, 'sk_test_');
    } catch (Throwable $exception) {
        return false;
    }
}

function admin_testing_delete_if_present(PDO $db, string $table, int $userId): int
{
    try {
        $stmt = $db->prepare('DELETE FROM `' . $table . '` WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    } catch (Throwable $exception) {
        return 0;
    }
}

function admin_testing_reset_stripe_remote(array $target): array
{
    if (!admin_testing_is_stripe_test_mode()) {
        throw new RuntimeException(
            'Stripe cleanup is only available while Llama Scout is configured with a Stripe test secret key.'
        );
    }

    $subscriptionId = trim((string) ($target['stripe_subscription_id'] ?? ''));
    $customerId = trim((string) ($target['stripe_customer_id'] ?? ''));
    $client = llama_stripe_client();

    $subscriptionCanceled = false;
    $customerDeleted = false;

    if ($subscriptionId !== '') {
        try {
            $client->subscriptions->cancel($subscriptionId, []);
            $subscriptionCanceled = true;
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            if (stripos($exception->getMessage(), 'No such subscription') === false) {
                throw $exception;
            }
        }
    }

    if ($customerId !== '') {
        try {
            $client->customers->delete($customerId, []);
            $customerDeleted = true;
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            if (stripos($exception->getMessage(), 'No such customer') === false) {
                throw $exception;
            }
        }
    }

    return [
        'subscription_canceled' => $subscriptionCanceled,
        'customer_deleted' => $customerDeleted,
    ];
}

function admin_testing_reset(
    PDO $db,
    int $actorUserId,
    int $targetUserId,
    bool $wipeStripe,
    bool $wipeScout,
    bool $wipeSavedPlaces
): array {
    if (!admin_users_current_is_owner($db, $actorUserId)) {
        throw new RuntimeException('Only an Owner can reset test-account state.');
    }

    if ($targetUserId < 1 || $targetUserId === $actorUserId) {
        throw new RuntimeException('Choose a valid test account.');
    }

    if (!$wipeStripe && !$wipeScout && !$wipeSavedPlaces) {
        throw new RuntimeException('Select at least one test state to wipe.');
    }

    $target = admin_users_get($db, $targetUserId);
    if (!$target || !empty($target['anonymized_at'])) {
        throw new RuntimeException('That test account is not available.');
    }

    if (user_has_role('owner', $targetUserId) || user_has_role('admin', $targetUserId)) {
        throw new RuntimeException('Owner and Admin accounts cannot be reset with Testing tools.');
    }

    $stripeResult = [
        'subscription_canceled' => false,
        'customer_deleted' => false,
    ];

    if ($wipeStripe) {
        $stripeResult = admin_testing_reset_stripe_remote($target);
    }

    $db->beginTransaction();

    try {
        $counts = [
            'saved_places' => 0,
            'scout_rows' => 0,
        ];

        if ($wipeStripe) {
            admin_testing_delete_if_present($db, 'membership_grants', $targetUserId);

            $stmt = $db->prepare(
                'UPDATE users
                 SET stripe_customer_id = NULL,
                     stripe_subscription_id = NULL,
                     stripe_cancel_at_period_end = 0,
                     membership_status = "none",
                     membership_interval = NULL,
                     membership_started_at = NULL,
                     membership_ends_at = NULL
                 WHERE id = ?'
            );
            $stmt->execute([$targetUserId]);
        }

        if ($wipeScout) {
            llama_ensure_scout_extensions_table($db);
            llama_ensure_scout_rank_history_table($db);

            foreach (['scout_extensions', 'scout_rank_history', 'scout_applications', 'scout_training'] as $table) {
                $counts['scout_rows'] += admin_testing_delete_if_present($db, $table, $targetUserId);
            }

            $roleStmt = $db->prepare(
                'DELETE ur
                 FROM user_roles ur
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = ?
                   AND r.slug IN ("scout", "master-scout", "master_scout")'
            );
            $roleStmt->execute([$targetUserId]);

            try {
                $badgeStmt = $db->prepare(
                    'DELETE ub
                     FROM user_badges ub
                     INNER JOIN badge_definitions bd ON bd.id = ub.badge_id
                     WHERE ub.user_id = ?
                       AND bd.slug = "master-scout"'
                );
                $badgeStmt->execute([$targetUserId]);
            } catch (Throwable $exception) {
                // Badge storage is optional for the reset itself.
            }

            $profileStmt = $db->prepare('DELETE FROM scout_profiles WHERE user_id = ?');
            $profileStmt->execute([$targetUserId]);
            $counts['scout_rows'] += $profileStmt->rowCount();

            if (!$wipeStripe) {
                $membershipStmt = $db->prepare(
                    'UPDATE users
                     SET membership_status = "none",
                         membership_interval = NULL,
                         membership_started_at = NULL,
                         membership_ends_at = NULL
                     WHERE id = ?
                       AND membership_status = "complimentary"
                       AND (stripe_subscription_id IS NULL OR stripe_subscription_id = "")'
                );
                $membershipStmt->execute([$targetUserId]);
            }
        }

        if ($wipeSavedPlaces) {
            $counts['saved_places'] += admin_testing_delete_if_present($db, 'user_saved_places', $targetUserId);
            $counts['saved_places'] += admin_testing_delete_if_present($db, 'saved_places', $targetUserId);
        }

        /*
         * Billing and role changes should be visible on the very next
         * request. End existing sessions when either authority-bearing
         * state is reset so a test browser cannot keep stale access.
         */
        if ($wipeStripe || $wipeScout) {
            admin_testing_delete_if_present($db, 'sessions', $targetUserId);
            admin_testing_delete_if_present($db, 'user_remember_tokens', $targetUserId);
        }

        admin_users_audit(
            $db,
            $actorUserId,
            $targetUserId,
            'system.test_account_reset',
            'Reset selected test-account state.',
            [
                'wipe_stripe' => $wipeStripe,
                'wipe_scout' => $wipeScout,
                'wipe_saved_places' => $wipeSavedPlaces,
                'stripe_test_mode' => admin_testing_is_stripe_test_mode(),
                'stripe_subscription_canceled' => $stripeResult['subscription_canceled'],
                'stripe_customer_deleted' => $stripeResult['customer_deleted'],
                'saved_place_rows_removed' => $counts['saved_places'],
                'scout_rows_removed' => $counts['scout_rows'],
            ]
        );

        $db->commit();

        return $counts + $stripeResult;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}
