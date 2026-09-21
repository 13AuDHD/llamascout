<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/account-information.php';

$adminUser =
    moderation_require_admin();

$db = db();

$actorUserId =
    (int) (
        $adminUser['id']
        ?? 0
    );

$targetUserId =
    (int) (
        $_POST['user_id']
        ?? 0
    );

$verificationAction =
    trim(
        (string) (
            $_POST['verification_action']
            ?? 'send_verification'
        )
    );

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
    || $targetUserId < 1
) {
    header(
        'Location: /users.php',
        true,
        303
    );

    exit;
}

if (
    !moderation_verify_csrf(
        (string) (
            $_POST['csrf_token']
            ?? ''
        )
    )
) {
    $_SESSION['admin_user_verification_error'] =
        'Your session token expired. Reload the page and try again.';

    header(
        'Location: /user.php?id='
        . $targetUserId,
        true,
        303
    );

    exit;
}

try {
    if (
        !in_array(
            $verificationAction,
            [
                'send_verification',
                'manual_verify',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Unknown email verification action.'
        );
    }

    if (
        $targetUserId
        === $actorUserId
    ) {
        throw new RuntimeException(
            'You cannot change email verification state for your own Admin account from this control.'
        );
    }

    $target =
        admin_users_get(
            $db,
            $targetUserId
        );

    if (!$target) {
        throw new RuntimeException(
            'The account no longer exists.'
        );
    }

    if (!empty($target['anonymized_at'])) {
        throw new RuntimeException(
            'An anonymized account cannot be reverified.'
        );
    }

    $actorIsOwner =
        admin_users_current_is_owner(
            $db,
            $actorUserId
        );

    $targetIsOwner =
        user_has_role(
            'owner',
            $targetUserId
        );

    if (
        $targetIsOwner
        && !$actorIsOwner
    ) {
        throw new RuntimeException(
            'Only an Owner can change verification state for an Owner account.'
        );
    }

    if (
        $verificationAction
        === 'manual_verify'
    ) {
        if (
            !empty(
                $target['email_verified_at']
            )
        ) {
            $_SESSION['admin_user_verification_notice'] =
                'This email address is already verified.';
        } else {
            $verifyStmt =
                $db->prepare(
                    'UPDATE users
                     SET email_verified_at = UTC_TIMESTAMP()
                     WHERE id = ?
                       AND anonymized_at IS NULL
                       AND email_verified_at IS NULL'
                );

            $verifyStmt->execute([
                $targetUserId,
            ]);

            if (
                $verifyStmt->rowCount()
                < 1
            ) {
                throw new RuntimeException(
                    'The email address could not be marked as verified.'
                );
            }

            admin_users_audit(
                $db,
                $actorUserId,
                $targetUserId,
                'user.email_manually_verified',
                'Manually marked the account email address as verified.',
                [
                    'email' =>
                        (string) (
                            $target['email']
                            ?? ''
                        ),
                ]
            );

            $_SESSION['admin_user_verification_notice'] =
                'Email address was manually marked as verified.';
        }
    } else {
        $result =
            llama_admin_issue_reverification(
                $db,
                $targetUserId
            );

        /*
         * A previously authenticated browser should not remain usable after
         * Admin explicitly puts this account back into verification.
         */
        admin_users_force_logout(
            $db,
            $actorUserId,
            $targetUserId
        );

        admin_users_audit(
            $db,
            $actorUserId,
            $targetUserId,
            'user.email_reverification_required',
            'Required the account to verify its email address again.',
            [
                'email' =>
                    (string) $result['email'],

                'verification_email_sent' =>
                    !empty($result['sent']),
            ]
        );

        if (!empty($result['sent'])) {
            $_SESSION['admin_user_verification_notice'] =
                'Email verification was reset and a fresh verification message was sent to '
                . $result['email']
                . '. The user was signed out everywhere.';
        } else {
            $_SESSION['admin_user_verification_warning'] =
                'Email verification was reset and the user was signed out everywhere, but the verification email could not be sent. They can use Resend Verification after signing in again.';
        }
    }

} catch (Throwable $exception) {
    $reference =
        llama_log_caught_exception(
            $exception,
            'admin.user_email_verification_action',
            [
                'target_user_id' =>
                    $targetUserId,

                'verification_action' =>
                    $verificationAction,
            ],
            [
                InvalidArgumentException::class,
                RuntimeException::class,
            ]
        );

    $_SESSION['admin_user_verification_error'] =
        $reference === null
            ? $exception->getMessage()
            : llama_error_message_with_reference(
                'Email verification could not be updated.',
                $reference
            );
}

header(
    'Location: /user.php?id='
    . $targetUserId,
    true,
    303
);

exit;
