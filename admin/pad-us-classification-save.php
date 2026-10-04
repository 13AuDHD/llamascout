<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/pad-us-sync.php';
require_once dirname(__DIR__) . '/app/pad-us-review.php';

$adminUser =
    moderation_require_admin();

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {
    http_response_code(405);
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
    http_response_code(403);
    exit;
}

$designation =
    trim(
        (string) (
            $_POST['designation']
            ?? ''
        )
    );

$designationCode =
    trim(
        (string) (
            $_POST['designation_code']
            ?? ''
        )
    );

$mode =
    trim(
        (string) (
            $_POST['mode']
            ?? 'pending'
        )
    );

$propertyTypeSlug =
    trim(
        (string) (
            $_POST['property_type_slug']
            ?? ''
        )
    );

$surface =
    isset(
        $_POST['surface_in_place_form']
    );

$notes =
    trim(
        (string) (
            $_POST['notes']
            ?? ''
        )
    );

$stateCode =
    strtoupper(
        trim(
            (string) (
                $_POST['state']
                ?? 'CO'
            )
        )
    );

try {
    if (
        $mode === 'mapped'
        && $propertyTypeSlug !== ''
    ) {
        $mainDb =
            db();

        $check =
            $mainDb->prepare(
                'SELECT COUNT(*)
                 FROM place_property_types
                 WHERE slug = ?
                   AND active = 1'
            );

        $check->execute([
            $propertyTypeSlug,
        ]);

        if (
            (int) $check
                ->fetchColumn() < 1
        ) {
            throw new InvalidArgumentException(
                'The selected Llama Scout property type is not active.'
            );
        }
    }

    llama_pad_us_save_classification_mapping(
        reference_db(),
        $designation,
        $designationCode,
        $mode,
        $propertyTypeSlug,
        $surface,
        $notes
    );

    $query =
        http_build_query(
            [
                'state' =>
                    $stateCode,
                'saved' =>
                    '1',
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

    header(
        'Location: /pad-us-classifications.php?'
        . $query
    );

    exit;

} catch (Throwable $exception) {
    $reference =
        llama_log_caught_exception(
            $exception,
            'admin.pad_us_classification_save',
            [
                'designation' =>
                    $designation,
                'mode' =>
                    $mode,
            ],
            [
                InvalidArgumentException::class,
                RuntimeException::class,
            ]
        );

    $query =
        http_build_query(
            [
                'state' =>
                    $stateCode,
                'error' =>
                    $reference === null
                        ? $exception->getMessage()
                        : llama_error_message_with_reference(
                            'The PAD-US classification could not be saved.',
                            $reference
                        ),
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

    header(
        'Location: /pad-us-classifications.php?'
        . $query
    );

    exit;
}
