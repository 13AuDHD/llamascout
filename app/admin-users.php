<?php

declare(strict_types=1);

require_once __DIR__ . '/points.php';

function admin_users_current_is_owner(
    PDO $db,
    int $userId
): bool {
    return user_has_role(
        'owner',
        $userId
    );
}

function admin_user_avatar_src(
    ?string $profileImageSrc,
    string $siteUrl
): string {
    $profileImageSrc = trim((string) $profileImageSrc);

    if ($profileImageSrc !== '') {
        return llama_profile_image_url(
            $profileImageSrc,
            $siteUrl
        );
    }

    return rtrim($siteUrl, '/') .
        '/images/default-profile.png';
}

function admin_user_profile_image_sql(
    string $userAlias = 'u'
): string {
    return '(SELECT cpi.image_src
             FROM community_profile_images cpi
             WHERE cpi.user_id = ' . $userAlias . '.id
             ORDER BY
                cpi.sort_order ASC,
                cpi.id ASC
             LIMIT 1)';
}

function admin_users_audit(
    PDO $db,
    int $actorUserId,
    ?int $targetUserId,
    string $action,
    string $summary,
    array $metadata = []
): void {
    $stmt = $db->prepare(
        'INSERT INTO admin_audit_log (
            actor_user_id,
            target_user_id,
            action,
            summary,
            metadata,
            ip_address
        ) VALUES (?, ?, ?, ?, ?, ?)'
    );

    $metadataJson = $metadata
        ? json_encode(
            $metadata,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        )
        : null;

    $ipAddress = trim(
        (string) ($_SERVER['REMOTE_ADDR'] ?? '')
    );

    $stmt->execute([
        $actorUserId,
        $targetUserId,
        $action,
        $summary,
        $metadataJson,
        $ipAddress !== '' ? $ipAddress : null,
    ]);
}

