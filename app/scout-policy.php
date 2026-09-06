<?php

declare(strict_types=1);

function llama_scout_policy_table_exists(PDO $db): bool
{
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
         LIMIT 1'
    );

    $stmt->execute([
        'scout_policy',
    ]);

    return $stmt->fetchColumn() !== false;
}

function llama_require_scout_policy_table(PDO $db): void
{
    if (
        llama_scout_policy_table_exists($db)
    ) {
        return;
    }

    /*
     * Schema creation belongs in an explicit one-time SQL migration.
     *
     * Do not CREATE or ALTER tables from normal application requests.
     * MariaDB/MySQL DDL can implicitly commit an active transaction.
     */
    throw new RuntimeException(
        'Scout policy storage is not initialized. Run the Scout policy database migration.'
    );
}

function llama_scout_policy_value(
    PDO $db,
    string $key
): mixed {
    llama_require_scout_policy_table($db);

    $stmt = $db->prepare(
        'SELECT policy_value, value_type
         FROM scout_policy
         WHERE policy_key = ?
         LIMIT 1'
    );

    $stmt->execute([
        $key,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException(
            'Scout policy setting "' . $key . '" is not configured.'
        );
    }

    return match ((string) $row['value_type']) {
        'int' => (int) $row['policy_value'],

        'float' => (float) $row['policy_value'],

        'bool' => in_array(
            strtolower(
                (string) $row['policy_value']
            ),
            [
                '1',
                'true',
                'yes',
                'on',
            ],
            true
        ),

        default =>
            (string) $row['policy_value'],
    };
}

function llama_scout_policy_int(
    PDO $db,
    string $key
): int {
    return (int) llama_scout_policy_value(
        $db,
        $key
    );
}

function llama_scout_policy_bool(
    PDO $db,
    string $key
): bool {
    return (bool) llama_scout_policy_value(
        $db,
        $key
    );
}

function llama_policy_add_months(
    string $dateTime,
    int $months
): string {
    $date =
        new DateTimeImmutable(
            $dateTime
        );

    return $date
        ->modify(
            '+' .
            max(
                0,
                $months
            ) .
            ' months'
        )
        ->format(
            'Y-m-d H:i:s'
        );
}

function llama_policy_subtract_months(
    string $dateTime,
    int $months
): string {
    $date =
        new DateTimeImmutable(
            $dateTime
        );

    return $date
        ->modify(
            '-' .
            max(
                0,
                $months
            ) .
            ' months'
        )
        ->format(
            'Y-m-d H:i:s'
        );
}
