<?php

declare(strict_types=1);

require_once
    dirname(__DIR__)
    . '/app/auth.php';

require_once
    dirname(__DIR__)
    . '/app/passkeys.php';

require_once
    dirname(__DIR__)
    . '/app/passkey-library.php';


start_llama_session();


header(
    'Content-Type: application/json; charset=utf-8'
);


function llama_passkey_login_verify_response(
    int $status,
    array $body
): never {
    http_response_code(
        $status
    );

    echo json_encode(
        $body,
        JSON_THROW_ON_ERROR
    );

    exit;
}


try {
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '')
        !==
        'POST'
    ) {
        llama_passkey_login_verify_response(
            405,
            [
                'ok' =>
                    false,
                'message' =>
                    'POST required.',
            ]
        );
    }

    llama_passkey_assert_library_ready();

    $origin =
        trim(
            (string) (
                $_SERVER['HTTP_ORIGIN']
                ?? ''
            )
        );

    if (
        $origin !== ''
        &&
        $origin !==
            LLAMA_PASSKEY_ORIGIN
    ) {
        throw new RuntimeException(
            'Passkey sign-in must finish on the Llama Scout account site.'
        );
    }

    $db =
        db();

    $flow =
        llama_passkey_flow(
            $db
        );

    $result =
        $flow->authenticate(
            (string) file_get_contents(
                'php://input'
            )
        );

    $userId =
        llama_passkey_user_id_from_handle(
            $db,
            $result->userHandle
        );

    if ($userId < 1) {
        throw new RuntimeException(
            'The passkey account could not be found.'
        );
    }

    $stmt =
        $db->prepare(
            '
            SELECT
                id,
                status,
                anonymized_at

            FROM users

            WHERE id = ?

            LIMIT 1
            '
        );

    $stmt->execute([
        $userId
    ]);

    $user =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!is_array($user)) {
        throw new RuntimeException(
            'The passkey account could not be found.'
        );
    }

    if (
        !empty(
            $user['anonymized_at']
        )
        ||
        in_array(
            (string) (
                $user['status']
                ?? ''
            ),
            [
                'suspended',
                'disabled',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'This account cannot sign in.'
        );
    }

    if (
        !llama_passkey_account_is_owner(
            $db,
            $userId
        )
    ) {
        throw new RuntimeException(
            'This passkey is not authorized for Owner sign-in.'
        );
    }

    /*
     * TOTP remains the emergency fallback for passkey-enabled
     * Owner accounts. If it was reset/removed, the Owner must
     * restore MFA before passkey sign-in is enabled again.
     */
    if (
        !llama_mfa_is_enabled(
            $userId,
            $db
        )
    ) {
        throw new RuntimeException(
            'This Owner account must restore MFA before passkey sign-in can be used.'
        );
    }

    llama_mfa_clear_session_state();

    clear_remember_cookie();

    session_regenerate_id(
        true
    );

    $_SESSION[
        'user_id'
    ] =
        $userId;

    $_SESSION[
        'logged_in_at'
    ] =
        time();

    llama_mfa_mark_session_verified(
        $userId
    );

    $loginStmt =
        $db->prepare(
            '
            UPDATE users

            SET
                last_login_at =
                    UTC_TIMESTAMP(),

                dormancy_notice_sent_at =
                    NULL

            WHERE id = ?
            '
        );

    $loginStmt->execute([
        $userId
    ]);

    $destination =
        llama_safe_return_url(
            (string) (
                $_GET['return']
                ?? ''
            )
        )
        ?:
        'https://account.llamascout.com/';

    llama_passkey_login_verify_response(
        200,
        [
            'ok' =>
                true,
            'destination' =>
                $destination,
            'possible_clone' =>
                $result->possibleClone,
        ]
    );

} catch (
    \ShipMonk\Passkeys\Ceremony\VerificationException
    $exception
) {
    llama_passkey_login_verify_response(
        401,
        [
            'ok' =>
                false,
            'message' =>
                'The passkey could not be verified. '
                . $exception->getMessage(),
        ]
    );

} catch (Throwable $exception) {
    $reference =
        llama_log_caught_exception(
            $exception,
            'account.passkey_login_verify',
            [],
            [
                InvalidArgumentException::class,
                RuntimeException::class,
            ]
        );

    $message =
        $reference === null
            ? $exception->getMessage()
            : llama_error_message_with_reference(
                'Passkey sign-in could not be completed.',
                $reference
            );

    llama_passkey_login_verify_response(
        401,
        [
            'ok' =>
                false,
            'message' =>
                $message,
        ]
    );
}
