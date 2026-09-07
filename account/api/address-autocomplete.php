<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/address-autocomplete.php';

require_login();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

function account_address_json(
    array $payload,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

if (
    strtoupper(
        (string) (
            $_SERVER['REQUEST_METHOD']
            ?? 'GET'
        )
    ) !== 'GET'
) {
    header('Allow: GET');

    account_address_json(
        [
            'success' => false,
            'message' => 'Method not allowed.',
        ],
        405
    );
}

$query =
    trim(
        (string) (
            $_GET['q']
            ?? ''
        )
    );

$latitude = null;
$longitude = null;

if (
    isset($_GET['lat'], $_GET['lon'])
    && is_numeric((string) $_GET['lat'])
    && is_numeric((string) $_GET['lon'])
) {
    $candidateLat =
        (float) $_GET['lat'];

    $candidateLon =
        (float) $_GET['lon'];

    if (
        $candidateLat >= -90
        && $candidateLat <= 90
        && $candidateLon >= -180
        && $candidateLon <= 180
    ) {
        $latitude = $candidateLat;
        $longitude = $candidateLon;
    }
}

try {
    $results =
        llama_address_autocomplete_query(
            $query,
            6,
            $latitude,
            $longitude
        );

    account_address_json(
        [
            'success' => true,
            'results' => $results,
            'location_bias' =>
                $latitude !== null
                && $longitude !== null,
        ]
    );
} catch (InvalidArgumentException $exception) {
    account_address_json(
        [
            'success' => false,
            'message' => $exception->getMessage(),
        ],
        400
    );
} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'api.account_address_autocomplete',
        [
            'user_id' =>
                (int) (
                    current_user()['id']
                    ?? 0
                ),
        ],
        [RuntimeException::class]
    );

    account_address_json(
        [
            'success' => false,
            'message' => $exception->getMessage(),
        ],
        503
    );
}
