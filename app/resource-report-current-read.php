<?php

declare(strict_types=1);

require_once __DIR__ . '/resource-report-projection.php';

/**
 * A separate read switch: migration/install and history-writing flags do not
 * automatically change public or Admin Resource answer presentation.
 */
function llama_resource_current_reads_enabled(): bool
{
    return getenv('LLAMA_RESOURCE_CURRENT_READS_ENABLED') === '1'
        && getenv('LLAMA_RESOURCE_HISTORY_WRITES_ENABLED') === '1';
}

/**
 * Check the actual database, never infer installation from merged GitHub SQL.
 * No DDL or writes. Do not require the current table for legacy Resources.
 */
function llama_resource_current_read_schema_ready(PDO $db): bool
{
    try {
        $db->query(
            'SELECT resource_id, target_kind, service_type, group_key,
                    question_key, answer_state, value_json, source_report_id,
                    row_version
             FROM resource_scout_current_answers LIMIT 0'
        );
        $db->query(
            'SELECT id, resource_id, target_kind, parent_place_id, created_at
             FROM resource_scout_reports LIMIT 0'
        );
        $db->query(
            'SELECT id, resource_id
             FROM resources LIMIT 0'
        );
        // A current row is not verifiable evidence without its immutable
        // report, schema and append-only protection.
        $db->query(
            'SELECT schema_version FROM resource_scout_report_schemas LIMIT 0'
        );
        $db->query(
            'SELECT report_id FROM resource_scout_report_answers LIMIT 0'
        );
        $triggerCount = (int) $db->query(
            "SELECT COUNT(*) FROM information_schema.triggers
             WHERE trigger_schema = DATABASE()
               AND trigger_name IN (
                  'rs_schema_immutable_u', 'rs_schema_immutable_d',
                  'rs_report_immutable_u', 'rs_report_immutable_d',
                  'rs_answer_immutable_u', 'rs_answer_immutable_d',
                  'rs_event_immutable_u', 'rs_event_immutable_d',
                  'rs_photo_immutable_u', 'rs_photo_immutable_d'
               )"
        )->fetchColumn();

        return $triggerCount === 10;
    } catch (PDOException) {
        return false;
    }
}

/**
 * Compare only the effective answer states/values. Unknown's historical
 * literal token is not a new answered value, and Unanswered stays distinct
 * from No and numeric zero. UI labels/options come from the same manifest.
 *
 * @return array<string,array{state:string,value:mixed}>
 */
function llama_resource_report_answer_fingerprint(array $projection): array
{
    $fingerprint = [];
    foreach ($projection['groups'] as $group) {
        foreach ($group['answers'] as $answer) {
            $state = (string) $answer['state'];
            $fingerprint[(string) $answer['answer_key']] = [
                'state' => $state,
                'value' => $state === 'answered' ? $answer['value'] : null,
            ];
        }
    }
    ksort($fingerprint, SORT_STRING);
    return $fingerprint;
}

/**
 * Read a Resource or linked-Facility report with conservative fallback.
 *
 * No backfill and no changes to Resource/Facility data. Current rows are
 * usable only if there is a matching immutable report, every source points
 * at the same Resource, the saved record is not newer, and the full projected
 * answers equal the still-authoritative legacy Experience JSON.
 *
 * @return array{projection:array<string,mixed>,experience:array<string,mixed>,
 *               source:string,reason:string}
 */
