<?php

declare(strict_types=1);

function admin_system_set_maintenance(
    PDO $db,
    int $actorUserId,
    array $data
): void {
    if (!admin_users_current_is_owner($db, $actorUserId)) {
        throw new RuntimeException(
            'Only an Owner can change maintenance mode.'
        );
    }

    $enabled =
        ((string) ($data['enabled'] ?? '0')) === '1';

    $message = trim(
        (string) ($data['message'] ?? '')
    );

    if ($message === '') {
        $message =
            'Llama Scout is getting a few upgrades.';
    }

    if (mb_strlen($message) > 500) {
        throw new RuntimeException(
            'Maintenance message must be 500 characters or less.'
        );
    }

    $returnAt = trim(
        (string) ($data['return_at'] ?? '')
    );

    $publicEnabled =
        isset($data['public_enabled']) ? '1' : '0';

    $accountEnabled =
        isset($data['account_enabled']) ? '1' : '0';

    $apiEnabled =
        isset($data['api_enabled']) ? '1' : '0';

    $before = llama_maintenance_state($db);

    $db->beginTransaction();

    try {
        llama_set_site_setting(
            $db,
            'maintenance_enabled',
            $enabled ? '1' : '0'
        );

        llama_set_site_setting(
            $db,
            'maintenance_message',
            $message
        );

        llama_set_site_setting(
            $db,
            'maintenance_return_at',
            $returnAt
        );

        llama_set_site_setting(
            $db,
            'maintenance_public_enabled',
            $publicEnabled
        );

        llama_set_site_setting(
            $db,
            'maintenance_account_enabled',
            $accountEnabled
        );

        llama_set_site_setting(
            $db,
            'maintenance_api_enabled',
            $apiEnabled
        );

        if (
            $enabled &&
            !$before['enabled']
        ) {
            llama_set_site_setting(
                $db,
                'maintenance_started_at',
                date('Y-m-d H:i:s')
            );

            llama_set_site_setting(
                $db,
                'maintenance_started_by',
                (string) $actorUserId
            );
        }

        if (!$enabled) {
            llama_set_site_setting(
                $db,
                'maintenance_started_at',
                ''
            );

            llama_set_site_setting(
                $db,
                'maintenance_started_by',
                ''
            );
        }

        $beforeEnabled =
            (bool) (
                $before['enabled']
                ?? false
            );

        if ($beforeEnabled !== $enabled) {
            $auditAction =
                $enabled
                    ? 'system.maintenance_enabled'
                    : 'system.maintenance_disabled';

            $auditSummary =
                $enabled
                    ? 'Enabled maintenance mode.'
                    : 'Disabled maintenance mode.';
        } else {
            $auditAction =
                'system.maintenance_updated';

            $auditSummary =
                $enabled
                    ? 'Updated active maintenance settings.'
                    : 'Updated maintenance settings while disabled.';
        }

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            $auditAction,
            $auditSummary,
            [
                'before' => $before,
                'after' => [
                    'enabled' => $enabled,
                    'message' => $message,
                    'return_at' => $returnAt,
                    'public_enabled' =>
                        $publicEnabled === '1',
                    'account_enabled' =>
                        $accountEnabled === '1',
                    'api_enabled' =>
                        $apiEnabled === '1',
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
}


function admin_system_audit_category_sql(): string
{
    return
        'CASE
            WHEN aal.action LIKE "user.%"
                THEN "users"
            WHEN aal.action LIKE "place.report_%"
                OR aal.action LIKE "report.%"
                THEN "reports"
            WHEN aal.action LIKE "place.%"
                OR aal.action LIKE "moderation.%"
                THEN "places"
            WHEN aal.action LIKE "scout.%"
                THEN "scouts"
            WHEN aal.action LIKE "points.%"
                THEN "points"
            WHEN aal.action LIKE "shop.%"
                OR aal.action LIKE "order.%"
                OR aal.action LIKE "product.%"
                THEN "shop"
            WHEN aal.action LIKE "system.%"
                THEN "system"
            WHEN aal.action LIKE "badge.%"
                THEN "badges"
            WHEN aal.action LIKE "policy.%"
                THEN "policy"
            ELSE "other"
         END';
}


function admin_system_audit_filters(
    array $input
): array {
    $category =
        strtolower(
            trim(
                (string) (
                    $input['category']
                    ?? ''
                )
            )
        );

    $allowedCategories = [
        '',
        'users',
        'places',
        'scouts',
        'points',
        'shop',
        'reports',
        'system',
        'badges',
        'policy',
        'other',
    ];

    if (
        !in_array(
            $category,
            $allowedCategories,
            true
        )
    ) {
        $category = '';
    }

    $dateFrom =
        trim(
            (string) (
                $input['date_from']
                ?? ''
            )
        );

    $dateTo =
        trim(
            (string) (
                $input['date_to']
                ?? ''
            )
        );

    if (
        $dateFrom !== ''
        && !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $dateFrom
        )
    ) {
        $dateFrom = '';
    }

    if (
        $dateTo !== ''
        && !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $dateTo
        )
    ) {
        $dateTo = '';
    }

    return [
        'q' =>
            mb_substr(
                trim(
                    (string) (
                        $input['q']
                        ?? ''
                    )
                ),
                0,
                120
            ),

        'category' =>
            $category,

        'actor_id' =>
            max(
                0,
                (int) (
                    $input['actor_id']
                    ?? 0
                )
            ),

        'target_id' =>
            max(
                0,
                (int) (
                    $input['target_id']
                    ?? 0
                )
            ),

        'date_from' =>
            $dateFrom,

        'date_to' =>
            $dateTo,

        'page' =>
            max(
                1,
                (int) (
                    $input['page']
                    ?? 1
                )
            ),
    ];
}


function admin_system_audit_search(
    PDO $db,
    array $filters,
    int $perPage = 50
): array {
    $filters =
        admin_system_audit_filters(
            $filters
        );

    $perPage =
        max(
            20,
            min(
                100,
                $perPage
            )
        );

    $where = [
        '1 = 1',
    ];

    $params = [];

    if ($filters['q'] !== '') {
        $needle =
            '%' .
            $filters['q'] .
            '%';

        $where[] =
            '(
                aal.action LIKE ?
                OR aal.summary LIKE ?
                OR aal.ip_address LIKE ?
                OR actor.username LIKE ?
                OR actor.display_name LIKE ?
                OR target.username LIKE ?
                OR target.display_name LIKE ?
                OR CAST(aal.id AS CHAR) = ?
             )';

        array_push(
            $params,
            $needle,
            $needle,
            $needle,
            $needle,
            $needle,
            $needle,
            $needle,
            $filters['q']
        );
    }

    if ($filters['actor_id'] > 0) {
        $where[] =
            'aal.actor_user_id = ?';

        $params[] =
            $filters['actor_id'];
    }

    if ($filters['target_id'] > 0) {
        $where[] =
            'aal.target_user_id = ?';

        $params[] =
            $filters['target_id'];
    }

    if ($filters['date_from'] !== '') {
        $where[] =
            'aal.created_at >= ?';

        $params[] =
            $filters['date_from'] .
            ' 00:00:00';
    }

    if ($filters['date_to'] !== '') {
        $where[] =
            'aal.created_at < DATE_ADD(?, INTERVAL 1 DAY)';

        $params[] =
            $filters['date_to'] .
            ' 00:00:00';
    }

    $categorySql =
        admin_system_audit_category_sql();

    if ($filters['category'] !== '') {
        $where[] =
            '(' .
            $categorySql .
            ') = ?';

        $params[] =
            $filters['category'];
    }

    $whereSql =
        implode(
            ' AND ',
            $where
        );

    $countSql =
        'SELECT COUNT(*)
         FROM admin_audit_log aal
         LEFT JOIN users actor
            ON actor.id = aal.actor_user_id
         LEFT JOIN users target
            ON target.id = aal.target_user_id
         WHERE ' .
         $whereSql;

    $countStmt =
        $db->prepare(
            $countSql
        );

    $countStmt->execute(
        $params
    );

    $total =
        (int)
        $countStmt->fetchColumn();

    $pages =
        max(
            1,
            (int)
            ceil(
                $total /
                $perPage
            )
        );

    $page =
        min(
            $filters['page'],
            $pages
        );

    $offset =
        ($page - 1) *
        $perPage;

    $sql =
        'SELECT
            aal.*,
            ' .
            $categorySql .
            ' AS category,
            COALESCE(
                NULLIF(actor.display_name, ""),
                NULLIF(actor.username, ""),
                "System"
            ) AS actor_name,
            actor.username AS actor_username,
            COALESCE(
                NULLIF(target.display_name, ""),
                NULLIF(target.username, ""),
                CASE
                    WHEN aal.target_user_id IS NULL
                        THEN NULL
                    ELSE "Former Llama Scout Member"
                END
            ) AS target_name,
            target.username AS target_username
         FROM admin_audit_log aal
         LEFT JOIN users actor
            ON actor.id = aal.actor_user_id
         LEFT JOIN users target
            ON target.id = aal.target_user_id
         WHERE ' .
         $whereSql .
         ' ORDER BY
            aal.created_at DESC,
            aal.id DESC
         LIMIT ' .
         $perPage .
         ' OFFSET ' .
         $offset;

    $stmt =
        $db->prepare(
            $sql
        );

    $stmt->execute(
        $params
    );

    return [
        'rows' =>
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [],

        'total' =>
            $total,

        'page' =>
            $page,

        'pages' =>
            $pages,

        'per_page' =>
            $perPage,

        'filters' =>
            $filters,
    ];
}


