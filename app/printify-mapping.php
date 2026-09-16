<?php

declare(strict_types=1);

require_once __DIR__ . '/printify.php';

function llama_printify_local_physical_variants(
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

function llama_printify_mapping_diagnostics(
    PDO $db,
    array $catalog
): array {
    $rows = llama_printify_local_physical_variants(
        $db
    );

    $result = [];

    foreach ($rows as $row) {
        $provider = strtolower(
            trim(
                (string) (
                    $row['fulfillment_provider']
                    ?? ''
                )
            )
        );

        /*
         * The health panel is for variants assigned to Printify.
         * Other provider mappings must never be suggested for takeover.
         */
        if ($provider !== 'printify') {
            continue;
        }

        $sku = trim(
            (string) ($row['sku'] ?? '')
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
        $message = 'No Printify mapping is saved.';

        if (
            $configuredVariantId !== ''
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
                $configuredProductId === ''
                || !hash_equals(
                    (string) ($remote['product_id'] ?? ''),
                    $configuredProductId
                )
            ) {
                $status = 'invalid';
                $message =
                    'Saved Printify product and variant IDs do not belong together.';
            } else {
                $status = 'mapped';
                $message =
                    'Saved Printify product and variant mapping is valid.';
            }
        } elseif ($configuredVariantId !== '') {
            $status = 'invalid';
            $message =
                'Saved Printify variant was not found in this store.';
        } elseif (count($matches) === 1) {
            $status = 'suggested';
            $message =
                'Exactly one Printify variant has the same SKU.';
        } elseif (count($matches) > 1) {
            $status = 'ambiguous';
            $message =
                'Multiple Printify variants use this SKU.';
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

function llama_printify_save_manual_mapping(
    PDO $db,
    int $actorUserId,
    int $localVariantId,
    string $printifyProductId,
    int $printifyVariantId,
    array $catalog
): void {
    if ($localVariantId < 1) {
        throw new InvalidArgumentException(
            'Choose a Llama Scout variant.'
        );
    }

    $printifyProductId = trim($printifyProductId);

    $remote =
        $catalog['variants'][$printifyVariantId]
        ?? null;

    if (
        !is_array($remote)
        || $printifyProductId === ''
        || !hash_equals(
            (string) ($remote['product_id'] ?? ''),
            $printifyProductId
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid Printify product variant.'
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
            'Only physical Shop variants can be mapped to Printify.'
        );
    }

    $localProvider = strtolower(
        trim(
            (string) (
                $local['fulfillment_provider']
                ?? ''
            )
        )
    );

    $hasExistingRemoteMapping =
        trim(
            (string) (
                $local['fulfillment_product_id']
                ?? ''
            )
        ) !== ''
        || trim(
            (string) (
                $local['fulfillment_variant_id']
                ?? ''
            )
        ) !== '';

    if (
        $hasExistingRemoteMapping
        && $localProvider !== 'printify'
    ) {
        throw new InvalidArgumentException(
            'That Llama Scout variant is already mapped to another fulfillment provider. Remove its existing provider mapping first.'
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
           ) = "printify"
           AND v.fulfillment_product_id = ?
           AND v.fulfillment_variant_id = ?
         LIMIT 1'
    );

    $duplicate->execute([
        $localVariantId,
        $printifyProductId,
        (string) $printifyVariantId,
    ]);

    $existing =
        $duplicate->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        throw new InvalidArgumentException(
            'That Printify variant is already mapped to ' .
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
            fulfillment_provider = "printify",
            fulfillment_product_id = ?,
            fulfillment_variant_id = ?
         WHERE id = ?'
    );

    $update->execute([
        $printifyProductId,
        (string) $printifyVariantId,
        $localVariantId,
    ]);

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'shop.printify_variant_mapped',
            'Mapped "' .
                (string) $local['product_name'] .
                ' / ' .
                (string) $local['name'] .
                '" to Printify variant #' .
                $printifyVariantId .
                '.',
            [
                'local_variant_id' =>
                    $localVariantId,
                'printify_product_id' =>
                    $printifyProductId,
                'printify_variant_id' =>
                    $printifyVariantId,
            ]
        );
    }
}

function llama_printify_unmap_local_variant(
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
        ) !== 'printify'
    ) {
        throw new InvalidArgumentException(
            'That variant is not currently mapped to Printify.'
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
            'shop.printify_variant_unmapped',
            'Removed Printify mapping from "' .
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

function llama_printify_apply_exact_mappings(
    PDO $db,
    int $actorUserId,
    array $catalog
): int {
    $diagnostics =
        llama_printify_mapping_diagnostics(
            $db,
            $catalog
        );

    $applied = 0;

    foreach ($diagnostics as $diagnostic) {
        if (
            ($diagnostic['status'] ?? '')
            !== 'suggested'
        ) {
            continue;
        }

        $matches = is_array(
            $diagnostic['matches'] ?? null
        )
            ? $diagnostic['matches']
            : [];

        $local = is_array(
            $diagnostic['local'] ?? null
        )
            ? $diagnostic['local']
            : [];

        if (count($matches) !== 1) {
            continue;
        }

        $remote = $matches[0];

        llama_printify_save_manual_mapping(
            $db,
            $actorUserId,
            (int) ($local['id'] ?? 0),
            (string) ($remote['product_id'] ?? ''),
            (int) ($remote['variant_id'] ?? 0),
            $catalog
        );

        $applied++;
    }

    return $applied;
}
