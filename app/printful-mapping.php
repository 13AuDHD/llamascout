<?php

declare(strict_types=1);

require_once __DIR__ . '/printful.php';

function llama_printful_mapping_diagnostics(
    PDO $db,
    array $catalog
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

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $result = [];

    foreach ($rows as $row) {
        $sku = trim((string) ($row['sku'] ?? ''));
        $provider = strtolower(
            trim((string) ($row['fulfillment_provider'] ?? ''))
        );

        $matches = $sku !== ''
            ? ($catalog['variants_by_sku'][strtolower($sku)] ?? [])
            : [];

        /*
         * Keep this page focused. Show existing Printful variants,
         * plus any physical local variant with an exact SKU match
         * waiting to be adopted into Printful.
         */
        if ($provider !== 'printful' && !$matches) {
            continue;
        }

        $configuredProductId = trim(
            (string) ($row['fulfillment_product_id'] ?? '')
        );

        $configuredVariantId = trim(
            (string) ($row['fulfillment_variant_id'] ?? '')
        );

        $status = 'unmapped';
        $message = 'No Printful mapping is saved.';

        if (
            $provider === 'printful'
            && $configuredVariantId !== ''
            && isset($catalog['variants'][(int) $configuredVariantId])
        ) {
            $remote = $catalog['variants'][(int) $configuredVariantId];

            if (
                $configuredProductId !== ''
                && (int) $configuredProductId
                    !== (int) ($remote['sync_product_id'] ?? 0)
            ) {
                $status = 'invalid';
                $message = 'Saved Printful product and variant IDs do not belong together.';
            } else {
                $status = 'mapped';
                $message = 'Saved Printful Sync Variant is valid.';
            }
        } elseif (
            $provider === 'printful'
            && $configuredVariantId !== ''
        ) {
            $status = 'invalid';
            $message = 'Saved Printful Sync Variant was not found in this store.';
        } elseif (count($matches) === 1) {
            $status = 'suggested';
            $message = $provider === 'printful'
                ? 'Exact SKU match found; provider IDs can be filled automatically.'
                : 'Exact Printful SKU match found; this variant can be safely mapped to Printful.';
        } elseif (count($matches) > 1) {
            $status = 'ambiguous';
            $message = 'Multiple Printful variants use this SKU.';
        } elseif ($sku === '') {
            $status = 'missing_sku';
            $message = 'Local variant needs a SKU before automatic matching.';
        }

        $result[] = [
            'local' => $row,
            'status' => $status,
            'message' => $message,
            'configured_product_id' => $configuredProductId,
            'configured_variant_id' => $configuredVariantId,
            'matches' => $matches,
        ];
    }

    return $result;
}

function llama_printful_apply_exact_mappings(
    PDO $db,
    int $actorUserId,
    array $catalog
): int {
    $diagnostics = llama_printful_mapping_diagnostics(
        $db,
        $catalog
    );

    $update = $db->prepare(
        'UPDATE shop_product_variants
         SET
            fulfillment_type = "provider",
            fulfillment_provider = "printful",
            fulfillment_product_id = ?,
            fulfillment_variant_id = ?
         WHERE id = ?'
    );

    $applied = 0;

    $db->beginTransaction();

    try {
        foreach ($diagnostics as $diagnostic) {
            if (($diagnostic['status'] ?? '') !== 'suggested') {
                continue;
            }

            $matches = $diagnostic['matches'] ?? [];

            if (count($matches) !== 1) {
                continue;
            }

            $local = $diagnostic['local'] ?? [];
            $currentProvider = strtolower(
                trim((string) ($local['fulfillment_provider'] ?? ''))
            );

            /*
             * Never silently steal a variant from another external
             * provider. Blank, Llama Scout, and Printful are safe.
             */
            if (
                !in_array(
                    $currentProvider,
                    ['', 'llama_scout', 'printful'],
                    true
                )
            ) {
                continue;
            }

            $match = $matches[0];

            $update->execute([
                (string) $match['sync_product_id'],
                (string) $match['sync_variant_id'],
                (int) $local['id'],
            ]);

            $applied += $update->rowCount() > 0 ? 1 : 0;
        }

        if (
            $applied > 0
            && function_exists('admin_users_audit')
        ) {
            admin_users_audit(
                $db,
                $actorUserId,
                null,
                'shop.printful_mappings_applied',
                'Applied ' . $applied . ' exact Printful SKU mapping' .
                    ($applied === 1 ? '' : 's') . '.',
                [
                    'mapping_count' => $applied,
                ]
            );
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    return $applied;
}
