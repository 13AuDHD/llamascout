<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Vary: Origin');

function llama_address_json(
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

function llama_address_allowed_host(
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

    if ($host === 'llamascout.com') {
        return true;
    }

    return str_ends_with(
        $host,
        '.llamascout.com'
    );
}

function llama_address_request_is_allowed(): bool
{
    $origin =
        trim(
            (string) (
                $_SERVER['HTTP_ORIGIN']
                ?? ''
            )
        );

    if ($origin !== '') {
        $originHost =
            (string) (
                parse_url(
                    $origin,
                    PHP_URL_HOST
                )
                ?? ''
            );

        return llama_address_allowed_host(
            $originHost
        );
    }

    $referer =
        trim(
            (string) (
                $_SERVER['HTTP_REFERER']
                ?? ''
            )
        );

    if ($referer !== '') {
        $refererHost =
            (string) (
                parse_url(
                    $referer,
                    PHP_URL_HOST
                )
                ?? ''
            );

        return llama_address_allowed_host(
            $refererHost
        );
    }

    return false;
}

$origin =
    trim(
        (string) (
            $_SERVER['HTTP_ORIGIN']
            ?? ''
        )
    );

if ($origin !== '') {
    $originHost =
        (string) (
            parse_url(
                $origin,
                PHP_URL_HOST
            )
            ?? ''
        );

    if (
        llama_address_allowed_host(
            $originHost
        )
    ) {
        header(
            'Access-Control-Allow-Origin: '
            . $origin
        );
    }
}

if (
    strtoupper(
        (string) (
            $_SERVER['REQUEST_METHOD']
            ?? 'GET'
        )
    ) === 'OPTIONS'
) {
    if (
        !llama_address_request_is_allowed()
    ) {
        llama_address_json(
            [
                'success' => false,
                'message' => 'Address lookup request is not allowed.',
            ],
            403
        );
    }

    header(
        'Access-Control-Allow-Methods: GET, OPTIONS'
    );
    header(
        'Access-Control-Allow-Headers: Accept'
    );
    header(
        'Access-Control-Max-Age: 600'
    );

    http_response_code(204);
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
    header('Allow: GET, OPTIONS');

    llama_address_json(
        [
            'success' => false,
            'message' => 'Method not allowed.',
        ],
        405
    );
}

/*
 * This is intentionally a reusable browser-facing Llama Scout API.
 * It is not tied to Scout authentication so checkout, account forms,
 * admin tools, and future public forms can all use the same service.
 *
 * The Geoapify key stays on the server. Browser requests are accepted
 * only when they originate from llamascout.com or one of its subdomains.
 */
if (
    !llama_address_request_is_allowed()
) {
    llama_address_json(
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

$queryLength =
    function_exists('mb_strlen')
        ? mb_strlen($query)
        : strlen($query);

if (
    $queryLength < 3
    || $queryLength > 150
) {
    llama_address_json(
        [
            'success' => false,
            'message' => 'Enter at least 3 characters of an address.',
        ],
        400
    );
}

$config = llama_config();

$apiKey =
    trim(
        (string) (
            $config['geoapify']['api_key']
            ?? ''
        )
    );

if ($apiKey === '') {
    llama_address_json(
        [
            'success' => false,
            'message' => 'Address lookup is not configured.',
        ],
        503
    );
}

$url =
    'https://api.geoapify.com/v1/geocode/autocomplete?'
    . http_build_query(
        [
            'text' => $query,
            'format' => 'json',
            'limit' => 6,
            'lang' => 'en',
            'apiKey' => $apiKey,
        ],
        '',
        '&',
        PHP_QUERY_RFC3986
    );

$curl = curl_init($url);

if ($curl === false) {
    llama_address_json(
        [
            'success' => false,
            'message' => 'Address lookup could not start.',
        ],
        503
    );
}

curl_setopt_array(
    $curl,
    [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: LlamaScout/1.0 (https://llamascout.com)',
        ],
    ]
);

$body = curl_exec($curl);

$httpStatus =
    (int) curl_getinfo(
        $curl,
        CURLINFO_RESPONSE_CODE
    );

$curlError =
    curl_error($curl);

curl_close($curl);

if (
    !is_string($body)
    || $body === ''
    || $httpStatus < 200
    || $httpStatus >= 300
) {
    error_log(
        'Geoapify address lookup failed. HTTP '
        . $httpStatus
        . (
            $curlError !== ''
                ? ' | ' . $curlError
                : ''
        )
    );

    llama_address_json(
        [
            'success' => false,
            'message' => 'Address lookup is temporarily unavailable.',
        ],
        502
    );
}

$decoded =
    json_decode(
        $body,
        true
    );

$rows =
    is_array(
        $decoded['results']
        ?? null
    )
        ? $decoded['results']
        : [];

$results = [];

foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }

    $addressLine1 =
        trim(
            (string) (
                $row['address_line1']
                ?? ''
            )
        );

    if ($addressLine1 === '') {
        $addressLine1 =
            trim(
                implode(
                    ' ',
                    array_filter(
                        [
                            $row['housenumber']
                                ?? null,
                            $row['street']
                                ?? null,
                        ],
                        static fn ($value): bool =>
                            is_string($value)
                            && trim($value) !== ''
                    )
                )
            );
    }

    $city =
        trim(
            (string) (
                $row['city']
                ?? $row['town']
                ?? $row['village']
                ?? $row['municipality']
                ?? $row['county']
                ?? ''
            )
        );

    $stateCode =
        strtoupper(
            trim(
                (string) (
                    $row['state_code']
                    ?? ''
                )
            )
        );

    $stateName =
        trim(
            (string) (
                $row['state']
                ?? ''
            )
        );

    $countryCode =
        strtolower(
            trim(
                (string) (
                    $row['country_code']
                    ?? ''
                )
            )
        );

    $state =
        $countryCode === 'us'
        && $stateCode !== ''
            ? $stateCode
            : (
                $stateName !== ''
                    ? $stateName
                    : $stateCode
            );

    $label =
        trim(
            (string) (
                $row['formatted']
                ?? ''
            )
        );

    if ($label === '') {
        $label =
            trim(
                implode(
                    ', ',
                    array_filter(
                        [
                            $addressLine1,
                            $city,
                            $state,
                            $row['postcode']
                                ?? null,
                            $row['country']
                                ?? null,
                        ],
                        static fn ($value): bool =>
                            is_string($value)
                            && trim($value) !== ''
                    )
                )
            );
    }

    if (
        $label === ''
        || $addressLine1 === ''
    ) {
        continue;
    }

    $results[] = [
        'label' => $label,
        'address_line_1' => $addressLine1,
        'city' => $city,
        'state' => $state,
        'postal_code' =>
            trim(
                (string) (
                    $row['postcode']
                    ?? ''
                )
            ),
        'country' =>
            trim(
                (string) (
                    $row['country']
                    ?? ''
                )
            ),
    ];
}

llama_address_json(
    [
        'success' => true,
        'results' => $results,
    ]
);
