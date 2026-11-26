<?php

declare(strict_types=1);

/**
 * Single-target Add Place contract.
 *
 * The existing Add Place + first Site bundle is retained only for submissions
 * made under the older workflow. New Place-only submissions carry an explicit
 * target-kind marker. These functions must never silently convert an existing
 * pending bundle to a different target kind.
 */
function llama_new_place_submission_kind(array $input, ?array $existing = null): string
{
    $stored = trim((string) ($existing['_submission_target_kind'] ?? 'legacy-bundle'));
    $requested = trim((string) ($input['submission_target_kind'] ?? ''));

    if ($existing !== null) {
        if (!in_array($stored, ['place', 'legacy-bundle'], true)) {
            throw new InvalidArgumentException('This Place submission has an unsupported target kind.');
        }
        if ($requested !== '' && $requested !== $stored) {
            throw new InvalidArgumentException(
                'A pending Place submission cannot be changed into a different contribution type.'
            );
        }
        return $stored;
    }

    if ($requested === '' || $requested === 'legacy-bundle') {
        return 'legacy-bundle';
    }
    if ($requested !== 'place') {
        throw new InvalidArgumentException(
            'Select a valid Place contribution. Site submissions have a separate review workflow.'
        );
    }
    return 'place';
}

/**
 * A one-target Place cannot carry new Site/Area observations, geometry, or photos.
 * Place questions that are also inheritable by a Site remain Place answers.
 */
function llama_new_place_assert_single_target(array $data): void
{
    $answers = llama_place_report_submission_answers($data);
    // The legacy registry scopes "visited_at" to Site, but Add Place also
    // requires it as submission provenance. It remains visit-date metadata
    // on this one Place report, not a newly published Site answer.
    $siteAnswers = (array) $answers['site_answers'];
    unset($siteAnswers['visited_at']);
    if ($siteAnswers !== []) {
        throw new InvalidArgumentException(
            'This Place submission includes Site answers. Add the Site separately under its parent Place.'
        );
    }
    if (!empty($data['_first_site_coordinates'])
        || !empty($data['_first_area'])
        || !empty($data['site_photos'])) {
        throw new InvalidArgumentException(
            'A Place submission cannot include a Site or Area. Submit one contribution at a time.'
        );
    }
}

/** Reject stale browser fields before staged child photos can be committed. */
function llama_new_place_assert_single_target_input(array $input): void
{
    if (trim((string) ($input['site_coordinates'] ?? '')) !== ''
        || trim((string) ($input['area_bundle_json'] ?? '')) !== '') {
        throw new InvalidArgumentException(
            'Site and Area locations must be submitted separately from the Place.'
        );
    }
    $rawSitePhotos = $input['site_photos_json'] ?? '[]';
    if (!is_string($rawSitePhotos)) {
        throw new InvalidArgumentException('Invalid Site photo input.');
    }
    $sitePhotos = json_decode($rawSitePhotos, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($sitePhotos) || $sitePhotos !== []) {
        throw new InvalidArgumentException(
            'Site photos must be submitted with the Site, not its parent Place.'
        );
    }
}

/** Place-only pending reports retain visit-date provenance and photo evidence. */
function llama_new_place_completion_summary(array $input, int $photoCount, bool $placeOnly): array
{
    $excluded = [];
    if ($placeOnly) {
        foreach (llama_report_questions() as $key => $field) {
            if ($key !== 'visited_at' && !in_array('place', (array) ($field['report_scopes'] ?? []), true)) {
                $excluded[] = $key;
            }
        }
    }
    return llama_place_report_question_completion_summary($input, $photoCount, $excluded);
}

/** Determine inapplicable recorded Place answers without changing stored data. */
function llama_new_place_inapplicable_keys(array $data): array
{
    $input = llama_place_report_scoring_input_from_data($data);
    $keys = [];
    foreach (llama_report_questions_for_scope('place', true) as $key => $field) {
        if (llama_place_report_answer_state($data, $key) !== 'unanswered'
            && !llama_place_report_question_applicable($input, $key)) {
            $keys[] = $key;
        }
    }
    return $keys;
}

/**
 * Normalize an unpublished Place-only draft after a contributor or moderator
 * saves changes. Stale dependent answers are not new observations. Never use
 * this on approved history or on legacy bundles.
 */
function llama_new_place_normalize_pending_applicability(array $data): array
{
    if (($data['_submission_target_kind'] ?? '') !== 'place') { return $data; }
    $keys = llama_new_place_inapplicable_keys($data);
    foreach ($keys as $key) {
        $field = llama_report_question($key);
        llama_place_report_set_path($data, (string) $field['storage'], null);
    }
    $data['_answer_state'] = array_values(array_diff(llama_place_report_unknown_fields($data), $keys));
    return $data;
}

/** Approval must never silently publish or discard stale pending evidence. */
function llama_new_place_assert_pending_applicability(array $data): void
{
    if (($data['_submission_target_kind'] ?? '') !== 'place') { return; }
    $keys = llama_new_place_inapplicable_keys($data);
    if ($keys) {
        $labels = array_map(static fn ($key): string => (string) (llama_report_question($key)['label'] ?? $key), $keys);
        throw new InvalidArgumentException('Some pending Place answers no longer apply: '
            . implode(', ', $labels) . '. Edit and save the submission before approval.');
    }
}
