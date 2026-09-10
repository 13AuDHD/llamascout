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
    (int) ($user['id'] ?? 0);

if ($userId < 1) {
    header('Location: /login.php');
    exit;
}

$error = '';
$notice = '';
$recoveryCodes = [];

$isRequired =
    llama_mfa_role_requires_mfa(
        $userId,
        $db
    );

$isEnabled =
    llama_mfa_is_enabled(
        $userId,
        $db
    );

$secret = null;
$provisioningUri = null;

if (
    !$isEnabled
    && !$isRequired
) {
    try {
        $record =
            llama_mfa_record(
                $userId,
                $db
            );

        if (
            !is_array($record)
            || empty(
                $record[
                    'secret_ciphertext'
                ]
            )
        ) {
            $secret =
                llama_mfa_begin_enrollment(
                    $userId,
                    $db
                );
        } else {
            $secret =
                llama_mfa_get_secret(
                    $userId,
                    $db
                );
        }

        if (
            is_string($secret)
            && $secret !== ''
        ) {
            $accountLabel =
                trim(
                    (string) (
                        $user['email']
                        ?? $user['username']
                        ?? 'Llama Scout Account'
                    )
                );

            $provisioningUri =
                llama_mfa_provisioning_uri(
                    $secret,
                    $accountLabel
                );
        }
    } catch (Throwable $exception) {
        $error =
            $exception->getMessage();
    }
}

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
                    $_POST['security_action']
                    ?? ''
                )
            );

        try {
            if (
                $action ===
                'enable-mfa'
            ) {
                if (
                    $isRequired
                ) {
                    throw new RuntimeException(
                        'Required MFA setup must be completed through the sign-in security flow.'
                    );
                }

                if ($isEnabled) {
                    throw new RuntimeException(
                        'Multi-factor authentication is already enabled.'
                    );
                }

                $recoveryCodes =
                    llama_mfa_enable(
                        $userId,
                        (string) (
                            $_POST['totp_code']
                            ?? ''
                        ),
                        $db
                    );

                llama_mfa_mark_session_verified(
                    $userId
                );

                llama_mfa_invalidate_remember_tokens(
                    $userId,
                    $db
                );

                $isEnabled = true;
                $notice =
                    'Multi-factor authentication is now enabled.';

            } elseif (
                $action ===
                'regenerate-recovery-codes'
            ) {
                $recoveryCodes =
                    llama_account_security_regenerate_recovery_codes(
                        $db,
                        $userId,
                        (string) (
                            $_POST['current_password']
                            ?? ''
                        ),
                        (string) (
                            $_POST['totp_code']
                            ?? ''
                        )
                    );

                $notice =
                    'Your recovery codes were replaced. Save the new codes now.';

            } elseif (
                $action ===
                'disable-mfa'
            ) {
                llama_account_security_disable_mfa(
                    $db,
                    $userId,
                    (string) (
                        $_POST['current_password']
                        ?? ''
                    ),
                    (string) (
                        $_POST['totp_code']
                        ?? ''
                    )
                );

                $isEnabled = false;
                $notice =
                    'Multi-factor authentication has been disabled.';

                $record =
                    llama_mfa_record(
                        $userId,
                        $db
                    );

                if (!$record) {
                    $secret =
                        llama_mfa_begin_enrollment(
                            $userId,
                            $db
                        );

                    $provisioningUri =
                        llama_mfa_provisioning_uri(
                            $secret,
                            (string) (
                                $user['email']
                                ?? $user['username']
                                ?? 'Llama Scout Account'
                            )
                        );
                }
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'account.security',
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
                        'The security change could not be completed.',
                        $reference
                    );
        }
    }
}

$remainingRecoveryCodes =
    $isEnabled
        ? llama_mfa_recovery_code_count(
            $userId,
            $db
        )
        : 0;

$pageTitle =
    'Password & Security | Llama Scout';

$pageStyles = [
    'account/pages/security.css',
];

require
    dirname(__DIR__)
    . '/partials/header.php';
?>

