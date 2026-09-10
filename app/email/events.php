<?php

declare(strict_types=1);

require_once __DIR__ . '/templates.php';


function llama_email_display_name(array $user): string
{
    $name = trim(
        (string) (
            $user['display_name']
            ?? ''
        )
    );

    if ($name !== '') {
        return $name;
    }

    $username = trim(
        (string) (
            $user['username']
            ?? ''
        )
    );

    return $username !== ''
        ? $username
        : 'Scout';
}


function llama_email_money(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}


function llama_email_membership_context(PDO $db): array
{
    $monthlyPrice = '$6.99 / month';
    $annualPrice = '$59.99 / year';

    try {
        require_once dirname(__DIR__) . '/memberships.php';

        if (function_exists('llama_membership_offers')) {
            $offers = llama_membership_offers($db);

            $monthly = $offers['monthly'] ?? null;
            $annual = $offers['annual'] ?? null;

            if ($monthly) {
                $monthlyPrice =
                    llama_email_money(
                        (int) $monthly['effective_price_cents']
                    )
                    . ' / month';
            }

            if ($annual) {
                $annualPrice =
                    llama_email_money(
                        (int) $annual['effective_price_cents']
                    )
                    . ' / year';
            }
        }
    } catch (Throwable $exception) {
        /*
         * Email delivery should not fail merely because current
         * membership pricing could not be loaded. Defaults remain.
         */
    }

    return [
        'membership_url' =>
            'https://llamascout.com/membership.php',
        'monthly_url' =>
            'https://account.llamascout.com/membership.php?plan=monthly',
        'annual_url' =>
            'https://account.llamascout.com/membership.php?plan=annual',
        'monthly_price' => $monthlyPrice,
        'annual_price' => $annualPrice,
        'demo_report_url' =>
            'https://llamascout.com/scout-report-demo.php',
        'map_url' =>
            'https://llamascout.com/map.php',
        'site_url' =>
            'https://llamascout.com',
    ];
}


/* =========================================================
   VERIFY EMAIL
   ========================================================= */

function send_verification_email(
    array $user,
    string $token
): bool {
    $db = db();

    $context = array_merge(
        llama_email_membership_context($db),
        [
            'display_name' =>
                llama_email_display_name($user),
            'username' =>
                (string) ($user['username'] ?? ''),
            'verification_url' =>
                'https://account.llamascout.com/verify-email.php?token='
                . urlencode($token),
        ]
    );

    return llama_email_send_template(
        $db,
        'verify_email',
        (string) $user['email'],
        $context,
        false,
        isset($user['id'])
            ? (int) $user['id']
            : null
    );
}


/* =========================================================
   WELCOME
   ========================================================= */

function send_welcome_email_for_user(
    PDO $db,
    int $userId
): bool {
    if ($userId < 1) {
        return false;
    }

    /*
     * Welcome is intentionally withheld until the Email Center
     * migration exists. Verification itself continues to work
     * from built-in defaults even before the migration.
     */
    if (!llama_email_schema_ready($db)) {
        return false;
    }

    $deliveryStmt = $db->prepare(
        'SELECT sent_at
         FROM email_event_deliveries
         WHERE user_id = ?
           AND event_key = ?
         LIMIT 1'
    );

    $deliveryStmt->execute([
        $userId,
        'welcome',
    ]);

    $sentAt = $deliveryStmt->fetchColumn();

    if ($sentAt) {
        return true;
    }

    $userStmt = $db->prepare(
        'SELECT
            id,
            email,
            username,
            display_name,
            email_verified_at,
            status
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $userStmt->execute([$userId]);

    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$user
        || empty($user['email_verified_at'])
        || (string) $user['status'] !== 'active'
    ) {
        return false;
    }

    $context = array_merge(
        llama_email_membership_context($db),
        [
            'display_name' =>
                llama_email_display_name($user),
            'username' =>
                (string) $user['username'],
        ]
    );

    $success = llama_email_send_template(
        $db,
        'welcome',
        (string) $user['email'],
        $context,
        false,
        $userId
    );

    if (!$success) {
        return false;
    }

    $delivery = $db->prepare(
        'INSERT INTO email_event_deliveries (
            user_id,
            event_key,
            sent_at
         ) VALUES (?, ?, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            sent_at = COALESCE(sent_at, VALUES(sent_at))'
    );

    $delivery->execute([
        $userId,
        'welcome',
    ]);

    return true;
}


