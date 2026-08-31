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

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            $enabled
                ? 'system.maintenance_enabled'
                : 'system.maintenance_disabled',
            $enabled
                ? 'Enabled maintenance mode.'
                : 'Disabled maintenance mode.',
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

function admin_system_audit_rows(
    PDO $db,
    int $limit = 200
): array {
    $limit = max(1, min(500, $limit));

    $sql =
        'SELECT
            aal.*,
            COALESCE(
                NULLIF(actor.display_name, ""),
                NULLIF(actor.username, ""),
                "System"
            ) AS actor_name,
            COALESCE(
                NULLIF(target.display_name, ""),
                NULLIF(target.username, ""),
                CASE
                    WHEN aal.target_user_id IS NULL
                        THEN NULL
                    ELSE "Former Llama Scout Member"
                END
            ) AS target_name
         FROM admin_audit_log aal
         LEFT JOIN users actor
            ON actor.id = aal.actor_user_id
         LEFT JOIN users target
            ON target.id = aal.target_user_id
         ORDER BY aal.created_at DESC, aal.id DESC
         LIMIT ' . $limit;

    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
