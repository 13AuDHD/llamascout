<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/membership-invitations.php';
require_once dirname(__DIR__) . '/app/access.php';

start_llama_session();

$db = db();

$token =
    trim(
        (string) (
            $_GET['token']
            ?? $_POST['token']
            ?? $_SESSION['complimentary_invite_token']
            ?? ''
        )
    );

$invitation = null;
$inviteStatus = null;
$error = '';
$notice = '';
$accepted = false;
$currentUser = current_user();


/* =========================================================
   TOKEN
   ========================================================= */

if (
    $token === ''
    || !preg_match(
        '/^[a-f0-9]{64}$/i',
        $token
    )
) {
    $error =
        'This complimentary membership invitation is not valid.';
} else {
    try {
        $invitation =
            llama_find_complimentary_invitation(
                $db,
                $token
            );

        if ($invitation) {
            $inviteStatus =
                llama_complimentary_invitation_status(
                    $invitation
                );
        }

        if (!$invitation) {
            $error =
                'This complimentary membership invitation could not be found.';
        } elseif (
            $inviteStatus
            === LLAMA_COMPLIMENTARY_INVITE_STATUS_REVOKED
        ) {
            $error =
                'This complimentary membership invitation has been revoked.';
        } elseif (
            $inviteStatus === 'expired'
        ) {
            $error =
                'This complimentary membership invitation has expired.';
        } elseif (
            $inviteStatus
            === LLAMA_COMPLIMENTARY_INVITE_STATUS_ACCEPTED
        ) {
            $accepted = true;
            $notice =
                'This complimentary membership invitation has already been accepted.';
        }
    } catch (Throwable $exception) {
        $reference =
            function_exists(
                'llama_log_caught_exception'
            )
                ? llama_log_caught_exception(
                    $exception,
                    'complimentary_invite_lookup'
                )
                : null;

        $error =
            function_exists(
                'llama_error_message_with_reference'
            )
                ? llama_error_message_with_reference(
                    'The invitation could not be loaded.',
                    $reference
                )
                : 'The invitation could not be loaded.';
    }
}


/* =========================================================
   SESSION HANDOFF
   ========================================================= */

if (
    $error === ''
    && !$accepted
    && $token !== ''
) {
    $_SESSION['complimentary_invite_token'] =
        $token;
}


/* =========================================================
   CURRENT USER
   ========================================================= */

$currentUserId =
    is_array($currentUser)
        ? (int) (
            $currentUser['id']
            ?? 0
        )
        : 0;

$currentEmail =
    is_array($currentUser)
        ? strtolower(
            trim(
                (string) (
                    $currentUser['email']
                    ?? ''
                )
            )
        )
        : '';

$inviteEmail =
    $invitation
        ? strtolower(
            trim(
                (string) (
                    $invitation['email']
                    ?? ''
                )
            )
        )
        : '';

$emailMatches =
    $currentEmail !== ''
    && $inviteEmail !== ''
    && hash_equals(
        $inviteEmail,
        $currentEmail
    );

$emailVerified =
    is_array($currentUser)
    && !empty(
        $currentUser['email_verified_at']
    );

$alreadyHasMemberAccess =
    $currentUserId > 0
    && user_has_member_access(
        $currentUserId
    );


/* =========================================================
   CSRF
   ========================================================= */

