<?php

declare(strict_types=1);

/*
 * Printify private API client for Llama Scout.
 *
 * Private configuration:
 * /private/printify.php
 *
 * Minimum configuration:
 *   token string
 *
 * Optional keys:
 *   shop_id         string|int|null  Auto-detected when possible
 *   shipping_method int              1 = Standard (default)
 *   auto_submit     bool             Keep false while testing
 *   webhook_secret  string|null      Derived safely from token when omitted
 *
 * No secrets are logged or returned to Admin.
 */

function llama_printify_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $path = dirname(__DIR__, 2) . '/private/printify.php';

    if (!is_file($path)) {
        $config = [];
        return $config;
    }

    $loaded = require $path;

    $config = is_array($loaded)
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
            ?? $config['api_token']
            ?? $config['api_key']
            ?? ''
        )
    );
}

function llama_printify_configured_shop_id(): string
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

function llama_printify_shipping_method(): int
{
    $config = llama_printify_config();

    $method = (int) (
        $config['shipping_method']
        ?? 1
    );

    return in_array(
        $method,
        [1, 2, 3, 4],
        true
    )
        ? $method
        : 1;
}

function llama_printify_user_agent(): string
{
    $config = llama_printify_config();

    $configured = trim(
        (string) (
            $config['user_agent']
            ?? ''
        )
    );

    return $configured !== ''
        ? $configured
        : 'LlamaScout/1.0 (+https://llamascout.com)';
}

function llama_printify_configured(): bool
{
    return llama_printify_token() !== '';
}

function llama_printify_error_message(
    array $decoded,
    string $fallback = 'Printify rejected the request.'
): string {
    $message = trim(
        (string) (
            $decoded['message']
            ?? $decoded['error']
            ?? ''
        )
    );

    $errors = $decoded['errors'] ?? null;

    if ($message === '' && is_array($errors)) {
        $message = trim(
            (string) (
                $errors['reason']
                ?? $errors['message']
                ?? ''
            )
        );

        if ($message === '') {
            foreach ($errors as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $message = trim($value);
                    break;
                }

                if (is_array($value)) {
                    $candidate = trim(
                        (string) (
                            $value['message']
                            ?? $value['reason']
                            ?? ''
                        )
                    );

                    if ($candidate !== '') {
                        $message = $candidate;
                        break;
                    }
                }
            }
        }
    }

    return $message !== ''
        ? $message
        : $fallback;
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
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json;charset=utf-8',
        'User-Agent: ' . llama_printify_user_agent(),
    ];

    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException(
            'Unable to initialize the Printify API request.'
        );
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
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
            ($message !== ''
                ? $message
                : 'Unknown network error.')
        );
    }

    $status = (int) curl_getinfo(
        $curl,
        CURLINFO_RESPONSE_CODE
    );

    curl_close($curl);

    $raw = trim((string) $raw);

    if ($raw === '') {
        if ($status >= 200 && $status < 300) {
            return [];
        }

        throw new RuntimeException(
            'Printify rejected the request with HTTP ' .
            $status . '.'
        );
    }

    $decoded = json_decode(
        $raw,
        true
    );

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'Printify returned an invalid response.'
        );
    }

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException(
            llama_printify_error_message(
                $decoded,
                'Printify rejected the request with HTTP ' .
                    $status . '.'
            )
        );
    }

    return $decoded;
}

