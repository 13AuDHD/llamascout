<?php

declare(strict_types=1);

/**
 * Canonical Area/Site storage. Endpoint authentication and CSRF remain with
 * callers; writes enforce target ownership, answer parsing and a transaction.
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
    if (!$db->inTransaction()) { throw new RuntimeException('Scoped answer writes require an active transaction.'); }
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

    $field = llama_scoped_report_field($fieldKey, $scope);
    $rawValue = $value;
    $value = llama_place_report_scoped_value($field, $value);
    llama_scoped_report_assert_owner($db, $placeId, $scope, $scopeId, $source);

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

    // Mirror approved/current Area and Site answers using the same canonical
    // parser. This stays in the caller's transaction with the legacy write.
    if (llama_report_current_store_available($db)) {
        llama_report_current_store_save(
            $db, $placeId, $scope, $source, $scopeId, $fieldKey, $rawValue
        );
    }
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
               AND scope_id = ?' . ($db->inTransaction() ? ' FOR UPDATE' : '')
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


/**
 * Resolve the effective Place Report answer set for an Area or Site.
 *
 * Precedence is deterministic:
 * Place -> parent Area -> selected Site.
 *
 * The caller supplies canonical Place Report values keyed by field key. Scoped
 * layers are loaded from place_report_scoped_answers. Stale scoped rows whose
 * field definitions no longer permit that reporting level are ignored rather
 * than exposed publicly.
 *
 * @return array{
 *     values:array<string,mixed>,
 *     provenance:array<string,array{scope:string,source:string,id:int}>,
 *     direct:array<string,mixed>,
 *     area:array<string,mixed>,
 *     site:array<string,mixed>
 * }
 */
function llama_scoped_report_effective_answers(PDO $db, int $placeId, array $placeValues,
    string $scope, int $scopeId, string $source, ?int $parentAreaId = null): array
{
    $target = ['scope' => $scope, 'source' => $source, 'id' => $scopeId, 'parent_area_id' => $parentAreaId];
    $resolved = llama_scoped_report_effective_answers_for_targets($db, $placeId, $placeValues, [$target]);
    return $resolved[$scope . ':' . $source . ':' . $scopeId];
}


/**
 * Resolve multiple Area/Site targets with one scoped-answer read.
 *
 * This is the public-rendering path for large campgrounds. It avoids one or
 * two database queries per Site while preserving the same Place -> Area ->
 * Site precedence used by llama_scoped_report_effective_answers().
 *
 * @param array<int,array<string,mixed>> $targets
 * @return array<string,array<string,mixed>>
 */
