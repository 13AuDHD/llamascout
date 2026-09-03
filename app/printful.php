<?php

declare(strict_types=1);

/*
 * Printful private API client for Llama Scout.
 *
 * Private configuration:
 * /private/printful.php
 *
 * Expected keys:
 *   token        string
 *   store_id     string|int|null  Optional for store-level tokens
 *   auto_confirm bool             Keep false while testing
 *
 * No secrets are logged or returned to Admin.
 */

function llama_printful_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $path = dirname(__DIR__, 2) . '/private/printful.php';

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

function llama_printful_token(): string
{
    $config = llama_printful_config();

    return trim(
        (string) (
            $config['token']
            ?? $config['api_key']
            ?? ''
        )
    );
}

function llama_printful_store_id(): string
{
    $config = llama_printful_config();

    return trim(
        (string) (
            $config['store_id']
            ?? ''
        )
    );
}

function llama_printful_auto_confirm(): bool
{
    $config = llama_printful_config();

    return !empty(
        $config['auto_confirm']
    );
}

function llama_printful_configured(): bool
{
    return llama_printful_token() !== '';
}

function llama_printful_request(
    string $method,
    string $path,
    ?array $payload = null,
    array $query = []
): array {
    $token = llama_printful_token();

    if ($token === '') {
        throw new RuntimeException(
            'Printful is not configured. Add the private token to /private/printful.php.'
        );
    }

    $url =
        'https://api.printful.com/' .
        ltrim($path, '/');

    if ($query) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    /*
     * Store-level private tokens are already tied to one store.
     * If an account-level token is ever used later, store_id can
     * be populated and the same client will send the required
     * X-PF-Store-Id header automatically.
     */
    $storeId = llama_printful_store_id();

    if ($storeId !== '') {
        $headers[] =
            'X-PF-Store-Id: ' .
            $storeId;
    }

    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException(
            'Unable to initialize the Printful API request.'
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
            'Printful request failed: ' .
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

    $decoded = json_decode(
        (string) $raw,
        true
    );

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'Printful returned an invalid response.'
        );
    }

    if ($status < 200 || $status >= 300) {
        $message = trim(
            (string) (
                $decoded['error']['message']
                ?? $decoded['result']
                ?? $decoded['message']
                ?? ''
            )
        );

        throw new RuntimeException(
            'Printful rejected the request' .
            ($message !== ''
                ? ': ' . $message
                : '.')
        );
    }

    return $decoded;
}

function llama_printful_stores(): array
{
    $response = llama_printful_request(
        'GET',
        'stores'
    );

    $result = $response['result'] ?? [];

    if (!is_array($result)) {
        return [];
    }

    /*
     * Printful may return either a single store object or a list,
     * depending on token/access context.
     */
    if (isset($result['id'])) {
        return [$result];
    }

    return array_values(
        array_filter(
            $result,
            'is_array'
        )
    );
}

function llama_printful_sync_products(): array
{
    $all = [];
    $offset = 0;
    $limit = 100;

    do {
        $response = llama_printful_request(
            'GET',
            'store/products',
            null,
            [
                'offset' => $offset,
                'limit' => $limit,
                'status' => 'all',
            ]
        );

        $rows = $response['result'] ?? [];

        if (!is_array($rows)) {
            $rows = [];
        }

        foreach ($rows as $row) {
            if (is_array($row)) {
                $all[] = $row;
            }
        }

        $paging = is_array(
            $response['paging'] ?? null
        )
            ? $response['paging']
            : [];

        $total = max(
            0,
            (int) (
                $paging['total']
                ?? count($all)
            )
        );

        $offset += $limit;

    } while (
        $rows
        && count($all) < $total
    );

    return $all;
}

function llama_printful_sync_product(
    int $syncProductId
): array {
    if ($syncProductId < 1) {
        throw new InvalidArgumentException(
            'A valid Printful Sync Product ID is required.'
        );
    }

    $response = llama_printful_request(
        'GET',
        'store/products/' .
            $syncProductId
    );

    $result = $response['result'] ?? [];

    return is_array($result)
        ? $result
        : [];
}