function admin_system_audit_actor_options(
    PDO $db
): array {
    return
        $db->query(
            'SELECT DISTINCT
                u.id,
                COALESCE(
                    NULLIF(u.display_name, ""),
                    NULLIF(u.username, ""),
                    CONCAT("User #", u.id)
                ) AS name
             FROM admin_audit_log aal
             INNER JOIN users u
                ON u.id = aal.actor_user_id
             ORDER BY name ASC'
        )->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];
}


function admin_system_audit_metadata(
    mixed $value
): array {
    if (
        !is_string($value)
        || trim($value) === ''
    ) {
        return [];
    }

    $decoded =
        json_decode(
            $value,
            true
        );

    return
        is_array($decoded)
            ? $decoded
            : [];
}


function admin_system_flatten_metadata(
    array $metadata,
    string $prefix = ''
): array {
    $rows = [];

    foreach (
        $metadata
        as
        $key => $value
    ) {
        $label =
            $prefix !== ''
                ? $prefix . '.' . $key
                : (string) $key;

        if (
            is_array($value)
            && !array_is_list($value)
        ) {
            $rows =
                array_merge(
                    $rows,
                    admin_system_flatten_metadata(
                        $value,
                        $label
                    )
                );

            continue;
        }

        if (is_array($value)) {
            $value =
                implode(
                    ', ',
                    array_map(
                        static fn (
                            mixed $item
                        ): string =>
                            is_scalar($item)
                                ? (string) $item
                                : json_encode(
                                    $item,
                                    JSON_UNESCAPED_SLASHES |
                                    JSON_UNESCAPED_UNICODE
                                ),
                        $value
                    )
                );
        } elseif (is_bool($value)) {
            $value =
                $value
                    ? 'Yes'
                    : 'No';
        } elseif ($value === null) {
            $value =
                'None';
        }

        $rows[] = [
            'key' =>
                $label,

            'value' =>
                (string) $value,
        ];
    }

    return $rows;
}


