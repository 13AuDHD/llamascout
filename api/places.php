<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $places = places_public();

    echo json_encode(
        [
            'ok' => true,
            'count' => count($places),
            'places' => $places,
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {
    $reference = llama_log_caught_exception($e, 'api_places');
    http_response_code(500);

    echo json_encode(
        [
            'ok' => false,
            'error' => llama_error_message_with_reference('Unable to load places.', $reference),
            'reference' => $reference,
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}
