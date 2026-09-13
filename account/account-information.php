<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/account-information.php';
require_once dirname(__DIR__) . '/app/account-security.php';
require_once dirname(__DIR__) . '/app/support-code.php';

require_login();

$db = db();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);

$notice = '';
$warning = '';
$error = '';

$schemaReady =
    llama_account_info_schema_ready(
        $db
    );

if (!$schemaReady) {
    $error =
        'Account Information is not available until its database upgrade has been installed.';
}

if (
    $error === ''
    && empty($_SESSION['account_information_csrf'])
) {
    $_SESSION['account_information_csrf'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    (string) (
        $_SESSION['account_information_csrf']
        ?? ''
    );

if (
    $error === ''
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
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
        $error =
            'Your session token expired. Reload the page and try again.';
    } else {
        $action =
            trim(
                (string) (
                    $_POST['account_information_action']
                    ?? ''
                )
            );

        try {
            if ($action === 'save-public-identity') {
                llama_account_info_save_public_identity(
                    $db,
                    $userId,
                    (string) ($_POST['display_name'] ?? ''),
                    (string) ($_POST['username'] ?? '')
                );

                $notice =
                    'Your public account identity has been updated.';

            } elseif ($action === 'save-private-details') {
                llama_account_info_save_private_details(
                    $db,
                    $userId,
                    $_POST
                );

                $notice =
                    'Your private account details have been updated.';

            } elseif ($action === 'change-email') {
                $result =
                    llama_account_info_request_email_change(
                        $db,
                        $userId,
                        (string) ($_POST['new_email'] ?? ''),
                        (string) ($_POST['current_password'] ?? '')
                    );

                if (!empty($result['sent'])) {
                    $notice =
                        'Verification was sent to '
                        . $result['email']
                        . '. Your current email remains active until the new address is verified.';
                } else {
                    $warning =
                        'The new email address was saved as pending, but the verification message could not be sent. Try the resend button below.';
                }

            } elseif ($action === 'resend-email-change') {
                $account =
                    llama_account_info_user(
                        $db,
                        $userId
                    );

                $pendingEmail =
                    trim(
                        (string) (
                            $account['pending_email']
                            ?? ''
                        )
                    );

                if ($pendingEmail === '') {
                    throw new InvalidArgumentException(
                        'There is no pending email change to resend.'
                    );
                }

                $result =
                    llama_account_info_request_email_change(
                        $db,
                        $userId,
                        $pendingEmail,
                        (string) ($_POST['current_password'] ?? '')
                    );

                if (!empty($result['sent'])) {
                    $notice =
                        'A fresh verification link was sent to '
                        . $pendingEmail
                        . '.';
                } else {
                    $warning =
                        'A fresh verification record was created, but the message could not be sent.';
                }

            } elseif ($action === 'cancel-email-change') {
                llama_account_info_cancel_email_change(
                    $db,
                    $userId
                );

                $notice =
                    'The pending email change was cancelled.';

            } elseif (
                in_array(
                    $action,
                    [
                        'save-support-pin',
                        'remove-support-pin',
                    ],
                    true
                )
            ) {
                $accountBeforeSecurityChange =
                    llama_account_info_user(
                        $db,
                        $userId
                    );

                if (
                    !$accountBeforeSecurityChange
                    || empty(
                        $accountBeforeSecurityChange['email_verified_at']
                    )
                ) {
                    throw new RuntimeException(
                        'Verify your sign-in email before changing your Support PIN.'
                    );
                }

                if (
                    !llama_mfa_is_enabled(
                        $userId,
                        $db
                    )
                ) {
                    throw new RuntimeException(
                        'Multi-factor authentication must be enabled before creating or removing a Support PIN.'
                    );
                }

                llama_account_security_verify_password(
                    $db,
                    $userId,
                    (string) ($_POST['current_password'] ?? '')
                );

                llama_account_security_verify_totp(
                    $db,
                    $userId,
                    (string) ($_POST['totp_code'] ?? '')
                );

                llama_mfa_mark_session_verified(
                    $userId
                );

                if ($action === 'save-support-pin') {
                    $pin =
                        (string) (
                            $_POST['support_pin']
                            ?? ''
                        );

                    $confirmPin =
                        (string) (
                            $_POST['support_pin_confirm']
                            ?? ''
                        );

                    if (
                        llama_support_pin_normalize($pin)
                        !==
                        llama_support_pin_normalize($confirmPin)
                    ) {
                        throw new InvalidArgumentException(
                            'The two Support PIN entries do not match.'
                        );
                    }

                    llama_support_pin_set(
                        $db,
                        $userId,
                        $pin
                    );

                    $notice =
                        'Your Support PIN was saved and its one-time MFA reset allowance is ready.';
                } else {
                    llama_support_pin_remove(
                        $db,
                        $userId
                    );

                    $notice =
                        'Your Support PIN was removed.';
                }

            } elseif ($action !== '') {
                throw new InvalidArgumentException(
                    'Choose a valid account information action.'
                );
            }

        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'account.information',
                    [
                        'user_id' => $userId,
                        'action' => $action,
                    ],
                    [
                        InvalidArgumentException::class,
                        RuntimeException::class,
                    ]
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'Your account information could not be updated.',
                        $reference
                    );
        }
    }
}

