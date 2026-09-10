<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/mail.php';
require_once dirname(__DIR__) . '/app/membership-invitations.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$actorUserId =
    (int) (
        $adminUser['id']
        ?? 0
    );

$notice = '';
$warning = '';
$error = '';
$oneTimeInviteUrl = '';

$stats =
    admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle =
    'Complimentary Invitations';

$adminPageEyebrow =
    'Communications';

$adminActiveNav =
    'complimentary-invitations';


function complimentary_admin_invitation_by_id(
    PDO $db,
    int $invitationId
): ?array {
    if ($invitationId < 1) {
        return null;
    }

    $stmt =
        $db->prepare(
            'SELECT
                i.*,
                inviter.username
                    AS inviter_username,
                inviter.display_name
                    AS inviter_display_name,
                accepted_user.username
                    AS accepted_username,
                accepted_user.display_name
                    AS accepted_display_name
             FROM membership_invitations i
             LEFT JOIN users inviter
                ON inviter.id = i.invited_by
             LEFT JOIN users accepted_user
                ON accepted_user.id = i.accepted_by
             WHERE i.id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $invitationId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return $row ?: null;
}


function complimentary_admin_invite_url(
    string $token
): string {
    return
        'https://account.llamascout.com/complimentary-invite.php?token='
        . rawurlencode($token);
}


function complimentary_admin_send(
    PDO $db,
    array $created,
    array $stored = []
): bool {
    return
        send_complimentary_invitation_email(
            $db,
            array_merge(
                $stored,
                $created
            ),
            (string) $created['token']
        );
}


/* =========================================================
   POST ACTIONS
   ========================================================= */

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
) {
    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action =
                trim(
                    (string) (
                        $_POST['invite_action']
                        ?? ''
                    )
                );

            if ($action === 'create') {
                $email =
                    (string) (
                        $_POST['email']
                        ?? ''
                    );

                $durationDays =
                    (int) (
                        $_POST['duration_days']
                        ?? 0
                    );

                $expiresInDays =
                    (int) (
                        $_POST['expires_in_days']
                        ?? 14
                    );

                $reason =
                    trim(
                        (string) (
                            $_POST['reason']
                            ?? ''
                        )
                    );

                $notes =
                    trim(
                        (string) (
                            $_POST['notes']
                            ?? ''
                        )
                    );

                $created =
                    llama_create_complimentary_invitation(
                        $db,
                        $email,
                        $durationDays,
                        $reason !== ''
                            ? $reason
                            : null,
                        $notes !== ''
                            ? $notes
                            : null,
                        $actorUserId,
                        $expiresInDays
                    );

                $stored =
                    complimentary_admin_invitation_by_id(
                        $db,
                        (int) $created['id']
                    )
                    ?: [];

                $oneTimeInviteUrl =
                    complimentary_admin_invite_url(
                        (string) $created['token']
                    );

                try {
                    $sent =
                        complimentary_admin_send(
                            $db,
                            $created,
                            $stored
                        );

                    if ($sent) {
                        $notice =
                            'Complimentary invitation created and emailed to '
                            . $created['email']
                            . '.';
                    } else {
                        $warning =
                            'The invitation was created, but the email template is disabled or mail delivery failed. Copy the one-time invitation link below.';
                    }
                } catch (Throwable $mailException) {
                    $warning =
                        'The invitation was created, but its email could not be sent. Copy the one-time invitation link below.';

                    llama_log_caught_exception(
                        $mailException,
                        'admin.complimentary_invitation_email',
                        [
                            'invitation_id' =>
                                (int) $created['id'],
                        ]
                    );
                }

            } elseif ($action === 'resend') {
                $invitationId =
                    (int) (
                        $_POST['invitation_id']
                        ?? 0
                    );

                $old =
                    complimentary_admin_invitation_by_id(
                        $db,
                        $invitationId
                    );

                if (!$old) {
                    throw new InvalidArgumentException(
                        'Invitation not found.'
                    );
                }

                $status =
                    llama_complimentary_invitation_status(
                        $old
                    );

                if (
                    $status
                    === LLAMA_COMPLIMENTARY_INVITE_STATUS_ACCEPTED
                ) {
                    throw new InvalidArgumentException(
                        'An accepted invitation cannot be resent.'
                    );
                }

                $expiresInDays =
                    max(
                        1,
                        min(
                            90,
                            (int) (
                                $_POST['expires_in_days']
                                ?? LLAMA_COMPLIMENTARY_INVITE_DEFAULT_EXPIRY_DAYS
                            )
                        )
                    );

                /*
                 * Creating a fresh invitation automatically revokes any
                 * still-pending invitation for this same email. The old
                 * raw token cannot be recovered by design.
                 */
                $created =
                    llama_create_complimentary_invitation(
                        $db,
                        (string) $old['email'],
                        (int) $old['grant_duration_days'],
                        $old['reason'] !== null
                            ? (string) $old['reason']
                            : null,
                        $old['notes'] !== null
                            ? (string) $old['notes']
                            : null,
                        $actorUserId,
                        $expiresInDays
                    );

                $stored =
                    complimentary_admin_invitation_by_id(
                        $db,
                        (int) $created['id']
                    )
                    ?: [];

                $oneTimeInviteUrl =
                    complimentary_admin_invite_url(
                        (string) $created['token']
                    );

                try {
                    $sent =
                        complimentary_admin_send(
                            $db,
                            $created,
                            $stored
                        );

                    if ($sent) {
                        $notice =
                            'A fresh invitation was created and emailed to '
                            . $created['email']
                            . '. The previous pending link is no longer valid.';
                    } else {
                        $warning =
                            'A fresh invitation was created, but email delivery failed. Copy the new one-time link below.';
                    }
                } catch (Throwable $mailException) {
                    $warning =
                        'A fresh invitation was created, but its email could not be sent. Copy the new one-time link below.';

                    llama_log_caught_exception(
                        $mailException,
                        'admin.complimentary_invitation_resend_email',
                        [
                            'old_invitation_id' =>
                                $invitationId,

                            'new_invitation_id' =>
                                (int) $created['id'],
                        ]
                    );
                }

            } elseif ($action === 'revoke') {
                $invitationId =
                    (int) (
                        $_POST['invitation_id']
                        ?? 0
                    );

                $reason =
                    trim(
                        (string) (
                            $_POST['revoke_reason']
                            ?? ''
                        )
                    );

                llama_revoke_complimentary_invitation(
                    $db,
                    $invitationId,
                    $actorUserId,
                    $reason
                );

                $notice =
                    'Complimentary invitation revoked.';
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.complimentary_invitation_action',
                    [],
                    [
                        InvalidArgumentException::class,
                        RuntimeException::class,
                    ]
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The invitation action could not be completed.',
                        $reference
                    );
        }
    }
}


