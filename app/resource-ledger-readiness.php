<?php

declare(strict_types=1);

/**
 * Read-only deployment inspection of the Resource/linked-Facility sidecar.
 * This is a structural health check, NOT permission to enable either flag.
 * No DDL/DML, mutations, commits, hidden migrations, or production backfill.
 */
function llama_resource_ledger_readiness(PDO $db): array
{
    $expectedColumns = [
        'resource_scout_report_schemas' => [
            'schema_version', 'definition_json', 'created_at',
        ],
        'resource_scout_reports' => [
            'id', 'resource_id', 'target_kind', 'parent_place_id',
            'submission_id', 'logical_report_key', 'revision_number',
            'prior_report_id', 'contributor_id', 'role_at_submission',
            'submitted_at', 'reviewed_by', 'reviewed_at', 'visit_date',
            'schema_version', 'client_submission_key', 'content_sha256',
            'created_at',
        ],
        'resource_scout_report_answers' => [
            'id', 'report_id', 'service_type', 'group_key', 'question_key',
            'canonical_question_key', 'answer_state', 'value_json',
            'legacy_value_json', 'created_at',
        ],
        'resource_scout_report_events' => [
            'id', 'report_id', 'event_type', 'actor_user_id', 'reason',
            'metadata_json', 'created_at',
        ],
        'resource_scout_report_photos' => [
            'id', 'report_id', 'photo_path', 'photo_path_sha256',
            'sort_order', 'photo_snapshot_json', 'created_at',
        ],
        'resource_scout_current_answers' => [
            'id', 'resource_id', 'target_kind', 'service_type',
            'group_key', 'question_key', 'answer_state', 'value_json',
            'source_report_id', 'row_version', 'updated_at',
        ],
    ];

    $expectedTriggers = [];
    foreach ([
        'schema' => 'resource_scout_report_schemas',
        'report' => 'resource_scout_reports',
        'answer' => 'resource_scout_report_answers',
        'event' => 'resource_scout_report_events',
        'photo' => 'resource_scout_report_photos',
    ] as $short => $table) {
        $expectedTriggers['rs_' . $short . '_immutable_u'] = [$table, 'UPDATE'];
        $expectedTriggers['rs_' . $short . '_immutable_d'] = [$table, 'DELETE'];
    }

    $expectedForeignKeys = [
        'fk_resource_report_record' => ['resource_scout_reports', 'resource_id', 'resources', 'id'],
        'fk_resource_report_submission' => ['resource_scout_reports', 'submission_id', 'resource_submissions', 'id'],
        'fk_resource_report_schema' => ['resource_scout_reports', 'schema_version', 'resource_scout_report_schemas', 'schema_version'],
        'fk_resource_answer_report' => ['resource_scout_report_answers', 'report_id', 'resource_scout_reports', 'id'],
        'fk_resource_event_report' => ['resource_scout_report_events', 'report_id', 'resource_scout_reports', 'id'],
        'fk_resource_photo_report' => ['resource_scout_report_photos', 'report_id', 'resource_scout_reports', 'id'],
        'fk_resource_current_record' => ['resource_scout_current_answers', 'resource_id', 'resources', 'id'],
        'fk_resource_current_report' => ['resource_scout_current_answers', 'source_report_id', 'resource_scout_reports', 'id'],
    ];

    $expectedChecks = [
        'chk_resource_report_kind',
        'chk_resource_report_revision',
        'chk_resource_answer_state',
        'chk_resource_current_kind',
        'chk_resource_current_state',
        'chk_resource_current_version',
    ];

    $expectedUniqueIndexes = [
        'resource_scout_reports' => [
            'uq_resource_report_submission',
            'uq_resource_report_revision',
            'uq_resource_report_client',
        ],
        'resource_scout_report_answers' => ['uq_resource_answer_occurrence'],
        'resource_scout_report_photos' => ['uq_resource_report_photo'],
        'resource_scout_current_answers' => ['uq_resource_current_occurrence'],
    ];

    $report = [
        'schema_ready' => false,
        'status' => 'unverified',
        'checks' => [],
        'counts' => [],
        'integrity' => [],
        'flags' => [
            'history_writes_enabled' => getenv('LLAMA_RESOURCE_HISTORY_WRITES_ENABLED') === '1',
            'current_reads_enabled' => getenv('LLAMA_RESOURCE_CURRENT_READS_ENABLED') === '1',
        ],
        'activation_approved' => false, // Staging approval cannot be inferred from DB structure.
    ];

    try {
        $tables = [];
        foreach ($db->query(
            'SELECT table_name, column_name
             FROM information_schema.columns
             WHERE table_schema = DATABASE()'
        )->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tables[(string) $row['table_name']][(string) $row['column_name']] = true;
        }

        foreach ($expectedColumns as $table => $columns) {
            $missing = array_values(array_filter(
                $columns,
                static fn (string $column): bool => !isset($tables[$table][$column])
            ));
            $report['checks'][] = [
                'label' => $table . ' columns',
                'ok' => $missing === [],
                'details' => $missing === []
                    ? count($columns) . ' required columns present'
                    : 'Missing: ' . implode(', ', $missing),
            ];
        }

        $triggers = [];
        foreach ($db->query(
            'SELECT trigger_name, event_object_table, event_manipulation,
                    action_timing
             FROM information_schema.triggers
             WHERE trigger_schema = DATABASE()'
        )->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $triggers[(string) $row['trigger_name']] = $row;
        }
        $invalidTriggers = [];
        foreach ($expectedTriggers as $name => [$table, $event]) {
            $found = $triggers[$name] ?? null;
            if (!$found
                || $found['event_object_table'] !== $table
                || $found['event_manipulation'] !== $event
                || $found['action_timing'] !== 'BEFORE') {
                $invalidTriggers[] = $name;
            }
        }
        $report['checks'][] = [
            'label' => 'Immutable history triggers',
            'ok' => $invalidTriggers === [],
            'details' => $invalidTriggers === []
                ? 'All 10 required BEFORE UPDATE/DELETE triggers match their tables'
                : 'Missing or mismatched: ' . implode(', ', $invalidTriggers),
        ];

        $fkRows = $db->query(
            'SELECT constraint_name, table_name, column_name,
                    referenced_table_name, referenced_column_name
             FROM information_schema.key_column_usage
             WHERE table_schema = DATABASE()
               AND referenced_table_name IS NOT NULL'
        )->fetchAll(PDO::FETCH_ASSOC);
        $fks = [];
        foreach ($fkRows as $row) {
            $fks[(string) $row['constraint_name']] = [
                $row['table_name'], $row['column_name'],
                $row['referenced_table_name'], $row['referenced_column_name'],
            ];
        }
        $invalidForeignKeys = [];
        foreach ($expectedForeignKeys as $name => $identity) {
            if (($fks[$name] ?? null) !== $identity) {
                $invalidForeignKeys[] = $name;
            }
        }
        $report['checks'][] = [
            'label' => 'Foreign-key target ownership',
            'ok' => $invalidForeignKeys === [],
            'details' => $invalidForeignKeys === []
                ? 'All 8 required Resource, submission, report and schema relationships exist'
                : 'Missing or mismatched: ' . implode(', ', $invalidForeignKeys),
        ];

        $constraints = [];
        foreach ($db->query(
            'SELECT constraint_name, table_name
             FROM information_schema.table_constraints
             WHERE constraint_schema = DATABASE() AND constraint_type = \'CHECK\''
        )->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $constraints[(string) $row['constraint_name']] = $row['table_name'];
        }
        $missingChecks = array_values(array_filter(
            $expectedChecks,
            static fn (string $name): bool => !isset($constraints[$name])
        ));
        $report['checks'][] = [
            'label' => 'Target, answer-state and version constraints',
            'ok' => $missingChecks === [],
            'details' => $missingChecks === []
                ? 'All 6 required CHECK constraints exist'
                : 'Missing: ' . implode(', ', $missingChecks),
        ];

        $indexes = [];
        foreach ($db->query(
            'SELECT table_name, index_name, non_unique
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()'
        )->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((int) $row['non_unique'] === 0) {
                $indexes[(string) $row['table_name']][(string) $row['index_name']] = true;
            }
        }
        $missingIndexes = [];
        foreach ($expectedUniqueIndexes as $table => $indexNames) {
            foreach ($indexNames as $name) {
                if (!isset($indexes[$table][$name])) {
                    $missingIndexes[] = $table . '.' . $name;
                }
            }
        }
        $report['checks'][] = [
            'label' => 'Duplicate-prevention indexes',
            'ok' => $missingIndexes === [],
            'details' => $missingIndexes === []
                ? 'All 6 required unique indexes exist'
                : 'Missing: ' . implode(', ', $missingIndexes),
        ];

        $report['schema_ready'] = !in_array(
            false,
            array_column($report['checks'], 'ok'),
            true
        );
        if (!$report['schema_ready']) {
            $report['status'] = 'schema_incomplete';
            return $report;
        }

        // SELECT-only coverage; old approved submissions missing a ledger
        // report are expected before opt-in and MUST NOT be backfilled here.
        foreach ($expectedColumns as $table => $_columns) {
            $report['counts'][$table] = (int) $db
                ->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }
        $report['counts']['standalone_resources'] = (int) $db
            ->query('SELECT COUNT(*) FROM resources WHERE parent_place_id IS NULL')
            ->fetchColumn();
        $report['counts']['linked_facilities'] = (int) $db
            ->query('SELECT COUNT(*) FROM resources WHERE parent_place_id IS NOT NULL')
            ->fetchColumn();
        $report['counts']['approved_submissions_without_history'] = (int) $db
            ->query(
                "SELECT COUNT(*) FROM resource_submissions s
                 LEFT JOIN resource_scout_reports r ON r.submission_id = s.id
                 WHERE s.status = 'approved' AND r.id IS NULL"
            )->fetchColumn();

        $report['integrity']['current_answer_foreign_target'] = (int) $db
            ->query(
                'SELECT COUNT(*)
                 FROM resource_scout_current_answers a
                 JOIN resource_scout_reports r ON r.id = a.source_report_id
                 WHERE r.resource_id <> a.resource_id'
            )->fetchColumn();
        $report['integrity']['current_answer_wrong_kind'] = (int) $db
            ->query(
                "SELECT COUNT(*)
                 FROM resource_scout_current_answers a
                 JOIN resources r ON r.id = a.resource_id
                 WHERE (a.target_kind = 'resource' AND r.parent_place_id IS NOT NULL)
                    OR (a.target_kind = 'facility' AND r.parent_place_id IS NULL)"
            )->fetchColumn();

        $report['status'] = array_sum($report['integrity']) > 0
            ? 'integrity_review_required' : 'schema_verified';
        return $report;
    } catch (Throwable $error) {
        // Do not leak database schema errors to a browser page or logs.
        $report['schema_ready'] = false;
        $report['status'] = 'inspection_failed';
        $report['checks'][] = [
            'label' => 'Database read permissions',
            'ok' => false,
            'details' => 'Inspection unavailable with the current database permissions',
        ];
        $report['counts'] = [];
        $report['integrity'] = [];
        return $report;
    }
}
