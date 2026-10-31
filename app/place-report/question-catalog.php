<?php

declare(strict_types=1);

/**
 * The single consumable question catalog for every reporting interface.
 *
 * Question definitions, choices, ownership, visibility, dependencies and
 * order are resolved once by llama_place_report_fields(). Pages may apply
 * permissions and display components but must not redefine questions.
 */

/** @return array<string,array<string,mixed>> */
function llama_report_questions(): array
{
    return llama_place_report_fields();
}

/** Return the same question identity to forms, moderators and readers. */
function llama_report_question(string $key): ?array
{
    return llama_report_questions()[$key] ?? null;
}

/**
 * Give any reporting screen the same scoped question inventory.
 *
 * Reconciliation shows every active, scope-eligible question, even when its
 * conditional dependencies are currently false. It NEVER bypasses ownership.
 * Normal screens may further restrict access based on auth or membership.
 *
 * @return array<string,array<string,mixed>>
 */
function llama_report_questions_for_scope(
    string $scope,
    bool $reconciliation = false,
    ?callable $eligible = null
): array {
    if (!in_array($scope, ['place', 'area', 'site'], true)) {
        throw new InvalidArgumentException('Invalid reporting scope.');
    }
    $result = [];
    foreach (llama_report_questions() as $key => $field) {
        if (!in_array($scope, (array) ($field['report_scopes'] ?? []), true)
            || !empty($field['derived'])) {
            continue;
        }
        if (!$reconciliation && $eligible !== null && !$eligible($field)) {
            continue;
        }
        $result[$key] = $field;
    }
    // Reconciliation bypasses conditional display rules, never question
    // identity or declared ownership. Presentation pages can apply their
    // normal visibility and membership restrictions afterward.
    return $result;
}

/** Use one layout/group ordering projection for all reporting pages. */
function llama_report_question_groups(
    string $scope,
    bool $reconciliation = false,
    ?callable $eligible = null
): array {
    return llama_place_report_group_eligible_questions(
        llama_report_questions_for_scope($scope, $reconciliation, $eligible)
    );
}

/**
 * Group an already-authorized question selection for any renderer.
 * Accepts only keys from the canonical catalog, preserving the caller's
 * eligibility and permission filtering without duplicating definitions.
 */
function llama_report_group_selected_questions(array $selected): array
{
    $catalog = llama_report_questions();
    $eligible = [];
    foreach ($selected as $key => $_field) {
        if (isset($catalog[$key])) {
            $eligible[$key] = $catalog[$key];
        }
    }
    return llama_place_report_group_eligible_questions($eligible);
}

/** Approved public-report group inventory from the same central catalog. */
function llama_report_public_question_groups(): array
{
    return llama_place_report_effective_report_groups(llama_report_questions());
}

/**
 * View-neutral question metadata. Answer values live in the canonical answer
 * service, never in a copy of this catalog.
 */
function llama_report_question_manifest(string $scope, bool $reconciliation = false): array
{
    $result = [];
    foreach (llama_report_questions_for_scope($scope, $reconciliation) as $key => $field) {
        $result[] = [
            'key' => $key,
            'label' => (string) ($field['label'] ?? $key),
            'type' => (string) ($field['type'] ?? 'text'),
            'options' => (array) ($field['options'] ?? []),
            'allow_unknown' => (bool) ($field['allow_unknown'] ?? false),
            'scopes' => (array) ($field['report_scopes'] ?? []),
            'section' => (string) ($field['layout_section'] ?? $field['section'] ?? ''),
            'group' => (string) ($field['approved_question_group'] ?? $field['layout_group'] ?? ''),
            'order' => (int) ($field['approved_question_position'] ?? 0),
            'visibility' => $field['approved_visibility'] ?? null,
            'applicable_if' => (array) ($field['applicable_if'] ?? []),
            'inheritance' => (string) ($field['scope_inheritance'] ?? 'none'),
        ];
    }
    return $result;
}
