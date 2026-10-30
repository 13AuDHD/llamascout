<?php

declare(strict_types=1);

/**
 * Canonical read-only current-answer model.
 *
 * This adapter does not choose a new winner. It wraps the existing published
 * Place, scoped inheritance, and imported campsite baseline precedence in one
 * consistent answer envelope.
 */

/**
 * Normalize one recorded current value through the canonical field contract.
 *
 * Explicit recorded null remains an answered null. Unknown and Unanswered are
 * represented by state rather than leaking persistence sentinel values.
 */
function llama_place_report_current_answer_value(
    array $field,
    mixed $rawValue,
    string $state
): mixed {
    if ($state !== 'answered') {
        return null;
    }

    if ($rawValue === null) {
        return null;
    }

    $formValue =
        llama_place_report_answer_form_value(
            $field,
            $rawValue
        );

    $parsed =
        llama_place_report_parse_answer(
            $field,
            $formValue
        );

    return $parsed['state'] === 'answered'
        ? $parsed['value']
        : $rawValue;
}

/**
 * Build one canonical current-answer envelope.
 *
 * @return array{
 *     field_key:string,
 *     state:string,
 *     value:mixed,
 *     recorded:bool,
 *     inherited:bool,
 *     direct:bool,
 *     baseline:bool,
 *     imported:bool,
 *     target:array{scope:string,source:string,id:int},
 *     origin:?array<string,mixed>
 * }
 */
function llama_place_report_current_answer_envelope(
    array $field,
    bool $recorded,
    mixed $rawValue,
    array $target,
    ?array $provenance = null
): array {
    $fieldKey =
        (string) (
            $field['key']
            ?? ''
        );

    if ($fieldKey === '') {
        throw new InvalidArgumentException(
            'A canonical field key is required.'
        );
    }

    $targetScope =
        (string) (
            $target['scope']
            ?? ''
        );

    $targetSource =
        (string) (
            $target['source']
            ?? ''
        );

    $targetId =
        (int) (
            $target['id']
            ?? 0
        );

    if (
        !in_array(
            $targetScope,
            ['place', 'area', 'site'],
            true
        )
        || $targetId < 1
    ) {
        throw new InvalidArgumentException(
            'A valid reporting target is required.'
        );
    }

    $unknownToken =
        llama_place_report_unknown_token();

    $state =
        !$recorded
            ? 'unanswered'
            : (
                $rawValue === $unknownToken
                    ? 'unknown'
                    : 'answered'
            );

    $origin =
        $recorded
            ? (array) ($provenance ?? [])
            : null;

    $originScope =
        is_array($origin)
            ? (string) ($origin['scope'] ?? '')
            : '';

    $originType =
        is_array($origin)
            ? (string) ($origin['origin_type'] ?? '')
            : '';

    if (
        $recorded
        && $originType === ''
    ) {
        $originType =
            $originScope === 'place'
                ? 'published_place'
                : 'scoped_override';

        $origin['origin_type'] =
            $originType;
    }

    $inherited =
        $recorded
        && $originScope !== ''
        && $originScope !== $targetScope;

    $baseline =
        $recorded
        && $originType === 'campsite_baseline';

    $imported =
        $baseline
        && (bool) (
            is_array($origin)
                ? ($origin['imported'] ?? false)
                : false
        );

    $direct =
        $recorded
        && !$inherited
        && !$baseline;

    return [
        'field_key' =>
            $fieldKey,

        'state' =>
            $state,

        'value' =>
            llama_place_report_current_answer_value(
                $field,
                $rawValue,
                $state
            ),

        'recorded' =>
            $recorded,

        'inherited' =>
            $inherited,

        'direct' =>
            $direct,

        'baseline' =>
            $baseline,

        'imported' =>
            $imported,

        'target' => [
            'scope' =>
                $targetScope,

            'source' =>
                $targetSource,

            'id' =>
                $targetId,
        ],

        'origin' =>
            $origin,
    ];
}

/**
 * Return true when a field can contribute an effective answer to the target.
 *
 * A Site can inherit Place and Area answers even when it cannot directly write
 * those fields. An Area can inherit Place answers. A Place has no ancestor.
 */
