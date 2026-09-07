<?php

declare(strict_types=1);

/*
 * Shared Llama Scout address autocomplete service.
 *
 * This file contains the Geoapify integration only. It does not decide
 * whether a particular caller is allowed to use it. Public, account,
 * admin, checkout, or other endpoints can wrap this shared helper with
 * the access policy appropriate for that surface.
 */

function llama_address_autocomplete_query(
    string $query,
    int $limit = 6
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

    $url =
        'https://api.geoapify.com/v1/geocode/autocomplete?'
        . http_build_query(
            [
                'text' => $query,
                'format' => 'json',
                'limit' => $limit,
                'lang' => 'en',
                'apiKey' => $apiKey,
            ],
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
        error_log(
            'Geoapify address lookup returned invalid JSON.'
        );

        throw new RuntimeException(
            'Address lookup is temporarily unavailable.'
        );
    }

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
            continue;
        }

        $results[] = [
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
        ];
    }

    return $results;
}
