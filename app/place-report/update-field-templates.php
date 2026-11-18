<?php

declare(strict_types=1);

/**
 * Reuse the canonical PHP field renderer for scoped Update questions that do
 * not exist in Update Place's normal shared form markup.
 *
 * The shared Place workflow still restricts its direct SQL fields. A Site or
 * Area answer is independently saved by the verified scoped Update writer,
 * so its HTML control must not be dropped solely because it lacks a Place
 * persistence path. This helper never expands server-side write permissions.
 *
 * @param array<string,array<string,mixed>> $renderedPlaceFields
 * @return array<string,array<string,mixed>>
 */
function llama_scoped_update_missing_field_templates(array $renderedPlaceFields): array
{
    $alreadyRendered = array_fill_keys(array_keys($renderedPlaceFields), true);
    $missing = [];

    foreach (['site', 'area'] as $scope) {
        foreach (llama_report_questions_for_scope($scope, true) as $key => $field) {
            if (isset($alreadyRendered[$key]) || isset($missing[$key])
                || !llama_place_update_allows_field($field)
                || !empty($field['derived'])
                || !empty($field['pricing_managed'])
                || !empty($field['location_field'])) {
                continue;
            }

            $missing[$key] = $field;
        }
    }

    return $missing;
}
