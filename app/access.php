<?php

declare(strict_types=1);

function user_has_member_access(?int $userId = null): bool
{
    if ($userId === null) {
        $user = current_user();

        if (!$user || empty($user['id'])) {
            return false;
        }

        $userId = (int) $user['id'];
    }

    if ($userId < 1) {
        return false;
    }

    /*
     * Paid / subscription access.
     *
     * We query only the membership fields needed for access.
     * No Stripe API calls and no membership service dependency.
     */
    $stmt = db()->prepare(
        "
        SELECT
            membership_status,
            membership_ends_at

        FROM users

        WHERE id = ?

        LIMIT 1
        "
    );

    $stmt->execute([$userId]);

    $membership = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($membership) {
        $status = strtolower(
            trim((string) ($membership['membership_status'] ?? 'none'))
        );

        if (
            in_array(
                $status,
                [
                    'active',
                    'trialing',
                    'past_due',
                    'complimentary',
                ],
                true
            )
        ) {
            $endsAt = $membership['membership_ends_at'] ?? null;

            if (
                $endsAt === null
                || strtotime((string) $endsAt) >= time()
            ) {
                return true;
            }
        }
    }

    /*
     * Active Scout access.
     *
     * Scout status itself is an entitlement to full Llama Scout
     * access. Billing state remains separate so a paid subscription
     * can finish its already-paid period without causing a gap when
     * Stripe later marks that subscription canceled.
     */
    $stmt = db()->prepare(
        "
        SELECT 1

        FROM scout_profiles sp

        INNER JOIN user_roles ur
          ON ur.user_id = sp.user_id

        INNER JOIN roles r
          ON r.id = ur.role_id

        WHERE sp.user_id = ?
          AND sp.status = 'active'
          AND (
                sp.active_through IS NULL
                OR sp.active_through >= NOW()
          )
          AND r.slug IN (
                'scout',
                'master-scout',
                'master_scout'
          )

        LIMIT 1
        "
    );

    $stmt->execute([$userId]);

    if ($stmt->fetchColumn()) {
        return true;
    }


    /*
     * Complimentary grant access.
     */
    $stmt = db()->prepare(
        "
        SELECT 1

        FROM membership_grants

        WHERE user_id = ?
          AND grant_type = 'complimentary'
          AND revoked_at IS NULL
          AND starts_at <= NOW()
          AND ends_at >= NOW()

        LIMIT 1
        "
    );

    $stmt->execute([$userId]);

    return (bool) $stmt->fetchColumn();
}
