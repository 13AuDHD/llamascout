<?php

declare(strict_types=1);

/*
 * Llama Scout shipping-label integration.
 *
 * Secrets and the fulfillment-origin address live in:
 * /private/shipping.php
 *
 * This file contains no schema creation or alteration.
 */

function llama_shipping_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $path = dirname(__DIR__) . '/private/shipping.php';

    if (!is_file($path)) {
        return [];
    }

    $loaded = require $path;

    $config = is_array($loaded)
        ? $loaded
        : [];

    return $config;
}

function llama_shipping_easypost_configured(): bool
{
    $config = llama_shipping_config();
    $key = trim(
        (string) (
            $config['easypost']['api_key']
            ?? ''
        )
    );

    $from = is_array(
        $config['from_address']
        ?? null
    )
        ? $config['from_address']
        : [];

    return $key !== ''
        && trim((string) ($from['street1'] ?? '')) !== ''
        && trim((string) ($from['city'] ?? '')) !== ''
        && trim((string) ($from['state'] ?? '')) !== ''
        && trim((string) ($from['zip'] ?? '')) !== ''
        && trim((string) ($from['country'] ?? '')) !== '';
}

function llama_shipping_easypost_api_key(): string
{
    $config = llama_shipping_config();

    $key = trim(
        (string) (
            $config['easypost']['api_key']
            ?? ''
        )
    );

    if ($key === '') {
        throw new RuntimeException(
            'EasyPost is not configured. Add the API key to /private/shipping.php.'
        );
    }

    return $key;
}

function llama_shipping_from_address(): array
{
    $config = llama_shipping_config();

    $address = is_array(
        $config['from_address']
        ?? null
    )
        ? $config['from_address']
        : [];

    foreach (
        ['street1', 'city', 'state', 'zip', 'country']
        as $required
    ) {
        if (
            trim(
                (string) (
                    $address[$required]
                    ?? ''
                )
            ) === ''
        ) {
            throw new RuntimeException(
                'Llama Scout Fulfillment origin address is incomplete in /private/shipping.php.'
            );
        }
    }

    if (
        trim(
            (string) (
                $address['name']
                ?? ''
            )
        ) === ''
    ) {
        $address['name'] =
            'Llama Scout Fulfillment';
    }

    return $address;
}

function llama_shipping_easypost_request(
    string $method,
    string $path,
    ?array $payload = null
): array {
    $key = llama_shipping_easypost_api_key();

    $url =
        'https://api.easypost.com/v2/' .
        ltrim($path, '/');

    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException(
            'Unable to initialize the shipping API request.'
        );
    }

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $key . ':',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]
    );

    if ($payload !== null) {
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            )
        );
    }

    $raw = curl_exec($ch);

    if ($raw === false) {
        $message = curl_error($ch);
        curl_close($ch);

        throw new RuntimeException(
            'EasyPost request failed: ' .
            ($message !== ''
                ? $message
                : 'Unknown network error.')
        );
    }

    $status =
        (int) curl_getinfo(
            $ch,
            CURLINFO_RESPONSE_CODE
        );

    curl_close($ch);

    $decoded =
        json_decode(
            (string) $raw,
            true
        );

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'EasyPost returned an invalid response.'
        );
    }

    if ($status < 200 || $status >= 300) {
        $message =
            trim(
                (string) (
                    $decoded['error']['message']
                    ?? $decoded['error']['code']
                    ?? $decoded['message']
                    ?? ''
                )
            );

        throw new RuntimeException(
            'EasyPost rejected the request' .
            ($message !== ''
                ? ': ' . $message
                : '.')
        );
    }

    return $decoded;
}

function llama_shipping_address_payload(
    array $address,
    ?string $fallbackName = null
): array {
    $result = [];

    $mapping = [
        'name',
        'company',
        'street1',
        'street2',
        'city',
        'state',
        'zip',
        'country',
        'phone',
        'email',
    ];

    foreach ($mapping as $field) {
        $value = trim(
            (string) (
                $address[$field]
                ?? ''
            )
        );

        if ($value !== '') {
            $result[$field] = $value;
        }
    }

    if (
        empty($result['name'])
        && $fallbackName !== null
        && trim($fallbackName) !== ''
    ) {
        $result['name'] =
            trim($fallbackName);
    }

    return $result;
}

function llama_shipping_tracking_url(
    string $carrier,
    string $trackingCode
): string {
    $trackingCode =
        rawurlencode(
            trim($trackingCode)
        );

    $carrier =
        strtolower(
            trim($carrier)
        );

    return match ($carrier) {
        'usps' =>
            'https://tools.usps.com/go/TrackConfirmAction?tLabels=' .
            $trackingCode,

        'ups' =>
            'https://www.ups.com/track?tracknum=' .
            $trackingCode,

        'fedex' =>
            'https://www.fedex.com/fedextrack/?trknbr=' .
            $trackingCode,

        'dhl',
        'dhlexpress' =>
            'https://www.dhl.com/global-en/home/tracking.html?tracking-id=' .
            $trackingCode,

        'ontrac' =>
            'https://www.ontrac.com/tracking/?number=' .
            $trackingCode,

        default => '',
    };
}
