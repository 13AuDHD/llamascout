<?php

declare(strict_types=1);

function llama_ensure_scout_policy_table(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS scout_policy (
            policy_key varchar(100) NOT NULL,
            policy_value varchar(255) NOT NULL,
            value_type enum('int','float','bool','string')
                NOT NULL DEFAULT 'string',
            description varchar(500) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT current_timestamp(),
            updated_at datetime NOT NULL DEFAULT current_timestamp()
                ON UPDATE current_timestamp(),
            PRIMARY KEY (policy_key)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );
}

function llama_scout_policy_value(
    PDO $db,
    string $key
): mixed {
    llama_ensure_scout_policy_table($db);

    $stmt = $db->prepare(
        'SELECT policy_value, value_type
         FROM scout_policy
         WHERE policy_key = ?
         LIMIT 1'
    );
    $stmt->execute([$key]);
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
            strtolower((string) $row['policy_value']),
            ['1','true','yes','on'],
            true
        ),
        default => (string) $row['policy_value'],
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
    $date = new DateTimeImmutable($dateTime);
    return $date
        ->modify('+' . max(0, $months) . ' months')
        ->format('Y-m-d H:i:s');
}

function llama_policy_subtract_months(
    string $dateTime,
    int $months
): string {
    $date = new DateTimeImmutable($dateTime);
    return $date
        ->modify('-' . max(0, $months) . ' months')
        ->format('Y-m-d H:i:s');
}