function llama_place_report_field_relevant_to_target(
    array $field,
    string $targetScope
): bool {
    $allowed =
        (array) (
            $field['report_scopes']
            ?? []
        );

    $candidateScopes =
        match ($targetScope) {
            'site' =>
                ['site', 'area', 'place'],

            'area' =>
                ['area', 'place'],

            'place' =>
                ['place'],

            default =>
                [],
        };

    return array_intersect(
        $allowed,
        $candidateScopes
    ) !== [];
}

/**
 * Build Place current-answer envelopes from the existing published projection.
 *
 * @return array<string,array<string,mixed>>
 */
function llama_place_report_current_place_answers(
    PDO $db,
    int $placeId
): array {
    if ($placeId < 1) {
        throw new InvalidArgumentException(
            'Invalid Place ID.'
        );
    }

    $data = [];

    foreach (
        llama_place_report_current_values(
            $db,
            $placeId
        )
        as $path => $value
    ) {
        llama_place_report_set_path(
            $data,
            $path,
            $value
        );
    }

    $data['_answer_state'] =
        llama_place_report_published_answer_state(
            $db,
            $placeId
        );

    $recordedValues =
        llama_scoped_report_values_from_data(
            $data
        );

    $target = [
        'scope' =>
            'place',

        'source' =>
            'place',

        'id' =>
            $placeId,
    ];

    $answers = [];

    foreach (
        llama_place_report_fields()
        as $fieldKey => $field
    ) {
        if (
            !llama_place_report_field_relevant_to_target(
                $field,
                'place'
            )
        ) {
            continue;
        }

        $recorded =
            array_key_exists(
                $fieldKey,
                $recordedValues
            );

        $answers[$fieldKey] =
            llama_place_report_current_answer_envelope(
                $field,
                $recorded,
                $recorded
                    ? $recordedValues[$fieldKey]
                    : null,
                $target,
                $recorded
                    ? [
                        'scope' =>
                            'place',

                        'source' =>
                            'place',

                        'id' =>
                            $placeId,

                        'origin_type' =>
                            'published_place',
                    ]
                    : null
            );
    }

    return $answers;
}

/**
 * Build effective Area or Site current-answer envelopes.
 *
 * The supplied target is reverified against the Place before reading.
 *
 * @return array<string,array<string,mixed>>
 */
function llama_place_report_current_target_answers(
    PDO $db,
    int $placeId,
    array $target
): array {
    if ($placeId < 1) {
        throw new InvalidArgumentException(
            'Invalid Place ID.'
        );
    }

    require_once __DIR__ . '/report-targets.php';
    require_once __DIR__ . '/target-model.php';

    $scope =
        (string) (
            $target['scope']
            ?? ''
        );

    $source =
        (string) (
            $target['source']
            ?? ''
        );

    $id =
        (int) (
            $target['id']
            ?? 0
        );

    $verified =
        llama_report_verified_target(
            $db,
            $placeId,
            $scope,
            $source,
            $id
        );

    if (
        (string) $verified['scope']
        === 'place'
    ) {
        return
            llama_place_report_current_place_answers(
                $db,
                $placeId
            );
    }

    $model =
        llama_place_report_target_model(
            $db,
            $placeId,
            $verified
        );

    $answers = [];

    foreach (
        llama_place_report_fields()
        as $fieldKey => $field
    ) {
        if (
            !llama_place_report_field_relevant_to_target(
                $field,
                (string) $verified['scope']
            )
        ) {
            continue;
        }

        $recorded =
            array_key_exists(
                $fieldKey,
                (array) ($model['values'] ?? [])
            );

        $answers[$fieldKey] =
            llama_place_report_current_answer_envelope(
                $field,
                $recorded,
                $recorded
                    ? $model['values'][$fieldKey]
                    : null,
                $verified,
                $recorded
                    ? (
                        (array) (
                            $model['provenance'][$fieldKey]
                            ?? []
                        )
                    )
                    : null
            );
    }

    return $answers;
}

/**
 * One entry point for Place, Area, or Site current-answer reads.
 *
 * @return array{
 *     place_id:int,
 *     target:array<string,mixed>,
 *     answers:array<string,array<string,mixed>>
 * }
 */