<section class="account-security-page">

    <header class="account-security-header">
        <p class="eyebrow">
            Account security
        </p>

        <h1>
            Password &amp; Security
        </h1>

        <p>
            Manage your password and protect your account
            with multi-factor authentication.
        </p>
    </header>


    <?php if ($notice !== ''): ?>
        <div class="account-security-message is-success">
            <?= htmlspecialchars(
                $notice,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>


    <?php if ($error !== ''): ?>
        <div
            class="account-security-message is-error"
            role="alert"
        >
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>


    <?php if ($recoveryCodes): ?>
        <section class="account-security-card is-important">
            <div class="account-security-card-heading">
                <i
                    class="fa-solid fa-key"
                    aria-hidden="true"
                ></i>

                <div>
                    <h2>
                        Save your recovery codes
                    </h2>

                    <p>
                        Each code works once. These exact codes
                        cannot be shown again.
                    </p>
                </div>
            </div>

            <div class="account-security-recovery-grid">
                <?php foreach (
                    $recoveryCodes
                    as $recoveryCode
                ): ?>
                    <code>
                        <?= htmlspecialchars(
                            $recoveryCode,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </code>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>


    <div class="account-security-grid">

        <section class="account-security-card">
            <div class="account-security-card-heading">
                <i
                    class="fa-solid fa-lock"
                    aria-hidden="true"
                ></i>

                <div>
                    <h2>Password</h2>

                    <p>
                        Change your password using a secure
                        email reset link.
                    </p>
                </div>
            </div>

            <a
                class="account-security-button"
                href="/forgot-password.php"
            >
                Change Password
            </a>
        </section>


        <section class="account-security-card">
            <div class="account-security-card-heading">
                <i
                    class="fa-solid fa-shield-halved"
                    aria-hidden="true"
                ></i>

                <div>
                    <h2>
                        Multi-factor authentication
                    </h2>

                    <?php if ($isRequired): ?>
                        <p>
                            Required for Owner and Admin accounts.
                        </p>
                    <?php elseif ($isEnabled): ?>
                        <p>
                            Enabled. Your authenticator is required
                            when signing in.
                        </p>
                    <?php else: ?>
                        <p>
                            Optional. Add an authenticator app as a
                            second step when signing in.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="account-security-status <?= $isEnabled ? 'is-on' : 'is-off' ?>">
                <span></span>
                <?= $isEnabled ? 'Enabled' : 'Not enabled' ?>
            </div>

            <?php if (
                !$isEnabled
                && !$isRequired
                && is_string($provisioningUri)
                && $provisioningUri !== ''
                && is_string($secret)
                && $secret !== ''
            ): ?>

                <div class="account-security-setup">
                    <h3>
                        Set up an authenticator
                    </h3>

                    <p>
                        Scan this QR code with your authenticator app,
                        then enter the current 6-digit code below.
                    </p>

                    <div
                        id="mfa-qr"
                        class="account-security-qr"
                        data-otpauth="<?= htmlspecialchars(
                            $provisioningUri,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    ></div>

                    <details class="account-security-manual">
                        <summary>
                            Can't scan the QR code?
                        </summary>

                        <p>
                            Enter this setup key manually:
                        </p>

                        <code>
                            <?= htmlspecialchars(
                                $secret,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </code>
                    </details>

                    <form
                        method="post"
                        class="account-security-form"
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
                            name="security_action"
                            value="enable-mfa"
                        >

                        <label>
                            <span>
                                Authentication code
                            </span>

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
                            Enable MFA
                        </button>
                    </form>
                </div>

            <?php elseif ($isEnabled): ?>

                <p class="account-security-meta">
                    <?= (int) $remainingRecoveryCodes ?>
                    unused recovery
                    <?= $remainingRecoveryCodes === 1
                        ? 'code remains'
                        : 'codes remain'
                    ?>.
                </p>

                <details class="account-security-manage">
                    <summary>
                        Replace recovery codes
                    </summary>

                    <form
                        method="post"
                        class="account-security-form"
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
                            name="security_action"
                            value="regenerate-recovery-codes"
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
                            <span>Authenticator code</span>
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
                            Replace Recovery Codes
                        </button>
                    </form>
                </details>


                <?php if (!$isRequired): ?>
                    <details class="account-security-manage is-danger">
                        <summary>
                            Disable multi-factor authentication
                        </summary>

                        <p>
                            This removes the authenticator requirement
                            and invalidates all recovery codes.
                        </p>

                        <form
                            method="post"
                            class="account-security-form"
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
                                name="security_action"
                                value="disable-mfa"
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
                                <span>Authenticator code</span>
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
                                Disable MFA
                            </button>
                        </form>
                    </details>
                <?php endif; ?>

            <?php endif; ?>
        </section>

    </div>


    <p class="account-security-back">
        <a href="/">
            <i
                class="fa-solid fa-arrow-left"
                aria-hidden="true"
            ></i>
            Return to My Account
        </a>
    </p>

</section>

<?php if (
    !$isEnabled
    && !$isRequired
    && is_string($provisioningUri)
    && $provisioningUri !== ''
): ?>
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
        defer
    ></script>

    <script
        src="https://llamascout.com/js/mfa-setup.js"
        defer
    ></script>
<?php endif; ?>

<?php
require
    dirname(__DIR__)
    . '/partials/footer.php';