if (
    $currentUserId > 0
    && empty(
        $_SESSION[
            'complimentary_invite_csrf'
        ]
    )
) {
    $_SESSION[
        'complimentary_invite_csrf'
    ] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    (string) (
        $_SESSION[
            'complimentary_invite_csrf'
        ]
        ?? ''
    );


/* =========================================================
   ACCEPT
   ========================================================= */

if (
    $error === ''
    && !$accepted
    && ($_SERVER['REQUEST_METHOD'] ?? '')
        === 'POST'
) {
    try {
        if ($currentUserId < 1) {
            throw new RuntimeException(
                'Sign in before accepting this invitation.'
            );
        }

        $submittedCsrf =
            (string) (
                $_POST['csrf_token']
                ?? ''
            );

        if (
            $csrfToken === ''
            || $submittedCsrf === ''
            || !hash_equals(
                $csrfToken,
                $submittedCsrf
            )
        ) {
            throw new RuntimeException(
                'Your session token expired. Reload the page and try again.'
            );
        }

        if (!$emailMatches) {
            throw new RuntimeException(
                'This invitation was issued to a different email address.'
            );
        }

        if (!$emailVerified) {
            throw new RuntimeException(
                'Verify your email address before accepting this invitation.'
            );
        }

        if ($alreadyHasMemberAccess) {
            throw new RuntimeException(
                'This account already has member access.'
            );
        }

        $grantId =
            llama_accept_complimentary_invitation(
                $db,
                $token,
                $currentUserId
            );

        unset(
            $_SESSION[
                'complimentary_invite_token'
            ],
            $_SESSION[
                'complimentary_invite_csrf'
            ]
        );

        $accepted = true;
        $inviteStatus =
            LLAMA_COMPLIMENTARY_INVITE_STATUS_ACCEPTED;

        $notice =
            'Complimentary Complete Access is now active on your account.';

        /*
         * The membership lifecycle worker sends the separate
         * Complimentary Access Granted email. Acceptance itself
         * remains successful even if email delivery is delayed.
         */
    } catch (Throwable $exception) {
        $reference =
            function_exists(
                'llama_log_caught_exception'
            )
                ? llama_log_caught_exception(
                    $exception,
                    'complimentary_invite_accept',
                    [
                        'user_id' =>
                            $currentUserId > 0
                                ? $currentUserId
                                : null,

                        'invitation_id' =>
                            $invitation
                                ? (int) (
                                    $invitation['id']
                                    ?? 0
                                )
                                : null,
                    ],
                    [
                        RuntimeException::class,
                        InvalidArgumentException::class,
                    ]
                )
                : null;

        $error =
            $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The complimentary membership could not be activated.',
                    $reference
                );
    }
}


/* =========================================================
   DISPLAY HELPERS
   ========================================================= */

function complimentary_invite_e(
    string $value
): string {
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function complimentary_invite_date(
    ?string $value
): string {
    $value =
        trim(
            (string) $value
        );

    if ($value === '') {
        return 'Not available';
    }

    try {
        return (
            new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC')
            )
        )->format('F j, Y');
    } catch (Throwable) {
        return $value;
    }
}


$durationDays =
    $invitation
        ? max(
            1,
            (int) (
                $invitation[
                    'grant_duration_days'
                ]
                ?? 0
            )
        )
        : 0;

$expiresAt =
    $invitation
        ? complimentary_invite_date(
            $invitation['expires_at']
            ?? null
        )
        : '';

$reason =
    $invitation
        ? trim(
            (string) (
                $invitation['reason']
                ?? ''
            )
        )
        : '';

$returnPath =
    '/complimentary-invite.php?token='
    . rawurlencode($token);

$loginUrl =
    '/login.php?return='
    . rawurlencode(
        $returnPath
    );

$registerUrl =
    '/register.php?invite='
    . rawurlencode($token);

?>
<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Complimentary Membership | Llama Scout
    </title>

    <meta
        name="robots"
        content="noindex,nofollow"
    >

    <link
        rel="stylesheet"
        href="https://llamascout.com/css/site.css"
    >

    <link
        rel="stylesheet"
        href="https://llamascout.com/css/account/features/auth.css"
    >

    <link
        rel="stylesheet"
        href="https://llamascout.com/css/account/pages/complimentary-invite.css"
    >

    <script
        src="https://llamascout.com/js/accessibility.js"
    ></script>

</head>


<body class="account-auth-body">

