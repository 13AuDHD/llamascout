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
    header('Location: /scout-training.php', true, 303);
    exit;
}

if ($status !== 'application_started') {
    header('Location: /', true, 303);
    exit;
}

$application =
    llama_scout_onboarding_application(
        $db,
        (int) $profile['id'],
        $userId
    );

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
            llama_scout_save_application(
                $db,
                $userId,
                $_POST
            );

            header(
                'Location: /scout-training.php',
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

$pageTitle =
    'Scout Application | Llama Scout';

require dirname(__DIR__) . '/partials/header.php';

function scout_app_value(
    array $application,
    string $key
): string {
    return (string) (
        $_POST[$key]
        ?? $application[$key]
        ?? ''
    );
}
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
    <p class="account-eyebrow">Step 2 of 5</p>
    <h1>About You</h1>
    <p>
        This information helps Basecamp understand who is joining
        the Scout team and how your experience may contribute.
    </p>
</header>

<?php if ($error !== ''): ?>
    <div class="account-scout-notice is-error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (
    $application
    && !empty($application['review_notes'])
): ?>
    <div class="account-scout-notice is-attention">
        <strong>Basecamp returned your onboarding for changes.</strong>
        <p>
            <?= nl2br(
                htmlspecialchars(
                    (string) $application['review_notes'],
                    ENT_QUOTES,
                    'UTF-8'
                )
            ) ?>
        </p>
    </div>
<?php endif; ?>

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
        <p class="account-eyebrow">Contact</p>
        <h2>Basic information</h2>

        <div class="account-scout-form-grid">

            <label class="is-wide">
                <span>Legal name</span>
                <input
                    type="text"
                    name="legal_name"
                    maxlength="150"
                    value="<?= htmlspecialchars(
                        scout_app_value($application ?: [], 'legal_name'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >
            </label>

            <label class="is-wide">
                <span>Address</span>
                <input
                    type="text"
                    name="address_line_1"
                    maxlength="150"
                    value="<?= htmlspecialchars(
                        scout_app_value($application ?: [], 'address_line_1'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >
            </label>

            <label class="is-wide">
                <span>Address line 2</span>
                <input
                    type="text"
                    name="address_line_2"
                    maxlength="150"
                    value="<?= htmlspecialchars(
                        scout_app_value($application ?: [], 'address_line_2'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
            </label>

            <label>
                <span>City</span>
                <input
                    type="text"
                    name="city"
                    maxlength="100"
                    value="<?= htmlspecialchars(
                        scout_app_value($application ?: [], 'city'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >
            </label>

            <label>
                <span>State / region</span>
                <input
                    type="text"
                    name="state_region"
                    maxlength="100"
                    value="<?= htmlspecialchars(
                        scout_app_value($application ?: [], 'state_region'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >
            </label>

            <label>
                <span>Postal code</span>
                <input
                    type="text"
                    name="postal_code"
                    maxlength="30"
                    value="<?= htmlspecialchars(
                        scout_app_value($application ?: [], 'postal_code'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >
            </label>

            <label>
                <span>Country</span>
                <input
                    type="text"
                    name="country"
                    maxlength="100"
                    value="<?= htmlspecialchars(
                        scout_app_value($application ?: [], 'country')
                            ?: 'United States',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >
            </label>

            <label class="is-wide">
                <span>Phone (optional)</span>
                <input
                    type="tel"
                    name="phone"
                    maxlength="40"
                    value="<?= htmlspecialchars(
                        scout_app_value($application ?: [], 'phone'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
            </label>

        </div>
    </section>


    <section class="account-scout-panel">
        <p class="account-eyebrow">Experience</p>
        <h2>Tell us about your perspective</h2>

        <div class="account-scout-form-stack">

            <label>
                <span>Why would you like to become a Llama Scout?</span>
                <textarea
                    name="why_scout"
                    rows="5"
                    maxlength="4000"
                ><?= htmlspecialchars(
                    scout_app_value($application ?: [], 'why_scout'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>
            </label>

            <label>
                <span>Travel, camping, overlanding, or outdoor experience</span>
                <textarea
                    name="travel_experience"
                    rows="5"
                    maxlength="4000"
                ><?= htmlspecialchars(
                    scout_app_value($application ?: [], 'travel_experience'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>
            </label>

            <label>
                <span>Experience evaluating roads, campsites, or outdoor conditions</span>
                <textarea
                    name="field_experience"
                    rows="5"
                    maxlength="4000"
                ><?= htmlspecialchars(
                    scout_app_value($application ?: [], 'field_experience'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>
            </label>

            <label>
                <span>Accessibility experience or perspective (optional)</span>
                <textarea
                    name="accessibility_experience"
                    rows="4"
                    maxlength="4000"
                ><?= htmlspecialchars(
                    scout_app_value($application ?: [], 'accessibility_experience'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>
            </label>

            <label>
                <span>Sensory experience or perspective (optional)</span>
                <textarea
                    name="sensory_experience"
                    rows="4"
                    maxlength="4000"
                ><?= htmlspecialchars(
                    scout_app_value($application ?: [], 'sensory_experience'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>
            </label>

        </div>
    </section>


    <section class="account-scout-panel">
        <p class="account-eyebrow">Commitments</p>
        <h2>Scout expectations</h2>

        <div class="account-scout-check-list">

            <label>
                <input
                    type="checkbox"
                    name="agrees_accuracy"
                    value="1"
                    <?= isset($_POST['agrees_accuracy'])
                        || !empty($application['agrees_accuracy'])
                        ? 'checked'
                        : '' ?>
                    required
                >
                <span>
                    <strong>Accuracy</strong>
                    I will distinguish what I personally observed from
                    what I do not know, and I will not intentionally
                    submit misleading Place information.
                </span>
            </label>

            <label>
                <input
                    type="checkbox"
                    name="agrees_safety"
                    value="1"
                    <?= isset($_POST['agrees_safety'])
                        || !empty($application['agrees_safety'])
                        ? 'checked'
                        : '' ?>
                    required
                >
                <span>
                    <strong>Safety</strong>
                    I understand that access, road, weather, fire,
                    and other outdoor conditions can change and must
                    be described responsibly.
                </span>
            </label>

            <label>
                <input
                    type="checkbox"
                    name="agrees_conduct"
                    value="1"
                    <?= isset($_POST['agrees_conduct'])
                        || !empty($application['agrees_conduct'])
                        ? 'checked'
                        : '' ?>
                    required
                >
                <span>
                    <strong>Community conduct</strong>
                    I will use Scout access in good faith and follow
                    Llama Scout community and moderation policies.
                </span>
            </label>

        </div>
    </section>

    <div class="account-scout-form-actions">
        <button
            class="account-scout-button"
            type="submit"
        >
            Submit and continue to training
        </button>
    </div>

</form>

</div>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
