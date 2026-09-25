<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT GEOGRAPHY NORMALIZATION

   Keeps geographic labels consistent before they are stored
   or compared.

   County-equivalent names should use the official full name,
   such as:

   La Plata County
   St. Tammany Parish
   Fairbanks North Star Borough
   Yukon-Koyukuk Census Area
   San Juan Municipio

   Coordinates are preferred because they allow the U.S.
   Census Geocoder to identify the exact county-equivalent
   without guessing from a text label.
   ========================================================= */


/**
 * Fetch JSON from a public geography service.
 */
function llama_geography_json(
    string $url,
    array $headers = [],
    int $timeout = 10
): ?array {
    $curl =
        curl_init($url);

    if ($curl === false) {
        return null;
    }

    curl_setopt_array(
        $curl,
        [
            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_FOLLOWLOCATION =>
                true,

            CURLOPT_CONNECTTIMEOUT =>
                5,

            CURLOPT_TIMEOUT =>
                max(
                    3,
                    min(
                        15,
                        $timeout
                    )
                ),

            CURLOPT_HTTPHEADER =>
                array_merge(
                    [
                        'Accept: application/json',
                        'User-Agent: LlamaScout/1.0 (https://llamascout.com)',
                    ],
                    $headers
                ),
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

    if (
        !is_string($body)
        || $body === ''
        || $status < 200
        || $status >= 300
    ) {
        return null;
    }

    $decoded =
        json_decode(
            $body,
            true
        );

    return
        is_array($decoded)
            ? $decoded
            : null;
}


/**
 * Normalize obvious county-equivalent formatting without
 * inventing a jurisdiction type.
 *
 * This intentionally does not append "County" to a bare name.
 * Bare labels can be ambiguous in places such as Alaska,
 * Louisiana, Puerto Rico, and independent-city jurisdictions.
 */
function llama_county_text_cleanup(
    ?string $county
): ?string {
    $county =
        trim(
            (string) $county
        );

    if ($county === '') {
        return null;
    }

    $county =
        preg_replace(
            '/\s+/u',
            ' ',
            $county
        )
        ?? $county;

    /*
     * Expand common terminal abbreviations only when their
     * meaning is unambiguous.
     */
    $county =
        preg_replace(
            '/\s+Co\.?$/iu',
            ' County',
            $county
        )
        ?? $county;

    $county =
        preg_replace(
            '/\s+Par\.?$/iu',
            ' Parish',
            $county
        )
        ?? $county;

    $county =
        preg_replace(
            '/\s+Boro\.?$/iu',
            ' Borough',
            $county
        )
        ?? $county;

    return
        trim($county) !== ''
            ? trim($county)
            : null;
}


/**
 * Build a comparison key that groups obvious variants.
 *
 * Examples:
 *
 * La Plata
 * La Plata Co.
 * La Plata County
 *
 * all compare as:
 *
 * la plata
 */
function llama_county_comparison_key(
    ?string $county
): string {
    $county =
        llama_county_text_cleanup(
            $county
        );

    if ($county === null) {
        return '';
    }

    $value =
        mb_strtolower(
            trim($county),
            'UTF-8'
        );

    $value =
        preg_replace(
            '/\s+(county|parish|borough|census area|municipio|municipality)$/iu',
            '',
            $value
        )
        ?? $value;

    $value =
        preg_replace(
            '/[[:punct:]]+/u',
            ' ',
            $value
        )
        ?? $value;

    $value =
        preg_replace(
            '/\s+/u',
            ' ',
            $value
        )
        ?? $value;

    return
        trim($value);
}


/**
 * Ask the U.S. Census Geocoder for the official county-equivalent
 * at a coordinate.
 *
 * Returns:
 *
 * [
 *     'name' => 'La Plata County',
 *     'geoid' => '08067'
 * ]
 *
 * or null when the service cannot resolve the coordinate.
 */
function llama_official_county_from_coordinates(
    float $latitude,
    float $longitude
): ?array {
    if (
        $latitude < -90
        || $latitude > 90
        || $longitude < -180
        || $longitude > 180
    ) {
        return null;
    }

    $url =
        'https://geocoding.geo.census.gov/geocoder/geographies/coordinates?'
        . http_build_query(
            [
                'x' =>
                    number_format(
                        $longitude,
                        7,
                        '.',
                        ''
                    ),

                'y' =>
                    number_format(
                        $latitude,
                        7,
                        '.',
                        ''
                    ),

                'benchmark' =>
                    'Public_AR_Current',

                'vintage' =>
                    'Current_Current',

                'format' =>
                    'json',
            ]
        );

    $result =
        llama_geography_json(
            $url,
            [],
            10
        );

    $geographies =
        is_array(
            $result['result']['geographies']
            ?? null
        )
            ? $result['result']['geographies']
            : [];

    $counties =
        is_array(
            $geographies['Counties']
            ?? null
        )
            ? $geographies['Counties']
            : [];

    $county =
        is_array(
            $counties[0]
            ?? null
        )
            ? $counties[0]
            : null;

    if (!$county) {
        return null;
    }

    $name =
        llama_county_text_cleanup(
            (string) (
                $county['NAME']
                ?? ''
            )
        );

    if ($name === null) {
        return null;
    }

    $geoid =
        trim(
            (string) (
                $county['GEOID']
                ?? ''
            )
        );

    return [
        'name' =>
            $name,

        'geoid' =>
            $geoid !== ''
                ? $geoid
                : null,
    ];
}


/**
 * Final storage guard for a Place county value.
 *
 * When valid coordinates are available, the official Census
 * county-equivalent always wins.
 *
 * If the external lookup is unavailable, keep a cleaned version
 * of the submitted value instead of guessing the jurisdiction.
 */
function llama_normalize_place_county(
    ?string $county,
    mixed $latitude = null,
    mixed $longitude = null
): ?string {
    if (
        is_numeric($latitude)
        && is_numeric($longitude)
    ) {
        $official =
            llama_official_county_from_coordinates(
                (float) $latitude,
                (float) $longitude
            );

        if (
            is_array($official)
            && trim(
                (string) (
                    $official['name']
                    ?? ''
                )
            ) !== ''
        ) {
            return
                trim(
                    (string) $official['name']
                );
        }
    }

    return
        llama_county_text_cleanup(
            $county
        );
}
