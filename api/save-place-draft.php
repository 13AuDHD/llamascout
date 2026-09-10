<?php

declare(strict_types=1);

require_once
    dirname(__DIR__)
    . '/app/bootstrap.php';

require_once
    dirname(__DIR__)
    . '/app/place-drafts.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Cache-Control: no-store, max-age=0'
);

function draft_save_api_response(
    bool $success,
    array $payload = [],
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
            ],
            $payload
        ),
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {
    draft_save_api_response(
        false,
        [
            'message' =>
                'This endpoint only accepts POST requests.',
        ],
        405
    );
}

$user =
    current_user();

$userId =
    (int) (
        $user['id']
        ?? 0
    );

if ($userId < 1) {
    draft_save_api_response(
        false,
        [
            'message' =>
                'Your sign-in session has expired. Sign in again and retry the save.',
        ],
        401
    );
}

if (
    !community_verify_csrf(
        (string) (
            $_POST['csrf_token']
            ?? ''
        )
    )
) {
    draft_save_api_response(
        false,
        [
            'message' =>
                'Your session token expired. Reload the Add a Place page and try again.',
        ],
        419
    );
}

$draftId =
    max(
        0,
        (int) (
            $_POST['draft_id']
            ?? 0
        )
    );

try {
    $savedDraftId =
        llama_place_draft_save(
            db(),
            $userId,
            $draftId,
            $_POST
        );

    draft_save_api_response(
        true,
        [
            'draft_id' =>
                $savedDraftId,

            'message' =>
                'Place saved for later.',

            'redirect' =>
                'https://account.llamascout.com/saved-later.php?saved='
                . $savedDraftId,
        ]
    );

} catch (Throwable $exception) {
    $reference =
        llama_log_caught_exception(
            $exception,
            'place.draft.api_save',
            [
                'user_id' =>
                    $userId,

                'draft_id' =>
                    $draftId,
            ],
            [
                InvalidArgumentException::class,
                RuntimeException::class,
            ]
        );

    $message =
        $reference === null
            ? $exception->getMessage()
            : llama_error_message_with_reference(
                'The Place could not be saved for later.',
                $reference
            );

    draft_save_api_response(
        false,
        [
            'message' =>
                $message,
        ],
        400
    );
}
