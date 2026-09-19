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
   VERIFY EMAIL CHANGE
   ========================================================= */

function send_email_change_verification_email(
    PDO $db,
    array $user,
    string $newEmail,
    string $token
): bool {
    $newEmail =
        strtolower(
            trim($newEmail)
        );

    if (
        $newEmail === ''
        || !filter_var(
            $newEmail,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $token = trim($token);

    if (
        !preg_match(
            '/^[a-f0-9]{64}$/i',
            $token
        )
    ) {
        return false;
    }

    $context = [
        'display_name' =>
            llama_email_display_name($user),
        'username' =>
            (string) ($user['username'] ?? ''),
        'new_email' =>
            $newEmail,
        'verification_url' =>
            'https://account.llamascout.com/verify-email-change.php?token='
            . rawurlencode($token),
    ];

    return llama_email_send_template(
        $db,
        'email_change_verification',
        $newEmail,
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


/* =========================================================
   CENTRAL EVENT HELPERS
   ========================================================= */

function llama_email_user_by_id(
    PDO $db,
    int $userId
): ?array {
    if ($userId < 1) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT
            id,
            email,
            username,
            display_name,
            email_verified_at,
            status,
            anonymized_at
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}


function send_password_changed_email(
    PDO $db,
    int $userId
): bool {
    $user = llama_email_user_by_id(
        $db,
        $userId
    );

    if (!$user) {
        return false;
    }

    $context = [
        'display_name' =>
            llama_email_display_name($user),
        'username' =>
            (string) ($user['username'] ?? ''),
        'account_url' =>
            'https://account.llamascout.com/',
        'support_url' =>
            'https://llamascout.com/contact.php',
    ];

    return llama_email_send_template(
        $db,
        'password_changed',
        (string) $user['email'],
        $context,
        false,
        $userId
    );
}


function send_scout_invitation_email(
    PDO $db,
    array $candidate
): bool {
    $email = trim(
        (string) (
            $candidate['email']
            ?? ''
        )
    );

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $context = [
        'display_name' =>
            llama_email_display_name($candidate),
        'username' =>
            (string) ($candidate['username'] ?? ''),
        'scout_invite_url' =>
            'https://account.llamascout.com/scout-invite.php',
    ];

    return llama_email_send_template(
        $db,
        'scout_invitation',
        $email,
        $context,
        false,
        isset($candidate['id'])
            ? (int) $candidate['id']
            : null
    );
}


function send_contribution_review_email(
    PDO $db,
    int $userId,
    string $reviewStatus,
    string $contributionType,
    int $contributionId,
    string $placeName,
    string $reviewNotes = '',
    int $pointsAwarded = 0,
    string $contributionUrl = '',
    string $placeUrl = ''
): bool {
    $user = llama_email_user_by_id(
        $db,
        $userId
    );

    if (!$user) {
        return false;
    }

    $templateKey = match ($reviewStatus) {
        'approved' =>
            'contribution_approved',
        'needs-changes', 'changes-requested' =>
            'contribution_changes_requested',
        'rejected', 'not-approved' =>
            'contribution_not_approved',
        default =>
            '',
    };

    if ($templateKey === '') {
        throw new InvalidArgumentException(
            'Unknown contribution review email status.'
        );
    }

    $context = [
        'display_name' =>
            llama_email_display_name($user),
        'contribution_type' =>
            trim($contributionType) !== ''
                ? trim($contributionType)
                : 'contribution',
        'contribution_id' =>
            (string) max(0, $contributionId),
        'place_name' =>
            trim($placeName) !== ''
                ? trim($placeName)
                : 'this Place',
        'review_notes' =>
            trim($reviewNotes) !== ''
                ? trim($reviewNotes)
                : 'No additional review notes were added.',
        'points_awarded' =>
            number_format(max(0, $pointsAwarded)),
        'contribution_url' =>
            trim($contributionUrl),
        'place_url' =>
            trim($placeUrl),
    ];

    return llama_email_send_template(
        $db,
        $templateKey,
        (string) $user['email'],
        $context,
        false,
        $userId
    );
}

/* =========================================================
   NEWSLETTER DELIVERY
   ========================================================= */

function send_newsletter_issue_email(
    PDO $db,
    array $user,
    string $subject,
    string $title,
    string $typeLabel,
    string $bodyText,
    string $bodyHtml,
    string $preferencesUrl,
    bool $accountNotice = false
): bool {
    $email =
        trim(
            (string) (
                $user['email']
                ?? ''
            )
        );

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $userId =
        isset($user['id'])
            ? (int) $user['id']
            : null;

    $footerCopy =
        $accountNotice
            ? 'This is an account-wide Llama Scout notice. Manage optional email preferences'
            : 'Manage which optional Llama Scout emails you receive';

    $context = [
        'newsletter_subject' =>
            trim($subject),

        'newsletter_type' =>
            trim($typeLabel),

        'newsletter_title' =>
            trim($title),

        /*
         * Plain-text rendering uses this value.
         */
        'newsletter_content' =>
            trim($bodyText),

        'site_url' =>
            'https://llamascout.com/',

        'email_preferences_url' =>
            trim($preferencesUrl),

        'newsletter_footer_copy' =>
            $footerCopy,

        /*
         * HTML rendering may use only explicitly approved raw HTML.
         * The newsletter sender sanitizes this content before it
         * reaches this helper.
         */
        '_raw_html' => [
            'newsletter_content' =>
                $bodyHtml,
        ],
    ];

    return llama_email_send_template(
        $db,
        'newsletter_issue',
        $email,
        $context,
        false,
        $userId
    );
}

/* =========================================================
   SUPPORT
   ========================================================= */

function send_support_admin_new_ticket_email(
    PDO $db,
    string $adminEmail,
    array $request,
    int $requestId,
    string $ticketNumber,
    string $categoryLabel,
    string $preferredContactLabel,
    string $requestDetailsText
): bool {
    $adminEmail = trim($adminEmail);

    if (
        $adminEmail === ''
        || !filter_var(
            $adminEmail,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $context = [
        'ticket_number' =>
            trim($ticketNumber),
        'support_category' =>
            trim($categoryLabel) !== ''
                ? trim($categoryLabel)
                : 'Support',
        'requester_name' =>
            (string) ($request['name'] ?? ''),
        'requester_email' =>
            (string) ($request['email'] ?? ''),
        'preferred_contact' =>
            trim($preferredContactLabel) !== ''
                ? trim($preferredContactLabel)
                : 'Email',
        'request_details' =>
            trim($requestDetailsText),
        'ticket_subject' =>
            (string) ($request['subject'] ?? ''),
        'ticket_message' =>
            (string) ($request['message'] ?? ''),
        'admin_ticket_url' =>
            'https://admin.llamascout.com/support.php?id='
            . max(0, $requestId),
    ];

    return llama_email_send_template(
        $db,
        'support_admin_new_ticket',
        $adminEmail,
        $context,
        false,
        null
    );
}


function send_support_ticket_received_email(
    PDO $db,
    array $request,
    string $ticketNumber,
    string $preferredContactLabel,
    string $ticketExtraDetails = ''
): bool {
    $email = trim(
        (string) ($request['email'] ?? '')
    );

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $requesterName = trim(
        (string) ($request['name'] ?? '')
    );

    $context = [
        'requester_name' =>
            $requesterName !== ''
                ? $requesterName
                : 'there',
        'ticket_number' =>
            trim($ticketNumber),
        'ticket_subject' =>
            (string) ($request['subject'] ?? ''),
        'preferred_contact' =>
            trim($preferredContactLabel) !== ''
                ? trim($preferredContactLabel)
                : 'Email',
        'ticket_extra_details' =>
            trim($ticketExtraDetails),
        'support_url' =>
            'https://llamascout.com/contact.php',
    ];

    return llama_email_send_template(
        $db,
        'support_ticket_received',
        $email,
        $context,
        false,
        !empty($request['user_id'])
            ? (int) $request['user_id']
            : null
    );
}


function send_support_status_email(
    PDO $db,
    array $request,
    string $newStatus,
    string $ticketNumber
): bool {
    $email = trim(
        (string) ($request['email'] ?? '')
    );

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $templateKey = match ($newStatus) {
        'waiting' =>
            'support_ticket_waiting',
        'resolved' =>
            'support_ticket_resolved',
        default =>
            'support_ticket_reopened',
    };

    $requesterName = trim(
        (string) ($request['name'] ?? '')
    );

    $context = [
        'requester_name' =>
            $requesterName !== ''
                ? $requesterName
                : 'there',
        'ticket_number' =>
            trim($ticketNumber),
        'ticket_subject' =>
            (string) ($request['subject'] ?? ''),
        'support_url' =>
            'https://llamascout.com/contact.php',
    ];

    return llama_email_send_template(
        $db,
        $templateKey,
        $email,
        $context,
        false,
        !empty($request['user_id'])
            ? (int) $request['user_id']
            : null
    );
}



/* =========================================================
   MEMBERSHIP LIFECYCLE
   ========================================================= */

function send_membership_lifecycle_email(
    PDO $db,
    array $user,
    string $templateKey,
    array $context
): bool {
    $allowedTemplates = [
        'llamaversary',
        'membership_started',
        'membership_cancel_scheduled',
        'membership_payment_failed',
        'membership_ended',
        'complimentary_started',
        'complimentary_ending',
    ];

    if (!in_array($templateKey, $allowedTemplates, true)) {
        throw new InvalidArgumentException(
            'Unknown membership lifecycle email template.'
        );
    }

    $email = trim(
        (string) ($user['email'] ?? '')
    );

    $userId = (int) ($user['id'] ?? 0);

    if (
        $userId < 1
        || $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    return llama_email_send_template(
        $db,
        $templateKey,
        $email,
        $context,
        false,
        $userId
    );
}


/* =========================================================
   PROMOTION CAMPAIGNS
   ========================================================= */

function llama_promotion_campaign_template_key(
    string $deliveryType
): string {
    return $deliveryType === 'reminder'
        ? 'promotion_campaign_reminder'
        : 'promotion_campaign_announcement';
}


function llama_promotion_campaign_email_enabled(
    PDO $db,
    string $deliveryType
): bool {
    $template = llama_email_template(
        $db,
        llama_promotion_campaign_template_key(
            $deliveryType
        )
    );

    return
        $template !== null
        && !empty($template['enabled']);
}


function send_promotion_campaign_email(
    PDO $db,
    array $user,
    string $deliveryType,
    array $context
): bool {
    $email = trim(
        (string) ($user['email'] ?? '')
    );

    $userId = (int) ($user['id'] ?? 0);

    if (
        $userId < 1
        || $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    return llama_email_send_template(
        $db,
        llama_promotion_campaign_template_key(
            $deliveryType
        ),
        $email,
        $context,
        false,
        $userId
    );
}


/* =========================================================
   SHOP ORDER EMAIL
   ========================================================= */

function send_shop_order_confirmation_email(
    PDO $db,
    string $email,
    array $context,
    ?int $userId = null
): bool {
    $email = trim($email);

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    return llama_email_send_template(
        $db,
        'order_confirmation',
        $email,
        $context,
        false,
        $userId
    );
}


function send_shop_fulfillment_notification_email(
    PDO $db,
    string $event,
    string $email,
    array $context,
    ?int $userId = null
): bool {
    $event = strtolower(trim($event));

    $templateKey = match ($event) {
        'delivered' => 'order_delivered',
        'shipped' => 'order_shipped',
        default => '',
    };

    if ($templateKey === '') {
        throw new InvalidArgumentException(
            'Unknown Shop fulfillment email event.'
        );
    }

    $email = trim($email);

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    return llama_email_send_template(
        $db,
        $templateKey,
        $email,
        $context,
        false,
        $userId
    );
}


function send_shop_refund_confirmation_email(
    PDO $db,
    string $email,
    array $context,
    ?int $userId = null
): bool {
    $email = trim($email);

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    return llama_email_send_template(
        $db,
        'refund_confirmation',
        $email,
        $context,
        false,
        $userId
    );
}