$account =
    $schemaReady
        ? llama_account_info_user(
            $db,
            $userId
        )
        : null;

$accountEmailVerified =
    $account
    && !empty(
        $account['email_verified_at']
    );

$mfaEnabled =
    $account
        ? llama_mfa_is_enabled(
            $userId,
            $db
        )
        : false;

$supportRecord = null;
$supportPinSet = false;
$resetAvailable = false;

if ($account) {
    try {
        $supportRecord =
            llama_support_pin_record(
                $db,
                $userId
            );

        $supportPinSet =
            is_array($supportRecord)
            && !empty(
                $supportRecord['pin_hash']
            );

        $resetAvailable =
            $supportPinSet
            && empty(
                $supportRecord['mfa_reset_used_at']
            );

    } catch (Throwable $exception) {
        llama_log_caught_exception(
            $exception,
            'account.information.support_pin_status',
            [
                'user_id' => $userId,
            ]
        );
    }
}

$pageTitle =
    'Account Information | Llama Scout';

$pageRobots =
    'noindex,nofollow';

$pageStyles = [
    'account/pages/account-information.css',
];

require dirname(__DIR__)
    . '/partials/header.php';

$e =
    static fn (mixed $value): string =>
        htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );

$selectedTimezone =
    $account
        ? llama_user_timezone($account)
        : llama_default_timezone();

$displayPhone =
    $account
        ? llama_account_info_format_phone(
            $account['phone_number']
            ?? null
        )
        : '';
?>

<main
    id="main-content"
    class="account-information-page"
