<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-users.php';


function admin_badges_definitions(
    PDO $db
): array {
    return
        $db->query(
            'SELECT
                bd.*,
                (
                    SELECT COUNT(*)
                    FROM user_badges ub
                    WHERE ub.badge_id = bd.id
                      AND ub.review_status = "earned"
                ) AS earned_count,
                (
                    SELECT COUNT(*)
                    FROM user_badges ub
                    WHERE ub.badge_id = bd.id
                      AND ub.review_status <> "earned"
                ) AS review_count
             FROM badge_definitions bd
             ORDER BY
                bd.sort_order ASC,
                bd.name ASC,
                bd.id ASC'
        )->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}


function admin_badges_definition(
    PDO $db,
    int $badgeId
): ?array {
    $stmt =
        $db->prepare(
            'SELECT
                bd.*,
                (
                    SELECT COUNT(*)
                    FROM user_badges ub
                    WHERE ub.badge_id = bd.id
                      AND ub.review_status = "earned"
                ) AS earned_count,
                (
                    SELECT COUNT(*)
                    FROM user_badges ub
                    WHERE ub.badge_id = bd.id
                      AND ub.review_status <> "earned"
                ) AS review_count
             FROM badge_definitions bd
             WHERE bd.id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $badgeId,
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    return
        $row
            ?: null;
}


function admin_badges_recipients(
    PDO $db,
    int $badgeId
): array {
    $stmt =
        $db->prepare(
            'SELECT
                ub.*,
                COALESCE(
                    NULLIF(u.display_name, ""),
                    NULLIF(u.username, ""),
                    CONCAT("User #", ub.user_id)
                ) AS member_name,
                u.username,
                u.email,
                COALESCE(
                    NULLIF(a.display_name, ""),
                    NULLIF(a.username, ""),
                    CASE
                        WHEN ub.awarded_by IS NULL
                            THEN "Automatic / system"
                        ELSE CONCAT("User #", ub.awarded_by)
                    END
                ) AS awarded_by_name
             FROM user_badges ub
             LEFT JOIN users u
                ON u.id = ub.user_id
             LEFT JOIN users a
                ON a.id = ub.awarded_by
             WHERE ub.badge_id = ?
             ORDER BY
                CASE ub.review_status
                    WHEN "earned" THEN 1
                    ELSE 0
                END,
                ub.awarded_at DESC,
                ub.id DESC'
        );

    $stmt->execute([
        $badgeId,
    ]);

    return
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}


function admin_badges_user_badges(
    PDO $db,
    int $userId
): array {
    $stmt =
        $db->prepare(
            'SELECT
                ub.*,
                bd.slug,
                bd.name,
                bd.description,
                bd.category,
                bd.source_organization,
                bd.icon,
                bd.image_src,
                bd.award_type,
                bd.threshold_value,
                bd.is_active,
                COALESCE(
                    NULLIF(a.display_name, ""),
                    NULLIF(a.username, ""),
                    CASE
                        WHEN ub.awarded_by IS NULL
                            THEN "Automatic / system"
                        ELSE CONCAT("User #", ub.awarded_by)
                    END
                ) AS awarded_by_name
             FROM user_badges ub
             INNER JOIN badge_definitions bd
                ON bd.id = ub.badge_id
             LEFT JOIN users a
                ON a.id = ub.awarded_by
             WHERE ub.user_id = ?
             ORDER BY
                CASE ub.review_status
                    WHEN "earned" THEN 0
                    ELSE 1
                END,
                bd.sort_order ASC,
                ub.awarded_at DESC'
        );

    $stmt->execute([
        $userId,
    ]);

    return
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}


function admin_badges_user_badge(
    PDO $db,
    int $userId,
    int $userBadgeId
): ?array {
    if (
        $userId < 1
        || $userBadgeId < 1
    ) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT
            ub.*,
            bd.slug,
            bd.name,
            bd.category,
            bd.icon,
            bd.image_src
         FROM user_badges ub
         INNER JOIN badge_definitions bd
            ON bd.id = ub.badge_id
         WHERE ub.id = ?
           AND ub.user_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $userBadgeId,
        $userId,
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    return
        $row
            ?: null;
}


function admin_badges_stats(
    PDO $db
): array {
    $row =
        $db->query(
            'SELECT
                (
                    SELECT COUNT(*)
                    FROM badge_definitions
                    WHERE is_active = 1
                ) AS active_badges,
                (
                    SELECT COUNT(*)
                    FROM badge_definitions
                    WHERE is_active = 0
                ) AS inactive_badges,
                (
                    SELECT COUNT(*)
                    FROM user_badges
                    WHERE review_status = "earned"
                ) AS earned_awards,
                (
                    SELECT COUNT(*)
                    FROM user_badges
                    WHERE review_status <> "earned"
                ) AS pending_review'
        )->fetch(PDO::FETCH_ASSOC)
        ?: [];

    return [
        'active_badges' =>
            (int) (
                $row['active_badges']
                ?? 0
            ),

        'inactive_badges' =>
            (int) (
                $row['inactive_badges']
                ?? 0
            ),

        'earned_awards' =>
            (int) (
                $row['earned_awards']
                ?? 0
            ),

        'pending_review' =>
            (int) (
                $row['pending_review']
                ?? 0
            ),
    ];
}


function admin_badges_slugify(
    string $value
): string {
    $value =
        strtolower(
            trim(
                $value
            )
        );

    $value =
        preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $value
        )
        ?? '';

    return
        trim(
            $value,
            '-'
        );
}


