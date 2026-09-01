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

$status =
    (string) $profile['status'];

if ($status === 'invited') {
    header('Location: /scout-invite.php', true, 303);
    exit;
}

if ($status === 'application_started') {
    header('Location: /scout-application.php', true, 303);
    exit;
}

if (
    !in_array(
        $status,
        [
            'application_submitted',
            'training',
            'pending_approval',
        ],
        true
    )
) {
    header('Location: /', true, 303);
    exit;
}

$training =
    llama_scout_begin_training(
        $db,
        $userId
    );

$profile =
    llama_scout_onboarding_profile(
        $db,
        $userId
    );

$status =
    (string) $profile['status'];

$error = '';

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
    && $status === 'training'
) {
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
            llama_scout_complete_training(
                $db,
                $userId,
                $_POST
            );

            header(
                'Location: /scout-training.php?complete=1',
                true,
                303
            );
            exit;
        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();
        }
    }
}

$profile =
    llama_scout_onboarding_profile(
        $db,
        $userId
    );

$status =
    (string) $profile['status'];

$pageTitle =
    'Scout Training | Llama Scout';

require dirname(__DIR__) . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/account-scout-onboarding.css"
>

<section class="account-scout-page">

<div class="account-scout-shell">

<a class="account-scout-back" href="/">
    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
    My account
</a>

<header class="account-scout-hero">
    <p class="account-eyebrow">
        <?= $status === 'pending_approval'
            ? 'Step 4 of 5'
            : 'Step 3 of 5' ?>
    </p>

    <h1>
        <?= $status === 'pending_approval'
            ? 'Onboarding complete'
            : 'Scout Training' ?>
    </h1>

    <p>
        <?= $status === 'pending_approval'
            ? 'Your application and training are complete. Basecamp will review your onboarding before Scout access becomes active.'
            : 'Review the Scout operating expectations below and acknowledge each section before finishing training.' ?>
    </p>
</header>

<?php if ($error !== ''): ?>
    <div class="account-scout-notice is-error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($status === 'pending_approval'): ?>

<section class="account-scout-panel account-scout-awaiting">
    <div class="account-scout-status-icon is-good">
        <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
    </div>

    <h2>Awaiting Basecamp approval</h2>

    <p>
        There is nothing else you need to submit right now.
        An Admin can review your About You application and training
        acknowledgements from Basecamp.
    </p>

    <div class="account-scout-progress-list">
        <div class="is-complete">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <span><strong>Invitation accepted</strong></span>
        </div>
        <div class="is-complete">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <span><strong>About You submitted</strong></span>
        </div>
        <div class="is-complete">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <span><strong>Scout training complete</strong></span>
        </div>
        <div class="is-current">
            <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
            <span><strong>Basecamp review</strong></span>
        </div>
    </div>

    <a class="account-scout-button is-secondary" href="/">
        Return to my account
    </a>
</section>

<?php else: ?>

<form
    method="post"
    class="account-scout-form"
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

    <section class="account-scout-panel">
        <p class="account-eyebrow">1. Tools + access</p>
        <h2>Scout access is trust, not a private-data shortcut</h2>
        <p>
            Scout tools exist to improve Place information and support
            field contributions. Member-only data, precise coordinates,
            moderation information, and private account information must
            not be copied into public posts or shared outside their intended
            context.
        </p>
        <p>
            When you contribute to a Place, identify what you personally
            observed and use the normal community workflow for later corrections.
        </p>
    </section>

    <section class="account-scout-panel">
        <p class="account-eyebrow">2. Accuracy</p>
        <h2>Unknown is better than invented</h2>
        <p>
            Do not guess just to fill a field. Conditions change, and a Scout
            Report should communicate uncertainty rather than creating false
            confidence. Dates, access, closures, road conditions, amenities,
            connectivity, and sensory observations should reflect what you
            actually know.
        </p>
    </section>

    <section class="account-scout-panel">
        <p class="account-eyebrow">3. Safety</p>
        <h2>A Scout Report does not guarantee conditions</h2>
        <p>
            Outdoor access can change after a visit. Weather, fire, flooding,
            snow, gates, land-management rules, road damage, and vehicle
            capability all matter. Record conditions carefully and avoid
            language that promises another person can safely reach a Place.
        </p>
    </section>

    <section class="account-scout-panel">
        <p class="account-eyebrow">4. Privacy</p>
        <h2>Protect people as carefully as Place data</h2>
        <p>
            Do not publish private personal information about other visitors,
            residents, property owners, or community members. Photos should
            document the Place rather than expose people unnecessarily.
            Llama Scout removes embedded photo metadata through its normal
            upload pipeline.
        </p>
    </section>

    <section class="account-scout-panel">
        <p class="account-eyebrow">Acknowledgements</p>
        <h2>Finish Scout training</h2>

        <div class="account-scout-check-list">

            <label>
                <input
                    type="checkbox"
                    name="acknowledged_tools"
                    value="1"
                    required
                >
                <span>
                    <strong>Scout tools + access</strong>
                    I understand how Scout access should be used.
                </span>
            </label>

            <label>
                <input
                    type="checkbox"
                    name="acknowledged_accuracy"
                    value="1"
                    required
                >
                <span>
                    <strong>Accuracy</strong>
                    I will separate observations from assumptions and use
                    Unknown when I genuinely do not know.
                </span>
            </label>

            <label>
                <input
                    type="checkbox"
                    name="acknowledged_safety"
                    value="1"
                    required
                >
                <span>
                    <strong>Safety</strong>
                    I understand that conditions change and Scout Reports
                    are not guarantees of access or safety.
                </span>
            </label>

            <label>
                <input
                    type="checkbox"
                    name="acknowledged_privacy"
                    value="1"
                    required
                >
                <span>
                    <strong>Privacy</strong>
                    I will protect private member information and avoid
                    unnecessarily identifying people in field contributions.
                </span>
            </label>

        </div>
    </section>

    <div class="account-scout-form-actions">
        <button
            class="account-scout-button"
            type="submit"
        >
            Finish training and submit for approval
        </button>
    </div>

</form>

<?php endif; ?>

</div>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
