<?php

declare(strict_types=1);


/**
 * Flatten published Scout Report data to field-key values while preserving
 * explicit Unknown answers. This is the input shape used by scoped answers.
 */
function llama_scoped_report_values_from_data(array $data): array
{
    $values = [];

    foreach (
        llama_place_report_fields()
        as $fieldKey => $field
    ) {
        $fieldKey =
            (string) $fieldKey;

        $state =
            llama_place_report_answer_state(
                $data,
                $fieldKey
            );

        if ($state === 'unknown') {
            $values[$fieldKey] =
                llama_place_report_unknown_token();

            continue;
        }

        if ($state !== 'answered') {
            continue;
        }

        $values[$fieldKey] =
            llama_place_report_form_value_from_data(
                $data,
                $fieldKey
            );
    }

    return $values;
}

/**
 * Build nested Scout Report data from a flat effective answer set.
 *
 * Scoped answers are stored by field key, while the shared warning engine
 * reads the normal nested Scout Report structure. This adapter is the only
 * bridge needed between those two representations; warning rules themselves
 * remain in llama_place_report_quick_warnings().
 */
function llama_scoped_report_data_from_values(array $values): array
{
    $definitions =
        llama_place_report_fields();

    $data = [
        'details' => [],
        'amenities' => [],
        'connectivity' => [],
        'sensory' => [
            'daytime' => [],
            'nighttime' => [],
            'details' => [],
        ],
        'rules' => [],
        'experience' => [],
        'photos' => [],
    ];

    $unknownFields = [];
    $unknownToken =
        llama_place_report_unknown_token();

    foreach (
        $values
        as $fieldKey => $value
    ) {
        $fieldKey =
            (string) $fieldKey;

        $field =
            $definitions[$fieldKey]
            ?? null;

        if (!is_array($field)) {
            continue;
        }

        if ($value === $unknownToken) {
            $unknownFields[] =
                $fieldKey;

            continue;
        }

        llama_place_report_set_path(
            $data,
            (string) (
                $field['storage']
                ?? ''
            ),
            $value
        );
    }

    $data['_answer_state'] =
        array_values(
            array_unique(
                $unknownFields
            )
        );

    return $data;
}

/**
 * Run the shared Quick-warning rules against effective Place/Area/Site values.
 */
function llama_scoped_report_quick_warnings(array $values): array
{
    return llama_place_report_quick_warnings(
        llama_scoped_report_data_from_values(
            $values
        )
    );
}
