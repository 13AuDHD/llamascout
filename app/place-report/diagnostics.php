<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/place-report.php';

/** Read-only registry and production-schema validation, with no migrations. */
function llama_place_report_contract_issues(): array
{
    $issues = [];
    $fields = llama_place_report_fields();
    $storages = [];
    foreach (llama_place_report_approved_question_groups() as $group => [$label, $keys]) {
        foreach ($keys as $position => $key) {
            $field = $fields[$key] ?? null;
            if (!$field) { $issues[] = 'Missing approved field: ' . $key; continue; }
            if (($field['approved_question_group'] ?? '') !== $group
                || ($field['approved_question_position'] ?? null) !== $position) {
                $issues[] = 'Question ordering mismatch: ' . $key;
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
            && !in_array($path, ['visited_at','contributor_notes'], true)) {
            $issues[] = 'Missing storage adapter: ' . $key;
        }
    }
    return $issues;
}

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
    ];
    foreach (llama_place_report_published_definitions() as $definition) {
        $table = $definition['table'];
        $required[$table][] = $definition['column'];
        if (!empty($definition['shared']['compatibility_mirror'])) {
            $mirror = llama_place_report_storage_definition(['storage' => $definition['shared']['compatibility_mirror']['storage']]);
            $required[$mirror['table']][] = $mirror['column'];
        }
        $required[$table][] = $table === 'places' ? 'id' : 'place_id';
        if (isset($definition['period'])) { $required[$table][] = 'period'; }
    }
    $statement = $db->query('SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
    $columns = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[$column['TABLE_NAME']][$column['COLUMN_NAME']] = $column;
    }
    $issues = [];
    foreach ($required as $table => $names) {
        foreach (array_unique($names) as $column) {
            if (!isset($columns[$table][$column])) { $issues[] = 'Missing column: ' . $table . '.' . $column; }
        }
    }
    foreach (llama_place_report_published_definitions() as $definition) {
        $column = $columns[$definition['table']][$definition['column']] ?? null;
        if (!$column) { continue; }
        $field = $definition['shared'];
        $name = $definition['table'] . '.' . $definition['column'];
        if (!empty($field['allow_unknown']) && $column['IS_NULLABLE'] !== 'YES') {
            $issues[] = 'Unknown requires a nullable fact column: ' . $name;
        }
        $numeric = in_array(strtolower($column['DATA_TYPE']),
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
    $statement = $db->query('SELECT TABLE_NAME, ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()');
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $table) {
        if (isset($required[$table['TABLE_NAME']]) && strcasecmp((string) $table['ENGINE'], 'InnoDB') !== 0) {
            $issues[] = 'Transactional InnoDB table required: ' . $table['TABLE_NAME'];
        }
    }
    $statement = $db->query("SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
        FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'place_report_scoped_answers' ORDER BY INDEX_NAME, SEQ_IN_INDEX");
    $indexes = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $index) {
        if ((int) $index['NON_UNIQUE'] === 0) { $indexes[$index['INDEX_NAME']][] = $index['COLUMN_NAME']; }
    }
    $identity = ['place_id','scope_type','scope_source','scope_id','field_key'];
    sort($identity);
    $unique = false;
    foreach ($indexes as $columns) { sort($columns); if ($columns === $identity) { $unique = true; } }
    if (!$unique) { $issues[] = 'Missing unique scoped-answer identity index (Place, scope, source, target, field).'; }
    return $issues;
}
