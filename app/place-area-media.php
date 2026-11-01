<?php

declare(strict_types=1);

require_once __DIR__ . '/place-report/report-targets.php';

/**
 * Area gallery persistence.
 *
 * Areas are real Place children and own their own photos. Do not store Area
 * photos in place_images or place_campsite_images.
 */
function llama_place_area_images(
    PDO $db,
    int $placeId,
    int $areaFeatureId
): array {
    if ($placeId < 1 || $areaFeatureId < 1) {
        return [];
    }

    try {
        llama_report_verified_target(
            $db,
            $placeId,
            'area',
            'map_feature',
            $areaFeatureId
        );
    } catch (InvalidArgumentException) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT
            id,
            place_id,
            area_feature_id,
            src,
            alt_text,
            caption,
            is_featured,
            sort_order,
            uploaded_by,
            source_provider,
            source_external_id,
            source_url,
            source_credit,
            source_updated_at,
            created_at,
            updated_at
         FROM place_area_images
         WHERE place_id = ?
           AND area_feature_id = ?
         ORDER BY
            is_featured DESC,
            sort_order ASC,
            id ASC'
    );

    $stmt->execute([
        $placeId,
        $areaFeatureId,
    ]);

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    ) ?: [];
}

function llama_place_area_image(
    PDO $db,
    int $placeId,
    int $areaFeatureId,
    int $imageId
): array {
    if (
        $placeId < 1
        || $areaFeatureId < 1
        || $imageId < 1
    ) {
        return [];
    }

    try {
        llama_report_verified_target(
            $db,
            $placeId,
            'area',
            'map_feature',
            $areaFeatureId
        );
    } catch (InvalidArgumentException) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT
            id,
            place_id,
            area_feature_id,
            src,
            alt_text,
            caption,
            is_featured,
            sort_order,
            uploaded_by,
            source_provider,
            source_external_id,
            source_url,
            source_credit,
            source_updated_at,
            created_at,
            updated_at
         FROM place_area_images
         WHERE id = ?
           AND place_id = ?
           AND area_feature_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $imageId,
        $placeId,
        $areaFeatureId,
    ]);

    $row = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return is_array($row)
        ? $row
        : [];
}

function llama_place_area_image_insert(
    PDO $db,
    int $placeId,
    int $areaFeatureId,
    string $src,
    ?string $altText = null,
    ?string $caption = null,
    ?int $uploadedBy = null,
    ?string $sourceProvider = null,
    ?string $sourceExternalId = null,
    ?string $sourceUrl = null,
    ?string $sourceCredit = null,
    ?string $sourceUpdatedAt = null
): int {
    llama_report_verified_target(
        $db,
        $placeId,
        'area',
        'map_feature',
        $areaFeatureId
    );

    $src =
        '/'
        . ltrim(
            trim($src),
            '/'
        );

    if (
        $src === '/'
        || str_contains(
            $src,
            '..'
        )
    ) {
        throw new InvalidArgumentException(
            'A valid Area photo path is required.'
        );
    }

    $orderStmt = $db->prepare(
        'SELECT
            COALESCE(MAX(sort_order), -1),
            COUNT(*)
         FROM place_area_images
         WHERE place_id = ?
           AND area_feature_id = ?'
    );

    $orderStmt->execute([
        $placeId,
        $areaFeatureId,
    ]);

    $row =
        $orderStmt->fetch(
            PDO::FETCH_NUM
        )
        ?: [-1, 0];

    $sortOrder =
        ((int) ($row[0] ?? -1))
        + 1;

    $isFeatured =
        ((int) ($row[1] ?? 0)) === 0
            ? 1
            : 0;

    $stmt = $db->prepare(
        'INSERT INTO place_area_images (
            place_id,
            area_feature_id,
            src,
            alt_text,
            caption,
            is_featured,
            sort_order,
            uploaded_by,
            source_provider,
            source_external_id,
            source_url,
            source_credit,
            source_updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $placeId,
        $areaFeatureId,
        $src,
        $altText !== null
            ? mb_substr(
                trim($altText),
                0,
                300
            )
            : null,
        $caption !== null
            ? mb_substr(
                trim($caption),
                0,
                500
            )
            : null,
        $isFeatured,
        $sortOrder,
        $uploadedBy !== null
            && $uploadedBy > 0
                ? $uploadedBy
                : null,
        $sourceProvider !== null
            ? trim($sourceProvider)
            : null,
        $sourceExternalId !== null
            ? trim($sourceExternalId)
            : null,
        $sourceUrl !== null
            ? trim($sourceUrl)
            : null,
        $sourceCredit !== null
            ? trim($sourceCredit)
            : null,
        $sourceUpdatedAt !== null
            ? trim($sourceUpdatedAt)
            : null,
    ]);

    return (int) $db->lastInsertId();
}

function llama_place_area_image_set_featured(
    PDO $db,
    int $placeId,
    int $areaFeatureId,
    int $imageId
): void {
    if (
        !llama_place_area_image(
            $db,
            $placeId,
            $areaFeatureId,
            $imageId
        )
    ) {
        throw new InvalidArgumentException(
            'That Area photo could not be found.'
        );
    }

    $clear = $db->prepare(
        'UPDATE place_area_images
         SET is_featured = 0
         WHERE place_id = ?
           AND area_feature_id = ?'
    );

    $clear->execute([
        $placeId,
        $areaFeatureId,
    ]);

    $set = $db->prepare(
        'UPDATE place_area_images
         SET is_featured = 1
         WHERE id = ?
           AND place_id = ?
           AND area_feature_id = ?'
    );

    $set->execute([
        $imageId,
        $placeId,
        $areaFeatureId,
    ]);
}