<main class="complimentary-invite-page">

    <a
        class="complimentary-invite-logo-link"
        href="https://llamascout.com"
        aria-label="Llama Scout home"
    >
        <img
            src="https://llamascout.com/images/logo.png"
            alt="Llama Scout"
            class="account-auth-logo"
        >
    </a>


    <section class="complimentary-invite-card">

        <div class="complimentary-invite-badge">
            <i
                class="fa-solid fa-gift"
                aria-hidden="true"
            ></i>

            Complimentary Membership
        </div>


        <?php if ($accepted): ?>

            <div class="complimentary-invite-success-icon">
                <i
                    class="fa-solid fa-circle-check"
                    aria-hidden="true"
                ></i>
            </div>

            <h1>
                Complete Access is yours.
            </h1>

            <p class="complimentary-invite-intro">
                <?= complimentary_invite_e($notice) ?>
            </p>

            <div class="complimentary-invite-unlocked">

                <strong>
                    You can start using the full Llama Scout experience now.
                </strong>

                <div>
                    <span>
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        Exact Place locations
                    </span>

                    <span>
                        <i class="fa-solid fa-map" aria-hidden="true"></i>
                        Member map layers
                    </span>

                    <span>
                        <i class="fa-solid fa-images" aria-hidden="true"></i>
                        Complete galleries
                    </span>

                    <span>
                        <i class="fa-solid fa-cloud-sun" aria-hidden="true"></i>
                        Exact-location weather
                    </span>

                    <span>
                        <i class="fa-solid fa-ear-listen" aria-hidden="true"></i>
                        Sensory details
                    </span>

                    <span>
                        <i class="fa-solid fa-road" aria-hidden="true"></i>
                        Access details
                    </span>
                </div>

            </div>

            <div class="complimentary-invite-actions">

                <a
                    class="complimentary-invite-primary"
                    href="https://llamascout.com/map.php"
                >
                    Explore the Member Map
                </a>

                <a
                    class="complimentary-invite-secondary"
                    href="/"
                >
                    Go to My Account
                </a>

            </div>


        <?php elseif ($error !== ''): ?>

            <div class="complimentary-invite-error-icon">
                <i
                    class="fa-solid fa-circle-exclamation"
                    aria-hidden="true"
                ></i>
            </div>

            <h1>
                Invitation unavailable
            </h1>

            <div
                class="complimentary-invite-notice is-error"
                role="alert"
            >
                <?= complimentary_invite_e($error) ?>
            </div>

            <p class="complimentary-invite-intro">
                If you believe this invitation should still be available,
                contact Llama Scout and include the email address the
                invitation was sent to.
            </p>

            <div class="complimentary-invite-actions">
                <a
                    class="complimentary-invite-secondary"
                    href="https://llamascout.com"
                >
                    Return to Llama Scout
                </a>
            </div>


        <?php else: ?>

            <h1>
                You’ve been invited.
            </h1>

            <p class="complimentary-invite-intro">
                Llama Scout has reserved
                <strong><?= number_format($durationDays) ?> days</strong>
                of Complete Access for
                <strong><?= complimentary_invite_e($inviteEmail) ?></strong>.
            </p>


            <div class="complimentary-invite-details">

                <div>
                    <span>Complete Access</span>
                    <strong>
                        <?= number_format($durationDays) ?>
                        days
                    </strong>
                </div>

                <div>
                    <span>Invitation expires</span>
                    <strong>
                        <?= complimentary_invite_e($expiresAt) ?>
                    </strong>
                </div>

                <?php if ($reason !== ''): ?>
                    <div class="is-wide">
                        <span>Invitation</span>
                        <strong>
                            <?= complimentary_invite_e($reason) ?>
                        </strong>
                    </div>
                <?php endif; ?>

            </div>


            <div class="complimentary-invite-benefits">

                <h2>
                    What Complete Access unlocks
                </h2>

                <div>
                    <span>
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        Exact coordinates and Place locations
                    </span>

                    <span>
                        <i class="fa-solid fa-map" aria-hidden="true"></i>
                        Exact pins, street-level zoom, and member map layers
                    </span>

                    <span>
                        <i class="fa-solid fa-images" aria-hidden="true"></i>
                        Complete Place photo galleries
                    </span>

                    <span>
                        <i class="fa-solid fa-road" aria-hidden="true"></i>
                        Full road, vehicle, and access details
                    </span>

                    <span>
                        <i class="fa-solid fa-ear-listen" aria-hidden="true"></i>
                        Sensory conditions and Scout Notes
                    </span>

                    <span>
                        <i class="fa-solid fa-cloud-sun" aria-hidden="true"></i>
                        Exact-location weather and 5-day forecast
                    </span>
                </div>

            </div>


            <?php if ($currentUserId < 1): ?>

                <div class="complimentary-invite-account-state">

                    <strong>
                        Already have a Llama Scout account?
                    </strong>

                    <p>
                        Sign in with
                        <?= complimentary_invite_e($inviteEmail) ?>
                        to accept the invitation.
                    </p>

                </div>

                <div class="complimentary-invite-actions">

                    <a
                        class="complimentary-invite-primary"
                        href="<?= complimentary_invite_e($loginUrl) ?>"
                    >
                        Sign In to Accept
                    </a>

                    <a
                        class="complimentary-invite-secondary"
                        href="<?= complimentary_invite_e($registerUrl) ?>"
                    >
                        Create an Account
                    </a>

                </div>


            <?php elseif (!$emailMatches): ?>

                <div
                    class="complimentary-invite-notice is-error"
                    role="alert"
                >
                    You are signed in as
                    <strong><?= complimentary_invite_e($currentEmail) ?></strong>,
                    but this invitation belongs to
                    <strong><?= complimentary_invite_e($inviteEmail) ?></strong>.
                </div>

                <div class="complimentary-invite-actions">

                    <a
                        class="complimentary-invite-secondary"
                        href="/logout.php"
                    >
                        Sign Out
                    </a>

                </div>


            <?php elseif (!$emailVerified): ?>

                <div class="complimentary-invite-account-state">

                    <strong>
                        One step first.
                    </strong>

                    <p>
                        Verify
                        <?= complimentary_invite_e($currentEmail) ?>
                        before activating complimentary access.
                    </p>

                </div>

                <div class="complimentary-invite-actions">

                    <a
                        class="complimentary-invite-primary"
                        href="/verify-email.php"
                    >
                        Verify My Email
                    </a>

                </div>


            <?php elseif ($alreadyHasMemberAccess): ?>

                <div class="complimentary-invite-account-state">

                    <strong>
                        You already have Complete Access.
                    </strong>

                    <p>
                        This invitation cannot be stacked onto an
                        active membership or complimentary grant.
                    </p>

                </div>

                <div class="complimentary-invite-actions">

                    <a
                        class="complimentary-invite-primary"
                        href="/"
                    >
                        Go to My Account
                    </a>

                </div>


            <?php else: ?>

                <form
                    method="post"
                    class="complimentary-invite-accept-form"
                >

                    <input
                        type="hidden"
                        name="token"
                        value="<?= complimentary_invite_e($token) ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= complimentary_invite_e($csrfToken) ?>"
                    >

                    <div class="complimentary-invite-account-state">

                        <strong>
                            Ready to activate
                        </strong>

                        <p>
                            This will add
                            <?= number_format($durationDays) ?>
                            days of complimentary Complete Access to
                            <?= complimentary_invite_e($currentEmail) ?>.
                        </p>

                    </div>

                    <button
                        class="complimentary-invite-primary"
                        type="submit"
                    >
                        <i
                            class="fa-solid fa-gift"
                            aria-hidden="true"
                        ></i>

                        Activate Complete Access
                    </button>

                </form>

            <?php endif; ?>

        <?php endif; ?>

    </section>


    <p class="complimentary-invite-footer">
        Llama Scout · know the place before you go
    </p>

</main>

</body>

</html>
