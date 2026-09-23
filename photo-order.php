<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

require_verified_email();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function contributor_photo_order_respond(
    int $status,
    array $payload
): never {
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    contributor_photo_order_respond(405, [
        'success' => false,
        'message' => 'Photo order changes must use POST.',
    ]);
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$context = strtolower(trim((string) ($_POST['context'] ?? '')));
$csrf = (string) ($_POST['csrf_token'] ?? '');

if (!llama_photo_verify_csrf($csrf)) {
    contributor_photo_order_respond(403, [
        'success' => false,
        'message' => 'Your photo upload session expired. Refresh the page and try again.',
    ]);
}

try {
    if ($context !== 'add-place') {
        throw new InvalidArgumentException(
            'Photo ordering is not available for this uploader.'
        );
    }

    if (!llama_photo_context_allowed($context, $userId)) {
        contributor_photo_order_respond(403, [
            'success' => false,
            'message' => 'You do not have permission to reorder these photos.',
        ]);
    }

    $token = llama_photo_stage_token(
        (string) ($_POST['token'] ?? '')
    );

    if (
        !llama_photo_stage_manifest_exists(
            $context,
            $userId,
            $token
        )
    ) {
        throw new RuntimeException(
            'This photo upload session is no longer available. Reload the page to restore the saved photos.'
        );
    }

    $submitted = llama_photo_decode_form_photos(
        $_POST['photos_json'] ?? '[]'
    );

    $manifest = llama_photo_read_manifest(
        $context,
        $userId,
        $token
    );

    if (count($submitted) !== count($manifest)) {
        throw new RuntimeException(
            'The photo list changed while you were reordering it. Reload the page and try again.'
        );
    }

    $manifestByPath = [];

    foreach ($manifest as $photo) {
        if (!is_array($photo)) {
            throw new RuntimeException(
                'The staged photo list is invalid. Reload the page and try again.'
            );
        }

        $path = trim((string) ($photo['path'] ?? ''));

        if (
            $path === ''
            || isset($manifestByPath[$path])
        ) {
            throw new RuntimeException(
                'The staged photo list is invalid. Reload the page and try again.'
            );
        }

        $manifestByPath[$path] = $photo;
    }

    $ordered = [];
    $seen = [];

    foreach ($submitted as $submittedPhoto) {
        $path = trim(
            (string) ($submittedPhoto['path'] ?? '')
        );

        if (
            $path === ''
            || !isset($manifestByPath[$path])
            || isset($seen[$path])
        ) {
            throw new InvalidArgumentException(
                'The photo order could not be verified. Reload the page and try again.'
            );
        }

        $photo = $manifestByPath[$path];
        $photo['alt'] = mb_substr(
            trim(
                (string) (
                    $submittedPhoto['alt']
                    ?? $photo['alt']
                    ?? ''
                )
            ),
            0,
            300
        );

        $ordered[] = $photo;
        $seen[$path] = true;
    }

    if (count($seen) !== count($manifestByPath)) {
        throw new InvalidArgumentException(
            'The photo order could not be verified. Reload the page and try again.'
        );
    }

    llama_photo_write_manifest(
        $context,
        $userId,
        $token,
        $ordered
    );

    contributor_photo_order_respond(200, [
        'success' => true,
        'token' => $token,
        'photos' => array_values($ordered),
    ]);

} catch (InvalidArgumentException | RuntimeException $exception) {
    contributor_photo_order_respond(422, [
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    $reference = llama_log_caught_exception(
        $exception,
        'photo.contributor_order',
        [
            'user_id' => $userId,
            'context' => $context,
        ]
    );

    contributor_photo_order_respond(500, [
        'success' => false,
        'message' => llama_error_message_with_reference(
            'The photo order could not be saved.',
            (string) $reference
        ),
    ]);
}
