<?php

declare(strict_types=1);

require_once
    dirname(__DIR__)
    . '/app/bootstrap.php';

require_once
    dirname(__DIR__)
    . '/app/account-security.php';

header(
    'Content-Type: application/json; charset=utf-8'
);

function llama_passkey_options_response(
    int $status,
    array|string $body
): never {
    http_response_code(
        $status
    );

    echo
        is_string($body)
            ? $body
            : json_encode(
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
        llama_passkey_options_response(
            405,
            [
                'ok' =>
                    false,
                'message' =>
                    'POST required.',
            ]
        );
    }

    require_login();
    require_verified_email();

    $db =
        db();

    $user =
        current_user();

    $userId =
        (int) (
            $user['id']
            ?? 0
        );

    if (
        $userId < 1
        ||
        !llama_passkey_account_is_owner(
            $db,
            $userId
        )
    ) {
        llama_passkey_options_response(
            403,
            [
                'ok' =>
                    false,
                'message' =>
                    'Owner access is required for passkeys.',
            ]
        );
    }

    llama_passkey_assert_library_ready();

    $body =
        json_decode(
            (string) file_get_contents(
                'php://input'
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

    if (!is_array($body)) {
        throw new InvalidArgumentException(
            'Invalid request.'
        );
    }

    $csrf =
        (string) (
            $body['csrf_token']
            ?? ''
        );

    if (
        !llama_account_security_verify_csrf(
            $csrf
        )
    ) {
        llama_passkey_options_response(
            403,
            [
                'ok' =>
                    false,
                'message' =>
                    'Your session expired. Reload the page and try again.',
            ]
        );
    }

    if (
        !llama_mfa_is_enabled(
            $userId,
            $db
        )
    ) {
        throw new RuntimeException(
            'TOTP must be enabled before a passkey can be added.'
        );
    }

    llama_account_security_verify_totp(
        $db,
        $userId,
        (string) (
            $body['totp_code']
            ?? ''
        )
    );

    llama_mfa_mark_session_verified(
        $userId
    );

    $label =
        llama_passkey_validate_label(
            (string) (
                $body['label']
                ?? ''
            )
        );

    llama_passkey_begin_registration_authorization(
        $userId,
        $label
    );

    $userHandle =
        llama_passkey_user_handle(
            $db,
            $userId
        );

    $username =
        trim(
            (string) (
                $user['email']
                ?? ''
            )
        );

    if ($username === '') {
        throw new RuntimeException(
            'The account email could not be loaded.'
        );
    }

    $displayName =
        trim(
            (string) (
                $user['display_name']
                ?: $user['username']
                ?: $username
            )
        );

    $flow =
        llama_passkey_flow(
            $db
        );

    $options =
        $flow->registrationOptions(
            userHandle:
                $userHandle,
            username:
                $username,
            displayName:
                $displayName
        );

    llama_passkey_options_response(
        200,
        $options->toJson()
    );

} catch (Throwable $exception) {
    llama_passkey_clear_registration_authorization();

    $reference =
        llama_log_caught_exception(
            $exception,
            'account.passkey_registration_options',
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
                'Passkey registration could not begin.',
                $reference
            );

    llama_passkey_options_response(
        400,
        [
            'ok' =>
                false,
            'message' =>
                $message,
        ]
    );
}
