<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');

try {
    /*
     * Never send exact coordinates unless the current authenticated account
     * actually has member access. The browser is not trusted to enforce this.
     */
    $hasMemberMapAccess = user_has_member_access();
    $places = places_map($hasMemberMapAccess);

    echo json_encode(
        [
            'ok' => true,
            'count' => count($places),
            'member_map_access' => $hasMemberMapAccess,
            'coordinate_precision' => $hasMemberMapAccess ? 'exact' : 'approximate',
            'max_zoom' => $hasMemberMapAccess ? 20 : 11,
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
