<?php

declare(strict_types=1);

function llama_resource_image_url(mixed $value): string
{
    $src = trim((string) $value);

    if ($src === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $src) === 1) {
        return $src;
    }

    return '/' . ltrim($src, '/');
}

function llama_resource_images(
    PDO $db,
    int $resourceId
): array {
    if ($resourceId < 1) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT
            id,
            resource_id,
            src,
            alt_text,
            caption,
            is_featured,
            sort_order,
            uploaded_by,
            source_provider,
            source_url,
            created_at,
            updated_at
         FROM resource_images
         WHERE resource_id = ?
         ORDER BY
            is_featured DESC,
            sort_order ASC,
            id ASC'
    );

    $stmt->execute([$resourceId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_resource_featured_image(
    PDO $db,
    int $resourceId
): array {
    $images = llama_resource_images(
        $db,
        $resourceId
    );

    return $images[0] ?? [];
}

function llama_resource_image(
    PDO $db,
    int $resourceId,
    int $imageId
): array {
    if ($resourceId < 1 || $imageId < 1) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT
            id,
            resource_id,
            src,
            alt_text,
            caption,
            is_featured,
            sort_order,
            uploaded_by,
            source_provider,
            source_url,
            created_at,
            updated_at
         FROM resource_images
         WHERE id = ?
           AND resource_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $imageId,
        $resourceId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row)
        ? $row
        : [];
}

function llama_resource_image_insert(
    PDO $db,
    int $resourceId,
    string $src,
    ?string $altText,
    ?int $uploadedBy
): int {
    if ($resourceId < 1) {
        throw new InvalidArgumentException(
            'A valid Resource is required.'
        );
    }

    $existing =
        llama_resource_images(
            $db,
            $resourceId
        );

    if (count($existing) >= 3) {
        throw new InvalidArgumentException(
            'A Resource can have up to 3 photos.'
        );
    }

    $src = '/' . ltrim(trim($src), '/');

    if ($src === '/' || str_contains($src, '..')) {
        throw new InvalidArgumentException(
            'A valid Resource photo path is required.'
        );
    }

    $sortOrder = count($existing);
    $isFeatured = $sortOrder === 0 ? 1 : 0;

    $stmt = $db->prepare(
        'INSERT INTO resource_images (
            resource_id,
            src,
            alt_text,
            is_featured,
            sort_order,
            uploaded_by
         ) VALUES (?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $resourceId,
        $src,
        $altText !== null
            ? mb_substr(trim($altText), 0, 300)
            : null,
        $isFeatured,
        $sortOrder,
        $uploadedBy !== null && $uploadedBy > 0
            ? $uploadedBy
            : null,
    ]);

    return (int) $db->lastInsertId();
}

function llama_resource_images_reorder(
    PDO $db,
    int $resourceId,
    array $imageIds
): void {
    $images = llama_resource_images(
        $db,
        $resourceId
    );

    $existingIds = array_values(
        array_map(
            static fn (array $image): int =>
                (int) ($image['id'] ?? 0),
            $images
        )
    );

    $imageIds = array_values(
        array_map('intval', $imageIds)
    );

    sort($existingIds);
    $checkIds = $imageIds;
    sort($checkIds);

    if ($existingIds !== $checkIds) {
        throw new InvalidArgumentException(
            'The Resource photo order is invalid.'
        );
    }

    $stmt = $db->prepare(
        'UPDATE resource_images
         SET sort_order = ?,
             is_featured = ?
         WHERE id = ?
           AND resource_id = ?'
    );

    foreach ($imageIds as $index => $imageId) {
        $stmt->execute([
            $index,
            $index === 0 ? 1 : 0,
            $imageId,
            $resourceId,
        ]);
    }
}