/* =========================================================
   PASSWORD RESET
   ========================================================= */

function send_password_reset_email(
    array $user,
    string $resetUrl
): bool {
    $db = db();

    $context = array_merge(
        llama_email_membership_context($db),
        [
            'display_name' =>
                llama_email_display_name($user),
            'username' =>
                (string) ($user['username'] ?? ''),
            'reset_url' =>
                $resetUrl,
        ]
    );

    return llama_email_send_template(
        $db,
        'password_reset',
        (string) $user['email'],
        $context,
        false,
        isset($user['id'])
            ? (int) $user['id']
            : null
    );
}


/* =========================================================
   GOODBYE
   ========================================================= */

function send_goodbye_email(
    string $email,
    string $displayName,
    string $username = '',
    ?int $formerUserId = null
): bool {
    $db = db();

    $context = array_merge(
        llama_email_membership_context($db),
        [
            'display_name' =>
                trim($displayName) !== ''
                    ? trim($displayName)
                    : (
                        trim($username) !== ''
                            ? trim($username)
                            : 'Scout'
                    ),
            'username' =>
                $username,
        ]
    );

    return llama_email_send_template(
        $db,
        'goodbye',
        $email,
        $context,
        false,
        $formerUserId
    );
}
/* =========================================================
   COMPLIMENTARY MEMBERSHIP INVITATION
   ========================================================= */

function send_complimentary_invitation_email(
    PDO $db,
    array $invitation,
    string $token,
    string $recipientName = ''
): bool {
    $email =
        strtolower(
            trim(
                (string) (
                    $invitation['email']
                    ?? ''
                )
            )
        );

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        throw new InvalidArgumentException(
            'Complimentary invitation does not have a valid recipient email.'
        );
    }

    $token = trim($token);

    if (
        !preg_match(
            '/^[a-f0-9]{64}$/i',
            $token
        )
    ) {
        throw new InvalidArgumentException(
            'Complimentary invitation token is invalid.'
        );
    }

    $durationDays =
        max(
            1,
            (int) (
                $invitation['duration_days']
                ?? $invitation['grant_duration_days']
                ?? 0
            )
        );

    $expiresAt =
        trim(
            (string) (
                $invitation['expires_at']
                ?? ''
            )
        );

    if ($expiresAt === '') {
        $expiresInDays =
            max(
                1,
                (int) (
                    $invitation['expires_in_days']
                    ?? 14
                )
            );

        $expiresAt =
            (
                new DateTimeImmutable(
                    'now',
                    new DateTimeZone('UTC')
                )
            )
                ->modify(
                    '+'
                    . $expiresInDays
                    . ' days'
                )
                ->format(
                    'Y-m-d H:i:s'
                );
    }

    try {
        $expiresLabel =
            (
                new DateTimeImmutable(
                    $expiresAt,
                    new DateTimeZone('UTC')
                )
            )->format('F j, Y');
    } catch (Throwable) {
        $expiresLabel = $expiresAt;
    }

    $reason =
        trim(
            (string) (
                $invitation['reason']
                ?? ''
            )
        );

    if ($reason === '') {
        $reason =
            'Llama Scout would like you to experience Complete Access.';
    }

    $recipientName =
        trim($recipientName);

    if ($recipientName === '') {
        $recipientName = 'there';
    }

    $context = array_merge(
        llama_email_membership_context($db),
        [
            'recipient_name' =>
                $recipientName,

            'invite_email' =>
                $email,

            'complimentary_days' =>
                (string) $durationDays,

            'invite_expires' =>
                $expiresLabel,

            'invite_reason' =>
                $reason,

            'invite_url' =>
                'https://account.llamascout.com/complimentary-invite.php?token='
                . rawurlencode($token),
        ]
    );

    return llama_email_send_template(
        $db,
        'complimentary_invitation',
        $email,
        $context,
        false,
        null
    );
}
