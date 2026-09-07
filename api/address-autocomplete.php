<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

$user = current_user();
$userId = (int) ($user['id'] ?? 0);

if ($userId < 1) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Sign in to use address lookup.',
    ]);

    exit;
}

$query =
    trim(
        (string) (
            $_GET['q']
            ?? ''
        )
    );

if (
    mb_strlen($query) < 3
    || mb_strlen($query) > 150
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Enter at least 3 characters of an address.',
    ]);

    exit;
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
    http_response_code(503);

    echo json_encode([
        'success' => false,
        'message' => 'Address lookup is not configured.',
    ]);

    exit;
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
    http_response_code(503);

    echo json_encode([
        'success' => false,
        'message' => 'Address lookup could not start.',
    ]);

    exit;
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
        'Geoapify Scout address lookup failed. HTTP '
        . $httpStatus
        . ($curlError !== ''
            ? ' | ' . $curlError
            : '')
    );

    http_response_code(502);

    echo json_encode([
        'success' => false,
        'message' => 'Address lookup is temporarily unavailable.',
    ]);

    exit;
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

echo json_encode(
    [
        'success' => true,
        'results' => $results,
    ],
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
);
