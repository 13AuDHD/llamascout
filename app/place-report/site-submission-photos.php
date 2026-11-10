<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/location-photo-policy.php';

/**
 * Photo evidence for the first Site of a new Place. The Site does not have a
 * database ID until approval, so the owned submission folder is its identity.
 * Never put these photographs in the Place gallery as a fallback.
 */
function llama_place_report_first_site_photos(array $data, int $submissionId): array
{
    $photos = $data['site_photos'] ?? [];
    if (!is_array($photos) || !array_is_list($photos)
        || count($photos) > llama_location_gallery_photo_limit('site')) {
        throw new InvalidArgumentException('First Site photos must be a list of up to 10.');
    }

    $prefix = '/uploads/place-submissions/' . $submissionId . '/site-photos/';
    $seen = [];
    $normalized = [];
    foreach ($photos as $photo) {
        if (!is_array($photo)) {
            throw new InvalidArgumentException('Invalid first Site photo.');
        }
        $path = trim((string) ($photo['path'] ?? $photo['src'] ?? ''));
        $name = substr($path, strlen($prefix));
        if ($submissionId < 1 || !str_starts_with($path, $prefix)
            || $name === '' || str_contains($name, '..')
            || !preg_match('/^[a-zA-Z0-9._-]+$/D', $name)
            || isset($seen[$path])) {
            throw new InvalidArgumentException('First Site photo is not owned by this Place submission.');
        }
        $seen[$path] = true;
        $normalized[] = $photo + ['path' => $path];
    }
    return $normalized;
}
