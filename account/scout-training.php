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
            $reference = llama_log_caught_exception(
                $exception,
                'account.scout_training',
                ['user_id' => $userId],
                [InvalidArgumentException::class, RuntimeException::class]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference('Scout training could not be saved.', $reference);
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
    href="https://llamascout.com/css/account/features/scout-onboarding.css"
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
    data-scout-training-form
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
        name="video_watched"
        value=""
        data-scout-video-watched
    >

    <section class="account-scout-panel account-scout-video-panel">
        <p class="account-eyebrow">Scout orientation</p>
        <h2>Watch the complete Scout training video</h2>
        <p>
            This orientation covers what Llama Scout expects in the field,
            how observations should be collected, and the limits of Scout
            authority. The final production video is expected to run about
            four to five minutes. The current file may be a shorter test video.
        </p>

        <div class="account-scout-video-wrap">
            <video
                controls
                playsinline
                preload="metadata"
                data-scout-training-video
            >
                <source
                    src="https://llamascout.com/videos/scout-training.mp4"
                    type="video/mp4"
                >
                Your browser does not support the Scout training video.
            </video>
        </div>

        <div
            class="account-scout-video-status"
            data-scout-video-status
            role="status"
        >
            <i class="fa-solid fa-circle-play" aria-hidden="true"></i>
            <span>Watch the video through to the end to unlock the final acknowledgements.</span>
        </div>
    </section>

    <section class="account-scout-panel">
        <p class="account-eyebrow">Rules + commitments</p>
        <h2>What you agree to as a Llama Scout</h2>
        <p>
            These commitments apply whenever you are acting as a Llama Scout
            or submitting observations gathered for a Scout Report. Official
            land-manager rules, laws, closures, posted instructions, and local
            requirements always take priority over Llama Scout guidance.
        </p>

        <div class="account-scout-check-list account-scout-training-checks">

            <label>
                <input type="checkbox" name="ack_local_rules" value="1" required>
                <span>
                    <strong>Official rules come first</strong>
                    I will follow all applicable local laws, closures, posted
                    instructions, land-manager rules, regulations, and guidelines
                    while Scouting a location. When they differ from Llama Scout
                    guidance, the official rule or instruction supersedes Llama Scout.
                </span>
            </label>

            <label>
                <input type="checkbox" name="ack_positive_example" value="1" required>
                <span>
                    <strong>Represent Llama Scout responsibly</strong>
                    I will act respectfully, responsibly, and lawfully; I will set a
                    positive example for Llama Scout and will not claim authority I
                    do not have over visitors, property owners, agencies, or land managers.
                </span>
            </label>

            <label>
                <input type="checkbox" name="ack_no_boosters" value="1" required>
                <span>
                    <strong>Test reception honestly</strong>
                    I will not use a cellular booster, repeater, signal amplifier,
                    or other power-boosting equipment when testing cellular or
                    satellite reception for a Scout Report. Reception results should
                    reflect ordinary service available at the location.
                </span>
            </label>

            <label>
                <input type="checkbox" name="ack_accuracy" value="1" required>
                <span>
                    <strong>Observed beats assumed</strong>
                    I will report what I personally observed, separate facts from
                    estimates or assumptions, include relevant visit conditions and
                    dates, and use Unknown rather than inventing information.
                </span>
            </label>

            <label>
                <input type="checkbox" name="ack_safety" value="1" required>
                <span>
                    <strong>Safety, privacy + stewardship</strong>
                    I will respect private property, closures, other visitors, wildlife,
                    and the environment. I understand that a Scout Report documents
                    conditions observed during a visit and is not a guarantee of future
                    access, road conditions, safety, or suitability for another person.
                </span>
            </label>

            <label class="account-scout-video-confirm is-locked" data-scout-video-confirm>
                <input
                    type="checkbox"
                    name="video_confirmed"
                    value="1"
                    required
                    disabled
                    data-scout-video-confirm-checkbox
                >
                <span>
                    <strong>I watched the entire training video</strong>
                    I confirm that I watched the Scout orientation video through to
                    the end before accepting these commitments.
                </span>
            </label>

            <label>
                <input type="checkbox" name="ack_privilege" value="1" required>
                <span>
                    <strong>Scout status is a privilege</strong>
                    I understand that becoming or remaining a Llama Scout is not an
                    entitlement. Scout participation may be ended by me or by Llama
                    Scout, and Scout access may be suspended or terminated when the
                    Scout program requirements or standards are not met.
                </span>
            </label>

            <label>
                <input type="checkbox" name="ack_no_employment" value="1" required>
                <span>
                    <strong>This is not employment</strong>
                    I understand that Scout participation is voluntary and is not a
                    job offer or employment relationship. I will not receive wages or
                    monetary compensation for my time, travel, efforts, or ordinary
                    Scout contributions unless Llama Scout separately agrees in writing
                    to a specific paid arrangement.
                </span>
            </label>

        </div>
    </section>

    <div class="account-scout-form-actions">
        <button
            class="account-scout-button"
            type="submit"
            data-scout-training-submit
            disabled
        >
            Finish training and submit for approval
        </button>
    </div>

</form>

<script>
(() => {
    const form = document.querySelector('[data-scout-training-form]');
    if (!form) return;

    const video = form.querySelector('[data-scout-training-video]');
    const watched = form.querySelector('[data-scout-video-watched]');
    const confirmBox = form.querySelector('[data-scout-video-confirm-checkbox]');
    const confirmLabel = form.querySelector('[data-scout-video-confirm]');
    const status = form.querySelector('[data-scout-video-status]');
    const submit = form.querySelector('[data-scout-training-submit]');
    const unlockVideoConfirmation = () => {
        watched.value = '1';
        confirmBox.disabled = false;
        confirmLabel.classList.remove('is-locked');
        status.classList.add('is-complete');
        status.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Video complete. Finish the acknowledgements below.</span>';
        refreshSubmit();
    };

    const refreshSubmit = () => {
        const required = [...form.querySelectorAll('input[type="checkbox"][required]')];
        submit.disabled = watched.value !== '1' || required.some((box) => box.disabled || !box.checked);
    };

    video?.addEventListener('ended', unlockVideoConfirmation);
    form.addEventListener('change', refreshSubmit);
    refreshSubmit();
})();
</script>

<?php endif; ?>

</div>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
