<?php

declare(strict_types=1);


/*
 * Shared Llama Scout address lookup service.
 *
 * Geoapify stays server-side. Autocomplete may be biased to the user's
 * current location. Reverse lookup turns browser coordinates into a
 * structured mailing-address suggestion.
 */


function llama_address_geoapify_key(): string
{
    $config = llama_config();

    $apiKey =
        trim(
            (string) (
                $config['geoapify']['api_key']
                ?? ''
            )
        );

    if ($apiKey === '') {
        throw new RuntimeException(
            'Address lookup is not configured.'
        );
    }

    return $apiKey;
}


function llama_address_geoapify_request(
    string $endpoint,
    array $params
): array {
    $params['format'] = 'json';
    $params['lang'] = 'en';
    $params['apiKey'] =
        llama_address_geoapify_key();

    $url =
        'https://api.geoapify.com/v1/geocode/'
        . $endpoint
        . '?'
        . http_build_query(
            $params,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException(
            'Address lookup could not start.'
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

        throw new RuntimeException(
            'Address lookup is temporarily unavailable.'
        );
    }

    $decoded =
        json_decode(
            $body,
            true
        );

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'Address lookup is temporarily unavailable.'
        );
    }

    return $decoded;
}


/*
 * =========================================================
 * AUTOMATIC ADDRESS SEARCH BIAS
 * =========================================================
 *
 * Priority:
 * 1. Explicit coordinates supplied by the browser, such as the
 *    "Use my location" control.
 * 2. Cloudflare's visitor-location latitude/longitude headers.
 * 3. A short, session-cached IP geolocation lookup.
 * 4. No bias if none of those are available.
 *
 * IP-derived location is intentionally only a search-ranking hint.
 * It is never saved as the user's mailing address.
 */


function llama_address_valid_bias(
    mixed $latitude,
    mixed $longitude
): ?array {
    if (
        !is_numeric($latitude)
        || !is_numeric($longitude)
    ) {
        return null;
    }

    $latitude =
        (float) $latitude;

    $longitude =
        (float) $longitude;

    if (
        $latitude < -90
        || $latitude > 90
        || $longitude < -180
        || $longitude > 180
    ) {
        return null;
    }

    return [
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];
}


function llama_address_cloudflare_bias(): ?array
{
    return llama_address_valid_bias(
        $_SERVER['HTTP_CF_IPLATITUDE']
            ?? null,
        $_SERVER['HTTP_CF_IPLONGITUDE']
            ?? null
    );
}


function llama_address_client_ip(): ?string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? '',
        $_SERVER['REMOTE_ADDR']
            ?? '',
    ];

    foreach ($candidates as $candidate) {
        $candidate =
            trim(
                (string) $candidate
            );

        if ($candidate === '') {
            continue;
        }

        if (
            filter_var(
                $candidate,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE
                | FILTER_FLAG_NO_RES_RANGE
            ) !== false
        ) {
            return $candidate;
        }
    }

    return null;
}


function llama_address_cached_ip_bias(): ?array
{
    $cacheKey =
        'llama_address_ip_bias_v1';

    $cached =
        $_SESSION[$cacheKey]
        ?? null;

    if (
        is_array($cached)
        && (int) (
            $cached['expires_at']
            ?? 0
        ) > time()
    ) {
        return llama_address_valid_bias(
            $cached['latitude']
                ?? null,
            $cached['longitude']
                ?? null
        );
    }

    $ip =
        llama_address_client_ip();

    if ($ip === null) {
        $_SESSION[$cacheKey] = [
            'expires_at' =>
                time() + 900,
            'latitude' => null,
            'longitude' => null,
        ];

        return null;
    }

    $curl =
        curl_init(
            'https://ipwho.is/'
            . rawurlencode($ip)
        );

    if ($curl === false) {
        return null;
    }

    curl_setopt_array(
        $curl,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: LlamaScout/1.0 (https://llamascout.com)',
            ],
        ]
    );

    $body =
        curl_exec($curl);

    $status =
        (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

    curl_close($curl);

    $bias = null;

    if (
        is_string($body)
        && $body !== ''
        && $status >= 200
        && $status < 300
    ) {
        $decoded =
            json_decode(
                $body,
                true
            );

        if (
            is_array($decoded)
            && (
                !array_key_exists(
                    'success',
                    $decoded
                )
                || $decoded['success'] === true
            )
        ) {
            $bias =
                llama_address_valid_bias(
                    $decoded['latitude']
                        ?? null,
                    $decoded['longitude']
                        ?? null
                );
        }
    }

    if ($bias === null) {
        /*
         * Cache failures briefly so an unavailable IP service does
         * not delay every autocomplete keystroke.
         */
        $_SESSION[$cacheKey] = [
            'expires_at' =>
                time() + 900,
            'latitude' => null,
            'longitude' => null,
        ];

        return null;
    }

    /*
     * IP location changes slowly and is approximate. A 12-hour
     * session cache keeps third-party IP lookups very low.
     */
    $_SESSION[$cacheKey] = [
        'expires_at' =>
            time() + 43200,
        'latitude' =>
            $bias['latitude'],
        'longitude' =>
            $bias['longitude'],
    ];

    return $bias;
}