function llama_printify_shops(): array
{
    $response = llama_printify_request(
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

function llama_printify_shop(): array
{
    static $resolved = null;

    if (is_array($resolved)) {
        return $resolved;
    }

    $shops = llama_printify_shops();
    $configuredId = llama_printify_configured_shop_id();

    if ($configuredId !== '') {
        foreach ($shops as $shop) {
            if (
                (string) ($shop['id'] ?? '')
                === $configuredId
            ) {
                $resolved = $shop;
                return $resolved;
            }
        }

        throw new RuntimeException(
            'The configured Printify shop_id was not found for this token.'
        );
    }

    if (count($shops) === 1) {
        $resolved = $shops[0];
        return $resolved;
    }

    $apiShops = array_values(
        array_filter(
            $shops,
            static fn(array $shop): bool =>
                strtolower(
                    trim(
                        (string) (
                            $shop['sales_channel']
                            ?? ''
                        )
                    )
                ) === 'api'
        )
    );

    if (count($apiShops) === 1) {
        $resolved = $apiShops[0];
        return $resolved;
    }

    if (!$shops) {
        throw new RuntimeException(
            'No Printify shops were returned for this token.'
        );
    }

    throw new RuntimeException(
        'Multiple Printify shops are available. Add the intended shop_id to /private/printify.php.'
    );
}

function llama_printify_shop_id(): string
{
    $shop = llama_printify_shop();

    $id = trim(
        (string) (
            $shop['id']
            ?? ''
        )
    );

    if ($id === '') {
        throw new RuntimeException(
            'Printify did not return a valid shop ID.'
        );
    }

    return $id;
}

function llama_printify_products(): array
{
    $all = [];
    $page = 1;
    $limit = 50;
    $shopId = llama_printify_shop_id();

    do {
        $response = llama_printify_request(
            'GET',
            'shops/' . rawurlencode($shopId) . '/products.json',
            null,
            [
                'page' => $page,
                'limit' => $limit,
            ]
        );

        $rows = is_array(
            $response['data'] ?? null
        )
            ? $response['data']
            : [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $all[] = $row;
            }
        }

        $lastPage = max(
            1,
            (int) (
                $response['last_page']
                ?? $page
            )
        );

        $page++;
    } while ($rows && $page <= $lastPage);

    return $all;
}

function llama_printify_product(
    string $productId
): array {
    $productId = trim($productId);

    if ($productId === '') {
        throw new InvalidArgumentException(
            'A valid Printify product ID is required.'
        );
    }

    return llama_printify_request(
        'GET',
        'shops/' .
            rawurlencode(llama_printify_shop_id()) .
            '/products/' .
            rawurlencode($productId) .
            '.json'
    );
}

function llama_printify_product_thumbnail(
    array $product
): string {
    $images = is_array(
        $product['images'] ?? null
    )
        ? $product['images']
        : [];

    foreach ($images as $image) {
        if (
            is_array($image)
            && !empty($image['is_default'])
            && trim((string) ($image['src'] ?? '')) !== ''
        ) {
            return trim((string) $image['src']);
        }
    }

    foreach ($images as $image) {
        if (
            is_array($image)
            && trim((string) ($image['src'] ?? '')) !== ''
        ) {
            return trim((string) $image['src']);
        }
    }

    return '';
}

function llama_printify_catalog(): array
{
    $products = llama_printify_products();

    $catalog = [
        'products' => [],
        'variants' => [],
        'variants_by_sku' => [],
    ];

    foreach ($products as $product) {
        $productId = trim(
            (string) (
                $product['id']
                ?? ''
            )
        );

        if ($productId === '') {
            continue;
        }

        $productName = trim(
            (string) (
                $product['title']
                ?? $product['name']
                ?? ''
            )
        );

        $variants = is_array(
            $product['variants'] ?? null
        )
            ? $product['variants']
            : [];

        $catalog['products'][$productId] = [
            'id' => $productId,
            'name' => $productName,
            'thumbnail_url' =>
                llama_printify_product_thumbnail($product),
            'variant_count' => count($variants),
            'print_provider_id' =>
                (int) (
                    $product['print_provider_id']
                    ?? 0
                ),
            'blueprint_id' =>
                (int) (
                    $product['blueprint_id']
                    ?? 0
                ),
            'visible' =>
                !empty($product['visible']),
        ];

        foreach ($variants as $variant) {
            if (!is_array($variant)) {
                continue;
            }

            if (
                array_key_exists('is_enabled', $variant)
                && empty($variant['is_enabled'])
            ) {
                continue;
            }

            $variantId = (int) (
                $variant['id']
                ?? 0
            );

            if ($variantId < 1) {
                continue;
            }

            $sku = trim(
                (string) (
                    $variant['sku']
                    ?? ''
                )
            );

            $row = [
                'product_id' => $productId,
                'product_name' => $productName,
                'variant_id' => $variantId,
                'name' => trim(
                    (string) (
                        $variant['title']
                        ?? $variant['name']
                        ?? ''
                    )
                ),
                'sku' => $sku,
                'enabled' =>
                    !empty($variant['is_enabled']),
                'available' =>
                    !array_key_exists('is_available', $variant)
                    || !empty($variant['is_available']),
                'price_cents' =>
                    max(0, (int) ($variant['price'] ?? 0)),
                'cost_cents' =>
                    max(0, (int) ($variant['cost'] ?? 0)),
            ];

            $catalog['variants'][$variantId] = $row;

            if ($sku !== '') {
                $key = strtolower($sku);

                if (!isset(
                    $catalog['variants_by_sku'][$key]
                )) {
                    $catalog['variants_by_sku'][$key] = [];
                }

                $catalog['variants_by_sku'][$key][] = $row;
            }
        }
    }

    return $catalog;
}
