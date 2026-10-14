<?php

declare(strict_types=1);

/**
 * Phase 3D: scoped-answer persistence service.
 *
 * This service deliberately does not alter the legacy Place Report JSON,
 * scoring, moderation, or public display. Its SQL table must be installed
 * before using it. Callers are responsible for authentication, authorization,
 * CSRF and verifying the selected Area/Site belongs to the Place.
 */
function llama_scoped_report_validate_target(string $scope, int $scopeId): void
{
    if (!in_array($scope, ['area', 'site'], true) || $scopeId < 1) {
        throw new InvalidArgumentException('A valid Area or Site ID is required.');
    }
}

/**
 * A target may be a drawn map feature or an imported canonical campsite.
 * Explicit source identity prevents collisions between their ID sequences.
 */
function llama_scoped_report_validate_source(string $scope, string $source): void
{
    $allowed = $scope === 'area'
        ? ['map_feature']
        : ['map_feature', 'campsite_record'];

    if (!in_array($source, $allowed, true)) {
        throw new InvalidArgumentException('Invalid reporting target source.');
    }
}

function llama_scoped_report_field(string $key, string $scope): array
{
    $field = llama_place_report_fields()[$key] ?? null;

    if (
        !is_array($field)
        || !in_array(
            $scope,
            (array) ($field['report_scopes'] ?? []),
            true
        )
    ) {
        throw new InvalidArgumentException(
            'This question is not available at the selected reporting level.'
        );
    }

    if (
        !empty($field['derived'])
        || !empty($field['location_field'])
    ) {
        throw new InvalidArgumentException(
            'Derived or location fields cannot be overridden.'
        );
    }

    return $field;
}

/**
 * Caller must validate ownership of scopeId and the authenticated Scout's
 * ability to edit placeId. This is a storage service, not a public endpoint.
 * Null, false, zero, empty lists and Unknown are stored as explicit answers.
 */
function llama_scoped_report_save(
    PDO $db,
    int $placeId,
    string $scope,
    int $scopeId,
    string $source,
    string $fieldKey,
    mixed $value
): void {
    if ($placeId < 1) {
        throw new InvalidArgumentException('Invalid Place ID.');
    }

    llama_scoped_report_validate_target(
        $scope,
        $scopeId
    );

    llama_scoped_report_validate_source(
        $scope,
        $source
    );

    llama_scoped_report_field(
        $fieldKey,
        $scope
    );

    $json =
        json_encode(
            $value,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
        );

    $stmt =
        $db->prepare(
            'INSERT INTO place_report_scoped_answers
                (
                    place_id,
                    scope_type,
                    scope_source,
                    scope_id,
                    field_key,
                    value_json
                )
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                value_json = VALUES(value_json),
                updated_at = CURRENT_TIMESTAMP'
        );

    $stmt->execute([
        $placeId,
        $scope,
        $source,
        $scopeId,
        $fieldKey,
        $json,
    ]);
}