function admin_badges_validate_url(
    string $value
): ?string {
    $value =
        trim(
            $value
        );

    if ($value === '') {
        return null;
    }

    if (
        !filter_var(
            $value,
            FILTER_VALIDATE_URL
        )
    ) {
        throw new RuntimeException(
            'Enter a valid evidence URL.'
        );
    }

    if (
        !in_array(
            strtolower(
                (string)
                parse_url(
                    $value,
                    PHP_URL_SCHEME
                )
            ),
            [
                'http',
                'https',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Evidence links must use HTTP or HTTPS.'
        );
    }

    return $value;
}


function admin_badges_save_definition(
    PDO $db,
    int $actorUserId,
    int $badgeId,
    array $data
): int {
    if (
        !admin_users_current_is_owner(
            $db,
            $actorUserId
        )
    ) {
        throw new RuntimeException(
            'Only an Owner can create or change badge definitions.'
        );
    }

    $existing =
        $badgeId > 0
            ? admin_badges_definition(
                $db,
                $badgeId
            )
            : null;

    if (
        $badgeId > 0
        && !$existing
    ) {
        throw new RuntimeException(
            'Badge definition not found.'
        );
    }

    $name =
        trim(
            (string) (
                $data['name']
                ?? ''
            )
        );

    if ($name === '') {
        throw new RuntimeException(
            'Badge name is required.'
        );
    }

    if (mb_strlen($name) > 150) {
        throw new RuntimeException(
            'Badge name must be 150 characters or fewer.'
        );
    }

    $incomingSlug =
        trim(
            (string) (
                $data['slug']
                ?? ''
            )
        );

    $slug =
        admin_badges_slugify(
            $incomingSlug !== ''
                ? $incomingSlug
                : $name
        );

    if ($slug === '') {
        throw new RuntimeException(
            'Badge slug is required.'
        );
    }

    if (strlen($slug) > 100) {
        throw new RuntimeException(
            'Badge slug must be 100 characters or fewer.'
        );
    }

    $duplicate =
        $db->prepare(
            'SELECT id
             FROM badge_definitions
             WHERE slug = ?
               AND id <> ?
             LIMIT 1'
        );

    $duplicate->execute([
        $slug,
        $badgeId,
    ]);

    if ($duplicate->fetchColumn()) {
        throw new RuntimeException(
            'That badge slug is already in use.'
        );
    }

    $description =
        trim(
            (string) (
                $data['description']
                ?? ''
            )
        );

    if (mb_strlen($description) > 500) {
        throw new RuntimeException(
            'Badge description must be 500 characters or fewer.'
        );
    }

    $category =
        strtolower(
            trim(
                (string) (
                    $data['category']
                    ?? 'community'
                )
            )
        );

    if (
        !in_array(
            $category,
            [
                'community',
                'scouting',
                'stewardship',
                'training',
                'special',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Choose a valid badge category.'
        );
    }

    $awardType =
        strtolower(
            trim(
                (string) (
                    $data['award_type']
                    ?? 'manual'
                )
            )
        );

    if (
        !in_array(
            $awardType,
            [
                'automatic',
                'manual',
                'credential',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Choose a valid award type.'
        );
    }

    $thresholdRaw =
        trim(
            (string) (
                $data['threshold_value']
                ?? ''
            )
        );

    $threshold =
        $thresholdRaw === ''
            ? null
            : max(
                0,
                (int) $thresholdRaw
            );

    $sourceOrganization =
        trim(
            (string) (
                $data['source_organization']
                ?? ''
            )
        );

    $icon =
        trim(
            (string) (
                $data['icon']
                ?? ''
            )
        );

    $imageSrc =
        trim(
            (string) (
                $data['image_src']
                ?? ''
            )
        );

    $sortOrder =
        max(
            0,
            min(
                99999,
                (int) (
                    $data['sort_order']
                    ?? 0
                )
            )
        );

    $active =
        ((string) (
            $data['is_active']
            ?? '0'
        )) === '1'
            ? 1
            : 0;

    if ($badgeId > 0) {
        $stmt =
            $db->prepare(
                'UPDATE badge_definitions
                 SET
                    slug = ?,
                    name = ?,
                    description = ?,
                    category = ?,
                    source_organization = ?,
                    icon = ?,
                    image_src = ?,
                    award_type = ?,
                    threshold_value = ?,
                    is_active = ?,
                    sort_order = ?
                 WHERE id = ?'
            );

        $stmt->execute([
            $slug,
            $name,
            $description !== ''
                ? $description
                : null,
            $category,
            $sourceOrganization !== ''
                ? $sourceOrganization
                : null,
            $icon !== ''
                ? $icon
                : null,
            $imageSrc !== ''
                ? $imageSrc
                : null,
            $awardType,
            $threshold,
            $active,
            $sortOrder,
            $badgeId,
        ]);

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'badge.definition_updated',
            'Updated badge definition "' .
                $name .
                '".',
            [
                'badge_id' =>
                    $badgeId,

                'before_slug' =>
                    $existing['slug']
                    ?? null,

                'after_slug' =>
                    $slug,

                'before_active' =>
                    isset(
                        $existing['is_active']
                    )
                        ? (int) $existing['is_active']
                        : null,

                'after_active' =>
                    $active,

                'award_type' =>
                    $awardType,

                'threshold_value' =>
                    $threshold,
            ]
        );

        return $badgeId;
    }

    $stmt =
        $db->prepare(
            'INSERT INTO badge_definitions (
                slug,
                name,
                description,
                category,
                source_organization,
                icon,
                image_src,
                award_type,
                threshold_value,
                is_active,
                sort_order
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

    $stmt->execute([
        $slug,
        $name,
        $description !== ''
            ? $description
            : null,
        $category,
        $sourceOrganization !== ''
            ? $sourceOrganization
            : null,
        $icon !== ''
            ? $icon
            : null,
        $imageSrc !== ''
            ? $imageSrc
            : null,
        $awardType,
        $threshold,
        $active,
        $sortOrder,
    ]);

    $newId =
        (int) $db->lastInsertId();

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'badge.definition_created',
        'Created badge definition "' .
            $name .
            '".',
        [
            'badge_id' =>
                $newId,

            'slug' =>
                $slug,

            'award_type' =>
                $awardType,

            'threshold_value' =>
                $threshold,
        ]
    );

    return $newId;
}


function admin_badges_award(
    PDO $db,
    int $actorUserId,
    int $userId,
    int $badgeId,
    string $note = '',
    string $evidenceUrl = ''
): void {
    if (
        $userId < 1
        || $badgeId < 1
    ) {
        throw new RuntimeException(
            'Choose a member and badge.'
        );
    }

    $badge =
        admin_badges_definition(
            $db,
            $badgeId
        );

    if (!$badge) {
        throw new RuntimeException(
            'Badge definition not found.'
        );
    }

    if (
        (int) $badge['is_active']
        !== 1
    ) {
        throw new RuntimeException(
            'Inactive badges cannot be newly awarded.'
        );
    }

    $userExists =
        $db->prepare(
            'SELECT id
             FROM users
             WHERE id = ?
             LIMIT 1'
        );

    $userExists->execute([
        $userId,
    ]);

    if (!$userExists->fetchColumn()) {
        throw new RuntimeException(
            'Member account not found.'
        );
    }

    $existing =
        $db->prepare(
            'SELECT id, review_status
             FROM user_badges
             WHERE user_id = ?
               AND badge_id = ?
             LIMIT 1'
        );

    $existing->execute([
        $userId,
        $badgeId,
    ]);

    $existingRow =
        $existing->fetch(PDO::FETCH_ASSOC);

    $note =
        mb_substr(
            trim($note),
            0,
            500
        );

    $evidence =
        admin_badges_validate_url(
            $evidenceUrl
        );

    if ($existingRow) {
        if (
            (string) $existingRow['review_status']
            === 'earned'
        ) {
            throw new RuntimeException(
                'This member already has that badge.'
            );
        }

        $stmt =
            $db->prepare(
                'UPDATE user_badges
                 SET
                    review_status = "earned",
                    awarded_by = ?,
                    awarded_at = CURRENT_TIMESTAMP,
                    evidence_url = ?,
                    note = ?
                 WHERE id = ?'
            );

        $stmt->execute([
            $actorUserId,
            $evidence,
            $note !== ''
                ? $note
                : null,
            (int) $existingRow['id'],
        ]);
    } else {
        $stmt =
            $db->prepare(
                'INSERT INTO user_badges (
                    user_id,
                    badge_id,
                    awarded_by,
                    review_status,
                    evidence_url,
                    note
                 ) VALUES (?, ?, ?, "earned", ?, ?)'
            );

        $stmt->execute([
            $userId,
            $badgeId,
            $actorUserId,
            $evidence,
            $note !== ''
                ? $note
                : null,
        ]);
    }

    admin_users_audit(
        $db,
        $actorUserId,
        $userId,
        'badge.awarded',
        'Awarded badge "' .
            (string) $badge['name'] .
            '".',
        [
            'badge_id' =>
                $badgeId,

            'badge_slug' =>
                $badge['slug'],

            'evidence_url' =>
                $evidence,

            'note' =>
                $note !== ''
                    ? $note
                    : null,
        ]
    );
}


function admin_badges_revoke(
    PDO $db,
    int $actorUserId,
    int $userBadgeId,
    string $reason
): void {
    $reason =
        trim(
            $reason
        );

    if ($reason === '') {
        throw new RuntimeException(
            'A reason is required when removing a badge.'
        );
    }

    $stmt =
        $db->prepare(
            'SELECT
                ub.*,
                bd.name AS badge_name,
                bd.slug AS badge_slug
             FROM user_badges ub
             INNER JOIN badge_definitions bd
                ON bd.id = ub.badge_id
             WHERE ub.id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $userBadgeId,
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException(
            'Badge award not found.'
        );
    }

    $db->prepare(
        'DELETE FROM user_badges
         WHERE id = ?'
    )->execute([
        $userBadgeId,
    ]);

    admin_users_audit(
        $db,
        $actorUserId,
        (int) $row['user_id'],
        'badge.revoked',
        'Removed badge "' .
            (string) $row['badge_name'] .
            '".',
        [
            'badge_id' =>
                (int) $row['badge_id'],

            'badge_slug' =>
                $row['badge_slug'],

            'user_badge_id' =>
                $userBadgeId,

            'reason' =>
                mb_substr(
                    $reason,
                    0,
                    500
                ),
        ]
    );
}


function admin_badges_replace_image_from_stage(
    PDO $db,
    int $actorUserId,
    int $badgeId,
    string $photoToken,
    array $submittedPhotos
): ?string {
    if (!$submittedPhotos) {
        return null;
    }

    if (count($submittedPhotos) > 1) {
        throw new RuntimeException(
            'A badge can have only one image.'
        );
    }

    $badge =
        admin_badges_definition(
            $db,
            $badgeId
        );

    if (!$badge) {
        throw new RuntimeException(
            'Badge definition not found.'
        );
    }

    $slug =
        admin_badges_slugify(
            (string) $badge['slug']
        );

    if ($slug === '') {
        throw new RuntimeException(
            'The badge needs a valid slug before an image can be saved.'
        );
    }

    $committed =
        llama_photo_commit_stage(
            'badges',
            $actorUserId,
            $photoToken,
            $submittedPhotos,
            '/images/badges'
        );

    if (!$committed) {
        return null;
    }

    $sourcePath =
        (string) (
            $committed[0]['path']
            ?? ''
        );

    if ($sourcePath === '') {
        throw new RuntimeException(
            'The badge image could not be saved.'
        );
    }

    $sourceAbsolute =
        dirname(__DIR__) .
        $sourcePath;

    $sourceMime =
        strtolower(
            trim(
                (string) (
                    $committed[0]['mime_type']
                    ?? ''
                )
            )
        );

    $finalExtension =
        $sourceMime === 'image/png'
            ? 'png'
            : 'jpg';

    $finalRelative =
        '/images/badges/' .
        $slug .
        '.' .
        $finalExtension;

    $finalAbsolute =
        dirname(__DIR__) .
        $finalRelative;

    if (
        !is_file($sourceAbsolute)
    ) {
        throw new RuntimeException(
            'The committed badge image is missing.'
        );
    }

    $oldImage =
        trim(
            (string) (
                $badge['image_src']
                ?? ''
            )
        );

    if (
        is_file($finalAbsolute)
        && realpath($finalAbsolute) !== realpath($sourceAbsolute)
    ) {
        @unlink($finalAbsolute);
    }

    if (
        $sourceAbsolute !== $finalAbsolute
        && !@rename(
            $sourceAbsolute,
            $finalAbsolute
        )
    ) {
        if (
            !@copy(
                $sourceAbsolute,
                $finalAbsolute
            )
            || !@unlink($sourceAbsolute)
        ) {
            throw new RuntimeException(
                'The badge image could not be renamed to its badge slug.'
            );
        }
    }

    if (
        $oldImage !== ''
        && $oldImage !== $finalRelative
    ) {
        $normalizedOld =
            '/' . ltrim(
                $oldImage,
                '/'
            );

        if (
            str_starts_with(
                $normalizedOld,
                '/images/badges/'
            )
            || str_starts_with(
                $normalizedOld,
                '/uploads/badges/'
            )
        ) {
            llama_photo_delete_owned_permanent_path(
                $oldImage,
                [
                    'images/badges',
                    'uploads/badges',
                ]
            );
        }
    }

    foreach (
        [
            'jpg',
            'jpeg',
            'png',
            'webp',
        ]
        as
        $legacyExtension
    ) {
        if ($legacyExtension === $finalExtension) {
            continue;
        }

        $legacyFile =
            dirname(__DIR__) .
            '/images/badges/' .
            $slug .
            '.' .
            $legacyExtension;

        if (is_file($legacyFile)) {
            @unlink($legacyFile);
        }
    }

    $db->prepare(
        'UPDATE badge_definitions
         SET image_src = ?
         WHERE id = ?'
    )->execute([
        $finalRelative,
        $badgeId,
    ]);

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'badge.image_replaced',
        'Uploaded badge image for "' .
            (string) $badge['name'] .
            '".',
        [
            'badge_id' => $badgeId,
            'badge_slug' => $slug,
            'before_image_src' =>
                $oldImage !== ''
                    ? $oldImage
                    : null,
            'after_image_src' => $finalRelative,
        ]
    );

    return $finalRelative;
}
