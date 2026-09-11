<?php

declare(strict_types=1);

require_once __DIR__ . '/place-report.php';


/*
 * =========================================================
 * SHARED PLACE REPORT ↔ PLACE UPDATE BRIDGE
 * =========================================================
 *
 * Suggest an Update now uses the same Place Report controls
 * as Add Place, moderator editing, and admin Place editing.
 *
 * The contributor edits the populated form normally.
 * We translate that complete form back into the existing
 * proposed-change format, and the persistence layer performs
 * the before/after comparison.
 *
 * There is intentionally no user-facing "Change" checkbox.
 */


function llama_place_update_field_by_storage(
    string $storage
): ?array {
    foreach (llama_place_report_fields() as $field) {
        if (
            (string) (
                $field['storage']
                ?? ''
            )
            === $storage
        ) {
            return $field;
        }
    }

    return null;
}


function llama_place_update_definitions(): array
{
    $persistence =
        community_place_update_field_definitions();

    $definitions = [];

    foreach ($persistence as $path => $definition) {
        $shared =
            llama_place_update_field_by_storage(
                (string) $path
            );

        if ($shared) {
            $definitions[$path] =
                array_merge(
                    $definition,
                    [
                        'shared' =>
                            $shared,

                        'label' =>
                            (string) (
                                $shared['label']
                                ?? $definition['label']
                                ?? $path
                            ),

                        'section' =>
                            (string) (
                                $shared['section']
                                ?? 'other'
                            ),
                    ]
                );

            continue;
        }

        $definitions[$path] =
            array_merge(
                $definition,
                [
                    'shared' =>
                        null,

                    'section' =>
                        'other',
                ]
            );
    }

    return $definitions;
}


function llama_place_update_sections(): array
{
    $shared =
        llama_place_report_sections();

    $sections = [];

    foreach (
        llama_place_update_definitions()
        as $path => $definition
    ) {
        $section =
            (string) (
                $definition['section']
                ?? 'other'
            );

        if (!isset($sections[$section])) {
            $sections[$section] =
                $shared[$section]
                ?? [
                    'label' =>
                        (string) (
                            $definition['group']
                            ?? 'Other'
                        ),

                    'description' =>
                        'Fields that can be updated on this Place.',

                    'icon' =>
                        'fa-pen-to-square',
                ];

            $sections[$section]['fields'] = [];
        }

        $sections[$section]['fields'][$path] =
            $definition;
    }

    return $sections;
}


/*
 * Build the populated shared Place Report form.
 *
 * $currentValues uses storage paths such as:
 *   details.vehicle_capacity
 *   sensory.daytime.noise
 *
 * $proposedValues contains an existing Needs Changes proposal
 * and takes precedence over the currently published value.
 */
function llama_place_update_shared_form_values(
    array $currentValues,
    array $unknownFields = [],
    array $proposedValues = []
): array {
    $values = [];

    $unknownLookup = [];

    foreach ($unknownFields as $fieldKey) {
        $fieldKey =
            trim(
                (string) $fieldKey
            );

        if ($fieldKey !== '') {
            $unknownLookup[$fieldKey] = true;
        }
    }

    foreach (
        llama_place_report_fields()
        as $key => $field
    ) {
        $storage =
            (string) (
                $field['storage']
                ?? ''
            );

        if ($storage === '') {
            continue;
        }

        $hasProposed =
            array_key_exists(
                $storage,
                $proposedValues
            );

        $hasCurrent =
            array_key_exists(
                $storage,
                $currentValues
            );

        if (
            !$hasProposed
            && !$hasCurrent
        ) {
            continue;
        }

        $value =
            $hasProposed
                ? $proposedValues[$storage]
                : $currentValues[$storage];

        $type =
            (string) (
                $field['type']
                ?? 'text'
            );

        /*
         * Existing Needs Changes submissions historically represented
         * an explicit Unknown as NULL. For controls that support Unknown,
         * restore that state in the shared form.
         */
        $explicitUnknown =
            $hasProposed
                ? (
                    $value === null
                    && !empty(
                        $field['allow_unknown']
                    )
                )
                : isset(
                    $unknownLookup[$key]
                );

        if ($type === 'checkbox') {
            if ((bool) $value) {
                $values[$key] = '1';
            }

            continue;
        }

        if ($explicitUnknown) {
            $values[$key] =
                llama_place_report_unknown_token();

            continue;
        }

        if (
            in_array(
                $type,
                [
                    'tri',
                    'rating',
                ],
                true
            )
            && $value === null
        ) {
            $values[$key] =
                llama_place_report_unanswered_token();

            continue;
        }

        if ($value === null) {
            $values[$key] = '';
            continue;
        }

        if (is_bool($value)) {
            $values[$key] =
                $value
                    ? '1'
                    : '0';

            continue;
        }

        $values[$key] =
            (string) $value;
    }

    return $values;
}


