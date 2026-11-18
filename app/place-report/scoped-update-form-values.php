<?php

declare(strict_types=1);

/**
 * Preserve typed scoped Scout Report answers in an Update draft.
 *
 * This adapter is for form presentation only. Moderation and publication
 * still validate answers against the verified target and canonical parser.
 * The registry owns all question keys and choice meanings.
 *
 * @param array<string,mixed> $answers Scoped proposed answers keyed by question ID
 * @return array<string,mixed>
 */
function llama_scoped_update_form_values(string $scope, array $answers): array
{
    if (!in_array($scope, ['site', 'area'], true)) {
        throw new InvalidArgumentException('Select a Site or Facility update target.');
    }

    $fields = llama_report_questions_for_scope($scope, true);
    $values = [];

    foreach ($answers as $key => $value) {
        if (!is_string($key) || !isset($fields[$key])) {
            continue;
        }

        $field = $fields[$key];
        $type = (string) ($field['type'] ?? '');

        if ($type === 'multiselect') {
            // Both a chosen list and an intentionally empty list are valid
            // drafts. A scalar is not a substitute for a set.
            if ($value === llama_place_report_unknown_token()) {
                $values[$key] = llama_place_report_unknown_token();
            } elseif (is_array($value) && array_is_list($value)
                && count(array_filter($value, 'is_scalar')) === count($value)) {
                $values[$key] = llama_place_report_answer_form_value($field, $value);
            }
            continue;
        }

        if (!is_scalar($value) && $value !== null) {
            continue;
        }
        $values[$key] = llama_place_report_answer_form_value($field, $value);
    }

    return $values;
}