function llama_scoped_report_effective_answers_for_targets(PDO $db, int $placeId,
    array $placeValues, array $targets): array
{
    if ($placeId < 1) { throw new InvalidArgumentException('Invalid Place ID.'); }
    if (!$targets) { return []; }
    $statement = $db->prepare('SELECT scope_type, scope_source, scope_id, field_key, value_json
        FROM place_report_scoped_answers WHERE place_id = ?');
    $statement->execute([$placeId]);
    $layers = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $layers[$row['scope_type']][$row['scope_source']][(int) $row['scope_id']][$row['field_key']]
            = json_decode($row['value_json'], true, 512, JSON_THROW_ON_ERROR);
    }
    // V3 current rows take precedence over older scoped records, while
    // targets without migrated answers retain their existing scoped values.
    // Load once per Place, not once for each Site in a large campground.
    if (llama_report_current_store_available($db)) {
        $currentQuery = $db->prepare(
            'SELECT target_scope, target_source, target_id, question_key,
                    answer_state, value_json
             FROM place_report_current_answers
             WHERE place_id = ? AND target_scope IN (\'area\', \'site\')'
        );
        $currentQuery->execute([$placeId]);
        foreach ($currentQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $owner = (string) $row['target_scope'];
            $sourceKey = (string) $row['target_source'];
            $targetKey = (int) $row['target_id'];
            $questionKey = (string) $row['question_key'];
            $state = (string) $row['answer_state'];
            if (!in_array($state, ['answered', 'unknown'], true)) {
                throw new UnexpectedValueException('Invalid current-answer state.');
            }
            $layers[$owner][$sourceKey][$targetKey][$questionKey] =
                $state === 'unknown'
                    ? llama_place_report_unknown_token()
                    : json_decode((string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR);
        }
    }
    $fields = llama_place_report_fields();
    $results = [];
    foreach ($targets as $target) {
        $scope = (string) $target['scope'];
        $source = (string) $target['source'];
        $id = (int) $target['id'];
        llama_scoped_report_validate_target($scope, $id);
        llama_scoped_report_validate_source($scope, $source);
        $parentAreaId = $scope === 'site' ? (int) ($target['parent_area_id'] ?? 0) : $id;
        $area = $parentAreaId > 0 ? ($layers['area']['map_feature'][$parentAreaId] ?? []) : [];
        $site = $scope === 'site' ? ($layers['site'][$source][$id] ?? []) : [];
        $filter = static function (array $values, string $owner) use ($fields): array {
            return array_filter($values, static fn(mixed $value, string $key): bool =>
                isset($fields[$key]) && empty($fields[$key]['derived']) && empty($fields[$key]['location_field'])
                && in_array($owner, (array) $fields[$key]['report_scopes'], true), ARRAY_FILTER_USE_BOTH);
        };
        $area = $filter($area, 'area');
        $site = $filter($site, 'site');
        $values = $inherited = $provenance = [];
        foreach ($fields as $key => $field) {
            $answer = llama_place_report_resolve_scoped_answer($field, $placeValues, $area, $site, $scope);
            if (!$answer['recorded']) { continue; }
            $values[$key] = $answer['value'];
            $owner = $answer['source_scope'];
            $provenance[$key] = ['scope' => $owner,
                'source' => $owner === 'place' ? 'place' : ($owner === 'area' ? 'map_feature' : $source),
                'id' => $owner === 'place' ? $placeId : ($owner === 'area' ? $parentAreaId : $id)];
            // A separate resolution omits the selected target for inheritance hints.
            $ancestor = llama_place_report_resolve_scoped_answer($field, $placeValues,
                $scope === 'site' ? $area : [], [], $scope);
            if ($ancestor['recorded']) { $inherited[$key] = $ancestor['value']; }
        }
        $results[$scope . ':' . $source . ':' . $id] = [
            'values' => $values, 'inherited' => $inherited, 'provenance' => $provenance,
            'direct' => $scope === 'site' ? $site : $area, 'area' => $area, 'site' => $site];
    }
    return $results;
}


/**
 * Convert flat canonical report answers to the same display strings used by
 * the public read-only Place Report renderer.
 *
 * @return array<string,string>
 */
function llama_scoped_report_display_values(
    array $values
): array {
    if (!$values) {
        return [];
    }

    $definitions =
        llama_place_report_fields();

    $data =
        [];

    $unknownFields =
        [];

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

    $display =
        [];

    foreach (
        array_keys($values)
        as $fieldKey
    ) {
        $fieldKey =
            (string) $fieldKey;

        $value =
            llama_place_report_display_value(
                $data,
                $fieldKey
            );

        if ($value !== null) {
            $display[$fieldKey] =
                $value;
        }
    }

    return $display;
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
    if (!$db->inTransaction()) { throw new RuntimeException('Scoped answer writes require an active transaction.'); }
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

    llama_scoped_report_assert_owner($db, $placeId, $scope, $scopeId, $source);
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

    // Clear the unified current observation in the same transaction.
    // Otherwise its higher read precedence would resurrect the override
    // immediately after the legacy row is removed.
    if (llama_report_current_store_available($db)) {
        llama_report_current_store_save(
            $db,
            $placeId,
            $scope,
            $source,
            $scopeId,
            $fieldKey,
            llama_place_report_unanswered_token()
        );
    }
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

/** Enforce Place ownership without trusting an incoming source/ID pair. */
function llama_scoped_report_assert_owner(PDO $db, int $placeId, string $scope, int $scopeId, string $source): void
{
    if ($source === 'campsite_record' && $scope === 'site') {
        $sql = 'SELECT id FROM place_campsites WHERE place_id = ? AND id = ? AND is_active = 1';
        $params = [$placeId, $scopeId];
    } else {
        $types = $scope === 'site' ? ['camping_site'] : ['camping_area','parking_area'];
        $sql = 'SELECT id FROM place_map_features WHERE place_id = ? AND id = ? AND is_active = 1 AND feature_type IN ('
            . implode(',', array_fill(0, count($types), '?')) . ')';
        $params = array_merge([$placeId,$scopeId],$types);
    }
    $statement = $db->prepare($sql . ($db->inTransaction() ? ' FOR UPDATE' : ''));
    $statement->execute($params);
    if (!$statement->fetchColumn()) {
        throw new InvalidArgumentException('The reporting target does not belong to this Place.');
    }
}