function llama_address_search_bias(
    ?float $latitude = null,
    ?float $longitude = null
): ?array {
    $explicit =
        llama_address_valid_bias(
            $latitude,
            $longitude
        );

    if ($explicit !== null) {
        return $explicit;
    }

    $cloudflare =
        llama_address_cloudflare_bias();

    if ($cloudflare !== null) {
        return $cloudflare;
    }

    return llama_address_cached_ip_bias();
}


function llama_address_result_from_row(
    array $row
): ?array {
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
                            $row['housenumber'] ?? null,
                            $row['street'] ?? null,
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

    $postalCode =
        trim(
            (string) (
                $row['postcode']
                ?? ''
            )
        );

    $country =
        trim(
            (string) (
                $row['country']
                ?? ''
            )
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
                            $postalCode,
                            $country,
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
        return null;
    }

    return [
        'label' => $label,
        'address_line_1' => $addressLine1,
        'city' => $city,
        'state' => $state,
        'postal_code' => $postalCode,
        'country' => $country,
        'latitude' =>
            isset($row['lat'])
            && is_numeric($row['lat'])
                ? (float) $row['lat']
                : null,
        'longitude' =>
            isset($row['lon'])
            && is_numeric($row['lon'])
                ? (float) $row['lon']
                : null,
        'distance_meters' =>
            isset($row['distance'])
            && is_numeric($row['distance'])
                ? (float) $row['distance']
                : null,
    ];
}


function llama_address_autocomplete_query(
    string $query,
    int $limit = 6,
    ?float $latitude = null,
    ?float $longitude = null
): array {
    $query = trim($query);

    $queryLength =
        function_exists('mb_strlen')
            ? mb_strlen($query)
            : strlen($query);

    if (
        $queryLength < 3
        || $queryLength > 150
    ) {
        throw new InvalidArgumentException(
            'Enter at least 3 characters of an address.'
        );
    }

    $limit =
        max(
            1,
            min(
                10,
                $limit
            )
        );

    $bias =
        llama_address_search_bias(
            $latitude,
            $longitude
        );

    $params = [
        'text' => $query,
        'limit' => $limit,
    ];

    if ($bias !== null) {
        $params['bias'] =
            'proximity:'
            . $bias['longitude']
            . ','
            . $bias['latitude'];
    }

    $decoded =
        llama_address_geoapify_request(
            'autocomplete',
            $params
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

        $result =
            llama_address_result_from_row(
                $row
            );

        if ($result !== null) {
            $results[] = $result;
        }
    }

    return $results;
}


function llama_address_reverse_lookup(
    float $latitude,
    float $longitude
): ?array {
    if (
        $latitude < -90
        || $latitude > 90
        || $longitude < -180
        || $longitude > 180
    ) {
        throw new InvalidArgumentException(
            'Location coordinates are not valid.'
        );
    }

    $decoded =
        llama_address_geoapify_request(
            'reverse',
            [
                'lat' => $latitude,
                'lon' => $longitude,
                'limit' => 1,
            ]
        );

    $rows =
        is_array(
            $decoded['results']
            ?? null
        )
            ? $decoded['results']
            : [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $result =
            llama_address_result_from_row(
                $row
            );

        if ($result !== null) {
            return $result;
        }
    }

    return null;
}