function admin_users_list(
    PDO $db,
    string $search = '',
    string $status = '',
    string $role = '',
    string $membership = ''
): array {
    $where = ['1 = 1'];
    $params = [];

    $search = trim($search);
    $status = trim($status);
    $role = trim($role);
    $membership = trim($membership);

    if ($search !== '') {
        $where[] = '(
            u.email LIKE ?
            OR u.username LIKE ?
            OR u.display_name LIKE ?
            OR CAST(u.id AS CHAR) = ?
        )';

        $needle = '%' . $search . '%';

        array_push(
            $params,
            $needle,
            $needle,
            $needle,
            $search
        );
    }

    if (
        in_array(
            $status,
            ['active', 'pending', 'suspended', 'disabled'],
            true
        )
    ) {
        $where[] = 'u.status = ?';
        $params[] = $status;
    }

    if ($membership !== '') {
        if ($membership === 'paid') {
            $where[] = "u.membership_status IN ('active','trialing')";
        } elseif ($membership === 'free') {
            $where[] = "u.membership_status NOT IN ('active','trialing')";
        }
    }

    if (
        in_array(
            $role,
            ['owner', 'admin', 'scout', 'member'],
            true
        )
    ) {
        $where[] = 'EXISTS (
            SELECT 1
            FROM user_roles ur_filter
            INNER JOIN roles r_filter
                ON r_filter.id = ur_filter.role_id
            WHERE ur_filter.user_id = u.id
              AND r_filter.slug = ?
        )';

        $params[] = $role;
    }

    $sql =
        'SELECT
            u.id,
            u.email,
            u.username,
            u.display_name,
            u.status,
            u.email_verified_at,
            u.last_login_at,
            u.created_at,
            u.membership_status,
            u.membership_interval,
            u.membership_ends_at,
            u.anonymized_at,
            ' . admin_user_profile_image_sql('u') . ' AS profile_image_src,
            GROUP_CONCAT(
                DISTINCT r.slug
                ORDER BY r.id
                SEPARATOR ","
            ) AS role_slugs,
            (
                SELECT COUNT(*)
                FROM place_contributions pc
                WHERE pc.user_id = u.id
                  AND pc.status = "approved"
            ) AS contribution_count
         FROM users u
         LEFT JOIN user_roles ur
            ON ur.user_id = u.id
         LEFT JOIN roles r
            ON r.id = ur.role_id
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY u.id
         ORDER BY
            (u.anonymized_at IS NOT NULL) ASC,
            u.created_at DESC
         LIMIT 250';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_users_get(
    PDO $db,
    int $userId
): ?array {
    $stmt = $db->prepare(
        'SELECT
            u.*,
            ' . admin_user_profile_image_sql('u') . ' AS profile_image_src,
            GROUP_CONCAT(
                DISTINCT r.slug
                ORDER BY r.id
                SEPARATOR ","
            ) AS role_slugs
         FROM users u
         LEFT JOIN user_roles ur
            ON ur.user_id = u.id
         LEFT JOIN roles r
            ON r.id = ur.role_id
         WHERE u.id = ?
         GROUP BY u.id
         LIMIT 1'
    );

    $stmt->execute([$userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function admin_users_roles(
    PDO $db,
    int $userId
): array {
    return user_roles(
        $userId
    );
}

function admin_users_stats(
    PDO $db,
    int $userId
): array {
    $queries = [
        'contributions' =>
            'SELECT COUNT(*)
             FROM place_contributions
             WHERE user_id = ?
               AND status = "approved"',

        'places_added' =>
            'SELECT COUNT(*)
             FROM place_contributions
             WHERE user_id = ?
               AND status = "approved"
               AND contribution_type = "new_place"',

        'updates' =>
            'SELECT COUNT(*)
             FROM place_contributions
             WHERE user_id = ?
               AND status = "approved"
               AND contribution_type <> "new_place"',

        'reports' =>
            'SELECT COUNT(*)
             FROM place_reports
             WHERE user_id = ?',

        'badges' =>
            'SELECT COUNT(*)
             FROM user_badges
             WHERE user_id = ?
               AND review_status = "earned"',

        'saved_places' =>
            'SELECT COUNT(*)
             FROM user_saved_places
             WHERE user_id = ?',

        'sessions' =>
            'SELECT COUNT(*)
             FROM sessions
             WHERE user_id = ?
               AND expires_at > NOW()',
    ];

    $stats = [];

    foreach ($queries as $key => $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $stats[$key] = (int) $stmt->fetchColumn();
        } catch (Throwable $exception) {
            error_log(
                'Admin user stat error [' .
                $key .
                ']: ' .
                $exception->getMessage()
            );

            $stats[$key] = 0;
        }
    }

    $stats['points'] =
        llama_points_total(
            $db,
            $userId
        );

    return $stats;
}

function admin_users_recent_contributions(
    PDO $db,
    int $userId
): array {
    try {
        $stmt = $db->prepare(
            'SELECT
                pc.id,
                pc.place_id,
                pc.contribution_type,
                pc.points_awarded,
                pc.approved_at,
                pc.created_at,
                p.name AS place_name,
                p.slug AS place_slug
             FROM place_contributions pc
             INNER JOIN places p
                ON p.id = pc.place_id
             WHERE pc.user_id = ?
             ORDER BY
                COALESCE(
                    pc.approved_at,
                    pc.created_at
                ) DESC
             LIMIT 20'
        );

        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $exception) {
        error_log(
            'Admin user contribution history error: ' .
            $exception->getMessage()
        );

        return [];
    }
}

function admin_users_audit_history(
    PDO $db,
    int $userId
): array {
    try {
        $stmt = $db->prepare(
            'SELECT
                aal.*,
                COALESCE(
                    NULLIF(actor.display_name, ""),
                    NULLIF(actor.username, ""),
                    "System"
                ) AS actor_name
             FROM admin_audit_log aal
             LEFT JOIN users actor
                ON actor.id = aal.actor_user_id
             WHERE aal.target_user_id = ?
             ORDER BY aal.created_at DESC
             LIMIT 30'
        );

        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $exception) {
        error_log(
            'Admin user audit history error: ' .
            $exception->getMessage()
        );

        return [];
    }
}

function admin_users_save_account(
    PDO $db,
    int $actorUserId,
    int $targetUserId,
    array $data
): void {
    $target = admin_users_get(
        $db,
        $targetUserId
    );

    if (!$target) {
        throw new RuntimeException(
            'The account no longer exists.'
        );
    }

    if (!empty($target['anonymized_at'])) {
        throw new RuntimeException(
            'An anonymized account cannot be edited.'
        );
    }

    $actorIsOwner = admin_users_current_is_owner(
        $db,
        $actorUserId
    );

    $targetIsOwner = user_has_role(
        'owner',
        $targetUserId
    );

    if (
        $targetIsOwner &&
        !$actorIsOwner
    ) {
        throw new RuntimeException(
            'Only an Owner can edit an Owner account.'
        );
    }

    $email = strtolower(
        trim((string) ($data['email'] ?? ''))
    );

    $username = trim(
        (string) ($data['username'] ?? '')
    );

    $displayName = trim(
        (string) ($data['display_name'] ?? '')
    );

    $timezone = trim(
        (string) ($data['timezone'] ?? 'America/Denver')
    );

    $status = trim(
        (string) ($data['status'] ?? 'active')
    );

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException(
            'Enter a valid email address.'
        );
    }

    if (
        $username !== '' &&
        !preg_match(
            '/^[A-Za-z0-9_]{4,16}$/',
            $username
        )
    ) {
        throw new RuntimeException(
            'Username must be 4 to 16 letters, numbers, or underscores.'
        );
    }

    if (mb_strlen($displayName) > 100) {
        throw new RuntimeException(
            'Display name is too long.'
        );
    }

    if (
        !in_array(
            $status,
            ['active', 'pending', 'suspended', 'disabled'],
            true
        )
    ) {
        throw new RuntimeException(
            'Invalid account status.'
        );
    }

    if (
        $targetUserId === $actorUserId &&
        $status !== 'active'
    ) {
        throw new RuntimeException(
            'You cannot suspend or disable your own account.'
        );
    }

    $duplicate = $db->prepare(
        'SELECT id
         FROM users
         WHERE email = ?
           AND id <> ?
         LIMIT 1'
    );

    $duplicate->execute([
        $email,
        $targetUserId,
    ]);

    if ($duplicate->fetchColumn()) {
        throw new RuntimeException(
            'That email address is already in use.'
        );
    }

    if ($username !== '') {
        $duplicate = $db->prepare(
            'SELECT id
             FROM users
             WHERE username = ?
               AND id <> ?
             LIMIT 1'
        );

        $duplicate->execute([
            $username,
            $targetUserId,
        ]);

        if ($duplicate->fetchColumn()) {
            throw new RuntimeException(
                'That username is already in use.'
            );
        }
    }

    $stmt = $db->prepare(
        'UPDATE users
         SET
            email = ?,
            username = ?,
            display_name = ?,
            timezone = ?,
            status = ?
         WHERE id = ?'
    );

    $stmt->execute([
        $email,
        $username !== '' ? $username : null,
        $displayName !== '' ? $displayName : null,
        $timezone !== '' ? $timezone : 'America/Denver',
        $status,
        $targetUserId,
    ]);

    admin_users_audit(
        $db,
        $actorUserId,
        $targetUserId,
        'user.account_updated',
        'Updated account identity or status.',
        [
            'before' => [
                'email' => $target['email'],
                'username' => $target['username'],
                'display_name' => $target['display_name'],
                'timezone' => $target['timezone'],
                'status' => $target['status'],
            ],
            'after' => [
                'email' => $email,
                'username' => $username,
                'display_name' => $displayName,
                'timezone' => $timezone,
                'status' => $status,
            ],
        ]
    );
}

function admin_users_set_roles(
    PDO $db,
    int $actorUserId,
    int $targetUserId,
    array $requestedRoles
): void {
    if (
        !admin_users_current_is_owner(
            $db,
            $actorUserId
        )
    ) {
        throw new RuntimeException(
            'Only an Owner can change account roles.'
        );
    }

    $target = admin_users_get(
        $db,
        $targetUserId
    );

    if (!$target) {
        throw new RuntimeException(
            'The account no longer exists.'
        );
    }

    if (!empty($target['anonymized_at'])) {
        throw new RuntimeException(
            'An anonymized account cannot receive roles.'
        );
    }

    $allowed = [
        'member',
        'scout',
        'admin',
        'owner',
    ];

    $requestedRoles = array_values(
        array_unique(
            array_intersect(
                $allowed,
                array_map(
                    'strval',
                    $requestedRoles
                )
            )
        )
    );

    if (
        !in_array(
            'member',
            $requestedRoles,
            true
        )
    ) {
        $requestedRoles[] = 'member';
    }

    if ($targetUserId === $actorUserId) {
        if (
            !in_array('owner', $requestedRoles, true) ||
            !in_array('admin', $requestedRoles, true)
        ) {
            throw new RuntimeException(
                'You cannot remove your own Owner or Admin access.'
            );
        }
    }

    if (
        in_array('owner', $requestedRoles, true) &&
        !in_array('admin', $requestedRoles, true)
    ) {
        $requestedRoles[] = 'admin';
    }

    $before = admin_users_roles(
        $db,
        $targetUserId
    );

    $db->beginTransaction();

    try {
        $delete = $db->prepare(
            'DELETE FROM user_roles
             WHERE user_id = ?'
        );

        $delete->execute([$targetUserId]);

        $roleStmt = $db->prepare(
            'SELECT id
             FROM roles
             WHERE slug = ?
             LIMIT 1'
        );

        $insert = $db->prepare(
            'INSERT INTO user_roles (
                user_id,
                role_id
             ) VALUES (?, ?)'
        );

        foreach ($requestedRoles as $roleSlug) {
            $roleStmt->execute([$roleSlug]);
            $roleId = (int) $roleStmt->fetchColumn();

            if ($roleId > 0) {
                $insert->execute([
                    $targetUserId,
                    $roleId,
                ]);
            }
        }

        admin_users_audit(
            $db,
            $actorUserId,
            $targetUserId,
            'user.roles_updated',
            'Updated account roles.',
            [
                'before' => $before,
                'after' => $requestedRoles,
            ]
        );

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function admin_users_force_logout(
    PDO $db,
    int $actorUserId,
    int $targetUserId
): int {
    if ($targetUserId === $actorUserId) {
        throw new RuntimeException(
            'Use the normal sign-out action for your own account.'
        );
    }

    $count = 0;

    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'DELETE FROM sessions
             WHERE user_id = ?'
        );

        $stmt->execute([$targetUserId]);
        $count += $stmt->rowCount();

        $stmt = $db->prepare(
            'DELETE FROM user_remember_tokens
             WHERE user_id = ?'
        );

        $stmt->execute([$targetUserId]);
        $count += $stmt->rowCount();

        admin_users_audit(
            $db,
            $actorUserId,
            $targetUserId,
            'user.sessions_revoked',
            'Revoked all active sessions and remember-me tokens.',
            [
                'rows_removed' => $count,
            ]
        );

        $db->commit();

        return $count;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function admin_users_profile_image_paths(
    PDO $db,
    int $userId
): array {
    try {
        $stmt = $db->prepare(
            'SELECT image_src
             FROM community_profile_images
             WHERE user_id = ?'
        );

        $stmt->execute([$userId]);

        return array_values(
            array_filter(
                array_map(
                    static fn(array $row): string =>
                        trim((string) ($row['image_src'] ?? '')),
                    $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
                )
            )
        );
    } catch (Throwable) {
        return [];
    }
}

function admin_users_delete_profile_files(
    int $userId,
    array $imageSources = []
): void {
    $root = dirname(__DIR__);

    foreach ($imageSources as $src) {
        $src = trim((string) $src);

        if (
            $src === '' ||
            !str_starts_with(
                $src,
                '/uploads/profile-images/'
            )
        ) {
            continue;
        }

        $path = realpath(
            dirname(
                $root .
                $src
            )
        );

        $file = realpath(
            $root .
            $src
        );

        $allowedRoot = realpath(
            $root .
            '/uploads/profile-images'
        );

        if (
            $file &&
            $allowedRoot &&
            str_starts_with(
                $file,
                $allowedRoot . DIRECTORY_SEPARATOR
            ) &&
            is_file($file)
        ) {
            @unlink($file);
        }
    }

    $userRoot =
        $root .
        '/uploads/profile-images';

    if (!is_dir($userRoot)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $userRoot,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item->isDir()) {
            continue;
        }

        $name = $item->getFilename();

        if ($name !== 'user-' . $userId) {
            continue;
        }

        $directory = $item->getPathname();

        $children = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($children as $child) {
            if ($child->isDir()) {
                @rmdir($child->getPathname());
            } else {
                @unlink($child->getPathname());
            }
        }

        @rmdir($directory);
    }
}

function admin_users_anonymize(
    PDO $db,
    int $actorUserId,
    int $targetUserId,
    string $reason
): void {
    if (
        !admin_users_current_is_owner(
            $db,
            $actorUserId
        )
    ) {
        throw new RuntimeException(
            'Only an Owner can anonymize an account.'
        );
    }

    if ($targetUserId === $actorUserId) {
        throw new RuntimeException(
            'You cannot anonymize your own account.'
        );
    }

    $target = admin_users_get(
        $db,
        $targetUserId
    );

    if (!$target) {
        throw new RuntimeException(
            'The account no longer exists.'
        );
    }

    if (!empty($target['anonymized_at'])) {
        throw new RuntimeException(
            'This account has already been anonymized.'
        );
    }

    if (
        user_has_role(
            'owner',
            $targetUserId
        )
    ) {
        throw new RuntimeException(
            'Remove Owner status before anonymizing this account.'
        );
    }

    $reason = trim($reason);

    if (mb_strlen($reason) < 8) {
        throw new RuntimeException(
            'Enter a brief reason for the anonymization.'
        );
    }

    $images = admin_users_profile_image_paths(
        $db,
        $targetUserId
    );

    $oldIdentity = [
        'email' => $target['email'],
        'username' => $target['username'],
        'display_name' => $target['display_name'],
    ];

    $deletedUsername =
        'deleted_user_' .
        $targetUserId;

    $deletedEmail =
        'deleted+' .
        $targetUserId .
        '@invalid.llamascout.local';

    $passwordHash = password_hash(
        bin2hex(random_bytes(32)),
        PASSWORD_DEFAULT
    );

    $db->beginTransaction();

    try {
        $deleteByUser = [
            'sessions',
            'user_remember_tokens',
            'email_verifications',
            'password_resets',
            'user_mfa_recovery_codes',
            'user_mfa',
            'user_saved_places',
            'saved_places',
            'community_profile_images',
            'community_profiles',
            'user_badges',
            'membership_grants',
            'scout_applications',
            'scout_training',
        ];

        foreach ($deleteByUser as $table) {
            try {
                $stmt = $db->prepare(
                    'DELETE FROM `' .
                    $table .
                    '`
                     WHERE user_id = ?'
                );

                $stmt->execute([$targetUserId]);
            } catch (Throwable $exception) {
                error_log(
                    'Anonymization cleanup skipped for ' .
                    $table .
                    ': ' .
                    $exception->getMessage()
                );
            }
        }

        $stmt = $db->prepare(
            'DELETE FROM user_roles
             WHERE user_id = ?'
        );

        $stmt->execute([$targetUserId]);

        $stmt = $db->prepare(
            'UPDATE scout_profiles
             SET
                status = "removed",
                removed_at = COALESCE(removed_at, NOW()),
                removed_by = ?,
                removal_reason = "Account anonymized."
             WHERE user_id = ?'
        );

        $stmt->execute([
            $actorUserId,
            $targetUserId,
        ]);

        $stmt = $db->prepare(
            'UPDATE users
             SET
                email = ?,
                username = ?,
                password_hash = ?,
                display_name = "Former Llama Scout Member",
                timezone = "America/Denver",
                status = "disabled",
                email_verified_at = NULL,
                last_login_at = NULL,
                dormancy_notice_sent_at = NULL,
                anonymized_at = NOW(),
                anonymized_by = ?,
                stripe_customer_id = NULL,
                stripe_subscription_id = NULL,
                stripe_cancel_at_period_end = 0,
                membership_status = "none",
                membership_interval = NULL,
                membership_started_at = NULL,
                membership_ends_at = NULL
             WHERE id = ?'
        );

        $stmt->execute([
            $deletedEmail,
            $deletedUsername,
            $passwordHash,
            $actorUserId,
            $targetUserId,
        ]);

        admin_users_audit(
            $db,
            $actorUserId,
            $targetUserId,
            'user.anonymized',
            'Anonymized account while preserving contribution history.',
            [
                'reason' => $reason,
                'previous_identity' => $oldIdentity,
                'preserved' => [
                    'places',
                    'place_contributions',
                    'place_provenance',
                    'place_submissions',
                    'place_updates',
                    'place_reports',
                    'scout_activity',
                    'scout_rank_history',
                ],
            ]
        );

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    admin_users_delete_profile_files(
        $targetUserId,
        $images
    );
}
