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
    if (!empty($answers['site_answers'])) {
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
