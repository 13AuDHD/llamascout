<?php

declare(strict_types=1);

require_once
    dirname(__DIR__)
    . '/app/bootstrap.php';

require_once
    dirname(__DIR__)
    . '/app/account-security.php';

require_login();
require_verified_email();

$db = db();
$user = current_user();

$userId =
    (int) (
        $user['id']
        ?? 0
    );

if ($userId < 1) {
    header(
        'Location: /login.php'
    );
    exit;
}

$error = '';
$notice = '';

$phoneNumber =
    llama_support_phone_number(
        $db,
        $userId
    );

$mfaEnabled =
    llama_mfa_is_enabled(
        $userId,
        $db
    );

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
) {
    $submittedCsrf =
        (string) (
            $_POST['csrf_token']
            ?? ''
        );

    if (
        !llama_account_security_verify_csrf(
            $submittedCsrf
        )
    ) {
        $error =
            'Your session expired. Reload the page and try again.';
    } else {
        $action =
            trim(
                (string) (
                    $_POST['support_action']
                    ?? ''
                )
            );

        try {
            if (!$mfaEnabled) {
                throw new RuntimeException(
                    'Multi-factor authentication must be enabled before creating a Support PIN.'
                );
            }

            llama_account_security_verify_password(
                $db,
                $userId,
                (string) (
                    $_POST['current_password']
                    ?? ''
                )
            );

            llama_account_security_verify_totp(
                $db,
                $userId,
                (string) (
                    $_POST['totp_code']
                    ?? ''
                )
            );

            llama_mfa_mark_session_verified(
                $userId
            );

            if (
                $action ===
                'save-support-pin'
            ) {
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
                    'Your Support PIN was saved. Its one-time MFA reset allowance is available.';

            } elseif (
                $action ===
                'remove-support-pin'
            ) {
                llama_support_pin_remove(
                    $db,
                    $userId
                );

                $notice =
                    'Your Support PIN was removed.';
            } else {
                throw new InvalidArgumentException(
                    'Choose a valid support security action.'
                );
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'account.support_pin',
                    [
                        'user_id' =>
                            $userId,
                        'action' =>
                            $action,
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
                        'The Support PIN change could not be completed.',
                        $reference
                    );
        }
    }
}

$phoneNumber =
    llama_support_phone_number(
        $db,
        $userId
    );

$supportRecord =
    llama_support_pin_record(
        $db,
        $userId
    );

$supportPinSet =
    is_array($supportRecord)
    &&
    !empty(
        $supportRecord['pin_hash']
    );

$resetAvailable =
    $supportPinSet
    &&
    empty(
        $supportRecord['mfa_reset_used_at']
    );

$pageTitle =
    'Support PIN | Llama Scout';

$pageStyles = [
    'account/pages/support-pin.css',
];

require
    dirname(__DIR__)
    . '/partials/header.php';
?>

<section class="support-pin-page">

    <header class="support-pin-header">
        <p class="eyebrow">
            Account recovery
        </p>

        <h1>
            Support PIN
        </h1>

        <p>
            Create an 8-digit code that you can read to Llama Scout
            support during a phone call.
        </p>
    </header>


    <?php if ($notice !== ''): ?>
        <div class="support-pin-message is-success">
            <?= htmlspecialchars(
                $notice,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>


    <?php if ($error !== ''): ?>
        <div
            class="support-pin-message is-error"
            role="alert"
        >
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>


    <section class="support-pin-card">

        <div class="support-pin-card-heading">
            <i
                class="fa-solid fa-phone-volume"
                aria-hidden="true"
            ></i>

            <div>
                <h2>Phone verification</h2>

                <p>
                    Support will call the phone number stored on your account
                    before asking for your Support PIN.
                </p>
            </div>
        </div>

        <?php if ($phoneNumber !== null): ?>

            <div class="support-pin-fact">
                <span>Phone number</span>
                <strong>
                    <?= htmlspecialchars(
                        $phoneNumber,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

            <a
                class="support-pin-secondary-link"
                href="/account-information.php"
            >
                Change phone number
            </a>

        <?php else: ?>

            <div class="support-pin-warning">
                Add a phone number before creating a Support PIN.
            </div>

            <a
                class="support-pin-button"
                href="/account-information.php"
            >
                Add phone number
            </a>

        <?php endif; ?>

    </section>


    <section class="support-pin-card">

        <div class="support-pin-card-heading">
            <i
                class="fa-solid fa-key"
                aria-hidden="true"
            ></i>

            <div>
                <h2>Support PIN</h2>

                <p>
                    The PIN can verify your identity on support calls as often
                    as needed. It can remove MFA from your account only once.
                </p>
            </div>
        </div>

        <div
            class="support-pin-status <?= $supportPinSet ? 'is-on' : 'is-off' ?>"
        >
            <span></span>
            <?= $supportPinSet
                ? 'Configured'
                : 'Not configured' ?>
        </div>

        <?php if ($supportPinSet): ?>

            <div class="support-pin-facts">

                <div class="support-pin-fact">
                    <span>MFA reset allowance</span>
                    <strong>
                        <?= $resetAvailable
                            ? 'Available'
                            : 'Already used' ?>
                    </strong>
                </div>

                <div class="support-pin-fact">
                    <span>Last verified by support</span>
                    <strong>
                        <?= !empty($supportRecord['last_verified_at'])
                            ? htmlspecialchars(
                                llama_format_viewer_datetime(
                                    (string) $supportRecord['last_verified_at']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            )
                            : 'Never' ?>
                    </strong>
                </div>

            </div>

            <?php if (!$resetAvailable): ?>
                <div class="support-pin-warning">
                    Your existing PIN can still verify future support calls.
                    Replacing it below creates one new MFA reset allowance.
                </div>
            <?php endif; ?>

        <?php endif; ?>


        <?php if (!$mfaEnabled): ?>

            <div class="support-pin-warning">
                MFA must be enabled before you can create or replace
                a Support PIN.
            </div>

            <a
                class="support-pin-button"
                href="/security.php"
            >
                Manage MFA
            </a>

        <?php elseif ($phoneNumber !== null): ?>

            <details
                class="support-pin-manage"
                <?= !$supportPinSet ? 'open' : '' ?>
            >
                <summary>
                    <?= $supportPinSet
                        ? 'Replace Support PIN'
                        : 'Create Support PIN' ?>
                </summary>

                <p>
                    For security, enter your current password and a fresh
                    authenticator code before saving the PIN.
                </p>

                <form
                    method="post"
                    class="support-pin-form"
                    autocomplete="off"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            llama_account_security_csrf_token(),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="support_action"
                        value="save-support-pin"
                    >

                    <label>
                        <span>New 8-digit Support PIN</span>
                        <input
                            name="support_pin"
                            type="password"
                            inputmode="numeric"
                            autocomplete="off"
                            pattern="[0-9]{8}"
                            maxlength="8"
                            required
                        >
                    </label>

                    <label>
                        <span>Confirm Support PIN</span>
                        <input
                            name="support_pin_confirm"
                            type="password"
                            inputmode="numeric"
                            autocomplete="off"
                            pattern="[0-9]{8}"
                            maxlength="8"
                            required
                        >
                    </label>

                    <label>
                        <span>Current password</span>
                        <input
                            name="current_password"
                            type="password"
                            autocomplete="current-password"
                            required
                        >
                    </label>

                    <label>
                        <span>Fresh authenticator code</span>
                        <input
                            name="totp_code"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            required
                        >
                    </label>

                    <button type="submit">
                        <?= $supportPinSet
                            ? 'Replace Support PIN'
                            : 'Save Support PIN' ?>
                    </button>
                </form>
            </details>


            <?php if ($supportPinSet): ?>

                <details class="support-pin-manage is-danger">
                    <summary>
                        Remove Support PIN
                    </summary>

                    <p>
                        This removes both phone support verification and
                        the unused MFA reset allowance.
                    </p>

                    <form
                        method="post"
                        class="support-pin-form"
                        autocomplete="off"
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                llama_account_security_csrf_token(),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="support_action"
                            value="remove-support-pin"
                        >

                        <label>
                            <span>Current password</span>
                            <input
                                name="current_password"
                                type="password"
                                autocomplete="current-password"
                                required
                            >
                        </label>

                        <label>
                            <span>Fresh authenticator code</span>
                            <input
                                name="totp_code"
                                type="text"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                required
                            >
                        </label>

                        <button
                            type="submit"
                            class="is-danger"
                        >
                            Remove Support PIN
                        </button>
                    </form>
                </details>

            <?php endif; ?>

        <?php endif; ?>

    </section>


    <p class="support-pin-back">
        <a href="/">
            <i
                class="fa-solid fa-arrow-left"
                aria-hidden="true"
            ></i>
            Return to My Account
        </a>
    </p>

</section>

<?php
require
    dirname(__DIR__)
    . '/partials/footer.php';