/*
 * Convert the normal shared Place Report POST back into the
 * existing update-submission format.
 *
 * Every supported field is supplied to the persistence layer.
 * submit_place_update() and community_resubmit_place_update()
 * compare each supplied value to the published value and keep
 * only actual differences.
 *
 * This means the user no longer has to manually identify which
 * fields changed.
 */
function llama_place_update_prepare_shared_input(
    array $input
): array {
    /*
     * Preserve compatibility with an already-normalized legacy
     * payload. This also keeps older callers safe while the UI
     * migration is being completed.
     */
    if (
        is_array(
            $input['change_fields']
            ?? null
        )
        && is_array(
            $input['field_value']
            ?? null
        )
    ) {
        return $input;
    }

    $prepared = $input;

    $changeFields = [];
    $fieldValues = [];

    $unknownToken =
        llama_place_report_unknown_token();

    $unansweredToken =
        llama_place_report_unanswered_token();

    foreach (
        llama_place_update_definitions()
        as $path => $definition
    ) {
        $shared =
            is_array(
                $definition['shared']
                ?? null
            )
                ? $definition['shared']
                : null;

        /*
         * A persistence field that is not represented in the
         * shared Place Report must not be changed just because
         * it is absent from the shared form.
         */
        if (!$shared) {
            continue;
        }

        $key =
            (string) (
                $shared['key']
                ?? ''
            );

        if ($key === '') {
            continue;
        }

        $type =
            (string) (
                $shared['type']
                ?? 'text'
            );

        /*
         * Unchecked HTML checkboxes are absent from POST.
         * In the shared Place Report that means False.
         */
        if ($type === 'checkbox') {
            $raw =
                !empty(
                    $input[$key]
                )
                    ? '1'
                    : '0';
        } else {
            if (
                !array_key_exists(
                    $key,
                    $input
                )
            ) {
                continue;
            }

            $raw = $input[$key];
        }

        if (is_array($raw)) {
            continue;
        }

        /*
         * The existing update persistence layer uses __NULL__
         * for a proposed empty/unknown value.
         */
        if (
            $raw === $unknownToken
            || $raw === $unansweredToken
        ) {
            $raw = '__NULL__';
        } elseif (
            is_string($raw)
            && trim($raw) === ''
        ) {
            $raw = '__NULL__';
        }

        $changeFields[] =
            (string) $path;

        $fieldValues[(string) $path] =
            $raw;
    }

    $prepared['change_fields'] =
        array_values(
            array_unique(
                $changeFields
            )
        );

    $prepared['field_value'] =
        $fieldValues;

    return $prepared;
}


function llama_place_update_decode_json(
    mixed $json,
    array $fallback = []
): array {
    if (is_array($json)) {
        return $json;
    }

    if (
        !is_string($json)
        || trim($json) === ''
    ) {
        return $fallback;
    }

    $decoded =
        json_decode(
            $json,
            true
        );

    return is_array($decoded)
        ? $decoded
        : $fallback;
}