function llama_resource_report_read(PDO $db, array $resource): array
{
    $legacy = llama_resource_report_projection($resource);
    $legacyExperience = is_array($resource['experience'] ?? null)
        ? $resource['experience'] : [];
    $fallback = static fn (string $reason): array => [
        'projection' => $legacy,
        'experience' => $legacyExperience,
        'source' => 'legacy',
        'reason' => $reason,
    ];

    if (!llama_resource_current_reads_enabled()) {
        return $fallback('read_switch_off');
    }
    if (!llama_resource_current_read_schema_ready($db)) {
        return $fallback('schema_unavailable');
    }

    $resourceId = (int) $legacy['target']['id'];
    $kind = (string) $legacy['target']['kind'];
    $parentId = (int) ($legacy['target']['parent_place_id'] ?? 0);

    try {
        $reportStmt = $db->prepare(
            'SELECT id, resource_id, target_kind, parent_place_id, created_at
             FROM resource_scout_reports
             WHERE resource_id = ?
             ORDER BY id DESC LIMIT 1'
        );
        $reportStmt->execute([$resourceId]);
        $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($report)) {
            return $fallback('no_approved_history');
        }
        if ((int) $report['resource_id'] !== $resourceId
            || (string) $report['target_kind'] !== $kind
            || (int) ($report['parent_place_id'] ?? 0) !== $parentId) {
            return $fallback('target_changed');
        }
        $savedUpdated = (string) ($resource['updated_at'] ?? '');
        $lastApproved = (string) ($report['created_at'] ?? '');
        if ($savedUpdated !== '' && $lastApproved !== ''
            && strcmp($savedUpdated, $lastApproved) > 0) {
            return $fallback('record_newer_than_approval');
        }
        if ($legacy['unmapped'] !== []) {
            return $fallback('legacy_unmapped_answers');
        }

        $answerStmt = $db->prepare(
            'SELECT c.service_type, c.group_key, c.question_key,
                    c.answer_state, c.value_json, c.target_kind,
                    c.source_report_id, c.row_version,
                    source.resource_id AS source_resource_id
             FROM resource_scout_current_answers c
             JOIN resource_scout_reports source
               ON source.id = c.source_report_id
             WHERE c.resource_id = ?
             ORDER BY c.service_type, c.group_key, c.question_key'
        );
        $answerStmt->execute([$resourceId]);

        $manifest = llama_resource_experience_manifest();
        $enabled = [];
        foreach ((array) ($resource['services'] ?? []) as $serviceRow) {
            if (is_array($serviceRow)) {
                $enabled[(string) ($serviceRow['service_type'] ?? '')] = true;
            }
        }

        $experience = [];
        $metadata = [];
        foreach ($answerStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $service = (string) $row['service_type'];
            $group = (string) $row['group_key'];
            $question = (string) $row['question_key'];
            $state = (string) $row['answer_state'];
            $path = $service . '/' . $group . '/' . $question;
            if ((int) $row['source_resource_id'] !== $resourceId
                || (string) $row['target_kind'] !== $kind
                || !isset($enabled[$service])
                    && $state !== 'unanswered'
                || !isset($manifest[$service][$group]['questions'][$question])
                    && $state !== 'unanswered'
                || !in_array($state, ['answered', 'unknown', 'unanswered'], true)
                || isset($metadata[$path])) {
                return $fallback('current_integrity_error');
            }
            $value = json_decode(
                (string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR
            );
            if ($state === 'answered' && $value === null
                || $state !== 'answered' && $value !== null) {
                return $fallback('current_state_value_mismatch');
            }
            $metadata[$path] = [
                'source_report_id' => (int) $row['source_report_id'],
                'row_version' => (int) $row['row_version'],
            ];
            if ($state === 'answered') {
                $experience[$service][$group][$question] = $value;
            } elseif ($state === 'unknown') {
                $experience[$service][$group][$question] = 'unknown';
            }
        }

        $shadow = $resource;
        $shadow['experience'] = $experience;
        $currentProjection = llama_resource_report_projection($shadow);
        if (llama_resource_report_answer_fingerprint($legacy)
            !== llama_resource_report_answer_fingerprint($currentProjection)) {
            return $fallback('current_legacy_mismatch');
        }

        foreach ($currentProjection['groups'] as &$group) {
            foreach ($group['answers'] as &$answer) {
                $key = (string) $answer['answer_key'];
                if (!isset($metadata[$key])) {
                    continue;
                }
                $answer['origin'] = 'resource_scout_current_answers';
                $answer['source_report_id'] = $metadata[$key]['source_report_id'];
                $answer['row_version'] = $metadata[$key]['row_version'];
            }
            unset($answer);
        }
        unset($group);

        return [
            'projection' => $currentProjection,
            'experience' => $experience,
            'source' => 'current_verified',
            'reason' => 'matching_approved_history',
        ];
    } catch (PDOException | JsonException | RuntimeException $error) {
        // A missing migration, corrupted JSON or out-of-sync ledger must
        // never break a public Resource/Facility page or overwrite legacy.
        error_log('Resource current read fallback: ' . $error->getMessage());
        return $fallback('current_read_failed');
    }
}
