<?php

declare(strict_types=1);

require_once __DIR__ . '/printful.php';

function llama_printful_local_physical_variants(
    PDO $db
): array {
    $stmt = $db->query(
        'SELECT
            v.id,
            v.product_id,
            p.name AS product_name,
            p.requires_shipping,
            v.name AS variant_name,
            v.sku,
            v.fulfillment_type,
            v.fulfillment_provider,
            v.fulfillment_product_id,
            v.fulfillment_variant_id,
            v.is_active
         FROM shop_product_variants v
         INNER JOIN shop_products p
            ON p.id = v.product_id
         WHERE p.requires_shipping = 1
         ORDER BY
            p.name ASC,
            v.sort_order ASC,
            v.id ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_printful_mapping_diagnostics(
    PDO $db,
    array $catalog
): array {
    $rows = llama_printful_local_physical_variants(
        $db
    );

    $result = [];

    foreach ($rows as $row) {
        $sku = trim(
            (string) ($row['sku'] ?? '')
        );

        $provider = strtolower(
            trim(
                (string) (
                    $row['fulfillment_provider']
                    ?? ''
                )
            )
        );

        $matches = $sku !== ''
            ? (
                $catalog['variants_by_sku'][
                    strtolower($sku)
                ]
                ?? []
            )
            : [];

        $configuredProductId = trim(
            (string) (
                $row['fulfillment_product_id']
                ?? ''
            )
        );

        $configuredVariantId = trim(
            (string) (
                $row['fulfillment_variant_id']
                ?? ''
            )
        );

        $status = 'unmapped';
        $message = 'No Printful mapping is saved.';

        if (
            $provider === 'printful'
            && $configuredVariantId !== ''
            && isset(
                $catalog['variants'][
                    (int) $configuredVariantId
                ]
            )
        ) {
            $remote =
                $catalog['variants'][
                    (int) $configuredVariantId
                ];

            if (
                $configuredProductId !== ''
                && (int) $configuredProductId
                    !==
                    (int) (
                        $remote['sync_product_id']
                        ?? 0
                    )
            ) {
                $status = 'invalid';
                $message =
                    'Saved Printful product and variant IDs do not belong together.';
            } else {
                $status = 'mapped';
                $message =
                    'Saved Printful Sync Variant is valid.';
            }
        } elseif (
            $provider === 'printful'
            && $configuredVariantId !== ''
        ) {
            $status = 'invalid';
            $message =
                'Saved Printful Sync Variant was not found in this store.';
        } elseif (count($matches) === 1) {
            $status = 'suggested';
            $message =
                'Exactly one Printful variant has the same SKU.';
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

function llama_printful_save_manual_mapping(
    PDO $db,
    int $actorUserId,
    int $localVariantId,
    int $syncVariantId,
    array $catalog
): void {
    if ($localVariantId < 1) {
        throw new InvalidArgumentException(
            'Choose a Llama Scout variant.'
        );
    }

    $remote =
        $catalog['variants'][$syncVariantId]
        ?? null;

    if (!is_array($remote)) {
        throw new InvalidArgumentException(
            'Choose a valid Printful variant.'
        );
    }

    $stmt = $db->prepare(
        'SELECT
            v.*,
            p.name AS product_name,
            p.requires_shipping
         FROM shop_product_variants v
         INNER JOIN shop_products p
            ON p.id = v.product_id
         WHERE v.id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $localVariantId,
    ]);

    $local =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$local) {
        throw new InvalidArgumentException(
            'The Llama Scout variant was not found.'
        );
    }

    if (
        (int) ($local['requires_shipping'] ?? 0)
        !== 1
    ) {
        throw new InvalidArgumentException(
            'Only physical Shop variants can be mapped to Printful.'
        );
    }

    $duplicate = $db->prepare(
        'SELECT
            v.id,
            p.name AS product_name,
            v.name AS variant_name
         FROM shop_product_variants v
         INNER JOIN shop_products p
            ON p.id = v.product_id
         WHERE v.id <> ?
           AND LOWER(
                COALESCE(
                    v.fulfillment_provider,
                    ""
                )
           ) = "printful"
           AND v.fulfillment_variant_id = ?
         LIMIT 1'
    );

    $duplicate->execute([
        $localVariantId,
        (string) $syncVariantId,
    ]);

    $existing =
        $duplicate->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        throw new InvalidArgumentException(
            'That Printful variant is already mapped to ' .
            (string) $existing['product_name'] .
            ' / ' .
            (string) $existing['variant_name'] .
            '.'
        );
    }

    $update = $db->prepare(
        'UPDATE shop_product_variants
         SET
            fulfillment_type = "provider",
            fulfillment_provider = "printful",
            fulfillment_product_id = ?,
            fulfillment_variant_id = ?
         WHERE id = ?'
    );

    $update->execute([
        (string) (
            $remote['sync_product_id']
            ?? ''
        ),
        (string) $syncVariantId,
        $localVariantId,
    ]);

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'shop.printful_variant_mapped',
            'Mapped "' .
                (string) $local['product_name'] .
                ' / ' .
                (string) $local['name'] .
                '" to Printful Sync Variant #' .
                $syncVariantId .
                '.',
            [
                'local_variant_id' =>
                    $localVariantId,
                'printful_sync_product_id' =>
                    (int) (
                        $remote['sync_product_id']
                        ?? 0
                    ),
                'printful_sync_variant_id' =>
                    $syncVariantId,
            ]
        );
    }
}

function llama_printful_unmap_local_variant(
    PDO $db,
    int $actorUserId,
    int $localVariantId
): void {
    $stmt = $db->prepare(
        'SELECT
            v.id,
            v.name,
            v.fulfillment_provider,
            p.name AS product_name
         FROM shop_product_variants v
         INNER JOIN shop_products p
            ON p.id = v.product_id
         WHERE v.id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $localVariantId,
    ]);

    $variant =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        throw new InvalidArgumentException(
            'The Llama Scout variant was not found.'
        );
    }

    if (
        strtolower(
            trim(
                (string) (
                    $variant['fulfillment_provider']
                    ?? ''
                )
            )
        ) !== 'printful'
    ) {
        throw new InvalidArgumentException(
            'That variant is not currently mapped to Printful.'
        );
    }

    $update = $db->prepare(
        'UPDATE shop_product_variants
         SET
            fulfillment_type = "manual",
            fulfillment_provider = "llama_scout",
            fulfillment_product_id = NULL,
            fulfillment_variant_id = NULL
         WHERE id = ?'
    );

    $update->execute([
        $localVariantId,
    ]);

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'shop.printful_variant_unmapped',
            'Removed Printful mapping from "' .
                (string) $variant['product_name'] .
                ' / ' .
                (string) $variant['name'] .
                '".',
            [
                'local_variant_id' =>
                    $localVariantId,
            ]
        );
    }
}