function llama_place_report_current_answer_model(
    PDO $db,
    int $placeId,
    array $target
): array {
    require_once __DIR__ . '/report-targets.php';

    $verified =
        llama_report_verified_target(
            $db,
            $placeId,
            (string) ($target['scope'] ?? ''),
            (string) ($target['source'] ?? ''),
            (int) ($target['id'] ?? 0)
        );

    return [
        'place_id' =>
            $placeId,

        'target' =>
            $verified,

        'answers' =>
            (string) $verified['scope'] === 'place'
                ? llama_place_report_current_place_answers(
                    $db,
                    $placeId
                )
                : llama_place_report_current_target_answers(
                    $db,
                    $placeId,
                    $verified
                ),
    ];
}


/**
 * Convert one canonical envelope back to the legacy flat scoped value shape.
 *
 * Unknown remains the existing transport token because legacy display helpers
 * already understand it. Unanswered answers are omitted by the map helper.
 */
function llama_place_report_current_answer_legacy_value(
    array $answer
): mixed {
    $state =
        (string) (
            $answer['state']
            ?? 'unanswered'
        );

    if ($state === 'unknown') {
        return llama_place_report_unknown_token();
    }

    return $answer['value'] ?? null;
}

/**
 * Project canonical current-answer envelopes into a legacy flat value map.
 *
 * Supported selections:
 * - effective: every recorded winner
 * - inherited: winners inherited from Place or Area
 * - local: winners recorded on the selected target itself
 * - scoped_override: target-local Scout/Admin scoped overrides only
 * - baseline: target-local campsite baseline values only
 *
 * @return array<string,mixed>
 */
