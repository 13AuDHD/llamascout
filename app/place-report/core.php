<?php

declare(strict_types=1);

/* Core tokens and configurable form requirements. */

function llama_place_report_unknown_token(): string
{
    return '__LLAMA_UNKNOWN__';
}

function llama_place_report_unanswered_token(): string
{
    return '__LLAMA_UNANSWERED__';
}

function llama_place_report_requirement_int(
    string $settingKey,
    int $default,
    int $minimum = 0,
    int $maximum = 10000
): int {
    static $cache = [];

    $minimum = max(0, $minimum);
    $maximum = max($minimum, $maximum);
    $default = max($minimum, min($maximum, $default));

    if (array_key_exists($settingKey, $cache)) {
        return $cache[$settingKey];
    }

    $value = null;

    /*
     * place-report.php is also loaded by CLI/tests before the full
     * application bootstrap in some workflows. In those contexts,
     * retain the canonical default instead of requiring a database.
     */
    if (function_exists('db')) {
        try {
            $statement = db()->prepare(
                'SELECT setting_value
                 FROM site_settings
                 WHERE setting_key = ?
                 LIMIT 1'
            );

            $statement->execute([$settingKey]);

            $stored = $statement->fetchColumn();

            if (
                $stored !== false
                && is_numeric($stored)
            ) {
                $value = (int) $stored;
            }
        } catch (Throwable) {
            $value = null;
        }
    }

    if ($value === null) {
        $value = $default;
    }

    $cache[$settingKey] =
        max(
            $minimum,
            min(
                $maximum,
                $value
            )
        );

    return $cache[$settingKey];
}

function llama_place_report_form_requirements(): array
{
    return [
        'description_min_characters' =>
            llama_place_report_requirement_int(
                'place_report_description_min_characters',
                1500
            ),

        'access_summary_min_characters' =>
            llama_place_report_requirement_int(
                'place_report_access_summary_min_characters',
                1500
            ),

        'sensory_summary_min_characters' =>
            llama_place_report_requirement_int(
                'place_report_sensory_summary_min_characters',
                1500
            ),
    ];
}

