<?php

declare(strict_types=1);

require_once __DIR__ . '/photo-staging.php';
require_once __DIR__ . '/resource-media.php';

/**
 * Resource submissions keep moderated photo evidence inside the existing
 * form_data JSON, under a private _photos key. No new table or gallery.
 */
function llama_resource_submission_photos(array $data, int $submissionId): array
{
    $raw = $data['_photos'] ?? [];
    if (!is_array($raw) || !array_is_list($raw)) {
        throw new InvalidArgumentException('Invalid Resource submission photo list.');
    }
    if (count($raw) > llama_location_gallery_photo_limit('resource')) {
        throw new InvalidArgumentException('A Resource can have up to 10 photos.');
    }

    $photos = [];
    $seen = [];
    foreach ($raw as $photo) {
        if (!is_array($photo)) {
            throw new InvalidArgumentException('Invalid Resource photo.');
        }
        $path = trim((string) ($photo['path'] ?? ''));
        $prefix = '/uploads/resource-submissions/' . $submissionId . '/';
        if ($submissionId < 1
            || !str_starts_with($path, $prefix)
            || !preg_match('/^[A-Za-z0-9._-]+$/D', substr($path, strlen($prefix)))
            || str_contains($path, '..')
            || isset($seen[$path])) {
            throw new InvalidArgumentException('Invalid or duplicate Resource photo path.');
        }
        $seen[$path] = true;
        $photos[] = [
            'path' => $path,
            'alt' => mb_substr(trim((string) ($photo['alt'] ?? '')), 0, 300),
        ];
    }
    return $photos;
}

/**
 * A moderator can omit individual images without deleting the submission.
 * Never accept arbitrary user-provided paths as authoritative.
 */
function llama_resource_review_selected_photos(array $photos, array $excluded): array
{
    $known = array_column($photos, null, 'path');
    foreach ($excluded as $path) {
        if (!is_string($path) || !isset($known[$path])) {
            throw new InvalidArgumentException('Invalid Resource photo selection.');
        }
        unset($known[$path]);
    }
    return array_values($known);
}

/**
 * Copy approved pictures to their Resource gallery. Copy before transaction
 * commit; delete only those copies on rollback, never the evidence originals.
 */
function llama_resource_publish_submission_photos(
    PDO $db,
    int $submissionId,
    int $resourceId,
    int $contributorId,
    array $photos,
    array &$copiedPaths
): int {
    if (!$db->inTransaction()) {
        throw new RuntimeException('Resource photo approval requires a database transaction.');
    }
    $remaining = llama_location_gallery_photo_limit('resource')
        - count(llama_resource_images($db, $resourceId));
    if (count($photos) > $remaining) {
        throw new InvalidArgumentException(
            'This Resource has room for only ' . max(0, $remaining)
            . ' more photos. Uncheck some submission photos before approving.'
        );
    }

    $directory = dirname(__DIR__) . '/uploads/resources/' . $resourceId;
    if ($photos && !is_dir($directory)
        && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The Resource photo directory could not be created.');
    }
    $count = 0;
    foreach ($photos as $photo) {
        $photo = llama_resource_submission_photos(
            ['_photos' => [$photo]],
            $submissionId
        )[0];
        $source = dirname(__DIR__) . $photo['path'];
        if (!is_file($source)) {
            throw new RuntimeException('A submitted Resource photo is missing. Ask the contributor to resubmit.');
        }
        $relative = '/uploads/resources/' . $resourceId . '/'
            . bin2hex(random_bytes(12)) . '-' . basename($photo['path']);
        $destination = dirname(__DIR__) . $relative;
        if (!copy($source, $destination)) {
            throw new RuntimeException('Could not attach a reviewed Resource photo.');
        }
        $copiedPaths[] = $relative;
        llama_resource_image_insert(
            $db,
            $resourceId,
            $relative,
            $photo['alt'] !== '' ? $photo['alt'] : null,
            $contributorId > 0 ? $contributorId : null
        );
        $count++;
    }
    return $count;
}

/** Clean only submission-owned files after an approved/rejected terminal review. */
function llama_resource_submission_cleanup_photos(array $photos, int $submissionId): void
{
    foreach (llama_resource_submission_photos(['_photos' => $photos], $submissionId) as $photo) {
        llama_photo_delete_owned_permanent_path(
            $photo['path'],
            ['uploads/resource-submissions/' . $submissionId]
        );
    }
    $directory = dirname(__DIR__) . '/uploads/resource-submissions/' . $submissionId;
    if (is_dir($directory)) {
        @rmdir($directory);
    }
}
