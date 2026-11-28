<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/resource-map-query.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=60');

try {
    $result = llama_resource_map_query(db(), $_GET);

    echo json_encode(
        ['ok' => true] + $result,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    header('Cache-Control: no-store');
    echo json_encode(['ok'=>false,'error'=>$exception->getMessage()]);
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
