<?php

declare(strict_types=1);

/*
 * Llama Scout Shop photo position swapping.
 *
 * Uses the existing shop_product_images.sort_order column.
 * No schema change is required.
 *
 * Behavior:
 * - Positions are normalized to 1..N before every swap.
 * - Moving photo 16 to position 4 swaps the two photos.
 * - Position 1 is the primary/hero photo.
 * - Moving a photo into position 1 also makes it primary.
 * - Moving the current primary away from position 1 makes the
 *   photo swapped into position 1 the new primary.
 */

function admin_shop_swap_product_photo_position(
    PDO $db,
    int $actorUserId,
    int $productId,
    int $imageId,
    int $requestedPosition
): void {
    if ($productId < 1 || $imageId < 1) {
        throw new InvalidArgumentException(
            'A valid product photo is required.'
        );
    }

    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'SELECT
                id,
                is_primary,
                sort_order
             FROM shop_product_images
             WHERE product_id = ?
             ORDER BY
                is_primary DESC,
                sort_order ASC,
                id ASC
             FOR UPDATE'
        );

        $stmt->execute([
            $productId,
        ]);

        $images =
            $stmt->fetchAll(PDO::FETCH_ASSOC)
            ?: [];

        $count = count($images);

        if ($count < 1) {
            throw new InvalidArgumentException(
                'This product has no photos.'
            );
        }

        if (
            $requestedPosition < 1
            || $requestedPosition > $count
        ) {
            throw new InvalidArgumentException(
                'Photo position must be between 1 and ' .
                $count .
                '.'
            );
        }

        /*
         * First normalize whatever is currently in the table.
         * The primary image is treated as position 1 because the
         * storefront already treats it as the hero image.
         */
        $normalize = $db->prepare(
            'UPDATE shop_product_images
             SET sort_order = ?
             WHERE id = ?
               AND product_id = ?'
        );

        $currentPosition = null;

        foreach ($images as $index => $image) {
            $position = $index + 1;

            $normalize->execute([
                $position,
                (int) $image['id'],
                $productId,
            ]);

            if ((int) $image['id'] === $imageId) {
                $currentPosition = $position;
            }
        }

        if ($currentPosition === null) {
            throw new InvalidArgumentException(
                'Product photo not found.'
            );
        }

        if ($currentPosition === $requestedPosition) {
            $db->commit();
            return;
        }

        $targetStmt = $db->prepare(
            'SELECT id
             FROM shop_product_images
             WHERE product_id = ?
               AND sort_order = ?
             LIMIT 1
             FOR UPDATE'
        );

        $targetStmt->execute([
            $productId,
            $requestedPosition,
        ]);

        $targetImageId =
            (int) $targetStmt->fetchColumn();

        if ($targetImageId < 1) {
            throw new RuntimeException(
                'The target photo position could not be found.'
            );
        }

        /*
         * Swap positions using a temporary negative value so the
         * operation is deterministic even if a unique index is
         * added to sort_order later.
         */
        $temp = $db->prepare(
            'UPDATE shop_product_images
             SET sort_order = -1
             WHERE id = ?
               AND product_id = ?'
        );

        $temp->execute([
            $imageId,
            $productId,
        ]);

        $moveTarget = $db->prepare(
            'UPDATE shop_product_images
             SET sort_order = ?
             WHERE id = ?
               AND product_id = ?'
        );

        $moveTarget->execute([
            $currentPosition,
            $targetImageId,
            $productId,
        ]);

        $moveCurrent = $db->prepare(
            'UPDATE shop_product_images
             SET sort_order = ?
             WHERE id = ?
               AND product_id = ?'
        );

        $moveCurrent->execute([
            $requestedPosition,
            $imageId,
            $productId,
        ]);

        /*
         * Position 1 and primary are intentionally the same thing.
         * This keeps the Admin numbering and storefront hero image
         * from disagreeing with each other.
         */
        $db->prepare(
            'UPDATE shop_product_images
             SET is_primary = 0
             WHERE product_id = ?'
        )->execute([
            $productId,
        ]);

        $primaryStmt = $db->prepare(
            'UPDATE shop_product_images
             SET is_primary = 1
             WHERE product_id = ?
               AND sort_order = 1'
        );

        $primaryStmt->execute([
            $productId,
        ]);

        if (function_exists('admin_users_audit')) {
            admin_users_audit(
                $db,
                $actorUserId,
                null,
                'shop.product_photo_position_changed',
                'Moved product photo from position ' .
                    $currentPosition .
                    ' to position ' .
                    $requestedPosition .
                    '.',
                [
                    'product_id' =>
                        $productId,
                    'image_id' =>
                        $imageId,
                    'swapped_image_id' =>
                        $targetImageId,
                    'position_before' =>
                        $currentPosition,
                    'position_after' =>
                        $requestedPosition,
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
}