function admin_system_health_card(
    string $key,
    string $label,
    string $status,
    string $value,
    string $detail,
    string $icon = 'fa-circle-check'
): array {
    if (
        !in_array(
            $status,
            [
                'good',
                'attention',
                'down',
            ],
            true
        )
    ) {
        $status =
            'attention';
    }

    return [
        'key' => $key,
        'label' => $label,
        'status' => $status,
        'value' => $value,
        'detail' => $detail,
        'icon' => $icon,
    ];
}


function admin_system_directory_stats(
    string $root,
    int $staleAfterSeconds = 86400
): array {
    $stats = [
        'exists' => is_dir($root),
        'writable' =>
            is_dir($root)
                ? is_writable($root)
                : is_writable(
                    dirname($root)
                ),
        'files' => 0,
        'bytes' => 0,
        'stale_files' => 0,
        'stale_bytes' => 0,
    ];

    if (!$stats['exists']) {
        return $stats;
    }

    $threshold =
        time() -
        $staleAfterSeconds;

    try {
        $iterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $root,
                    FilesystemIterator::SKIP_DOTS
                )
            );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $size =
                max(
                    0,
                    (int) $item->getSize()
                );

            $stats['files']++;
            $stats['bytes'] +=
                $size;

            if (
                $item->getMTime()
                < $threshold
            ) {
                $stats['stale_files']++;
                $stats['stale_bytes'] +=
                    $size;
            }
        }
    } catch (Throwable) {
        $stats['writable'] = false;
    }

    return $stats;
}


