<?php

declare(strict_types=1);

function shop_images_require_admin(int $userId): void
{
    $roles = function_exists('user_roles') ? user_roles($userId) : [];
    $roles = array_map('strtolower', $roles);

    if (!array_intersect(['owner', 'admin', 'administrator'], $roles)) {
        http_response_code(403);
        exit('You do not have permission to manage shop images.');
    }
}

function shop_images_product(int $productId): ?array
{
    $stmt = db()->prepare(
        'SELECT id, slug, name, status, primary_image_url
         FROM shop_products
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $productId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function shop_images_for_product(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT id, image_url, alt_text, option_name, option_value, is_primary, sort_order, created_at
         FROM shop_product_images
         WHERE product_id = :product_id
         ORDER BY is_primary DESC, sort_order ASC, id ASC'
    );
    $stmt->execute([':product_id' => $productId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function shop_images_add_staged(
    int $userId,
    int $productId,
    string $photoToken,
    array $submittedPhotos
): array {
    if (!shop_images_product($productId)) {
        throw new InvalidArgumentException('That product could not be found.');
    }

    $existing = shop_images_for_product($productId);
    $remaining = 20 - count($existing);

    if ($remaining <= 0) {
        throw new RuntimeException('This product already has the maximum of 20 photos.');
    }

    if (count($submittedPhotos) > $remaining) {
        throw new InvalidArgumentException('You can add only ' . $remaining . ' more product photo' . ($remaining === 1 ? '' : 's') . '.');
    }

    $db = db();
    $committed = [];

    try {
        $db->beginTransaction();

        $committed = llama_photo_commit_stage(
            'shop-products',
            $userId,
            $photoToken,
            $submittedPhotos,
            '/uploads/shop-products/' . $productId
        );

        $maxStmt = $db->prepare(
            'SELECT COALESCE(MAX(sort_order), -1)
             FROM shop_product_images
             WHERE product_id = :product_id'
        );
        $maxStmt->execute([':product_id' => $productId]);
        $sortOrder = (int) $maxStmt->fetchColumn() + 1;

        $insert = $db->prepare(
            'INSERT INTO shop_product_images
                (product_id, image_url, alt_text, is_primary, sort_order)
             VALUES
                (:product_id, :image_url, :alt_text, :is_primary, :sort_order)'
        );

        $hasPrimary = false;
        foreach ($existing as $image) {
            if ((int) ($image['is_primary'] ?? 0) === 1) {
                $hasPrimary = true;
                break;
            }
        }

        $firstPath = null;

        foreach ($committed as $index => $photo) {
            $isPrimary = !$hasPrimary && $index === 0;
            $path = (string) ($photo['path'] ?? '');

            $insert->execute([
                ':product_id' => $productId,
                ':image_url' => $path,
                ':alt_text' => trim((string) ($photo['alt'] ?? '')) ?: null,
                ':is_primary' => $isPrimary ? 1 : 0,
                ':sort_order' => $sortOrder++,
            ]);

            if ($isPrimary) {
                $firstPath = $path;
            }
        }

        if ($firstPath !== null) {
            $updateProduct = $db->prepare(
                'UPDATE shop_products SET primary_image_url = :url WHERE id = :id'
            );
            $updateProduct->execute([':url' => $firstPath, ':id' => $productId]);
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        foreach ($committed as $photo) {
            llama_photo_delete_owned_permanent_path(
                (string) ($photo['path'] ?? ''),
                ['uploads/shop-products']
            );
        }

        throw $exception;
    }

    return $committed;
}

function shop_images_set_primary(int $productId, int $imageId): void
{
    $db = db();
    $stmt = $db->prepare(
        'SELECT image_url FROM shop_product_images WHERE id = :id AND product_id = :product_id LIMIT 1'
    );
    $stmt->execute([':id' => $imageId, ':product_id' => $productId]);
    $path = $stmt->fetchColumn();

    if (!is_string($path) || $path === '') {
        throw new InvalidArgumentException('That product photo could not be found.');
    }

    $db->beginTransaction();

    try {
        $clear = $db->prepare('UPDATE shop_product_images SET is_primary = 0 WHERE product_id = :product_id');
        $clear->execute([':product_id' => $productId]);

        $set = $db->prepare('UPDATE shop_product_images SET is_primary = 1 WHERE id = :id AND product_id = :product_id');
        $set->execute([':id' => $imageId, ':product_id' => $productId]);

        $product = $db->prepare('UPDATE shop_products SET primary_image_url = :url WHERE id = :id');
        $product->execute([':url' => $path, ':id' => $productId]);

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function shop_images_delete(int $productId, int $imageId): void
{
    $db = db();
    $stmt = $db->prepare(
        'SELECT image_url, is_primary FROM shop_product_images WHERE id = :id AND product_id = :product_id LIMIT 1'
    );
    $stmt->execute([':id' => $imageId, ':product_id' => $productId]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        throw new InvalidArgumentException('That product photo could not be found.');
    }

    $path = (string) $image['image_url'];
    $wasPrimary = (int) $image['is_primary'] === 1;

    $db->beginTransaction();

    try {
        $delete = $db->prepare('DELETE FROM shop_product_images WHERE id = :id AND product_id = :product_id');
        $delete->execute([':id' => $imageId, ':product_id' => $productId]);

        if ($wasPrimary) {
            $next = $db->prepare(
                'SELECT id, image_url FROM shop_product_images
                 WHERE product_id = :product_id
                 ORDER BY sort_order ASC, id ASC LIMIT 1'
            );
            $next->execute([':product_id' => $productId]);
            $nextImage = $next->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($nextImage) {
                $set = $db->prepare('UPDATE shop_product_images SET is_primary = 1 WHERE id = :id');
                $set->execute([':id' => (int) $nextImage['id']]);
            }

            $updateProduct = $db->prepare('UPDATE shop_products SET primary_image_url = :url WHERE id = :id');
            $url = $nextImage ? (string) $nextImage['image_url'] : null;
            $updateProduct->bindValue(':url', $url, $url === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $updateProduct->bindValue(':id', $productId, PDO::PARAM_INT);
            $updateProduct->execute();
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }

    llama_photo_delete_owned_permanent_path($path, ['uploads/shop-products']);
}
