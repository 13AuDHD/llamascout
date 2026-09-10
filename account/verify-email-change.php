<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/database.php';
require_once dirname(__DIR__) . '/app/error-logging.php';

start_llama_session();

$db = db();

$success = '';
$error = '';

$token =
    trim(
        (string) (
            $_GET['token']
            ?? ''
        )
    );

if (
    $token === ''
    || !preg_match(
        '/^[a-f0-9]{64}$/i',
        $token
    )
) {
    $error =
        'That email-change verification link is invalid.';
} else {
    $tokenHash =
        hash(
            'sha256',
            $token
        );

    try {
        $db->beginTransaction();

        $stmt =
            $db->prepare(
                'SELECT
                    id,
                    user_id,
                    new_email
                 FROM email_change_verifications
                 WHERE token_hash = ?
                   AND used_at IS NULL
                   AND expires_at > UTC_TIMESTAMP()
                 LIMIT 1
                 FOR UPDATE'
            );

        $stmt->execute([
            $tokenHash,
        ]);

        $verification =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$verification) {
            $db->rollBack();

            $error =
                'That email-change verification link is invalid or has expired.';
        } else {
            $newEmail =
                strtolower(
                    trim(
                        (string) $verification['new_email']
                    )
                );

            $userStmt =
                $db->prepare(
                    'SELECT
                        id,
                        email,
                        pending_email
                     FROM users
                     WHERE id = ?
                     LIMIT 1
                     FOR UPDATE'
                );

            $userStmt->execute([
                (int) $verification['user_id'],
            ]);

            $user =
                $userStmt->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$user) {
                throw new RuntimeException(
                    'Account not found.'
                );
            }

            $pendingEmail =
                strtolower(
                    trim(
                        (string) (
                            $user['pending_email']
                            ?? ''
                        )
                    )
                );

            if (
                $pendingEmail === ''
                || !hash_equals(
                    $newEmail,
                    $pendingEmail
                )
            ) {
                throw new RuntimeException(
                    'This email change is no longer pending.'
                );
            }

            $duplicate =
                $db->prepare(
                    'SELECT id
                     FROM users
                     WHERE id <> ?
                       AND LOWER(email) = ?
                     LIMIT 1'
                );

            $duplicate->execute([
                (int) $user['id'],
                $newEmail,
            ]);

            if ($duplicate->fetchColumn()) {
                throw new RuntimeException(
                    'That email address is now in use by another account.'
                );
            }

            $update =
                $db->prepare(
                    'UPDATE users
                     SET
                        email = ?,
                        pending_email = NULL,
                        email_verified_at = UTC_TIMESTAMP()
                     WHERE id = ?'
                );

            $update->execute([
                $newEmail,
                (int) $user['id'],
            ]);

            $used =
                $db->prepare(
                    'UPDATE email_change_verifications
                     SET used_at = UTC_TIMESTAMP()
                     WHERE user_id = ?
                       AND used_at IS NULL'
                );

            $used->execute([
                (int) $user['id'],
            ]);

            /*
             * Any old registration/reverification token for the previous
             * address should not remain usable after the login email changes.
             */
            $expireOld =
                $db->prepare(
                    'UPDATE email_verifications
                     SET used_at = UTC_TIMESTAMP()
                     WHERE user_id = ?
                       AND used_at IS NULL'
                );

            $expireOld->execute([
                (int) $user['id'],
            ]);

            $db->commit();

            $success =
                'Your new email address has been verified and is now your Llama Scout sign-in email.';
        }

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $reference =
            llama_log_caught_exception(
                $exception,
                'account.verify_email_change'
            );

        $error =
            llama_error_message_with_reference(
                'Your email address could not be changed.',
                $reference
            );
    }
}
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
        Verify New Email | Llama Scout
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

    <script
        src="https://llamascout.com/js/accessibility.js"
    ></script>

</head>


<body class="account-auth-body">

<main class="account-auth">

    <a
        href="https://llamascout.com"
        aria-label="Llama Scout home"
    >
        <img
            src="https://llamascout.com/images/logo.png"
            alt="Llama Scout"
            class="account-auth-logo"
        >
    </a>

    <section class="account-auth-card">

        <h1>
            Verify your new email
        </h1>

        <?php if ($success !== ''): ?>

            <div
                class="account-status account-status--success"
                role="status"
            >
                <?= htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

            <a
                class="primary-button"
                href="/account-information.php"
            >
                Return to Account Information
            </a>

        <?php else: ?>

            <div
                class="account-status account-status--error"
                role="alert"
            >
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

            <a
                class="primary-button"
                href="/account-information.php"
            >
                Account Information
            </a>

        <?php endif; ?>

    </section>

</main>

</body>

</html>
