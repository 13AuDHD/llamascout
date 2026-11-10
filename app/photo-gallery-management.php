<?php

declare(strict_types=1);

/**
 * Validate one photo editor snapshot against a specific gallery owner.
 * An image's numeric ID, not its path, determines which record can be edited.
 *
 * @return list<array{id:int,alt:string}>|null
 */
function llama_photo_gallery_validate_snapshot(
    string $raw,
    string $expectedOwner,
    array $existingImages
): ?array {
    if ($raw === '') {
        return null;
    }
    if (strlen($raw) > 65536) {
        throw new InvalidArgumentException('Photo gallery changes are too large.');
    }
    $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)
        || !isset($payload['target'], $payload['items'])
        || !is_string($payload['target'])
        || !hash_equals($expectedOwner, $payload['target'])
        || !is_array($payload['items'])
        || !array_is_list($payload['items'])) {
        throw new InvalidArgumentException(
            'This photo gallery changed owner. Reload the page before saving.'
        );
    }
    $seen = [];
    $items = [];
    foreach ($payload['items'] as $entry) {
        if (!is_array($entry)
            || !isset($entry['id'], $entry['alt'])
            || (!is_int($entry['id']) && !ctype_digit((string) $entry['id']))
            || (int) $entry['id'] < 1 || !is_string($entry['alt'])) {
            throw new InvalidArgumentException('Invalid photo ID or description.');
        }
        $id = (int) $entry['id'];
        if (isset($seen[$id])) {
            throw new InvalidArgumentException('A photo appears more than once.');
        }
        $alt = trim($entry['alt']);
        if (mb_strlen($alt) > 300) {
            throw new InvalidArgumentException('Photo descriptions are limited to 300 characters.');
        }
        $seen[$id] = true;
        $items[] = ['id' => $id, 'alt' => $alt];
    }
    $ids = array_column($items, 'id');
    $currentIds = array_map(
        static fn (array $image): int => (int) $image['id'],
        $existingImages
    );
    sort($ids);
    sort($currentIds);
    if ($ids !== $currentIds) {
        throw new InvalidArgumentException(
            'The gallery was modified in another session. Reload before changing its order.'
        );
    }
    return $items;
}

/**
 * Imported images may have the same source path in multiple galleries.
 * Avoid physical file deletion while *any* Place, Site, Area, Resource
 * or submitted report photo record still references that path.
 */
function llama_photo_gallery_path_in_use(PDO $db, string $path): bool
{
    if (trim($path) === '') {
        return true;
    }
    foreach ([
        'place_images', 'place_campsite_images', 'place_area_images',
        'resource_images',
    ] as $table) {
        try {
            $stmt = $db->prepare(
                'SELECT 1 FROM ' . $table . ' WHERE src = ? LIMIT 1'
            );
            $stmt->execute([$path]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        } catch (PDOException $exception) {
            // Legacy deployments may lack Area or Resource gallery tables.
            // Keep files if there is any other schema/database error.
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1146) {
                error_log('Photo reference lookup failed: ' . $exception->getMessage());
                return true;
            }
        }
    }
    return false;
}
