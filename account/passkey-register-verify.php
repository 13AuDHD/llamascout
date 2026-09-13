<?php

declare(strict_types=1);

require_once
    dirname(__DIR__)
    . '/app/bootstrap.php';

header(
    'Content-Type: application/json; charset=utf-8'
);

function llama_passkey_verify_response(
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
        llama_passkey_verify_response(
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
        llama_passkey_verify_response(
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

    $authorization =
        llama_passkey_registration_authorization();

    if (
        !is_array($authorization)
        ||
        (int) (
            $authorization['user_id']
            ?? 0
        )
        !==
        $userId
    ) {
        throw new RuntimeException(
            'The passkey registration approval expired. Start again with a fresh TOTP code.'
        );
    }

    $expectedHandle =
        llama_passkey_user_handle(
            $db,
            $userId
        );

    $flow =
        llama_passkey_flow(
            $db
        );

    $flow->register(
        (string) file_get_contents(
            'php://input'
        ),
        expectedUserHandle:
            $expectedHandle
    );

    llama_passkey_clear_registration_authorization();

    llama_passkey_verify_response(
        200,
        [
            'ok' =>
                true,
            'message' =>
                'Passkey added.',
        ]
    );

} catch (
    \ShipMonk\Passkeys\Ceremony\VerificationException
    $exception
) {
    llama_passkey_clear_registration_authorization();

    llama_passkey_verify_response(
        400,
        [
            'ok' =>
                false,
            'message' =>
                'The passkey could not be verified. '
                . $exception->getMessage(),
        ]
    );

} catch (Throwable $exception) {
    llama_passkey_clear_registration_authorization();

    $reference =
        llama_log_caught_exception(
            $exception,
            'account.passkey_registration_verify',
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
                'Passkey registration could not be completed.',
                $reference
            );

    llama_passkey_verify_response(
        400,
        [
            'ok' =>
                false,
            'message' =>
                $message,
        ]
    );
}
