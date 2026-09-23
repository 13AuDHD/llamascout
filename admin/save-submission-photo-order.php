<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/app/bootstrap.php';

$adminUser =
    moderation_require_admin();

header(
    'Content-Type: application/json; charset=UTF-8'
);

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {
    http_response_code(405);
    header('Allow: POST');

    echo json_encode([
        'ok' => false,
        'message' =>
            'Photo order can only be saved with POST.',
    ]);

    exit;
}

$db = db();
$submissionId = 0;

try {
    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        http_response_code(403);

        throw new RuntimeException(
            'Your session could not be verified. Reload the page and try again.'
        );
    }

    $submissionId =
        (int) (
            $_POST['id']
            ?? 0
        );

    if ($submissionId < 1) {
        throw new InvalidArgumentException(
            'The Place submission could not be identified.'
        );
    }

    $orderJson =
        trim(
            (string) (
                $_POST['order']
                ?? ''
            )
        );

    if ($orderJson === '') {
        throw new InvalidArgumentException(
            'No photo order was provided.'
        );
    }

    try {
        $order =
            json_decode(
                $orderJson,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
    } catch (JsonException) {
        throw new InvalidArgumentException(
            'The photo order was invalid. Reload the page and try again.'
        );
    }

    if (!is_array($order)) {
        throw new InvalidArgumentException(
            'The photo order was invalid. Reload the page and try again.'
        );
    }

    $db->beginTransaction();

    $submission =
        moderation_submission(
            $db,
            $submissionId,
            true
        );

    if (!$submission) {
        throw new RuntimeException(
            'The Place submission could not be found.'
        );
    }

    if (
        !in_array(
            (string) $submission['status'],
            [
                'pending',
                'needs-changes',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'This Place submission is no longer awaiting review.'
        );
    }

    $data =
        is_array(
            $submission['data']
            ?? null
        )
            ? $submission['data']
            : [];

    $photos =
        is_array(
            $data['photos']
            ?? null
        )
            ? array_values(
                $data['photos']
            )
            : [];

    $photoCount =
        count($photos);

    if (
        count($order)
        !== $photoCount
    ) {
        throw new RuntimeException(
            'The submitted photos changed while this page was open. Reload the page before reordering them.'
        );
    }

    $normalizedOrder = [];

    foreach ($order as $position) {
        if (!is_int($position)) {
            throw new InvalidArgumentException(
                'The photo order was invalid. Reload the page and try again.'
            );
        }

        $normalizedOrder[] =
            $position;
    }

    $expectedOrder =
        $photoCount > 0
            ? range(
                0,
                $photoCount - 1
            )
            : [];

    $sortedOrder =
        $normalizedOrder;

    sort(
        $sortedOrder,
        SORT_NUMERIC
    );

    if ($sortedOrder !== $expectedOrder) {
        throw new InvalidArgumentException(
            'The photo order was invalid. Reload the page and try again.'
        );
    }

    $reorderedPhotos = [];

    foreach ($normalizedOrder as $index) {
        $reorderedPhotos[] =
            $photos[$index];
    }

    $data['photos'] =
        $reorderedPhotos;

    $update =
        $db->prepare(
            'UPDATE place_submissions
             SET submission_data = ?
             WHERE id = ?
               AND status IN ("pending","needs-changes")'
        );

    $update->execute([
        json_encode(
            $data,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        ),
        $submissionId,
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException(
            'The Place submission changed before the photo order could be saved.'
        );
    }

    $db->commit();

    echo json_encode([
        'ok' => true,
        'photo_count' =>
            $photoCount,
    ]);
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    $safeExceptions = [
        InvalidArgumentException::class,
        RuntimeException::class,
    ];

    $reference =
        llama_log_caught_exception(
            $exception,
            'admin.save_submission_photo_order',
            [
                'submission_id' =>
                    $submissionId,
                'admin_user_id' =>
                    (int) (
                        $adminUser['id']
                        ?? 0
                    ),
            ],
            $safeExceptions
        );

    if (http_response_code() < 400) {
        http_response_code(
            $reference === null
                ? 422
                : 500
        );
    }

    echo json_encode([
        'ok' => false,
        'message' =>
            $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The photo order could not be saved.',
                    $reference
                ),
    ]);
}
