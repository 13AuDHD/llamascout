<?php

declare(strict_types=1);

require_once __DIR__ . '/points.php';


function llama_place_draft_csrf_token(): string
{
    if (empty($_SESSION['place_draft_csrf'])) {
        $_SESSION['place_draft_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['place_draft_csrf'];
}

function llama_place_draft_verify_csrf(string $token): bool
{
    $stored = (string) ($_SESSION['place_draft_csrf'] ?? '');

    return $stored !== '' && $token !== '' && hash_equals($stored, $token);
}

function llama_place_draft_clean_form_data(array $input): array
{
    $blocked = [
        'csrf_token',
        'place_draft_csrf',
        'save_for_later',
        'submit_for_review',
        'remove_saved_place',
    ];

    $clean = [];

    foreach ($input as $key => $value) {
        $key = (string) $key;

        if (in_array($key, $blocked, true)) {
            continue;
        }

        if (is_array($value)) {
            $clean[$key] = array_values(
                array_map(
                    static fn (mixed $item): string =>
                        mb_substr((string) $item, 0, 5000),
                    $value
                )
            );
            continue;
        }

        $clean[$key] = mb_substr((string) $value, 0, 10000);
    }

    unset(
        $clean['photo_stage_token'],
        $clean['photos_json'],
        $clean['draft_id']
    );

    return $clean;
}

function llama_place_draft_photo_relative_dir(int $userId, int $draftId): string
{
    return '/uploads/drafts/place/user-' . $userId . '/draft-' . $draftId;
}

function llama_place_draft_photo_absolute_dir(int $userId, int $draftId): string
{
    return dirname(__DIR__) . llama_place_draft_photo_relative_dir($userId, $draftId);
}

function llama_place_draft_decode_json_array(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : [];
}

function llama_place_draft_snapshot_photos(
    int $userId,
    int $draftId,
    string $stageToken,
    array $submittedPhotos
): array {
    $destinationRelative = llama_place_draft_photo_relative_dir($userId, $draftId);
    $destinationAbsolute = llama_place_draft_photo_absolute_dir($userId, $draftId);

    if (!$submittedPhotos) {
        llama_photo_remove_tree($destinationAbsolute);

        if ($stageToken !== '') {
            try {
                llama_photo_stage_abandon('add-place', $userId, $stageToken);
            } catch (Throwable) {
            }
        }

        return [];
    }

    if ($stageToken === '') {
        throw new InvalidArgumentException(
            'The photo upload session is missing. Please upload the photos again.'
        );
    }

    $stageToken = llama_photo_stage_token($stageToken);
    $manifest = llama_photo_read_manifest('add-place', $userId, $stageToken);
    $manifestByPath = [];

    foreach ($manifest as $photo) {
        if (!is_array($photo)) {
            continue;
        }

        $path = trim((string) ($photo['path'] ?? ''));
        if ($path !== '') {
            $manifestByPath[$path] = $photo;
        }
    }

    $expectedPrefix = llama_photo_stage_relative_dir('add-place', $userId, $stageToken) . '/';
    $tmpAbsolute = $destinationAbsolute . '-tmp-' . bin2hex(random_bytes(5));

    if (!mkdir($tmpAbsolute, 0755, true) && !is_dir($tmpAbsolute)) {
        throw new RuntimeException('The draft photo directory could not be created.');
    }

    $saved = [];

    try {
        foreach ($submittedPhotos as $submitted) {
            if (!is_array($submitted)) {
                continue;
            }

            $sourceRelative = trim((string) ($submitted['path'] ?? ''));

            if (
                $sourceRelative === ''
                || !str_starts_with($sourceRelative, $expectedPrefix)
                || !isset($manifestByPath[$sourceRelative])
            ) {
                continue;
            }

            $sourceAbsolute = dirname(__DIR__) . $sourceRelative;
            if (!is_file($sourceAbsolute)) {
                throw new RuntimeException('A staged photo is missing. Please upload it again.');
            }

            $manifestPhoto = $manifestByPath[$sourceRelative];
            $filename = basename((string) ($manifestPhoto['filename'] ?? $sourceRelative));

            if ($filename === '') {
                throw new RuntimeException('A draft photo has an invalid filename.');
            }

            if (!copy($sourceAbsolute, $tmpAbsolute . '/' . $filename)) {
                throw new RuntimeException('A photo could not be saved with this draft.');
            }

            $savedRelative = $destinationRelative . '/' . $filename;

            $saved[] = [
                'path' => $savedRelative,
                'url' => llama_photo_public_url($savedRelative),
                'filename' => $filename,
                'original_name' => (string) ($manifestPhoto['original_name'] ?? ''),
                'mime_type' => (string) ($manifestPhoto['mime_type'] ?? 'image/jpeg'),
                'width' => (int) ($manifestPhoto['width'] ?? 0),
                'height' => (int) ($manifestPhoto['height'] ?? 0),
                'size' => (int) ($manifestPhoto['size'] ?? 0),
                'alt' => mb_substr(
                    trim((string) ($submitted['alt'] ?? $manifestPhoto['alt'] ?? '')),
                    0,
                    300
                ),
            ];
        }

        if (!$saved) {
            throw new RuntimeException('No uploaded photos were available to save with this draft.');
        }

        $backupAbsolute = $destinationAbsolute . '-backup-' . bin2hex(random_bytes(5));
        $hadExisting = is_dir($destinationAbsolute);

        if ($hadExisting && !rename($destinationAbsolute, $backupAbsolute)) {
            throw new RuntimeException('The previous draft photos could not be prepared for replacement.');
        }

        try {
            if (!rename($tmpAbsolute, $destinationAbsolute)) {
                throw new RuntimeException('The draft photos could not be finalized.');
            }

            if ($hadExisting) {
                llama_photo_remove_tree($backupAbsolute);
            }
        } catch (Throwable $exception) {
            if ($hadExisting && is_dir($backupAbsolute) && !is_dir($destinationAbsolute)) {
                @rename($backupAbsolute, $destinationAbsolute);
            }
            throw $exception;
        }

        llama_photo_stage_abandon('add-place', $userId, $stageToken);

        return $saved;
    } catch (Throwable $exception) {
        llama_photo_remove_tree($tmpAbsolute);
        throw $exception;
    }
}

function llama_place_draft_restore_photos(
    int $userId,
    int $draftId,
    array $savedPhotos
): array {
    if (!$savedPhotos) {
        return ['token' => '', 'photos' => []];
    }

    $token = llama_photo_stage_token();
    $stageRelative = llama_photo_stage_relative_dir('add-place', $userId, $token);
    $stageAbsolute = llama_photo_stage_absolute_dir('add-place', $userId, $token);

    if (!mkdir($stageAbsolute, 0755, true) && !is_dir($stageAbsolute)) {
        throw new RuntimeException('The draft photos could not be prepared for editing.');
    }

    $restored = [];

    try {
        $allowedPrefix = llama_place_draft_photo_relative_dir($userId, $draftId) . '/';

        foreach ($savedPhotos as $photo) {
            if (!is_array($photo)) {
                continue;
            }

            $sourceRelative = trim((string) ($photo['path'] ?? ''));

            if ($sourceRelative === '' || !str_starts_with($sourceRelative, $allowedPrefix)) {
                continue;
            }

            $sourceAbsolute = dirname(__DIR__) . $sourceRelative;
            if (!is_file($sourceAbsolute)) {
                continue;
            }

            $filename = basename((string) ($photo['filename'] ?? $sourceRelative));
            if (!copy($sourceAbsolute, $stageAbsolute . '/' . $filename)) {
                throw new RuntimeException('A saved draft photo could not be restored.');
            }

            $stagePath = $stageRelative . '/' . $filename;
            $restored[] = [
                'path' => $stagePath,
                'url' => llama_photo_public_url($stagePath),
                'filename' => $filename,
                'original_name' => (string) ($photo['original_name'] ?? ''),
                'mime_type' => (string) ($photo['mime_type'] ?? 'image/jpeg'),
                'width' => (int) ($photo['width'] ?? 0),
                'height' => (int) ($photo['height'] ?? 0),
                'size' => (int) ($photo['size'] ?? 0),
                'alt' => mb_substr(trim((string) ($photo['alt'] ?? '')), 0, 300),
            ];
        }

        if (!$restored) {
            llama_photo_remove_tree($stageAbsolute);
            return ['token' => '', 'photos' => []];
        }

        llama_photo_write_manifest('add-place', $userId, $token, $restored);

        return ['token' => $token, 'photos' => $restored];
    } catch (Throwable $exception) {
        llama_photo_remove_tree($stageAbsolute);
        throw $exception;
    }
}

function llama_place_draft_for_user(PDO $db, int $userId, int $draftId): ?array
{
    if ($userId < 1 || $draftId < 1) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT * FROM place_drafts WHERE id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->execute([$draftId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    $row['form_data'] = llama_place_draft_decode_json_array($row['form_data'] ?? '[]');
    $row['photos'] = llama_place_draft_decode_json_array($row['photos_json'] ?? '[]');

    return $row;
}

function llama_place_drafts_for_user(PDO $db, int $userId): array
{
    $stmt = $db->prepare(
        'SELECT * FROM place_drafts WHERE user_id = ? ORDER BY updated_at DESC, id DESC'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $row['form_data'] = llama_place_draft_decode_json_array($row['form_data'] ?? '[]');
        $row['photos'] = llama_place_draft_decode_json_array($row['photos_json'] ?? '[]');
    }
    unset($row);

    return $rows;
}

function llama_place_draft_count(PDO $db, int $userId): int
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM place_drafts WHERE user_id = ?');
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function llama_place_draft_save(PDO $db, int $userId, int $draftId, array $input): int
{
    if ($userId < 1) {
        throw new InvalidArgumentException('A signed-in account is required.');
    }

    $formData = llama_place_draft_clean_form_data($input);
    $name = trim((string) ($formData['name'] ?? ''));
    $name = $name !== '' ? mb_substr($name, 0, 200) : 'Untitled Place';
    $formJson = json_encode(
        $formData,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    if ($draftId > 0) {
        if (!llama_place_draft_for_user($db, $userId, $draftId)) {
            throw new RuntimeException('That saved Place could not be found.');
        }

        $stmt = $db->prepare(
            'UPDATE place_drafts
             SET draft_name = ?, form_data = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$name, $formJson, $draftId, $userId]);
    } else {
        $stmt = $db->prepare(
            'INSERT INTO place_drafts
                (user_id, draft_name, form_data, photos_json, created_at, updated_at)
             VALUES (?, ?, ?, "[]", UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([$userId, $name, $formJson]);
        $draftId = (int) $db->lastInsertId();
    }

    $stageToken = trim((string) ($input['photo_stage_token'] ?? ''));
    $submittedPhotos = llama_photo_decode_form_photos($input['photos_json'] ?? '[]');
    $savedPhotos = llama_place_draft_snapshot_photos(
        $userId,
        $draftId,
        $stageToken,
        $submittedPhotos
    );

    $photosJson = json_encode(
        $savedPhotos,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $stmt = $db->prepare(
        'UPDATE place_drafts
         SET photos_json = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$photosJson, $draftId, $userId]);

    return $draftId;
}

function llama_place_draft_delete(PDO $db, int $userId, int $draftId): bool
{
    if (!llama_place_draft_for_user($db, $userId, $draftId)) {
        return false;
    }

    $stmt = $db->prepare('DELETE FROM place_drafts WHERE id = ? AND user_id = ?');
    $stmt->execute([$draftId, $userId]);

    llama_photo_remove_tree(llama_place_draft_photo_absolute_dir($userId, $draftId));

    return true;
}

function llama_place_draft_progress(
    PDO $db,
    array $data,
    int $photoCount
): array {
    return llama_points_estimate_new_place(
        $db,
        $data,
        $photoCount
    );
}