/* =========================================================
   LIST
   ========================================================= */

$invitations = [];

try {
    llama_ensure_membership_invitation_storage(
        $db
    );

    $stmt =
        $db->query(
            'SELECT
                i.*,
                inviter.username
                    AS inviter_username,
                inviter.display_name
                    AS inviter_display_name,
                accepted_user.username
                    AS accepted_username,
                accepted_user.display_name
                    AS accepted_display_name
             FROM membership_invitations i
             LEFT JOIN users inviter
                ON inviter.id = i.invited_by
             LEFT JOIN users accepted_user
                ON accepted_user.id = i.accepted_by
             ORDER BY i.id DESC
             LIMIT 250'
        );

    $invitations =
        $stmt
            ? (
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: []
            )
            : [];
} catch (Throwable $exception) {
    $reference =
        llama_log_caught_exception(
            $exception,
            'admin.complimentary_invitation_list'
        );

    $error =
        llama_error_message_with_reference(
            'Complimentary invitations could not be loaded.',
            $reference
        );
}


$inviteCounts = [
    'pending' => 0,
    'accepted' => 0,
    'expired' => 0,
    'revoked' => 0,
];

foreach ($invitations as $invitation) {
    $status =
        llama_complimentary_invitation_status(
            $invitation
        );

    if (isset($inviteCounts[$status])) {
        $inviteCounts[$status]++;
    }
}