>
    <div class="account-information-shell">

        <a
            class="account-information-back"
            href="/"
        >
            <i
                class="fa-solid fa-arrow-left"
                aria-hidden="true"
            ></i>
            Back to account
        </a>

        <header class="account-information-header">
            <p class="account-eyebrow">
                Your account
            </p>

            <h1>
                Account information
            </h1>

            <p>
                Your account identity, private contact details,
                sign-in email, location settings, and phone support
                verification live here. Each section shows whether
                information can be public or is kept private.
            </p>
        </header>

        <?php if ($notice !== ''): ?>
            <div
                class="account-information-notice is-success"
                role="status"
            >
                <?= $e($notice) ?>
            </div>
        <?php endif; ?>

        <?php if ($warning !== ''): ?>
            <div
                class="account-information-notice"
                role="status"
            >
                <?= $e($warning) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div
                class="account-information-notice is-error"
                role="alert"
            >
                <?= $e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($account): ?>

            <section
                class="account-information-zone is-public"
                aria-labelledby="public-account-heading"
            >
                <header class="account-information-zone-heading">
                    <span class="account-information-visibility-badge is-public">
                        <i
                            class="fa-solid fa-globe"
                            aria-hidden="true"
                        ></i>
                        Public
                    </span>

                    <div>
                        <h2 id="public-account-heading">
                            Public account identity
                        </h2>

                        <p>
                            These are the account details other people may see
                            with your profile, contributions, badges, and Scout activity.
                        </p>
                    </div>
                </header>

                <section class="account-information-card">
                    <form
                        method="post"
                        class="account-information-form"
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= $e($csrfToken) ?>"
                        >

                        <input
                            type="hidden"
                            name="account_information_action"
                            value="save-public-identity"
                        >

                        <div class="account-information-grid">
                            <label>
                                <span>Display name</span>

                                <input
                                    type="text"
                                    name="display_name"
                                    maxlength="100"
                                    required
                                    autocomplete="nickname"
                                    value="<?= $e(
                                        $account['display_name']
                                        ?? ''
                                    ) ?>"
                                >

                                <small>
                                    Shown with your public Llama Scout identity.
                                </small>
                            </label>

                            <label>
                                <span>Username</span>

                                <input
                                    type="text"
                                    name="username"
                                    maxlength="16"
                                    required
                                    autocapitalize="none"
                                    spellcheck="false"
                                    autocomplete="username"
                                    value="<?= $e(
                                        $account['username']
                                        ?? ''
                                    ) ?>"
                                >

                                <small>
                                    4 to 16 letters, numbers, or underscores.
                                    Your username is part of your profile URL.
                                </small>
                            </label>
                        </div>

                        <button
                            class="account-information-button"
                            type="submit"
                        >
                            Save public identity
                        </button>
                    </form>
                </section>

                <a
                    class="account-information-profile-link"
                    href="/profile.php"
                >
                    <i
                        class="fa-solid fa-user"
                        aria-hidden="true"
                    ></i>

                    <span>
                        <strong>Public profile</strong>
                        <small>
                            Photos, bio, general location, social links,
                            badges, and profile visibility.
                        </small>
                    </span>

                    <i
                        class="fa-solid fa-chevron-right"
                        aria-hidden="true"
                    ></i>
                </a>
            </section>

            <section
                class="account-information-zone is-private"
                aria-labelledby="private-account-heading"
            >
                <header class="account-information-zone-heading">
                    <span class="account-information-visibility-badge is-private">
                        <i
                            class="fa-solid fa-lock"
                            aria-hidden="true"
                        ></i>
                        Private
                    </span>

                    <div>
                        <h2 id="private-account-heading">
                            Private account details
                        </h2>

                        <p>
                            Llama Scout uses these details for your account,
                            support, account recovery, and future transaction
                            or location-aware features. They are not shown on
                            your public profile.
                        </p>
                    </div>
                </header>

                <section class="account-information-card">
                    <header>
                        <div>
                            <p class="account-eyebrow">
                                Contact & location
                            </p>

                            <h3>
                                Personal information
                            </h3>
                        </div>

                        <span>
                            Private
                        </span>
                    </header>

                    <form
                        method="post"
                        class="account-information-form"
                        data-account-information-form
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= $e($csrfToken) ?>"
                        >

                        <input
                            type="hidden"
                            name="account_information_action"
                            value="save-private-details"
                        >

                        <input
                            type="hidden"
                            name="address_latitude"
                            value="<?= $e(
                                $account['address_latitude']
                                ?? ''
                            ) ?>"
                            data-address-latitude
                        >

                        <input
                            type="hidden"
                            name="address_longitude"
                            value="<?= $e(
                                $account['address_longitude']
                                ?? ''
                            ) ?>"
                            data-address-longitude
                        >

                        <div class="account-information-grid">

                            <label class="is-wide">
                                <span>Full name</span>

                                <input
                                    type="text"
                                    name="full_name"
                                    maxlength="150"
                                    autocomplete="name"
                                    value="<?= $e(
                                        $account['full_name']
                                        ?? ''
                                    ) ?>"
                                    placeholder="Your full name"
                                >

                                <small>
                                    Private. This is separate from the display
                                    name shown to the community.
                                </small>
                            </label>

                            <label class="is-wide">
                                <span>Phone number</span>

                                <input
                                    type="tel"
                                    name="phone_number"
                                    maxlength="32"
                                    autocomplete="tel"
                                    inputmode="tel"
                                    value="<?= $e($displayPhone) ?>"
                                    placeholder="(970) 555-1212"
                                    data-phone-input
                                >

                                <small>
                                    Used for Support PIN phone verification.
                                    US numbers are stored in +1 format.
                                </small>
                            </label>

                            <div class="account-information-address is-wide">
                                <div class="account-information-address-heading">
                                    <div>
                                        <strong>
                                            Mailing address
                                        </strong>

                                        <small>
                                            Start typing an address or use your
                                            current location to fill the address,
                                            city, state, and ZIP automatically.
                                        </small>
                                    </div>

                                    <button
                                        type="button"
                                        class="account-information-location-button"
                                        data-address-use-location
                                    >
                                        <i
                                            class="fa-solid fa-location-crosshairs"
                                            aria-hidden="true"
                                        ></i>

                                        Use my location
                                    </button>
                                </div>

                                <label class="is-wide account-information-address-search">
                                    <span>Street address</span>

                                    <input
                                        type="text"
                                        name="address_line_1"
                                        maxlength="190"
                                        autocomplete="address-line1"
                                        value="<?= $e(
                                            $account['address_line_1']
                                            ?? ''
                                        ) ?>"
                                        data-address-search
                                    >

                                    <div
                                        class="account-information-address-results"
                                        data-address-results
                                        hidden
                                    ></div>
                                </label>

                                <label class="is-wide">
                                    <span>
                                        Apartment, suite, unit, etc.
                                    </span>

                                    <input
                                        type="text"
                                        name="address_line_2"
                                        maxlength="190"
                                        autocomplete="address-line2"
                                        value="<?= $e(
                                            $account['address_line_2']
                                            ?? ''
                                        ) ?>"
                                    >
                                </label>

                                <div class="account-information-address-grid">
                                    <label>
                                        <span>City</span>

                                        <input
                                            type="text"
                                            name="address_city"
                                            maxlength="120"
                                            autocomplete="address-level2"
                                            value="<?= $e(
                                                $account['address_city']
                                                ?? ''
                                            ) ?>"
                                            data-address-city
                                        >
                                    </label>

                                    <label>
                                        <span>State / region</span>

                                        <input
                                            type="text"
                                            name="address_state"
                                            maxlength="120"
                                            autocomplete="address-level1"
                                            value="<?= $e(
                                                $account['address_state']
                                                ?? ''
                                            ) ?>"
                                            data-address-state
                                        >
                                    </label>

                                    <label>
                                        <span>ZIP / postal code</span>

                                        <input
                                            type="text"
                                            name="address_postal_code"
                                            maxlength="32"
                                            autocomplete="postal-code"
                                            value="<?= $e(
                                                $account['address_postal_code']
                                                ?? ''
                                            ) ?>"
                                            data-address-postal
                                        >
                                    </label>

                                    <label>
                                        <span>Country</span>

                                        <input
                                            type="text"
                                            name="address_country"
                                            maxlength="120"
                                            autocomplete="country-name"
                                            value="<?= $e(
                                                $account['address_country']
                                                ?? ''
                                            ) ?>"
                                            placeholder="United States"
                                            data-address-country
                                        >
                                    </label>
                                </div>

                                <p
                                    class="account-information-address-status"
                                    data-address-status
                                    role="status"
                                ></p>
                            </div>

                            <label class="is-wide">
                                <span>Time zone</span>

                                <select
                                    name="timezone"
                                    required
                                >
                                    <?php foreach (
                                        llama_timezones()
                                        as $timezone => $timezoneLabel
                                    ): ?>
                                        <option
                                            value="<?= $e($timezone) ?>"
                                            <?= $timezone === $selectedTimezone
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= $e($timezoneLabel) ?>
                                            (<?= $e($timezone) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <small>
                                    Dates and times across Llama Scout are shown
                                    in this time zone while you are signed in.
                                </small>
                            </label>

                        </div>

                        <button
                            class="account-information-button"
                            type="submit"
                        >
                            Save private details
                        </button>
                    </form>
                </section>

                <section
                    class="account-information-card"
                    id="email-address"
                >
                    <header>
                        <div>
                            <p class="account-eyebrow">
                                Sign-in
                            </p>

                            <h3>
                                Email address
                            </h3>
                        </div>

                        <span class="account-information-verified">
                            <i
                                class="fa-solid <?= $accountEmailVerified
                                    ? 'fa-circle-check'
                                    : 'fa-circle-exclamation' ?>"
                                aria-hidden="true"
                            ></i>

                            <?= $accountEmailVerified
                                ? 'Verified'
                                : 'Verification required' ?>
                        </span>
                    </header>

                    <div class="account-information-current-email">
                        <span>
                            <?= $accountEmailVerified
                                ? 'Current verified sign-in email'
                                : 'Current sign-in email, verification required' ?>
                        </span>

                        <strong>
                            <?= $e($account['email']) ?>
                        </strong>

                        <?php if (!$accountEmailVerified): ?>
                            <small>
                                If this address is correct, resend verification.
                                If it was entered incorrectly, replace it below.
                            </small>
                        <?php endif; ?>
                    </div>

                    <?php if (!$accountEmailVerified): ?>
                        <div class="account-information-email-note">
                            <i
                                class="fa-solid fa-envelope-circle-check"
                                aria-hidden="true"
                            ></i>

                            <p>
                                If the address above is correct, resend the
                                normal verification message instead of changing it.
                            </p>
                        </div>

                        <div class="account-information-inline-action">
                            <a
                                class="account-information-button is-secondary"
                                href="/resend-verification.php"
                            >
                                Resend verification to current email
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (
                        !empty($account['pending_email'])
                    ): ?>
                        <div class="account-information-pending-email">
                            <div>
                                <span>
                                    Waiting for verification
                                </span>

                                <strong>
                                    <?= $e(
                                        $account['pending_email']
                                    ) ?>
                                </strong>

                                <small>
                                    Your current email continues to work until
                                    this new address is verified.
                                </small>
                            </div>

                            <form method="post">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= $e($csrfToken) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="account_information_action"
                                    value="cancel-email-change"
                                >

                                <button
                                    class="account-information-button is-secondary"
                                    type="submit"
                                >
                                    Cancel change
                                </button>
                            </form>
                        </div>

                        <form
                            method="post"
                            class="account-information-resend"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= $e($csrfToken) ?>"
                            >

                            <input
                                type="hidden"
                                name="account_information_action"
                                value="resend-email-change"
                            >

                            <label>
                                <span>
                                    Current password
                                </span>

                                <input
                                    type="password"
                                    name="current_password"
                                    autocomplete="current-password"
                                    required
                                >
                            </label>

                            <button
                                class="account-information-button is-secondary"
                                type="submit"
                            >
                                Resend verification
                            </button>
                        </form>

                    <?php else: ?>
                        <form
                            method="post"
                            class="account-information-form"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= $e($csrfToken) ?>"
                            >

                            <input
                                type="hidden"
                                name="account_information_action"
                                value="change-email"
                            >

                            <div class="account-information-grid">
                                <label>
                                    <span>New email address</span>

                                    <input
                                        type="email"
                                        name="new_email"
                                        maxlength="254"
                                        autocomplete="email"
                                        required
                                    >
                                </label>

                                <label>
                                    <span>Current password</span>

                                    <input
                                        type="password"
                                        name="current_password"
                                        autocomplete="current-password"
                                        required
                                    >
                                </label>
                            </div>

                            <div class="account-information-email-note">
                                <i
                                    class="fa-solid fa-shield-halved"
                                    aria-hidden="true"
                                ></i>

                                <p>
                                    <?= $accountEmailVerified
                                        ? 'We will verify the new address first. Your current sign-in email will not change until the verification link succeeds.'
                                        : 'We will send verification to the corrected address. Your account remains verification-required until the new address is verified.' ?>
                                </p>
                            </div>

                            <button
                                class="account-information-button"
                                type="submit"
                            >
                                <?= $accountEmailVerified
                                    ? 'Send verification to new email'
                                    : 'Correct email + send verification' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </section>

                <section
                    class="account-information-card"
                    id="support-pin"
                >
                    <header>
                        <div>
                            <p class="account-eyebrow">
                                Phone support
                            </p>

                            <h3>
                                Support PIN
                            </h3>
                        </div>

                        <span>
                            Private
                        </span>
                    </header>

                    <div class="account-information-support-pin">
                        <div class="account-information-support-summary">
                            <i
                                class="fa-solid fa-phone-volume"
                                aria-hidden="true"
                            ></i>

                            <div>
                                <strong>
                                    Verify yourself during a support call
                                </strong>

                                <p>
                                    Support first confirms the phone number on
                                    your account, then asks for your private
                                    8-digit Support PIN.
                                </p>
                            </div>
                        </div>

                        <div class="account-information-support-facts">
                            <div>
                                <span>Phone</span>
                                <strong>
                                    <?= $displayPhone !== ''
                                        ? $e($displayPhone)
                                        : 'Not added' ?>
                                </strong>
                            </div>

                            <div>
                                <span>Support PIN</span>
                                <strong>
                                    <?= $supportPinSet
                                        ? 'Configured'
                                        : 'Not configured' ?>
                                </strong>
                            </div>

                            <div>
                                <span>Emergency MFA reset</span>
                                <strong>
                                    <?= $resetAvailable
                                        ? 'Available once'
                                        : (
                                            $supportPinSet
                                                ? 'Already used'
                                                : 'Not available'
                                        ) ?>
                                </strong>
                            </div>
                        </div>

                        <?php if (!$accountEmailVerified): ?>
                            <div class="account-information-security-note">
                                Verify your sign-in email before setting a Support PIN.
                            </div>

                        <?php elseif (!$mfaEnabled): ?>
                            <div class="account-information-security-note">
                                Enable multi-factor authentication in
                                Password &amp; Security before setting a Support PIN.
                            </div>

                        <?php elseif ($displayPhone === ''): ?>
                            <div class="account-information-security-note">
                                Add and save a phone number above before
                                creating a Support PIN.
                            </div>

                        <?php else: ?>
                            <details class="account-information-sensitive-action">
                                <summary>
                                    <?= $supportPinSet
                                        ? 'Replace Support PIN'
                                        : 'Create Support PIN' ?>
                                </summary>

                                <p>
                                    Replacing the PIN re-arms its one allowed
                                    emergency MFA reset. Your current password
                                    and authenticator code are required.
                                </p>

                                <form
                                    method="post"
                                    class="account-information-sensitive-form"
                                    autocomplete="off"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= $e($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="account_information_action"
                                        value="save-support-pin"
                                    >

                                    <label>
                                        <span>New 8-digit Support PIN</span>
                                        <input
                                            type="password"
                                            name="support_pin"
                                            inputmode="numeric"
                                            pattern="[0-9]{8}"
                                            minlength="8"
                                            maxlength="8"
                                            autocomplete="off"
                                            required
                                        >
                                    </label>

                                    <label>
                                        <span>Confirm Support PIN</span>
                                        <input
                                            type="password"
                                            name="support_pin_confirm"
                                            inputmode="numeric"
                                            pattern="[0-9]{8}"
                                            minlength="8"
                                            maxlength="8"
                                            autocomplete="off"
                                            required
                                        >
                                    </label>

                                    <label>
                                        <span>Current password</span>
                                        <input
                                            type="password"
                                            name="current_password"
                                            autocomplete="current-password"
                                            required
                                        >
                                    </label>

                                    <label>
                                        <span>Authenticator code</span>
                                        <input
                                            type="text"
                                            name="totp_code"
                                            inputmode="numeric"
                                            autocomplete="one-time-code"
                                            pattern="[0-9]{6}"
                                            maxlength="6"
                                            required
                                        >
                                    </label>

                                    <button
                                        class="account-information-button"
                                        type="submit"
                                    >
                                        <?= $supportPinSet
                                            ? 'Replace Support PIN'
                                            : 'Create Support PIN' ?>
                                    </button>
                                </form>
                            </details>

                            <?php if ($supportPinSet): ?>
                                <details class="account-information-sensitive-action is-danger">
                                    <summary>
                                        Remove Support PIN
                                    </summary>

                                    <p>
                                        This removes phone-support PIN verification
                                        and its emergency MFA reset allowance.
                                    </p>

                                    <form
                                        method="post"
                                        class="account-information-sensitive-form"
                                        autocomplete="off"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= $e($csrfToken) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="account_information_action"
                                            value="remove-support-pin"
                                        >

                                        <label>
                                            <span>Current password</span>
                                            <input
                                                type="password"
                                                name="current_password"
                                                autocomplete="current-password"
                                                required
                                            >
                                        </label>

                                        <label>
                                            <span>Authenticator code</span>
                                            <input
                                                type="text"
                                                name="totp_code"
                                                inputmode="numeric"
                                                autocomplete="one-time-code"
                                                pattern="[0-9]{6}"
                                                maxlength="6"
                                                required
                                            >
                                        </label>

                                        <button
                                            class="account-information-button is-danger"
                                            type="submit"
                                        >
                                            Remove Support PIN
                                        </button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </section>

            <section class="account-information-related">
                <header>
                    <p class="account-eyebrow">
                        Related settings
                    </p>

                    <h2>
                        Other account controls
                    </h2>
                </header>

                <div class="account-information-links">
                    <a href="/security.php">
                        <i
                            class="fa-solid fa-shield-halved"
                            aria-hidden="true"
                        ></i>

                        <span>
                            <strong>Password &amp; security</strong>
                            <small>
                                Password, MFA, recovery codes, and account security.
                            </small>
                        </span>

                        <i
                            class="fa-solid fa-chevron-right"
                            aria-hidden="true"
                        ></i>
                    </a>

                    <?php if (
                        function_exists(
                            'llama_passkey_account_is_owner'
                        )
                        && llama_passkey_account_is_owner(
                            $db,
                            $userId
                        )
                    ): ?>
                        <a href="/passkeys.php">
                            <i
                                class="fa-solid fa-fingerprint"
                                aria-hidden="true"
                            ></i>

                            <span>
                                <strong>Passkeys</strong>
                                <small>
                                    Manage Apple and Android passkeys.
                                </small>
                            </span>

                            <i
                                class="fa-solid fa-chevron-right"
                                aria-hidden="true"
                            ></i>
                        </a>
                    <?php endif; ?>

                    <a href="/email-preferences.php">
                        <i
                            class="fa-solid fa-envelope"
                            aria-hidden="true"
                        ></i>

                        <span>
                            <strong>Email preferences</strong>
                            <small>
                                Newsletters, Member Dispatch, and promotional email choices.
                            </small>
                        </span>

                        <i
                            class="fa-solid fa-chevron-right"
                            aria-hidden="true"
                        ></i>
                    </a>

                    <div class="account-information-coming-soon">
                        <i
                            class="fa-solid fa-sliders"
                            aria-hidden="true"
                        ></i>

                        <span>
                            <strong>Place preferences</strong>
                            <small>
                                Coming later. Personalize how Llama Scout
                                ranks and filters Places for you.
                            </small>
                        </span>

                        <em>Later</em>
                    </div>
                </div>
            </section>

        <?php endif; ?>

    </div>
</main>

<script
    src="https://llamascout.com/js/account-information.js"
    defer
></script>

<?php
require dirname(__DIR__)
    . '/partials/footer.php';
?>
