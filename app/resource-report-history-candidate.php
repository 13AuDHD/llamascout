<?php

declare(strict_types=1);

require_once __DIR__ . '/resource-report-projection.php';
require_once __DIR__ . '/place-report/immutable-history.php';

/**
 * Versioned Resource/linked-Facility schema CANDIDATE, never an approved
 * Place/Area/Site schema_version and never automatically inserted into
 * scout_report_schemas.
 *
 * Every instance has the composite Service / group / question identity;
 * the same canonical Area question may be answered multiple times in one
 * Resource report without overwriting a different restroom/group.
 */
function llama_resource_history_candidate_schema(): array
{
    $candidateQuestions = llama_resource_service_question_candidates();
    $occurrences = llama_resource_report_experience_field_catalog();

    foreach ($occurrences as $path => $field) {
        if ($field['classification'] === 'service_only_question'
            && !isset($candidateQuestions[$field['key']])) {
            throw new LogicException('Missing Resource question candidate for ' . $path);
        }
    }

    $definition = [
        'contract' => 'llama-resource-history-candidate-v1',
        'approval_status' => 'not_approved_for_immutable_writes',
        'target_kinds' => ['resource', 'facility'],
        'target_source' => 'resource_record',
        'answer_identity' => 'service/group/question',
        'service_question_candidates' => $candidateQuestions,
        'experience_occurrences' => $occurrences,
    ];
    $json = llama_scout_report_json($definition);

    return [
        'schema_version' => hash('sha256', $json),
        'definition_json' => $json,
        'definition' => $definition,
        'write_approved' => false,
    ];
}

/**
 * Preview one future immutable Resource contribution using actual approved
 * Resource data and EXPLICIT moderation provenance.
 *
 * No SQL, no side effects, no inferred visit dates, no conversion of mapped
 * Areas or Place rules. This is a reviewable payload contract, NOT a writer.
 *
 * @param array<string,mixed> $resource
 * @param array<string,mixed> $reviewEvidence
 * @return array<string,mixed>
 */
function llama_resource_history_candidate(
    array $resource,
    array $reviewEvidence = []
): array {
    $projection = llama_resource_report_projection($resource);
    $schema = llama_resource_history_candidate_schema();
    $target = $projection['target'];
    $occurrences = $schema['definition']['experience_occurrences'];

    $answers = [];
    foreach ($projection['groups'] as $group) {
        foreach ($group['answers'] as $answer) {
            if (!$answer['recorded']) {
                continue;
            }
            $path = (string) $answer['answer_key'];
            if (isset($answers[$path]) || !isset($occurrences[$path])) {
                throw new LogicException('Ambiguous Resource answer identity: ' . $path);
            }
            $identity = $occurrences[$path];
            $state = (string) $answer['state'];
            if (!in_array($state, ['answered', 'unknown'], true)) {
                throw new LogicException('Unanswered is not immutable report evidence.');
            }
            $answers[$path] = [
                'question_key' => (string) $answer['question_key'],
                'canonical_question_key' => $identity['canonical_question_key'],
                'service_type' => (string) $answer['service_type'],
                'group_key' => (string) $answer['group_key'],
                'state' => $state,
                'value' => $state === 'unknown' ? null : $answer['value'],
                // An explicit historical "unknown" token was stored by the
                // legacy Resource cleaner; retain it for provenance review.
                'legacy_value' => $answer['value'],
                'source' => $identity['source'],
            ];
        }
    }
    ksort($answers, SORT_STRING);

    $submissionId = (int) ($reviewEvidence['submission_id'] ?? 0);
    $contributorId = (int) ($reviewEvidence['contributor_id'] ?? 0);
    $reviewedBy = (int) ($reviewEvidence['reviewed_by'] ?? 0);
    $submittedAt = trim((string) ($reviewEvidence['submitted_at'] ?? ''));
    $visitDate = trim((string) ($reviewEvidence['visit_date'] ?? ''));

    $provenance = [
        'submission_id' => $submissionId > 0 ? $submissionId : null,
        'contributor_id' => $contributorId > 0 ? $contributorId : null,
        'reviewed_by' => $reviewedBy > 0 ? $reviewedBy : null,
        'submitted_at' => $submittedAt !== '' ? $submittedAt : null,
        // Resource submissions have no verified visit_date field today.
        'visit_date' => $visitDate !== '' ? $visitDate : null,
    ];

    // The current M2-006 Scout Report schema has place_id NOT NULL, allows
    // only place/area/site scopes, and its validator does not recognize
    // Service/group/question keys. Even a linked Facility's parent Place
    // does NOT make this an Area report. Do not bypass any of those guards.
    $blockers = [
        'Existing scout_reports requires place_id, but standalone Resources have no Place.',
        'Existing scout_reports target_scope CHECK permits only place, area and site.',
        'Immutable answer validation and current store do not support compound Service/group/question keys.',
        'Resource-only candidate questions have not been explicitly approved for the canonical report registry.',
        'Resource moderation photo evidence and target-specific publication still need a reviewed append-only schema.',
    ];
    foreach ([
        'submission_id' => $submissionId,
        'contributor_id' => $contributorId,
        'reviewed_by' => $reviewedBy,
    ] as $name => $value) {
        if ($value < 1) {
            $blockers[] = 'Missing verified moderation provenance: ' . $name;
        }
    }
    if ($submittedAt === '') {
        $blockers[] = 'Missing verified moderation provenance: submitted_at';
    }
    if ($projection['unmapped'] !== []) {
        $blockers[] = 'Legacy Experience includes unrecognized or inactive Service answers requiring reconciliation.';
    }

    $logicalKey = $submissionId > 0
        ? 'resource-submission:' . $submissionId . ':' . $target['key']
        : null;
    $snapshot = [
        'target' => $target,
        'schema_version' => $schema['schema_version'],
        'provenance' => $provenance,
        'logical_report_key' => $logicalKey,
        'answers' => $answers,
        'unmapped' => $projection['unmapped'],
    ];

    return [
        'contract' => 'llama-resource-history-candidate-v1',
        'status' => 'preview_only',
        'write_approved' => false,
        'schema_version' => $schema['schema_version'],
        'target' => $target,
        'logical_report_key' => $logicalKey,
        'provenance' => $provenance,
        'answers' => $answers,
        'unmapped' => $projection['unmapped'],
        'content_sha256' => hash('sha256', llama_scout_report_json($snapshot)),
        'blockers' => $blockers,
    ];
}