require __DIR__ . '/_header.php';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/admin/pages/complimentary-invitations.css"
>


<?php if ($notice !== ''): ?>
    <div class="admin-user-notice is-success">
        <?= moderation_e($notice) ?>
    </div>
<?php endif; ?>


<?php if ($warning !== ''): ?>
    <div class="admin-user-notice">
        <?= moderation_e($warning) ?>
    </div>
<?php endif; ?>


<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>


<?php if ($oneTimeInviteUrl !== ''): ?>

    <section class="admin-panel complimentary-invite-link-panel">

        <header class="admin-panel-header">
            <div>
                <p>One-Time Link</p>
                <h2>Invitation URL</h2>
            </div>

            <span>
                Shown only on this request
            </span>
        </header>

        <div class="complimentary-invite-link-body">

            <p>
                Llama Scout stores only the token hash. Once you leave
                this page, this exact raw invitation link cannot be
                recovered.
            </p>

            <input
                type="text"
                readonly
                value="<?= moderation_e($oneTimeInviteUrl) ?>"
                onclick="this.select();"
            >

        </div>

    </section>

<?php endif; ?>


<section class="complimentary-invite-stats">

    <div>
        <span>Pending</span>
        <strong><?= number_format($inviteCounts['pending']) ?></strong>
    </div>

    <div>
        <span>Accepted</span>
        <strong><?= number_format($inviteCounts['accepted']) ?></strong>
    </div>

    <div>
        <span>Expired</span>
        <strong><?= number_format($inviteCounts['expired']) ?></strong>
    </div>

    <div>
        <span>Revoked</span>
        <strong><?= number_format($inviteCounts['revoked']) ?></strong>
    </div>

</section>