function admin_system_format_bytes(
    int $bytes
): string {
    $bytes =
        max(
            0,
            $bytes
        );

    if ($bytes < 1024) {
        return
            $bytes .
            ' B';
    }

    $units = [
        'KB',
        'MB',
        'GB',
        'TB',
    ];

    $value =
        $bytes /
        1024;

    foreach ($units as $unit) {
        if (
            $value < 1024
            || $unit === 'TB'
        ) {
            return
                number_format(
                    $value,
                    $value >= 10
                        ? 1
                        : 2
                ) .
                ' ' .
                $unit;
        }

        $value /=
            1024;
    }

    return
        $bytes .
        ' B';
}


function admin_system_health(
    PDO $db
): array {
    $cards = [];

    try {
        $databaseVersion =
            (string)
            $db->query(
                'SELECT VERSION()'
            )->fetchColumn();

        $cards[] =
            admin_system_health_card(
                'database',
                'Database',
                'good',
                'Connected',
                $databaseVersion !== ''
                    ? 'MariaDB/MySQL ' . $databaseVersion
                    : 'Database connection is responding.',
                'fa-database'
            );
    } catch (Throwable $exception) {
        $cards[] =
            admin_system_health_card(
                'database',
                'Database',
                'down',
                'Unavailable',
                'The database health query failed.',
                'fa-database'
            );
    }

    $cards[] =
        admin_system_health_card(
            'php',
            'PHP',
            'good',
            PHP_VERSION,
            'Application runtime is available.',
            'fa-code'
        );

    $projectRoot =
        dirname(__DIR__);

    $privateRoot =
        dirname(
            $projectRoot
        ) .
        '/private';

    $configPath =
        $privateRoot .
        '/config.php';

    $cards[] =
        admin_system_health_card(
            'private_config',
            'Private configuration',
            is_readable($configPath)
                ? 'good'
                : 'down',
            is_readable($configPath)
                ? 'Readable'
                : 'Missing',
            is_readable($configPath)
                ? 'Private application configuration is available outside public_html.'
                : 'Required private application configuration cannot be read.',
            'fa-shield-halved'
        );

    $config = [];

    try {
        $config =
            llama_config();
    } catch (Throwable) {
        $config = [];
    }

    $turnstile =
        is_array(
            $config['turnstile']
            ?? null
        )
            ? $config['turnstile']
            : [];

    $turnstileReady =
        trim(
            (string) (
                $turnstile['site_key']
                ?? ''
            )
        ) !== ''
        &&
        trim(
            (string) (
                $turnstile['secret_key']
                ?? ''
            )
        ) !== '';

    $cards[] =
        admin_system_health_card(
            'turnstile',
            'Cloudflare Turnstile',
            $turnstileReady
                ? 'good'
                : 'down',
            $turnstileReady
                ? 'Configured'
                : 'Missing',
            $turnstileReady
                ? 'Site and secret keys are configured.'
                : 'Login and registration security configuration needs attention.',
            'fa-cloud'
        );

    $stripeConfig =
        $privateRoot .
        '/stripe.php';

    $stripeLibrary =
        $privateRoot .
        '/stripe-php/init.php';

    $stripeReady =
        is_readable(
            $stripeConfig
        )
        &&
        is_readable(
            $stripeLibrary
        );

    $cards[] =
        admin_system_health_card(
            'stripe',
            'Stripe',
            $stripeReady
                ? 'good'
                : 'down',
            $stripeReady
                ? 'Configured'
                : 'Missing',
            $stripeReady
                ? 'Private Stripe configuration and PHP library are available.'
                : 'Membership billing cannot operate until the private Stripe files are available.',
            'fa-credit-card'
        );

    $mailConfig =
        $privateRoot .
        '/mail.php';

    $mailFiles = [
        $privateRoot .
            '/phpmailer/Exception.php',
        $privateRoot .
            '/phpmailer/PHPMailer.php',
        $privateRoot .
            '/phpmailer/SMTP.php',
    ];

    $mailReady =
        is_readable(
            $mailConfig
        );

    foreach (
        $mailFiles
        as
        $mailFile
    ) {
        $mailReady =
            $mailReady
            && is_readable(
                $mailFile
            );
    }

    $cards[] =
        admin_system_health_card(
            'mail',
            'Email',
            $mailReady
                ? 'good'
                : 'down',
            $mailReady
                ? 'Configured'
                : 'Missing',
            $mailReady
                ? 'Mail configuration and PHPMailer are available.'
                : 'Verification and password-reset email cannot operate normally.',
            'fa-envelope'
        );

    $uploadsRoot =
        $projectRoot .
        '/uploads';

    $uploadsStats =
        admin_system_directory_stats(
            $uploadsRoot
        );

    $cards[] =
        admin_system_health_card(
            'uploads',
            'Uploads',
            $uploadsStats['writable']
                ? 'good'
                : 'down',
            $uploadsStats['writable']
                ? 'Writable'
                : 'Not writable',
            $uploadsStats['exists']
                ? number_format(
                    $uploadsStats['files']
                ) .
                ' files, ' .
                admin_system_format_bytes(
                    $uploadsStats['bytes']
                )
                : 'The uploads directory will be created when needed.',
            'fa-folder-open'
        );

    $stagingRoot =
        $uploadsRoot .
        '/staging';

    $stagingStats =
        admin_system_directory_stats(
            $stagingRoot
        );

    $stagingStatus =
        !$stagingStats['writable']
            ? 'down'
            : (
                $stagingStats['stale_files'] > 0
                    ? 'attention'
                    : 'good'
            );

    $stagingValue =
        $stagingStatus === 'down'
            ? 'Not writable'
            : (
                $stagingStats['stale_files'] > 0
                    ? number_format(
                        $stagingStats['stale_files']
                    ) .
                    ' stale'
                    : 'Clean'
            );

    $cards[] =
        admin_system_health_card(
            'photo_staging',
            'Photo staging',
            $stagingStatus,
            $stagingValue,
            number_format(
                $stagingStats['files']
            ) .
            ' staged files, ' .
            admin_system_format_bytes(
                $stagingStats['bytes']
            ) .
            '. ' .
            (
                $stagingStats['stale_files'] > 0
                    ? admin_system_format_bytes(
                        $stagingStats['stale_bytes']
                    ) .
                    ' is older than 24 hours.'
                    : 'No abandoned files are currently due for cleanup.'
            ),
            'fa-images'
        );

    try {
        $auditCount =
            (int)
            $db->query(
                'SELECT COUNT(*)
                 FROM admin_audit_log'
            )->fetchColumn();

        $lastAudit =
            (string) (
                $db->query(
                    'SELECT created_at
                     FROM admin_audit_log
                     ORDER BY id DESC
                     LIMIT 1'
                )->fetchColumn()
                ?: ''
            );

        $cards[] =
            admin_system_health_card(
                'audit',
                'Audit logging',
                'good',
                number_format(
                    $auditCount
                ) .
                ' records',
                $lastAudit !== ''
                    ? 'Last record: ' .
                        $lastAudit
                    : 'Audit table is available and ready.',
                'fa-clipboard-list'
            );
    } catch (Throwable) {
        $cards[] =
            admin_system_health_card(
                'audit',
                'Audit logging',
                'down',
                'Unavailable',
                'The administrative audit log could not be read.',
                'fa-clipboard-list'
            );
    }

    try {
        $stmt =
            $db->prepare(
                'SELECT last_run_at
                 FROM app_maintenance
                 WHERE maintenance_key = ?
                 LIMIT 1'
            );

        $stmt->execute([
            'scout_renewals',
        ]);

        $lastRun =
            $stmt->fetchColumn();

        if ($lastRun === false) {
            $cards[] =
                admin_system_health_card(
                    'scout_maintenance',
                    'Scout maintenance',
                    'attention',
                    'No run recorded',
                    'Scout renewal maintenance has not recorded a successful run yet.',
                    'fa-binoculars'
                );
        } else {
            $lastTimestamp =
                strtotime(
                    (string) $lastRun
                );

            $age =
                $lastTimestamp
                    ? time() -
                        $lastTimestamp
                    : PHP_INT_MAX;

            $status =
                $age <= 172800
                    ? 'good'
                    : 'attention';

            $cards[] =
                admin_system_health_card(
                    'scout_maintenance',
                    'Scout maintenance',
                    $status,
                    $status === 'good'
                        ? 'Current'
                        : 'Stale',
                    'Last recorded run: ' .
                    (string) $lastRun,
                    'fa-binoculars'
                );
        }
    } catch (Throwable) {
        $cards[] =
            admin_system_health_card(
                'scout_maintenance',
                'Scout maintenance',
                'down',
                'Unavailable',
                'Scout maintenance status could not be read.',
                'fa-binoculars'
            );
    }

    $summary = [
        'good' => 0,
        'attention' => 0,
        'down' => 0,
    ];

    foreach ($cards as $card) {
        $summary[
            $card['status']
        ]++;
    }

    return [
        'cards' => $cards,
        'summary' => $summary,
        'staging' => $stagingStats,
    ];
}


