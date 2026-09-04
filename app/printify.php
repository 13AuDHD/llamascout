<?php

declare(strict_types=1);

/*
 * Printify private API client for Llama Scout.
 *
 * Private configuration:
 * /private/printify.php
 *
 * Supported keys:
 *   token        string
 *   api_key      string
 *   access_token string
 *   shop_id      string|int|null
 *   auto_submit  bool
 */

function llama_printify_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $path =
        dirname(__DIR__, 2) .
        '/private/printify.php';

    if (!is_file($path)) {
        $config = [];
        return $config;
    }

    $loaded = require $path;

    $config =
        is_array($loaded)
            ? $loaded
            : [];

    return $config;
}

function llama_printify_token(): string
{
    $config = llama_printify_config();

    return trim(
        (string) (
            $config['token']
            ?? $config['api_key']
            ?? $config['access_token']
            ?? ''
        )
    );
}

function llama_printify_shop_id(): string
{
    $config = llama_printify_config();

    return trim(
        (string) (
            $config['shop_id']
            ?? ''
        )
    );
}

function llama_printify_auto_submit(): bool
{
    $config = llama_printify_config();

    return !empty(
        $config['auto_submit']
    );
}

function llama_printify_configured(): bool
{
    return llama_printify_token() !== '';
}

function llama_printify_request(
    string $method,
    string $path,
    ?array $payload = null,
    array $query = []
): array {
    $token = llama_printify_token();

    if ($token === '') {
        throw new RuntimeException(
            'Printify is not configured. Add the private token to /private/printify.php.'
        );
    }

    $url =
        'https://api.printify.com/v1/' .
        ltrim($path, '/');

    if ($query) {
        $url .= '?' .
            http_build_query($query);
    }

    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json;charset=utf-8',
    ];

    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException(
            'Unable to initialize the Printify API request.'
        );
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST =>
            strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ];

    if ($payload !== null) {
        $options[CURLOPT_POSTFIELDS] =
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );
    }

    curl_setopt_array(
        $curl,
        $options
    );

    $raw = curl_exec($curl);

    if ($raw === false) {
        $message = curl_error($curl);
        curl_close($curl);

        throw new RuntimeException(
            'Printify request failed: ' .
            (
                $message !== ''
                    ? $message
                    : 'Unknown network error.'
            )
        );
    }

    $status = (int) curl_getinfo(
        $curl,
        CURLINFO_RESPONSE_CODE
    );

    curl_close($curl);

    $decoded = json_decode(
        (string) $raw,
        true
    );

    if (
        !is_array($decoded)
        && trim((string) $raw) !== ''
    ) {
        throw new RuntimeException(
            'Printify returned an invalid response.'
        );
    }

    if ($status < 200 || $status >= 300) {
        $message = trim(
            (string) (
                $decoded['message']
                ?? $decoded['error']
                ?? ''
            )
        );

        throw new RuntimeException(
            'Printify rejected the request' .
            (
                $message !== ''
                    ? ': ' . $message
                    : '.'
            )
        );
    }

    return is_array($decoded)
        ? $decoded
        : [];
}

function llama_printify_shops(): array
{
    $response =
        llama_printify_request(
            'GET',
            'shops.json'
        );

    return array_values(
        array_filter(
            $response,
            'is_array'
        )
    );
}

function llama_printify_resolved_shop(): array
{
    $shops =
        llama_printify_shops();

    $configured =
        llama_printify_shop_id();

    if ($configured !== '') {
        foreach ($shops as $shop) {
            if (
                (string) (
                    $shop['id']
                    ?? ''
                ) === $configured
            ) {
                return $shop;
            }
        }

        throw new RuntimeException(
            'The configured Printify shop_id was not found for this token.'
        );
    }

    if (count($shops) === 1) {
        return $shops[0];
    }

    if (!$shops) {
        throw new RuntimeException(
            'No Printify shops are available for this token.'
        );
    }

    throw new RuntimeException(
        'Multiple Printify shops are available. Add shop_id to /private/printify.php.'
    );
}

function llama_printify_resolved_shop_id(): string
{
    $shop =
        llama_printify_resolved_shop();

    return trim(
        (string) (
            $shop['id']
            ?? ''
        )
    );
}

function llama_printify_products(): array
{
    $shopId =
        llama_printify_resolved_shop_id();

    if ($shopId === '') {
        return [];
    }

    $all = [];
    $page = 1;

    do {
        $response =
            llama_printify_request(
                'GET',
                'shops/' .
                    rawurlencode($shopId) .
                    '/products.json',
                null,
                [
                    'limit' => 50,
                    'page' => $page,
                ]
            );

        $rows =
            is_array(
                $response['data']
                ?? null
            )
                ? $response['data']
                : [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $all[] = $row;
            }
        }

        $lastPage =
            max(
                1,
                (int) (
                    $response['last_page']
                    ?? $page
                )
            );

        $page++;
    } while (
        $rows
        && $page <= $lastPage
    );

    return $all;
}