<div class="complimentary-invite-admin-grid">

    <section class="admin-panel">

        <header class="admin-panel-header">
            <div>
                <p>New Invitation</p>
                <h2>Grant Complete Access</h2>
            </div>

            <a
                class="admin-button is-secondary"
                href="/emails.php?template=complimentary_invitation"
            >
                Edit Email
            </a>
        </header>

        <form
            class="complimentary-invite-form"
            method="post"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= moderation_e(moderation_csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="invite_action"
                value="create"
            >

            <label>
                <span>Email address</span>

                <input
                    type="email"
                    name="email"
                    maxlength="254"
                    required
                >
            </label>

            <div class="complimentary-invite-form-grid">

                <label>
                    <span>Complete Access duration</span>

                    <div class="complimentary-number-field">
                        <input
                            type="number"
                            name="duration_days"
                            value="90"
                            min="1"
                            max="3660"
                            required
                        >
                        <small>days</small>
                    </div>
                </label>

                <label>
                    <span>Invitation expires</span>

                    <div class="complimentary-number-field">
                        <input
                            type="number"
                            name="expires_in_days"
                            value="14"
                            min="1"
                            max="90"
                            required
                        >
                        <small>days</small>
                    </div>
                </label>

            </div>

            <label>
                <span>Reason shown to recipient</span>

                <input
                    type="text"
                    name="reason"
                    maxlength="255"
                    placeholder="Example: Thanks for helping us test Llama Scout."
                >
            </label>

            <label>
                <span>Private Admin notes</span>

                <textarea
                    name="notes"
                    rows="5"
                    maxlength="5000"
                    placeholder="Not included in the email."
                ></textarea>
            </label>

            <button
                class="admin-button"
                type="submit"
            >
                <i
                    class="fa-solid fa-paper-plane"
                    aria-hidden="true"
                ></i>

                Create + Send Invitation
            </button>

        </form>

    </section>


    <section class="admin-panel">

        <header class="admin-panel-header">
            <div>
                <p>History</p>
                <h2>Complimentary Invitations</h2>
            </div>

            <span>
                Latest 250
            </span>
        </header>

        <?php if (!$invitations): ?>

            <div class="admin-empty-state">
                <p>No complimentary invitations yet.</p>
            </div>

        <?php else: ?>

            <div class="complimentary-invite-list">

                <?php foreach ($invitations as $invitation): ?>
                    <?php
                    $status =
                        llama_complimentary_invitation_status(
                            $invitation
                        );

                    $statusLabel =
                        match ($status) {
                            'pending' => 'Pending',
                            'accepted' => 'Accepted',
                            'revoked' => 'Revoked',
                            default => 'Expired',
                        };

                    $inviter =
                        trim(
                            (string) (
                                $invitation['inviter_display_name']
                                ?: $invitation['inviter_username']
                                ?: ''
                            )
                        );
                    ?>

                    <article class="complimentary-invite-row">

                        <div class="complimentary-invite-row-heading">

                            <div>
                                <strong>
                                    <?= moderation_e(
                                        (string) $invitation['email']
                                    ) ?>
                                </strong>

                                <small>
                                    <?= number_format(
                                        (int) $invitation['grant_duration_days']
                                    ) ?>
                                    days Complete Access
                                </small>
                            </div>

                            <span
                                class="complimentary-invite-status is-<?= moderation_e($status) ?>"
                            >
                                <?= moderation_e($statusLabel) ?>
                            </span>

                        </div>

                        <dl>
                            <div>
                                <dt>Created</dt>
                                <dd>
                                    <?= moderation_e(
                                        llama_format_viewer_datetime(
                                            (string) $invitation['created_at']
                                        )
                                    ) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Expires</dt>
                                <dd>
                                    <?= moderation_e(
                                        llama_format_viewer_datetime(
                                            (string) $invitation['expires_at']
                                        )
                                    ) ?>
                                </dd>
                            </div>

                            <?php if ($inviter !== ''): ?>
                                <div>
                                    <dt>Invited by</dt>
                                    <dd><?= moderation_e($inviter) ?></dd>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($invitation['reason'])): ?>
                                <div class="is-wide">
                                    <dt>Reason</dt>
                                    <dd>
                                        <?= moderation_e(
                                            (string) $invitation['reason']
                                        ) ?>
                                    </dd>
                                </div>
                            <?php endif; ?>

                        </dl>


                        <?php if ($status !== 'accepted'): ?>

                            <div class="complimentary-invite-row-actions">

                                <form method="post">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= moderation_e(moderation_csrf_token()) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="invite_action"
                                        value="resend"
                                    >

                                    <input
                                        type="hidden"
                                        name="invitation_id"
                                        value="<?= (int) $invitation['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="expires_in_days"
                                        value="14"
                                    >

                                    <button
                                        class="admin-button is-secondary"
                                        type="submit"
                                    >
                                        <i
                                            class="fa-solid fa-rotate"
                                            aria-hidden="true"
                                        ></i>

                                        Send Fresh Invitation
                                    </button>
                                </form>


                                <?php if ($status === 'pending'): ?>

                                    <form
                                        method="post"
                                        class="complimentary-revoke-form"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= moderation_e(moderation_csrf_token()) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="invite_action"
                                            value="revoke"
                                        >

                                        <input
                                            type="hidden"
                                            name="invitation_id"
                                            value="<?= (int) $invitation['id'] ?>"
                                        >

                                        <input
                                            type="text"
                                            name="revoke_reason"
                                            maxlength="255"
                                            placeholder="Revocation reason"
                                            required
                                        >

                                        <button
                                            class="admin-button is-secondary"
                                            type="submit"
                                        >
                                            Revoke
                                        </button>
                                    </form>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</div>


<?php require __DIR__ . '/_footer.php'; ?>
