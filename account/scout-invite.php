<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/scout-onboarding.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$db = db();

$profile =
    llama_scout_onboarding_profile(
        $db,
        $userId
    );

if (!$profile) {
    header('Location: /', true, 303);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !llama_scout_onboarding_verify_csrf(
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
                (string) (
                    $_POST['action']
                    ?? ''
                );

            if ($action === 'accept') {
                llama_scout_accept_invitation(
                    $db,
                    $userId
                );

                header(
                    'Location: /scout-application.php',
                    true,
                    303
                );
                exit;
            }

            if ($action === 'decline') {
                llama_scout_decline_invitation(
                    $db,
                    $userId
                );

                header(
                    'Location: /scout-invite.php?declined=1',
                    true,
                    303
                );
                exit;
            }
        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();
        }
    }

    $profile =
        llama_scout_onboarding_profile(
            $db,
            $userId
        );
}

$status =
    (string) $profile['status'];

if ($status === 'application_started') {
    header(
        'Location: /scout-application.php',
        true,
        303
    );
    exit;
}

if (
    in_array(
        $status,
        [
            'application_submitted',
            'training',
            'pending_approval',
        ],
        true
    )
) {
    header(
        'Location: /scout-training.php',
        true,
        303
    );
    exit;
}

$expired =
    llama_scout_invitation_expired(
        $profile
    );

$pageTitle =
    'Scout Invitation | Llama Scout';

require dirname(__DIR__) . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/account/features/scout-onboarding.css"
>

<section class="account-scout-page">

<div class="account-scout-shell">

<a class="account-scout-back" href="/">
    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
    My account
</a>

<header class="account-scout-hero">
    <p class="account-eyebrow">Step 1 of 5</p>
    <h1>You're invited to become a Llama Scout</h1>
    <p>
        Llama Scouts help keep Place information useful, current,
        and grounded in real field experience.
    </p>
</header>

<?php if ($error !== ''): ?>
    <div class="account-scout-notice is-error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($status === 'declined'): ?>

<section class="account-scout-panel">
    <div class="account-scout-status-icon">
        <i class="fa-solid fa-circle-minus" aria-hidden="true"></i>
    </div>
    <h2>Invitation declined</h2>
    <p>
        You declined this Scout invitation. Your regular Llama Scout
        account and community contributions are unchanged.
    </p>
</section>

<?php elseif ($expired): ?>

<section class="account-scout-panel">
    <div class="account-scout-status-icon">
        <i class="fa-solid fa-clock" aria-hidden="true"></i>
    </div>
    <h2>This invitation has expired</h2>
    <p>
        Scout invitations are open for 30 days. Basecamp can send
        you a new invitation if the opportunity is still available.
    </p>
</section>

<?php else: ?>

<div class="account-scout-grid">

<section class="account-scout-panel">
    <p class="account-eyebrow">What Scouts do</p>
    <h2>Field knowledge that helps other people plan</h2>

    <div class="account-scout-feature-list">
        <div>
            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
            <span>
                <strong>Scout Places</strong>
                Add new Places you've personally visited and improve existing records.
            </span>
        </div>
        <div>
            <i class="fa-solid fa-camera" aria-hidden="true"></i>
            <span>
                <strong>Document conditions</strong>
                Photos, access information, sensory details, amenities, and current conditions.
            </span>
        </div>
        <div>
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <span>
                <strong>Help protect accuracy</strong>
                Scouts are trusted contributors, but Scout observations remain transparent and attributable.
            </span>
        </div>
    </div>
</section>

<section class="account-scout-panel">
    <p class="account-eyebrow">Scout access</p>
    <h2>What comes with active Scout status</h2>
    <p>
        Active Scouts receive Scout tools, profile recognition,
        and complimentary full Llama Scout access while Scout status
        remains active.
    </p>
    <p>
        Scout status is maintained through ongoing approved new Place
        contributions under the current Scout policy.
    </p>
</section>

</div>

<section class="account-scout-panel account-scout-decision">
    <div>
        <p class="account-eyebrow">Your choice</p>
        <h2>Would you like to continue?</h2>
        <p>
            Accepting starts the About You application. You can review
            the rest of onboarding before becoming an active Scout.
        </p>
        <small>
            Invitation expires:
            <?= htmlspecialchars(
                (string) (
                    $profile['invitation_expires_at']
                    ?: 'No expiration'
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </small>
    </div>

    <div class="account-scout-decision-actions">
        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    llama_scout_onboarding_csrf_token(),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
            <input
                type="hidden"
                name="action"
                value="accept"
            >
            <button
                class="account-scout-button"
                type="submit"
            >
                Accept invitation
            </button>
        </form>

        <form
            method="post"
            onsubmit="return confirm('Decline this Scout invitation?');"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    llama_scout_onboarding_csrf_token(),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
            <input
                type="hidden"
                name="action"
                value="decline"
            >
            <button
                class="account-scout-button is-secondary"
                type="submit"
            >
                Decline
            </button>
        </form>
    </div>
</section>

<?php endif; ?>

</div>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
