<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/place-report.php';

/** Read-only registry and production-schema validation, with no migrations. */
function llama_place_report_contract_issues(): array
{
    $issues =
        array_merge(
            llama_place_report_approved_registry_issues(),
            llama_place_report_submission_answer_contract_issues(),
            llama_place_report_current_answer_contract_issues()
        );

    $fields =
        llama_place_report_fields();

    $approvedGroupPages =
        llama_place_report_approved_group_page_lookup();

    $approvedVisibility =
        llama_place_report_approved_question_visibility();

    $storages = [];
    foreach (llama_place_report_approved_question_groups() as $group => [$label, $keys]) {
        foreach ($keys as $position => $key) {
            $field = $fields[$key] ?? null;
            if (!$field) { $issues[] = 'Missing approved field: ' . $key; continue; }
            if (($field['approved_question_group'] ?? '') !== $group
                || ($field['approved_question_position'] ?? null) !== $position) {
                $issues[] = 'Question ordering mismatch: ' . $key;
            }

            $page =
                $approvedGroupPages[$group]
                ?? [];

            if (
                (string) ($field['approved_report_page'] ?? '')
                !== (string) ($page['page'] ?? '')
            ) {
                $issues[] =
                    'Question page ownership mismatch: '
                    . $key;
            }

            if (
                (string) ($field['approved_accent_role'] ?? '')
                !== (string) ($page['accent_role'] ?? '')
            ) {
                $issues[] =
                    'Question accent-role mismatch: '
                    . $key;
            }

            if (
                (string) ($field['approved_visibility'] ?? '')
                !== (string) ($approvedVisibility[$key] ?? '')
            ) {
                $issues[] =
                    'Question visibility mismatch: '
                    . $key;
            }
        }
    }
    foreach ($fields as $key => $field) {
        $path = (string) $field['storage'];
        if (isset($storages[$path])) { $issues[] = 'Duplicate storage: ' . $path; }
        $storages[$path] = $key;
        if (empty($field['report_scopes'])) { $issues[] = 'Missing ownership: ' . $key; }
        if (!in_array($field['type'], ['tri','checkbox','rating','permission','number','select','multiselect','text','textarea','date','url'], true)) {
            $issues[] = 'Unsupported answer type: ' . $key;
        }
        if (in_array($field['type'], ['tri','permission','rating'], true) && empty($field['allow_unknown'])) {
            $issues[] = 'Missing Unknown answer: ' . $key;
        }
        foreach ((array) $field['applicable_if'] as $rule) {
            if (isset($rule['field']) && !isset($fields[$rule['field']])) {
                $issues[] = 'Missing dependency for ' . $key . ': ' . $rule['field'];
            }
        }
        if (!llama_place_report_storage_definition($field)
            && empty($field['scoped_only_storage'])
            && !in_array($path, ['visited_at','contributor_notes'], true)) {
            $issues[] = 'Missing storage adapter: ' . $key;
        }
    }
    return $issues;
}

/**
 * Read-only preflight using metadata limited to the current database.
 * Shared hosting accounts may not have INFORMATION_SCHEMA access.
 */