/** @return array<string, mixed> Canonical field keys mapped to explicit values. */
function llama_scoped_report_load(
    PDO $db,
    int $placeId,
    string $scope,
    int $scopeId,
    string $source
): array {
    if ($placeId < 1) {
        throw new InvalidArgumentException('Invalid Place ID.');
    }

    llama_scoped_report_validate_target(
        $scope,
        $scopeId
    );

    llama_scoped_report_validate_source(
        $scope,
        $source
    );

    $stmt =
        $db->prepare(
            'SELECT
                field_key,
                value_json
             FROM place_report_scoped_answers
             WHERE place_id = ?
               AND scope_type = ?
               AND scope_source = ?
               AND scope_id = ?'
        );

    $stmt->execute([
        $placeId,
        $scope,
        $source,
        $scopeId,
    ]);

    $answers = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        as $row
    ) {
        $answers[
            (string) $row['field_key']
        ] =
            json_decode(
                (string) $row['value_json'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
    }

    return $answers;
}

/** Explicitly remove a scoped override so the field inherits again. */
function llama_scoped_report_clear_override(
    PDO $db,
    int $placeId,
    string $scope,
    int $scopeId,
    string $source,
    string $fieldKey
): void {
    if ($placeId < 1) {
        throw new InvalidArgumentException('Invalid Place ID.');
    }

    llama_scoped_report_validate_target(
        $scope,
        $scopeId
    );

    llama_scoped_report_validate_source(
        $scope,
        $source
    );

    llama_scoped_report_field(
        $fieldKey,
        $scope
    );

    $stmt =
        $db->prepare(
            'DELETE FROM place_report_scoped_answers
             WHERE place_id = ?
               AND scope_type = ?
               AND scope_source = ?
               AND scope_id = ?
               AND field_key = ?'
        );

    $stmt->execute([
        $placeId,
        $scope,
        $source,
        $scopeId,
        $fieldKey,
    ]);
}


/**
 * Normalize decoded JSON only for equality comparison.
 *
 * Lists keep their order. Associative arrays are key-sorted recursively so
 * semantically identical JSON objects do not become false conflicts because
 * their keys were encoded in a different order.
 */
function llama_scoped_report_comparable_value(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    if (array_is_list($value)) {
        return array_map(
            'llama_scoped_report_comparable_value',
            $value
        );
    }

    ksort($value);

    foreach ($value as $key => $item) {
        $value[$key] =
            llama_scoped_report_comparable_value(
                $item
            );
    }

    return $value;
}

function llama_scoped_report_values_equal(
    mixed $left,
    mixed $right
): bool {
    return
        json_encode(
            llama_scoped_report_comparable_value(
                $left
            ),
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
        )
        ===
        json_encode(
            llama_scoped_report_comparable_value(
                $right
            ),
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
        );
}


/**
 * Merge a map-only Site identity into its canonical campsite identity.
 *
 * This is intentionally conflict-safe:
 * - answer exists only on map_feature -> migrate it
 * - same answer exists on both identities -> dedupe it
 * - different answers exist for the same field -> abort with no writes
 *
 * The caller should include this in the same transaction that changes the
 * canonical campsite's map_feature_id. This function performs no commits.
 *
 * @return array{migrated:int,deduped:int}
 */
function llama_scoped_report_merge_site_identity(
    PDO $db,
    int $placeId,
    int $mapFeatureId,
    int $campsiteId
): array {
    if (
        $placeId < 1
        || $mapFeatureId < 1
        || $campsiteId < 1
    ) {
        throw new InvalidArgumentException(
            'A valid Place, mapped Site, and campsite are required.'
        );
    }

    $loadRows =
        static function (
            PDO $db,
            int $placeId,
            string $source,
            int $scopeId
        ): array {
            $stmt =
                $db->prepare(
                    'SELECT
                        field_key,
                        value_json
                     FROM place_report_scoped_answers
                     WHERE place_id = ?
                       AND scope_type = "site"
                       AND scope_source = ?
                       AND scope_id = ?
                     FOR UPDATE'
                );

            $stmt->execute([
                $placeId,
                $source,
                $scopeId,
            ]);

            $rows = [];

            foreach (
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: []
                as $row
            ) {
                $fieldKey =
                    (string) (
                        $row['field_key']
                        ?? ''
                    );

                if ($fieldKey === '') {
                    continue;
                }

                $json =
                    (string) (
                        $row['value_json']
                        ?? 'null'
                    );

                $rows[$fieldKey] = [
                    'json' =>
                        $json,

                    'value' =>
                        json_decode(
                            $json,
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        ),
                ];
            }

            return $rows;
        };

    $mapAnswers =
        $loadRows(
            $db,
            $placeId,
            'map_feature',
            $mapFeatureId
        );

    if (!$mapAnswers) {
        return [
            'migrated' => 0,
            'deduped' => 0,
        ];
    }

    $canonicalAnswers =
        $loadRows(
            $db,
            $placeId,
            'campsite_record',
            $campsiteId
        );

    $conflicts = [];

    foreach ($mapAnswers as $fieldKey => $mapAnswer) {
        if (
            !isset(
                $canonicalAnswers[
                    $fieldKey
                ]
            )
        ) {
            continue;
        }

        if (
            llama_scoped_report_values_equal(
                $mapAnswer['value'],
                $canonicalAnswers[
                    $fieldKey
                ]['value']
            )
        ) {
            continue;
        }

        $conflicts[] =
            ucwords(
                str_replace(
                    '_',
                    ' ',
                    $fieldKey
                )
            );
    }

    if ($conflicts) {
        sort(
            $conflicts,
            SORT_NATURAL
            | SORT_FLAG_CASE
        );

        throw new InvalidArgumentException(
            'This mapped Site and campsite have conflicting Scout Report answers for: '
            . implode(
                ', ',
                $conflicts
            )
            . '. Resolve those answers before linking them.'
        );
    }

    $migrated = 0;
    $deduped = 0;

    $insert =
        $db->prepare(
            'INSERT INTO place_report_scoped_answers
                (
                    place_id,
                    scope_type,
                    scope_source,
                    scope_id,
                    field_key,
                    value_json
                )
             VALUES (?, "site", "campsite_record", ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                value_json = VALUES(value_json),
                updated_at = CURRENT_TIMESTAMP'
        );

    foreach ($mapAnswers as $fieldKey => $mapAnswer) {
        if (
            isset(
                $canonicalAnswers[
                    $fieldKey
                ]
            )
        ) {
            $deduped++;
            continue;
        }

        $insert->execute([
            $placeId,
            $campsiteId,
            $fieldKey,
            $mapAnswer['json'],
        ]);

        $migrated++;
    }

    $delete =
        $db->prepare(
            'DELETE FROM place_report_scoped_answers
             WHERE place_id = ?
               AND scope_type = "site"
               AND scope_source = "map_feature"
               AND scope_id = ?'
        );

    $delete->execute([
        $placeId,
        $mapFeatureId,
    ]);

    return [
        'migrated' =>
            $migrated,

        'deduped' =>
            $deduped,
    ];
}
