<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/account-information.php';

require_login();

$db = db();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);

$notice = '';
$warning = '';
$error = '';

if (!llama_account_info_schema_ready($db)) {
    $error =
        'Account Information is not available until its database upgrade has been installed.';
}

if (
    $error === ''
    && empty(
        $_SESSION[
            'account_information_csrf'
        ]
    )
) {
    $_SESSION[
        'account_information_csrf'
    ] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    (string) (
        $_SESSION[
            'account_information_csrf'
        ]
        ?? ''
    );

if (
    $error === ''
    && ($_SERVER['REQUEST_METHOD'] ?? '')
        === 'POST'
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
        try {
            $action =
                trim(
                    (string) (
                        $_POST['account_information_action']
                        ?? ''
                    )
                );

            if ($action === 'save-identity') {
                llama_account_info_save_identity(
                    $db,
                    $userId,
                    (string) (
                        $_POST['display_name']
                        ?? ''
                    ),
                    (string) (
                        $_POST['username']
                        ?? ''
                    ),
                    (string) (
                        $_POST['phone_number']
                        ?? ''
                    )
                );

                $notice =
                    'Your account information has been updated.';

            } elseif ($action === 'change-email') {
                $result =
                    llama_account_info_request_email_change(
                        $db,
                        $userId,
                        (string) (
                            $_POST['new_email']
                            ?? ''
                        ),
                        (string) (
                            $_POST['current_password']
                            ?? ''
                        )
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
                        (string) (
                            $_POST['current_password']
                            ?? ''
                        )
                    );

                $notice =
                    !empty($result['sent'])
                        ? 'A fresh verification link was sent to ' . $pendingEmail . '.'
                        : '';

                if (empty($result['sent'])) {
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
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'account.information',
                    [
                        'user_id' =>
                            $userId,
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
    $error === ''
        ? llama_account_info_user(
            $db,
            $userId
        )
        : null;

$pageTitle =
    'Account Information | Llama Scout';

$pageRobots =
    'noindex,nofollow';

require dirname(__DIR__)
    . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/account/pages/account-information.css"
>

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
                Manage the private information used for your Llama Scout account.
                Public profile details remain separate.
            </p>
        </header>


        <?php if ($notice !== ''): ?>
            <div
                class="account-information-notice is-success"
                role="status"
            >
                <?= htmlspecialchars(
                    $notice,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>


        <?php if ($warning !== ''): ?>
            <div
                class="account-information-notice"
                role="status"
            >
                <?= htmlspecialchars(
                    $warning,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>


        <?php if ($error !== ''): ?>
            <div
                class="account-information-notice is-error"
                role="alert"
            >
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>


        <?php if ($account): ?>

            <section class="account-information-card">

                <header>
                    <div>
                        <p class="account-eyebrow">
                            Identity
                        </p>

                        <h2>
                            Basic account information
                        </h2>
                    </div>

                    <span>
                        Private
                    </span>
                </header>

                <form
                    method="post"
                    class="account-information-form"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $csrfToken,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="account_information_action"
                        value="save-identity"
                    >

                    <div class="account-information-grid">

                        <label>
                            <span>Display name</span>

                            <input
                                type="text"
                                name="display_name"
                                maxlength="100"
                                required
                                value="<?= htmlspecialchars(
                                    (string) (
                                        $account['display_name']
                                        ?? ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                        </label>

                        <label>
                            <span>Username</span>

                            <input
                                type="text"
                                name="username"
                                maxlength="16"
                                required
                                value="<?= htmlspecialchars(
                                    (string) (
                                        $account['username']
                                        ?? ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                            <small>
                                4 to 16 letters, numbers, or underscores.
                            </small>
                        </label>

                        <label class="is-wide">
                            <span>Phone number</span>

                            <input
                                type="tel"
                                name="phone_number"
                                maxlength="32"
                                autocomplete="tel"
                                value="<?= htmlspecialchars(
                                    (string) (
                                        $account['phone_number']
                                        ?? ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="Optional"
                            >

                            <small>
                                Optional. Kept private and not shown on your public profile.
                            </small>
                        </label>

                    </div>

                    <button
                        class="account-information-button"
                        type="submit"
                    >
                        Save account information
                    </button>

                </form>

            </section>


            <section class="account-information-card">

                <header>
                    <div>
                        <p class="account-eyebrow">
                            Sign-in email
                        </p>

                        <h2>
                            Email address
                        </h2>
                    </div>

                    <span class="account-information-verified">
                        <i
                            class="fa-solid fa-circle-check"
                            aria-hidden="true"
                        ></i>

                        Verified
                    </span>
                </header>

                <div class="account-information-current-email">

                    <span>Current verified email</span>

                    <strong>
                        <?= htmlspecialchars(
                            (string) $account['email'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>

                </div>


                <?php if (
                    !empty(
                        $account['pending_email']
                    )
                ): ?>

                    <div class="account-information-pending-email">

                        <div>
                            <span>
                                Waiting for verification
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $account['pending_email'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                            <small>
                                Your current email continues to work until this new address is verified.
                            </small>
                        </div>

                        <form method="post">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    $csrfToken,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
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
                            value="<?= htmlspecialchars(
                                $csrfToken,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
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
                            value="<?= htmlspecialchars(
                                $csrfToken,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
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
                                We will send verification to the new address first.
                                Your current sign-in email will not change until that
                                link is successfully verified.
                            </p>
                        </div>

                        <button
                            class="account-information-button"
                            type="submit"
                        >
                            Send verification to new email
                        </button>

                    </form>

                <?php endif; ?>

            </section>


            <section class="account-information-links">

                <a href="/profile.php">
                    <i
                        class="fa-solid fa-user"
                        aria-hidden="true"
                    ></i>

                    <span>
                        <strong>Public profile</strong>
                        <small>
                            Bio, photos, social links, camping style, and public profile settings
                        </small>
                    </span>

                    <i
                        class="fa-solid fa-chevron-right"
                        aria-hidden="true"
                    ></i>
                </a>

                <a href="/email-preferences.php">
                    <i
                        class="fa-solid fa-envelope"
                        aria-hidden="true"
                    ></i>

                    <span>
                        <strong>Email preferences</strong>
                        <small>
                            Newsletters, Member Dispatch, and promotional email choices
                        </small>
                    </span>

                    <i
                        class="fa-solid fa-chevron-right"
                        aria-hidden="true"
                    ></i>
                </a>

            </section>

        <?php endif; ?>

    </div>

</main>

<?php
require dirname(__DIR__)
    . '/partials/footer.php';
?>
