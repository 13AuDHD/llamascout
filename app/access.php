<?php

declare(strict_types=1);

function llama_access_utc_timestamp(
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
     * Owner / Admin access.
     *
     * Privileged site operators automatically receive Complete
     * Access without changing membership_status, touching Stripe,
     * or creating a complimentary membership grant.
     *
     * Administrative role and billing state remain separate.
     */
    $stmt = db()->prepare(
        "
        SELECT 1

        FROM user_roles ur

        INNER JOIN roles r
          ON r.id = ur.role_id

        WHERE ur.user_id = ?
          AND r.slug IN (
                'owner',
                'admin'
          )

        LIMIT 1
        "
    );

    $stmt->execute([$userId]);

    if ($stmt->fetchColumn()) {
        return true;
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

            if ($endsAt === null) {
                return true;
            }

            $endsTimestamp =
                llama_access_utc_timestamp(
                    (string) $endsAt
                );

            if (
                $endsTimestamp !== null
                && $endsTimestamp >= time()
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
                OR sp.active_through >= UTC_TIMESTAMP()
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
          AND starts_at <= UTC_TIMESTAMP()
          AND ends_at >= UTC_TIMESTAMP()

        LIMIT 1
        "
    );

    $stmt->execute([$userId]);

    return (bool) $stmt->fetchColumn();
}
