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


function llama_passkey_login_options_response(
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
        llama_passkey_login_options_response(
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
            'Passkey sign-in must begin from the Llama Scout account site.'
        );
    }

    $flow =
        llama_passkey_flow(
            db()
        );

    $options =
        $flow->authenticationOptions();

    llama_passkey_login_options_response(
        200,
        $options->toJson()
    );

} catch (Throwable $exception) {
    $reference =
        llama_log_caught_exception(
            $exception,
            'account.passkey_login_options',
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
                'Passkey sign-in could not begin.',
                $reference
            );

    llama_passkey_login_options_response(
        400,
        [
            'ok' =>
                false,
            'message' =>
                $message,
        ]
    );
}