function llama_place_report_schema_issues(PDO $db): array
{
    $required = [
        'place_sensory' => ['id'],
        'places' => ['id','place_report_answer_state','public_latitude','public_longitude','slug','source_type','public_summary','public_location_label'],
        'place_campsites' => ['id','place_id','map_feature_id','site_code','site_name','raw_site_type',
            'site_type','parking_style','hookup_status','accessible_status','latitude','longitude',
            'source_provider','source_external_id','is_active'],
        'place_report_scoped_answers' => ['place_id','scope_type','scope_source','scope_id','field_key','value_json','updated_at'],
        'place_map_features' => ['id','place_id','feature_type','is_active'],
        'place_update_submissions' => ['id','place_id','user_id','proposed_changes','original_values','revision_history','status','update_type'],
        'place_submissions' => ['id','user_id','place_id','submission_data','status'],
        'scout_report_schemas' => ['schema_version','definition_json','created_at'],
        'scout_reports' => ['id','logical_report_key','revision_number','prior_revision_id','amends_report_id',
            'submission_bundle_key','place_id','target_scope','target_source','target_id','target_label',
            'contributor_id','role_at_submission','visit_date','submitted_at','schema_version',
            'source_channel','client_submission_key','content_sha256'],
        'scout_report_answers' => ['id','report_id','question_key','answer_state','value_json','created_at'],
        'scout_report_moderation_events' => ['id','report_id','event_type','actor_user_id','reason','metadata_json','created_at'],
        'scout_report_photos' => ['id','report_id','photo_path','photo_path_sha256','sort_order','photo_snapshot_json','created_at'],
    ];

    foreach (llama_place_report_published_definitions() as $definition) {
        $table = (string) $definition['table'];
        $required[$table][] = (string) $definition['column'];
        if (!empty($definition['shared']['compatibility_mirror'])) {
            $mirror = llama_place_report_storage_definition([
                'storage' => $definition['shared']['compatibility_mirror']['storage'],
            ]);
            if (is_array($mirror)) {
                $required[$mirror['table']][] = $mirror['column'];
            }
        }
        $required[$table][] = $table === 'places' ? 'id' : 'place_id';
        if (isset($definition['period'])) {
            $required[$table][] = 'period';
        }
    }

    $issues = [];
    $tables = [];
    // SHOW TABLE STATUS and SHOW FULL COLUMNS require permissions only on
    // the application's selected database; they do not query INFORMATION_SCHEMA.
    foreach ($db->query('SHOW TABLE STATUS')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $tables[(string) $row['Name']] = $row;
    }

    $columns = [];
    foreach ($required as $table => $names) {
        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', (string) $table)) {
            $issues[] = 'Invalid registry table identifier: ' . $table;
            continue;
        }
        if (!isset($tables[$table])) {
            $issues[] = 'Missing table: ' . $table;
            continue;
        }
        if (strcasecmp((string) ($tables[$table]['Engine'] ?? ''), 'InnoDB') !== 0) {
            $issues[] = 'Transactional InnoDB table required: ' . $table;
        }
        $rows = $db->query('SHOW FULL COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $type = strtolower((string) ($row['Type'] ?? ''));
            preg_match('/\A[a-z]+/', $type, $match);
            $columns[$table][(string) $row['Field']] = [
                'DATA_TYPE' => $match[0] ?? '',
                'IS_NULLABLE' => strtoupper((string) ($row['Null'] ?? 'NO')),
            ];
        }
        foreach (array_unique($names) as $column) {
            if (!isset($columns[$table][$column])) {
                $issues[] = 'Missing column: ' . $table . '.' . $column;
            }
        }
    }

    foreach (llama_place_report_published_definitions() as $definition) {
        $column = $columns[$definition['table']][$definition['column']] ?? null;
        if (!$column) {
            continue;
        }
        $field = $definition['shared'];
        $name = $definition['table'] . '.' . $definition['column'];
        if (!empty($field['allow_unknown']) && $column['IS_NULLABLE'] !== 'YES') {
            $issues[] = 'Unknown requires a nullable fact column: ' . $name;
        }
        $numeric = in_array($column['DATA_TYPE'],
            ['tinyint','smallint','mediumint','int','bigint','decimal','float','double','bit'], true);
        if ($field['type'] === 'multiselect' && $numeric) {
            $issues[] = 'JSON answer list requires a text or JSON column: ' . $name;
        }
        if ($field['type'] === 'select' && $numeric) {
            foreach (array_keys((array) ($field['options'] ?? [])) as $option) {
                if (!is_numeric($option)) {
                    $issues[] = 'Text answer options require a text-compatible column: ' . $name;
                    break;
                }
            }
        }
    }

    $table = 'place_report_scoped_answers';
    if (isset($tables[$table])) {
        $indexes = [];
        foreach ($db->query('SHOW INDEX FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((int) $row['Non_unique'] === 0) {
                $indexes[(string) $row['Key_name']][(int) $row['Seq_in_index']] =
                    (string) $row['Column_name'];
            }
        }
        $identity = ['place_id','scope_type','scope_source','scope_id','field_key'];
        sort($identity);
        $unique = false;
        foreach ($indexes as $indexColumns) {
            ksort($indexColumns);
            $indexColumns = array_values($indexColumns);
            sort($indexColumns);
            if ($indexColumns === $identity) {
                $unique = true;
                break;
            }
        }
        if (!$unique) {
            $issues[] = 'Missing unique scoped-answer identity index (Place, scope, source, target, field).';
        }
    }

    $appendOnlyTables = [
        'scout_report_schemas',
        'scout_reports',
        'scout_report_answers',
        'scout_report_moderation_events',
        'scout_report_photos',
    ];
    $triggers = [];
    foreach ($db->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $triggers[(string) ($row['Trigger'] ?? '')] = $row;
    }
    foreach ($appendOnlyTables as $appendOnlyTable) {
        if (!isset($tables[$appendOnlyTable])) {
            continue;
        }
        foreach (['bu' => 'UPDATE', 'bd' => 'DELETE'] as $suffix => $operation) {
            $trigger = $appendOnlyTable . '_' . $suffix . '_immutable';
            if (!isset($triggers[$trigger])) {
                $issues[] = 'Missing append-only guard: ' . $trigger;
                continue;
            }
            $definition = $triggers[$trigger];
            if (strcasecmp((string) ($definition['Table'] ?? ''), $appendOnlyTable) !== 0
                || strcasecmp((string) ($definition['Timing'] ?? ''), 'BEFORE') !== 0
                || strcasecmp((string) ($definition['Event'] ?? ''), $operation) !== 0
                || !str_contains(
                    (string) ($definition['Statement'] ?? ''),
                    'Scout Report history is append-only'
                )) {
                $issues[] = 'Invalid append-only guard: ' . $trigger;
            }
        }
    }

    $requiredUniqueIndexes = [
        'scout_reports' => [
            ['logical_report_key', 'revision_number'],
            ['contributor_id', 'client_submission_key'],
        ],
        'scout_report_answers' => [
            ['report_id', 'question_key'],
        ],
        'scout_report_photos' => [
            ['report_id', 'photo_path_sha256'],
        ],
    ];
    foreach ($requiredUniqueIndexes as $indexTable => $expectedIndexes) {
        if (!isset($tables[$indexTable])) {
            continue;
        }
        $uniqueIndexes = [];
        foreach ($db->query('SHOW INDEX FROM `' . $indexTable . '`')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((int) ($row['Non_unique'] ?? 1) !== 0) {
                continue;
            }
            $uniqueIndexes[(string) $row['Key_name']][(int) $row['Seq_in_index']] =
                (string) $row['Column_name'];
        }
        foreach ($uniqueIndexes as &$indexColumns) {
            ksort($indexColumns);
            $indexColumns = array_values($indexColumns);
        }
        unset($indexColumns);
        foreach ($expectedIndexes as $expectedIndex) {
            if (!in_array($expectedIndex, $uniqueIndexes, true)) {
                $issues[] = 'Missing immutable-report unique index: '
                    . $indexTable . '(' . implode(',', $expectedIndex) . ')';
            }
        }
    }

    return array_values(array_unique($issues));
}
