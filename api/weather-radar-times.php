<?php

declare(strict_types=1);

/*
 * Llama Scout
 * NOAA nowCOAST radar frame times
 *
 * The map images still come directly from NOAA nowCOAST WMS.
 * This endpoint only reads the WMS capabilities document and
 * returns the exact observation timestamps advertised for the
 * CONUS MRMS radar layer. Keeping metadata same-origin avoids
 * browser CORS problems when reading XML.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

const RADAR_CAPABILITIES_URL =
    'https://nowcoast.noaa.gov/geoserver/observations/'
    . 'weather_radar/ows?service=WMS&version=1.3.0'
    . '&request=GetCapabilities';

const RADAR_LAYER_NAME =
    'conus_base_reflectivity_mosaic';

const CACHE_FRESH_SECONDS = 90;
const CACHE_STALE_SECONDS = 1800;


function radar_json(
    array $payload,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


function radar_cache_path(): string {
    return rtrim(
        sys_get_temp_dir(),
        DIRECTORY_SEPARATOR
    )
    . DIRECTORY_SEPARATOR
    . 'llamascout-radar-times-v1.json';
}


function radar_read_cache(
    int $maxAge
): ?array {
    $path = radar_cache_path();

    if (!is_file($path)) {
        return null;
    }

    $mtime = @filemtime($path);

    if (
        !is_int($mtime) ||
        time() - $mtime > $maxAge
    ) {
        return null;
    }

    $raw = @file_get_contents($path);

    if (
        !is_string($raw) ||
        $raw === ''
    ) {
        return null;
    }

    $decoded = json_decode(
        $raw,
        true
    );

    return is_array($decoded)
        ? $decoded
        : null;
}


function radar_write_cache(
    array $payload
): void {
    $encoded = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
    );

    if (!is_string($encoded)) {
        return;
    }

    @file_put_contents(
        radar_cache_path(),
        $encoded,
        LOCK_EX
    );
}


function radar_fetch_xml(
    string $url
): string {
    if (
        function_exists('curl_init')
    ) {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException(
                'Unable to initialize radar request.'
            );
        }

        curl_setopt_array(
            $handle,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT =>
                    'LlamaScout/1.0 (+https://llamascout.com)',
                CURLOPT_HTTPHEADER => [
                    'Accept: application/xml,text/xml;q=0.9,*/*;q=0.1'
                ]
            ]
        );

        $body = curl_exec($handle);
        $status = (int) curl_getinfo(
            $handle,
            CURLINFO_RESPONSE_CODE
        );

        $error = curl_error($handle);

        curl_close($handle);

        if (
            !is_string($body) ||
            $body === '' ||
            $status < 200 ||
            $status >= 300
        ) {
            throw new RuntimeException(
                'NOAA radar metadata request failed.'
                . (
                    $error !== ''
                        ? ' ' . $error
                        : ''
                )
            );
        }

        if (
            strlen($body) > 1024 * 1024
        ) {
            throw new RuntimeException(
                'NOAA radar metadata response was too large.'
            );
        }

        return $body;
    }

    $context = stream_context_create(
        [
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'ignore_errors' => false,
                'header' =>
                    "Accept: application/xml,text/xml\r\n"
                    . "User-Agent: LlamaScout/1.0 (+https://llamascout.com)\r\n"
            ]
        ]
    );

    $body = @file_get_contents(
        $url,
        false,
        $context
    );

    if (
        !is_string($body) ||
        $body === ''
    ) {
        throw new RuntimeException(
            'NOAA radar metadata request failed.'
        );
    }

    if (
        strlen($body) > 1024 * 1024
    ) {
        throw new RuntimeException(
            'NOAA radar metadata response was too large.'
        );
    }

    return $body;
}