function llama_place_report_current_answer_values(
    array $answers,
    string $selection = 'effective'
): array {
    if (
        !in_array(
            $selection,
            [
                'effective',
                'inherited',
                'local',
                'scoped_override',
                'baseline',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid current-answer projection.'
        );
    }

    $values = [];

    foreach ($answers as $fieldKey => $answer) {
        if (
            !is_array($answer)
            || empty($answer['recorded'])
        ) {
            continue;
        }

        $origin =
            (array) (
                $answer['origin']
                ?? []
            );

        $originType =
            (string) (
                $origin['origin_type']
                ?? ''
            );

        $include =
            match ($selection) {
                'inherited' =>
                    !empty($answer['inherited']),

                'local' =>
                    empty($answer['inherited']),

                'scoped_override' =>
                    empty($answer['inherited'])
                    && $originType === 'scoped_override',

                'baseline' =>
                    !empty($answer['baseline']),

                default =>
                    true,
            };

        if (!$include) {
            continue;
        }

        $values[(string) $fieldKey] =
            llama_place_report_current_answer_legacy_value(
                $answer
            );
    }

    return $values;
}

/**
 * Side-effect-free contract checks for the current-answer envelope.
 *
 * These checks intentionally use synthetic values so diagnostics can validate
 * state/origin semantics without requiring production records.
 */
function llama_place_report_current_answer_contract_issues(): array
{
    $issues = [];

    $field =
        llama_place_report_fields()[
            'amenity_potable_water'
        ]
        ?? null;

    if (!is_array($field)) {
        return [
            'Current-answer diagnostic field is unavailable.',
        ];
    }

    $placeTarget = [
        'scope' =>
            'place',

        'source' =>
            'place',

        'id' =>
            1,
    ];

    $siteTarget = [
        'scope' =>
            'site',

        'source' =>
            'campsite_record',

        'id' =>
            2,
    ];

    $unanswered =
        llama_place_report_current_answer_envelope(
            $field,
            false,
            null,
            $placeTarget
        );

    if (
        $unanswered['state'] !== 'unanswered'
        || $unanswered['recorded'] !== false
        || $unanswered['value'] !== null
        || $unanswered['origin'] !== null
    ) {
        $issues[] =
            'Current-answer Unanswered contract mismatch.';
    }

    $unknown =
        llama_place_report_current_answer_envelope(
            $field,
            true,
            llama_place_report_unknown_token(),
            $placeTarget,
            [
                'scope' =>
                    'place',

                'source' =>
                    'place',

                'id' =>
                    1,

                'origin_type' =>
                    'published_place',
            ]
        );

    if (
        $unknown['state'] !== 'unknown'
        || $unknown['recorded'] !== true
        || $unknown['value'] !== null
        || $unknown['direct'] !== true
    ) {
        $issues[] =
            'Current-answer Unknown contract mismatch.';
    }

    $explicitNo =
        llama_place_report_current_answer_envelope(
            $field,
            true,
            false,
            $placeTarget,
            [
                'scope' =>
                    'place',

                'source' =>
                    'place',

                'id' =>
                    1,

                'origin_type' =>
                    'published_place',
            ]
        );

    if (
        $explicitNo['state'] !== 'answered'
        || $explicitNo['recorded'] !== true
        || $explicitNo['value'] !== false
    ) {
        $issues[] =
            'Current-answer explicit No contract mismatch.';
    }

    $inherited =
        llama_place_report_current_answer_envelope(
            $field,
            true,
            true,
            $siteTarget,
            [
                'scope' =>
                    'area',

                'source' =>
                    'map_feature',

                'id' =>
                    3,

                'origin_type' =>
                    'scoped_override',
            ]
        );

    if (
        $inherited['state'] !== 'answered'
        || $inherited['inherited'] !== true
        || $inherited['direct'] !== false
        || $inherited['baseline'] !== false
        || $inherited['imported'] !== false
    ) {
        $issues[] =
            'Current-answer inherited Area contract mismatch.';
    }

    $imported =
        llama_place_report_current_answer_envelope(
            $field,
            true,
            true,
            $siteTarget,
            [
                'scope' =>
                    'site',

                'source' =>
                    'campsite_record',

                'id' =>
                    2,

                'origin_type' =>
                    'campsite_baseline',

                'imported' =>
                    true,

                'provider' =>
                    'RIDB',

                'external_id' =>
                    'test',
            ]
        );

    if (
        $imported['state'] !== 'answered'
        || $imported['inherited'] !== false
        || $imported['direct'] !== false
        || $imported['baseline'] !== true
        || $imported['imported'] !== true
        || (
            (string) (
                $imported['origin']['provider']
                ?? ''
            )
            !== 'RIDB'
        )
    ) {
        $issues[] =
            'Current-answer imported baseline contract mismatch.';
    }

    $projectionAnswers = [
        'unknown_field' =>
            $unknown,

        'inherited_field' =>
            $inherited,

        'baseline_field' =>
            $imported,

        'unanswered_field' =>
            $unanswered,
    ];

    $effectiveProjection =
        llama_place_report_current_answer_values(
            $projectionAnswers,
            'effective'
        );

    if (
        !array_key_exists(
            'unknown_field',
            $effectiveProjection
        )
        || $effectiveProjection['unknown_field']
            !== llama_place_report_unknown_token()
        || array_key_exists(
            'unanswered_field',
            $effectiveProjection
        )
    ) {
        $issues[] =
            'Current-answer effective projection contract mismatch.';
    }

    $inheritedProjection =
        llama_place_report_current_answer_values(
            $projectionAnswers,
            'inherited'
        );

    if (
        array_keys($inheritedProjection)
        !== ['inherited_field']
    ) {
        $issues[] =
            'Current-answer inherited projection contract mismatch.';
    }

    $localProjection =
        llama_place_report_current_answer_values(
            $projectionAnswers,
            'local'
        );

    if (
        !array_key_exists(
            'unknown_field',
            $localProjection
        )
        || !array_key_exists(
            'baseline_field',
            $localProjection
        )
        || array_key_exists(
            'inherited_field',
            $localProjection
        )
    ) {
        $issues[] =
            'Current-answer local projection contract mismatch.';
    }

    $baselineProjection =
        llama_place_report_current_answer_values(
            $projectionAnswers,
            'baseline'
        );

    if (
        array_keys($baselineProjection)
        !== ['baseline_field']
    ) {
        $issues[] =
            'Current-answer baseline projection contract mismatch.';
    }

    return $issues;
}