function admin_system_maintenance_history(
    PDO $db,
    int $limit = 10
): array {
    $limit =
        max(
            1,
            min(
                25,
                $limit
            )
        );

    return
        $db->query(
            'SELECT
                aal.*,
                COALESCE(
                    NULLIF(u.display_name, ""),
                    NULLIF(u.username, ""),
                    "System"
                ) AS actor_name
             FROM admin_audit_log aal
             LEFT JOIN users u
                ON u.id = aal.actor_user_id
             WHERE aal.action IN (
                "system.maintenance_enabled",
                "system.maintenance_disabled",
                "system.maintenance_updated"
             )
             ORDER BY
                aal.created_at DESC,
                aal.id DESC
             LIMIT ' .
             $limit
        )->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];
}


function admin_system_cleanup_staging(
    PDO $db,
    int $actorUserId,
    int $olderThanSeconds = 86400
): array {
    if (
        !admin_users_current_is_owner(
            $db,
            $actorUserId
        )
    ) {
        throw new RuntimeException(
            'Only an Owner can run system cleanup.'
        );
    }

    $root =
        dirname(__DIR__) .
        '/uploads/staging';

    $before =
        admin_system_directory_stats(
            $root,
            $olderThanSeconds
        );

    if (
        !$before['writable']
    ) {
        throw new RuntimeException(
            'Photo staging is not writable.'
        );
    }

    $deletedFiles = 0;
    $deletedBytes = 0;

    if (is_dir($root)) {
        $threshold =
            time() -
            $olderThanSeconds;

        $iterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $root,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::CHILD_FIRST
            );

        foreach ($iterator as $item) {
            $path =
                $item->getPathname();

            if ($item->isFile()) {
                if (
                    $item->getMTime()
                    >= $threshold
                ) {
                    continue;
                }

                $size =
                    max(
                        0,
                        (int) $item->getSize()
                    );

                if (@unlink($path)) {
                    $deletedFiles++;
                    $deletedBytes +=
                        $size;
                }

                continue;
            }

            $contents =
                @scandir(
                    $path
                );

            if (
                is_array($contents)
                && count($contents) === 2
            ) {
                @rmdir(
                    $path
                );
            }
        }
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'system.photo_staging_cleanup',
        'Ran abandoned photo staging cleanup.',
        [
            'deleted_files' =>
                $deletedFiles,
            'deleted_bytes' =>
                $deletedBytes,
            'stale_files_before' =>
                $before['stale_files'],
            'stale_bytes_before' =>
                $before['stale_bytes'],
            'older_than_seconds' =>
                $olderThanSeconds,
        ]
    );

    return [
        'deleted_files' =>
            $deletedFiles,

        'deleted_bytes' =>
            $deletedBytes,
    ];
}


function admin_system_last_scout_maintenance(
    PDO $db
): ?string {
    try {
        $stmt = $db->prepare(
            'SELECT last_run_at
             FROM app_maintenance
             WHERE maintenance_key = ?
             LIMIT 1'
        );

        $stmt->execute(['scout_renewals']);
        $value = $stmt->fetchColumn();

        return $value === false
            ? null
            : (string) $value;
    } catch (Throwable) {
        return null;
    }
}