function radar_parse_times(
    string $xml
): array {
    $previous =
        libxml_use_internal_errors(true);

    try {
        $document =
            new DOMDocument();

        $loaded =
            $document->loadXML(
                $xml,
                LIBXML_NONET
                | LIBXML_NOBLANKS
            );

        if (!$loaded) {
            throw new RuntimeException(
                'NOAA radar metadata was invalid XML.'
            );
        }

        $xpath =
            new DOMXPath($document);

        $layerQuery =
            '//*[local-name()="Layer"]'
            . '/*[local-name()="Name"'
            . ' and normalize-space(.)="'
            . RADAR_LAYER_NAME
            . '"]/..';

        $layers =
            $xpath->query(
                $layerQuery
            );

        if (
            !$layers ||
            $layers->length !== 1
        ) {
            throw new RuntimeException(
                'NOAA radar layer metadata was not found.'
            );
        }

        $layer =
            $layers->item(0);

        if (!$layer) {
            throw new RuntimeException(
                'NOAA radar layer metadata was not found.'
            );
        }

        $dimensions =
            $xpath->query(
                './/*[local-name()="Dimension"'
                . ' and translate(@name,'
                . '"ABCDEFGHIJKLMNOPQRSTUVWXYZ",'
                . '"abcdefghijklmnopqrstuvwxyz")="time"]',
                $layer
            );

        if (
            !$dimensions ||
            $dimensions->length < 1
        ) {
            throw new RuntimeException(
                'NOAA radar time metadata was not found.'
            );
        }

        $raw =
            trim(
                (string)
                $dimensions
                    ->item(0)
                    ?->textContent
            );

        if ($raw === '') {
            throw new RuntimeException(
                'NOAA radar time metadata was empty.'
            );
        }

        $now =
            time();

        $times = [];

        foreach (
            explode(',', $raw)
            as $value
        ) {
            $value =
                trim($value);

            if (
                !preg_match(
                    '/^\d{4}-\d{2}-\d{2}T'
                    . '\d{2}:\d{2}:\d{2}'
                    . '(?:\.\d{1,3})?Z$/',
                    $value
                )
            ) {
                continue;
            }

            $timestamp =
                strtotime($value);

            if (
                $timestamp === false ||
                $timestamp > $now + 300 ||
                $timestamp < $now - 86400
            ) {
                continue;
            }

            $times[
                gmdate(
                    'Y-m-d\TH:i:s\Z',
                    $timestamp
                )
            ] = $timestamp;
        }

        if (
            count($times) < 4
        ) {
            throw new RuntimeException(
                'NOAA returned too few radar frames.'
            );
        }

        asort(
            $times,
            SORT_NUMERIC
        );

        $ordered =
            array_keys($times);

        $latest =
            end($ordered);

        if (!is_string($latest)) {
            throw new RuntimeException(
                'NOAA radar latest frame was unavailable.'
            );
        }

        $latestTimestamp =
            (int)
            $times[$latest];

        $cutoff =
            $latestTimestamp - 3600;

        $oneHour =
            array_values(
                array_filter(
                    $ordered,
                    static function (
                        string $value
                    ) use (
                        $times,
                        $cutoff
                    ): bool {
                        return (
                            (int)
                            $times[$value]
                        ) >= $cutoff;
                    }
                )
            );

        $oneHour =
            array_slice(
                $oneHour,
                -13
            );

        if (
            count($oneHour) < 4
        ) {
            throw new RuntimeException(
                'NOAA returned too few one-hour radar frames.'
            );
        }

        return [
            'ok' => true,
            'times' => $oneHour,
            'latest' => $latest,
            'fetchedAt' =>
                gmdate(
                    'Y-m-d\TH:i:s\Z'
                ),
            'stale' => false
        ];

    } finally {
        libxml_clear_errors();

        libxml_use_internal_errors(
            $previous
        );
    }
}


$fresh =
    radar_read_cache(
        CACHE_FRESH_SECONDS
    );

if ($fresh !== null) {
    radar_json($fresh);
}


try {
    $xml =
        radar_fetch_xml(
            RADAR_CAPABILITIES_URL
        );

    $payload =
        radar_parse_times(
            $xml
        );

    radar_write_cache(
        $payload
    );

    radar_json($payload);

} catch (Throwable $error) {
    $stale =
        radar_read_cache(
            CACHE_STALE_SECONDS
        );

    if ($stale !== null) {
        $stale['stale'] = true;

        radar_json($stale);
    }

    error_log(
        'Llama Scout radar time metadata error: '
        . $error->getMessage()
    );

    radar_json(
        [
            'ok' => false,
            'times' => [],
            'latest' => null,
            'error' =>
                'Radar loop metadata is temporarily unavailable.'
        ],
        503
    );
}
