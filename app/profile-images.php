<?php

declare(strict_types=1);

function profile_images_for_user(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT i.id, i.image_src, i.alt_text, i.sort_order, i.uploaded_at,
                CASE WHEN p.primary_image_id = i.id THEN 1 ELSE 0 END AS is_primary
         FROM community_profile_images i
         LEFT JOIN community_profiles p ON p.user_id = i.user_id
         WHERE i.user_id = :user_id
         ORDER BY i.sort_order ASC, i.id ASC'
    );
    $stmt->execute([':user_id' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function profile_images_ensure_profile(int $userId): void
{
    $stmt = db()->prepare(
        'INSERT INTO community_profiles (user_id)
         VALUES (:user_id)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)'
    );
    $stmt->execute([':user_id' => $userId]);
}

function profile_images_add_staged(
    int $userId,
    string $photoToken,
    array $submittedPhotos
): array {
    $existing = profile_images_for_user($userId);
    $remaining = 5 - count($existing);

    if ($remaining <= 0) {
        throw new RuntimeException('You already have the maximum of 5 profile images.');
    }

    if (count($submittedPhotos) > $remaining) {
        throw new InvalidArgumentException('You can add only ' . $remaining . ' more profile image' . ($remaining === 1 ? '' : 's') . '.');
    }

    $db = db();
    $destination = '/uploads/profile-images/' . date('Y') . '/' . date('m') . '/user-' . $userId;
    $committed = [];

    try {
        $db->beginTransaction();
        profile_images_ensure_profile($userId);

        $committed = llama_photo_commit_stage(
            'profile-images',
            $userId,
            $photoToken,
            $submittedPhotos,
            $destination
        );

        $maxStmt = $db->prepare(
            'SELECT COALESCE(MAX(sort_order), -1)
             FROM community_profile_images
             WHERE user_id = :user_id'
        );
        $maxStmt->execute([':user_id' => $userId]);
        $sortOrder = (int) $maxStmt->fetchColumn() + 1;

        $insert = $db->prepare(
            'INSERT INTO community_profile_images
                (user_id, image_src, alt_text, sort_order)
             VALUES
                (:user_id, :image_src, :alt_text, :sort_order)'
        );

        $firstInsertedId = 0;

        foreach ($committed as $photo) {
            $insert->execute([
                ':user_id' => $userId,
                ':image_src' => (string) ($photo['path'] ?? ''),
                ':alt_text' => trim((string) ($photo['alt'] ?? '')) ?: null,
                ':sort_order' => $sortOrder++,
            ]);

            if ($firstInsertedId === 0) {
                $firstInsertedId = (int) $db->lastInsertId();
            }
        }

        $primaryStmt = $db->prepare(
            'SELECT primary_image_id FROM community_profiles WHERE user_id = :user_id LIMIT 1'
        );
        $primaryStmt->execute([':user_id' => $userId]);
        $primary = (int) ($primaryStmt->fetchColumn() ?: 0);

        if ($primary === 0 && $firstInsertedId > 0) {
            $set = $db->prepare(
                'UPDATE community_profiles SET primary_image_id = :image_id WHERE user_id = :user_id'
            );
            $set->execute([':image_id' => $firstInsertedId, ':user_id' => $userId]);
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        foreach ($committed as $photo) {
            llama_photo_delete_owned_permanent_path(
                (string) ($photo['path'] ?? ''),
                ['uploads/profile-images']
            );
        }

        throw $exception;
    }

    return $committed;
}

function profile_images_set_primary(int $userId, int $imageId): void
{
    $check = db()->prepare(
        'SELECT id FROM community_profile_images WHERE id = :id AND user_id = :user_id LIMIT 1'
    );
    $check->execute([':id' => $imageId, ':user_id' => $userId]);

    if (!$check->fetchColumn()) {
        throw new InvalidArgumentException('That profile image could not be found.');
    }

    profile_images_ensure_profile($userId);

    $stmt = db()->prepare(
        'UPDATE community_profiles SET primary_image_id = :image_id WHERE user_id = :user_id'
    );
    $stmt->execute([':image_id' => $imageId, ':user_id' => $userId]);
}

function profile_images_delete(int $userId, int $imageId): void
{
    $db = db();
    $stmt = $db->prepare(
        'SELECT image_src FROM community_profile_images WHERE id = :id AND user_id = :user_id LIMIT 1'
    );
    $stmt->execute([':id' => $imageId, ':user_id' => $userId]);
    $path = $stmt->fetchColumn();

    if (!is_string($path) || $path === '') {
        throw new InvalidArgumentException('That profile image could not be found.');
    }

    $db->beginTransaction();

    try {
        $primaryStmt = $db->prepare(
            'SELECT primary_image_id FROM community_profiles WHERE user_id = :user_id LIMIT 1'
        );
        $primaryStmt->execute([':user_id' => $userId]);
        $wasPrimary = (int) ($primaryStmt->fetchColumn() ?: 0) === $imageId;

        $delete = $db->prepare(
            'DELETE FROM community_profile_images WHERE id = :id AND user_id = :user_id'
        );
        $delete->execute([':id' => $imageId, ':user_id' => $userId]);

        if ($wasPrimary) {
            $next = $db->prepare(
                'SELECT id FROM community_profile_images WHERE user_id = :user_id ORDER BY sort_order ASC, id ASC LIMIT 1'
            );
            $next->execute([':user_id' => $userId]);
            $nextId = (int) ($next->fetchColumn() ?: 0);

            $set = $db->prepare(
                'UPDATE community_profiles SET primary_image_id = :image_id WHERE user_id = :user_id'
            );
            $set->bindValue(':image_id', $nextId > 0 ? $nextId : null, $nextId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $set->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $set->execute();
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }

    llama_photo_delete_owned_permanent_path($path, ['uploads/profile-images']);
}