function llama_printful_catalog(): array
{
    $products =
        llama_printful_sync_products();

    $catalog = [
        'products' => [],
        'variants' => [],
        'variants_by_sku' => [],
    ];

    foreach ($products as $product) {
        $productId =
            (int) (
                $product['id']
                ?? 0
            );

        if ($productId < 1) {
            continue;
        }

        $detail =
            llama_printful_sync_product(
                $productId
            );

        $syncProduct =
            is_array(
                $detail['sync_product']
                ?? null
            )
                ? $detail['sync_product']
                : $product;

        $syncVariants =
            is_array(
                $detail['sync_variants']
                ?? null
            )
                ? $detail['sync_variants']
                : [];

        $catalog['products'][$productId] = [
            'id' => $productId,
            'name' => trim(
                (string) (
                    $syncProduct['name']
                    ?? $product['name']
                    ?? ''
                )
            ),
            'thumbnail_url' => trim(
                (string) (
                    $syncProduct['thumbnail_url']
                    ?? $product['thumbnail_url']
                    ?? ''
                )
            ),
            'variant_count' =>
                count($syncVariants),
        ];

        foreach ($syncVariants as $variant) {
            if (!is_array($variant)) {
                continue;
            }

            $syncVariantId =
                (int) (
                    $variant['id']
                    ?? 0
                );

            if ($syncVariantId < 1) {
                continue;
            }

            $sku =
                trim(
                    (string) (
                        $variant['sku']
                        ?? ''
                    )
                );

            $row = [
                'sync_product_id' => $productId,
                'sync_product_name' =>
                    $catalog['products'][$productId]['name'],
                'sync_variant_id' => $syncVariantId,
                'catalog_variant_id' =>
                    (int) (
                        $variant['variant_id']
                        ?? 0
                    ),
                'name' => trim(
                    (string) (
                        $variant['name']
                        ?? ''
                    )
                ),
                'sku' => $sku,
                'synced' =>
                    !empty(
                        $variant['synced']
                    ),
                'availability_status' => trim(
                    (string) (
                        $variant['availability_status']
                        ?? ''
                    )
                ),
            ];

            $catalog['variants'][$syncVariantId] =
                $row;

            if ($sku !== '') {
                $key = strtolower($sku);

                if (!isset(
                    $catalog['variants_by_sku'][$key]
                )) {
                    $catalog['variants_by_sku'][$key] = [];
                }

                $catalog['variants_by_sku'][$key][] =
                    $row;
            }
        }
    }

    return $catalog;
}

function llama_printful_local_variant_diagnostics(
    PDO $db,
    array $catalog
): array {
    $stmt = $db->query(
        "SELECT
            v.id,
            v.product_id,
            p.name AS product_name,
            v.name AS variant_name,
            v.sku,
            v.fulfillment_product_id,
            v.fulfillment_variant_id,
            v.is_active
         FROM shop_product_variants v
         INNER JOIN shop_products p
            ON p.id = v.product_id
         WHERE LOWER(
            COALESCE(
                v.fulfillment_provider,
                ''
            )
         ) = 'printful'
         ORDER BY
            p.name ASC,
            v.sort_order ASC,
            v.id ASC"
    );

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    $result = [];

    foreach ($rows as $row) {
        $sku =
            trim(
                (string) (
                    $row['sku']
                    ?? ''
                )
            );

        $configuredProductId =
            trim(
                (string) (
                    $row['fulfillment_product_id']
                    ?? ''
                )
            );

        $configuredVariantId =
            trim(
                (string) (
                    $row['fulfillment_variant_id']
                    ?? ''
                )
            );

        $matches = [];

        if ($sku !== '') {
            $matches =
                $catalog['variants_by_sku'][
                    strtolower($sku)
                ]
                ?? [];
        }

        $status = 'unmapped';
        $message =
            'No Printful mapping is saved.';

        if (
            $configuredVariantId !== ''
            && isset(
                $catalog['variants'][
                    (int) $configuredVariantId
                ]
            )
        ) {
            $status = 'mapped';
            $message =
                'Saved Printful Sync Variant is valid.';
        } elseif (
            $configuredVariantId !== ''
        ) {
            $status = 'invalid';
            $message =
                'Saved Printful Sync Variant was not found in this store.';
        } elseif (count($matches) === 1) {
            $status = 'suggested';
            $message =
                'Exact SKU match found in Printful.';
        } elseif (count($matches) > 1) {
            $status = 'ambiguous';
            $message =
                'Multiple Printful variants use this SKU.';
        } elseif ($sku === '') {
            $status = 'missing_sku';
            $message =
                'Local variant needs a SKU before automatic matching.';
        }

        $result[] = [
            'local' => $row,
            'status' => $status,
            'message' => $message,
            'configured_product_id' =>
                $configuredProductId,
            'configured_variant_id' =>
                $configuredVariantId,
            'matches' => $matches,
        ];
    }

    return $result;
}
