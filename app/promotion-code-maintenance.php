<?php

declare(strict_types=1);

require_once __DIR__ . '/promotion-codes.php';


function llama_ensure_promotion_code_maintenance_storage(
    PDO $db
): void {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );

    $stmt->execute([
        'app_maintenance',
    ]);

    if (!$stmt->fetchColumn()) {
        throw new RuntimeException(
            'Promotion code maintenance storage is not initialized. Missing table: app_maintenance'
        );
    }
}


function llama_promotion_code_maintenance_is_due(
    PDO $db,
    int $intervalSeconds = 300
): bool {
    $intervalSeconds = max(
        60,
        $intervalSeconds
    );

    llama_ensure_promotion_code_maintenance_storage(
        $db
    );

    $stmt = $db->prepare(
        'SELECT last_run_at
         FROM app_maintenance
         WHERE maintenance_key = ?
         LIMIT 1'
    );

    $stmt->execute([
        'promotion_code_sync',
    ]);

    $lastRun = $stmt->fetchColumn();

    if (!$lastRun) {
        return true;
    }

    try {
        $timestamp =
            (
                new DateTimeImmutable(
                    (string) $lastRun,
                    new DateTimeZone('UTC')
                )
            )->getTimestamp();
    } catch (Throwable) {
        return true;
    }

    return
        (time() - $timestamp)
            >= $intervalSeconds;
}


function llama_mark_promotion_code_maintenance_run(
    PDO $db
): void {
    llama_ensure_promotion_code_maintenance_storage(
        $db
    );

    $stmt = $db->prepare(
        'INSERT INTO app_maintenance
         (
            maintenance_key,
            last_run_at
         )
         VALUES (?, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            last_run_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        'promotion_code_sync',
    ]);
}


function llama_run_promotion_code_maintenance(
    PDO $db,
    int $intervalSeconds = 300
): array {
    $summary = [
        'ran' => false,
        'checked' => 0,
        'changed' => 0,
    ];

    if (
        !llama_promotion_code_maintenance_is_due(
            $db,
            $intervalSeconds
        )
    ) {
        return $summary;
    }

    $lockStmt = $db->query(
        "SELECT GET_LOCK('llamascout_promotion_code_sync', 0)"
    );

    if (
        !$lockStmt
        || (int) $lockStmt->fetchColumn()
            !== 1
    ) {
        return $summary;
    }

    try {
        if (
            !llama_promotion_code_maintenance_is_due(
                $db,
                $intervalSeconds
            )
        ) {
            return $summary;
        }

        $sync =
            llama_sync_membership_promotion_codes(
                $db
            );

        llama_mark_promotion_code_maintenance_run(
            $db
        );

        return [
            'ran' => true,
            'checked' =>
                (int) (
                    $sync['checked']
                    ?? 0
                ),
            'changed' =>
                (int) (
                    $sync['changed']
                    ?? 0
                ),
        ];
    } finally {
        try {
            $db->query(
                "SELECT RELEASE_LOCK('llamascout_promotion_code_sync')"
            );
        } catch (Throwable) {
            // Connection cleanup releases the lock.
        }
    }
}