function llama_place_update_fetch_row(
    PDO $db,
    int $updateId
): ?array {
    $stmt =
        $db->prepare(
            'SELECT *
             FROM place_update_submissions
             WHERE id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $updateId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return $row ?: null;
}


function llama_place_update_history(
    array $row
): array {
    return llama_place_update_decode_json(
        $row['revision_history']
        ?? null
    );
}


function llama_place_update_append_history(
    PDO $db,
    int $updateId,
    array $event
): void {
    $row =
        llama_place_update_fetch_row(
            $db,
            $updateId
        );

    if (!$row) {
        throw new RuntimeException(
            'Place update history could not be found.'
        );
    }

    $history =
        llama_place_update_history(
            $row
        );

    $event['at'] =
        $event['at']
        ?? gmdate(
            'Y-m-d H:i:s'
        );

    $history[] =
        $event;

    $stmt =
        $db->prepare(
            'UPDATE place_update_submissions
             SET revision_history = ?
             WHERE id = ?'
        );

    $stmt->execute([
        json_encode(
            $history,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        ),
        $updateId,
    ]);
}


function llama_place_update_value_equal(
    mixed $a,
    mixed $b
): bool {
    if (is_bool($a)) {
        $a =
            $a
                ? '1'
                : '0';
    }

    if (is_bool($b)) {
        $b =
            $b
                ? '1'
                : '0';
    }

    if (
        $a === null
        || $b === null
    ) {
        return $a === $b;
    }

    return (string) $a
        === (string) $b;
}


function llama_place_update_changed_proposals(
    array $before,
    array $after
): array {
    $paths =
        array_unique(
            array_merge(
                array_keys($before),
                array_keys($after)
            )
        );

    $changes = [];

    foreach ($paths as $path) {
        $old =
            $before[$path]
            ?? null;

        $new =
            $after[$path]
            ?? null;

        $hadOld =
            array_key_exists(
                $path,
                $before
            );

        $hasNew =
            array_key_exists(
                $path,
                $after
            );

        if (
            $hadOld === $hasNew
            && llama_place_update_value_equal(
                $old,
                $new
            )
        ) {
            continue;
        }

        $changes[$path] = [
            'before_present' =>
                $hadOld,

            'before' =>
                $old,

            'after_present' =>
                $hasNew,

            'after' =>
                $new,
        ];
    }

    return $changes;
}


function llama_place_update_validate_input(
    array $input
): void {
    $selected =
        is_array(
            $input['change_fields']
            ?? null
        )
            ? array_values(
                array_unique(
                    array_map(
                        'strval',
                        $input['change_fields']
                    )
                )
            )
            : [];

    $values =
        is_array(
            $input['field_value']
            ?? null
        )
            ? $input['field_value']
            : [];

    $definitions =
        llama_place_update_definitions();

    foreach ($selected as $path) {
        if (
            !isset(
                $definitions[$path]
            )
        ) {
            continue;
        }

        if (
            !array_key_exists(
                $path,
                $values
            )
        ) {
            throw new InvalidArgumentException(
                'A value is missing for '
                . (string) (
                    $definitions[$path]['label']
                    ?? $path
                )
                . '.'
            );
        }
    }
}


function llama_place_update_submit(
    int $userId,
    array $place,
    array $input
): int {
    $input =
        llama_place_update_prepare_shared_input(
            $input
        );

    llama_place_update_validate_input(
        $input
    );

    $updateId =
        submit_place_update(
            $userId,
            $place,
            $input
        );

    $db = db();

    $row =
        llama_place_update_fetch_row(
            $db,
            $updateId
        );

    if ($row) {
        llama_place_update_append_history(
            $db,
            $updateId,
            [
                'type' =>
                    'submitted',

                'by' =>
                    'contributor',

                'proposed' =>
                    llama_place_update_decode_json(
                        $row['proposed_changes']
                        ?? '{}'
                    ),

                'original' =>
                    llama_place_update_decode_json(
                        $row['original_values']
                        ?? '{}'
                    ),

                'photo_count' =>
                    count(
                        llama_place_update_decode_json(
                            $row['photos']
                            ?? '[]'
                        )
                    ),

                'visited_at' =>
                    $row['visited_at']
                    ?? null,

                'contributor_notes' =>
                    $row['contributor_notes']
                    ?? null,
            ]
        );
    }

    return $updateId;
}


function llama_place_update_resubmit(
    int $userId,
    array $place,
    int $updateId,
    array $input
): int {
    $input =
        llama_place_update_prepare_shared_input(
            $input
        );

    llama_place_update_validate_input(
        $input
    );

    $db = db();

    $before =
        llama_place_update_fetch_row(
            $db,
            $updateId
        );

    if (!$before) {
        throw new RuntimeException(
            'This Place update could not be found.'
        );
    }

    $beforeProposed =
        llama_place_update_decode_json(
            $before['proposed_changes']
            ?? '{}'
        );

    $beforePhotos =
        llama_place_update_decode_json(
            $before['photos']
            ?? '[]'
        );

    $reviewRequest =
        trim(
            (string) (
                $before['review_notes']
                ?? ''
            )
        );

    $result =
        community_resubmit_place_update(
            $userId,
            $place,
            $updateId,
            $input
        );

    $after =
        llama_place_update_fetch_row(
            $db,
            $updateId
        );

    if ($after) {
        $afterProposed =
            llama_place_update_decode_json(
                $after['proposed_changes']
                ?? '{}'
            );

        $afterPhotos =
            llama_place_update_decode_json(
                $after['photos']
                ?? '[]'
            );

        llama_place_update_append_history(
            $db,
            $updateId,
            [
                'type' =>
                    'resubmitted',

                'by' =>
                    'contributor',

                'requested_changes' =>
                    $reviewRequest !== ''
                        ? $reviewRequest
                        : null,

                'proposal_changes' =>
                    llama_place_update_changed_proposals(
                        $beforeProposed,
                        $afterProposed
                    ),

                'proposed' =>
                    $afterProposed,

                'photo_count_before' =>
                    count(
                        $beforePhotos
                    ),

                'photo_count_after' =>
                    count(
                        $afterPhotos
                    ),

                'visited_at' =>
                    $after['visited_at']
                    ?? null,

                'contributor_notes' =>
                    $after['contributor_notes']
                    ?? null,
            ]
        );
    }

    return $result;
}


function llama_place_update_record_review(
    PDO $db,
    int $updateId,
    int $moderatorId,
    string $type,
    string $notes,
    array $item
): void {
    llama_place_update_append_history(
        $db,
        $updateId,
        [
            'type' =>
                $type,

            'by' =>
                'moderator',

            'moderator_id' =>
                $moderatorId,

            'review_notes' =>
                $notes,

            'proposed' =>
                is_array(
                    $item['proposed']
                    ?? null
                )
                    ? $item['proposed']
                    : [],

            'photo_count' =>
                count(
                    is_array(
                        $item['photo_list']
                        ?? null
                    )
                        ? $item['photo_list']
                        : []
                ),
        ]
    );
}


function llama_place_update_display_value(
    string $path,
    mixed $value,
    bool $explicitUnknown = false
): string {
    if ($explicitUnknown) {
        return 'Unknown';
    }

    if (
        $value === null
        || $value === ''
    ) {
        return 'Not provided';
    }

    $field =
        llama_place_update_field_by_storage(
            $path
        );

    if (!$field) {
        if (is_bool($value)) {
            return $value
                ? 'Yes'
                : 'No';
        }

        return (string) $value;
    }

    $type =
        (string) (
            $field['type']
            ?? ''
        );

    if ($type === 'tri') {
        return (bool) $value
            ? 'Yes'
            : 'No';
    }

    if ($type === 'rating') {
        return (int) $value
            . '/5';
    }

    if ($type === 'checkbox') {
        return (bool) $value
            ? 'Yes'
            : 'No';
    }

    if ($type === 'select') {
        $options =
            (array) (
                $field['options']
                ?? []
            );

        if (
            array_key_exists(
                (string) $value,
                $options
            )
        ) {
            return (string) $options[
                (string) $value
            ];
        }
    }

    if (
        ($field['format'] ?? '')
        === 'currency'
    ) {
        return '$'
            . number_format(
                (float) $value,
                2
            );
    }

    return (string) $value;
}


function llama_place_update_schema_icon(
    string $path
): string {
    $field =
        llama_place_update_field_by_storage(
            $path
        );

    if (!$field) {
        return 'fa-pen-to-square';
    }

    if (
        function_exists(
            'llama_place_report_field_icon'
        )
    ) {
        return llama_place_report_field_icon(
            (string) $field['key'],
            $field
        );
    }

    return match (
        (string) (
            $field['section']
            ?? ''
        )
    ) {
        'location' =>
            'fa-location-dot',

        'site_vehicle' =>
            'fa-car-side',

        'road_access' =>
            'fa-road',

        'amenities' =>
            'fa-circle-info',

        'connectivity' =>
            'fa-signal',

        'sensory' =>
            'fa-brain',

        'rules' =>
            'fa-cloud-sun',

        default =>
            'fa-pen-to-square',
    };
}
