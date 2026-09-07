<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/address-autocomplete.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

function public_address_json(
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

function public_address_allowed_host(
    string $host
): bool {
    $host =
        strtolower(
            trim(
                preg_replace(
                    '/:\d+$/',
                    '',
                    $host
                )
                ?? ''
            )
        );

    return
        $host === 'llamascout.com'
        || $host === 'www.llamascout.com';
}

function public_address_request_allowed(): bool
{
    $referer =
        trim(
            (string) (
                $_SERVER['HTTP_REFERER']
                ?? ''
            )
        );

    if ($referer === '') {
        return false;
    }

    $refererHost =
        (string) (
            parse_url(
                $referer,
                PHP_URL_HOST
            )
            ?? ''
        );

    return public_address_allowed_host(
        $refererHost
    );
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

    public_address_json(
        [
            'success' => false,
            'message' => 'Method not allowed.',
        ],
        405
    );
}

/*
 * Root-site browser forms may use this wrapper. Direct navigation,
 * hotlinking, and calls from unrelated sites are rejected.
 *
 * Account and admin surfaces should use their own same-origin wrappers
 * around app/address-autocomplete.php instead of making cross-origin
 * browser requests to this endpoint.
 */
if (!public_address_request_allowed()) {
    public_address_json(
        [
            'success' => false,
            'message' => 'Address lookup request is not allowed.',
        ],
        403
    );
}

$query =
    trim(
        (string) (
            $_GET['q']
            ?? ''
        )
    );

try {
    $results =
        llama_address_autocomplete_query(
            $query,
            6
        );

    public_address_json(
        [
            'success' => true,
            'results' => $results,
        ]
    );
} catch (InvalidArgumentException $exception) {
    public_address_json(
        [
            'success' => false,
            'message' => $exception->getMessage(),
        ],
        400
    );
} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'api.address_autocomplete',
        [],
        [RuntimeException::class]
    );

    public_address_json(
        [
            'success' => false,
            'message' => $exception->getMessage(),
        ],
        503
    );
}
