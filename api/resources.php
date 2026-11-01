<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/resources.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=60');

try {
    $resources =
        llama_public_resources(
            db()
        );

    echo json_encode(
        [
            'ok' => true,
            'count' => count($resources),
            'resources' => $resources,
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

} catch (Throwable $exception) {
    $reference =
        llama_log_caught_exception(
            $exception,
            'api_resources'
        );

    http_response_code(500);

    echo json_encode(
        [
            'ok' => false,
            'error' =>
                llama_error_message_with_reference(
                    'Unable to load Resources.',
                    $reference
                ),
            'reference' => $reference,
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );
}
