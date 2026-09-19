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

    /*
     * The server-side staging manifest is the source of truth for a live
     * uploader batch. The browser's photos_json field is useful for captions
     * and ordering, but a stale/missed hidden-field sync must never turn a
     * valid staged batch into "zero photos" and detach it from the draft.
     *
     * This matters especially for Save for Later, which uses a background
     * fetch rather than the form's normal submit event. If JavaScript misses
     * one sync, the files can still be safely recovered from their manifest.
     */
    if ($stageToken === '') {
        if (!$submittedPhotos) {
            llama_photo_remove_tree($destinationAbsolute);
            return [];
        }

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

    /*
     * If the stage itself is empty, the user really has removed every staged
     * photo. Clear the draft snapshot and retire that empty stage.
     */
    if (!$manifestByPath) {
        llama_photo_remove_tree($destinationAbsolute);

        try {
            llama_photo_stage_abandon('add-place', $userId, $stageToken);
        } catch (Throwable) {
        }

        return [];
    }

    /*
     * Merge browser metadata over the manifest, but never require the browser
     * list to contain every photo. A valid manifest entry must survive even if
     * photos_json arrived empty or stale.
     */
    $submittedByPath = [];

    foreach ($submittedPhotos as $submitted) {
        if (!is_array($submitted)) {
            continue;
        }

        $path = trim((string) ($submitted['path'] ?? ''));
        if ($path !== '') {
            $submittedByPath[$path] = $submitted;
        }
    }

    $submittedPhotos = [];

    foreach ($manifestByPath as $path => $manifestPhoto) {
        $submitted = $submittedByPath[$path] ?? [];

        $submittedPhotos[] = array_merge(
            $manifestPhoto,
            is_array($submitted) ? $submitted : [],
            ['path' => $path]
        );
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


function llama_place_draft_recoverable_stages(
    int $userId,
    int $maxAgeSeconds = 86400
): array {
    if ($userId < 1) {
        return [];
    }

    $root = dirname(__DIR__)
        . '/uploads/staging/add-place/user-'
        . $userId;

    if (!is_dir($root)) {
        return [];
    }

    $now = time();
    $batches = [];
    $entries = @scandir($root);

    if (!is_array($entries)) {
        return [];
    }

    foreach ($entries as $token) {
        if (!preg_match('/^[a-f0-9]{32}$/', (string) $token)) {
            continue;
        }

        $directory = $root . '/' . $token;
        $manifestPath = $directory . '/manifest.json';

        if (!is_dir($directory) || !is_file($manifestPath)) {
            continue;
        }

        $modifiedAt = (int) @filemtime($manifestPath);
        if (
            $modifiedAt < 1
            || ($now - $modifiedAt) > $maxAgeSeconds
        ) {
            continue;
        }

        $manifest = llama_photo_read_manifest(
            'add-place',
            $userId,
            (string) $token
        );

        $validPhotos = [];
        foreach ($manifest as $photo) {
            if (!is_array($photo)) {
                continue;
            }

            $relative = trim((string) ($photo['path'] ?? ''));
            if (
                $relative === ''
                || !is_file(dirname(__DIR__) . $relative)
            ) {
                continue;
            }

            $validPhotos[] = $photo;
        }

        if (!$validPhotos) {
            continue;
        }

        $batches[] = [
            'token' => (string) $token,
            'count' => count($validPhotos),
            'modified_at' => $modifiedAt,
        ];
    }

    usort(
        $batches,
        static fn (array $a, array $b): int =>
            ((int) ($b['modified_at'] ?? 0))
            <=> ((int) ($a['modified_at'] ?? 0))
    );

    return $batches;
}

function llama_place_draft_recover_staged_photos(
    PDO $db,
    int $userId,
    int $draftId,
    string $stageToken
): int {
    if ($userId < 1 || $draftId < 1) {
        throw new InvalidArgumentException('A saved Place is required.');
    }

    $draft = llama_place_draft_for_user($db, $userId, $draftId);
    if (!$draft) {
        throw new RuntimeException('That saved Place could not be found.');
    }

    if (!empty($draft['photos'])) {
        throw new RuntimeException('This saved Place already has photos attached.');
    }

    $stageToken = llama_photo_stage_token($stageToken);
    $manifest = llama_photo_read_manifest('add-place', $userId, $stageToken);

    if (!$manifest) {
        throw new RuntimeException('That staged photo batch is no longer available.');
    }

    $savedPhotos = llama_place_draft_snapshot_photos(
        $userId,
        $draftId,
        $stageToken,
        $manifest
    );

    $photosJson = json_encode(
        $savedPhotos,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_THROW_ON_ERROR
    );

    $stmt = $db->prepare(
        'UPDATE place_drafts
         SET photos_json = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$photosJson, $draftId, $userId]);

    return count($savedPhotos);
}

function llama_place_draft_save(PDO $db, int $userId, int $draftId, array $input): int
{
    if ($userId < 1) {
        throw new InvalidArgumentException('A signed-in account is required.');
    }

    $formData = llama_place_draft_clean_form_data($input);

    $saveToken =
        trim(
            (string) (
                $formData['draft_save_token']
                ?? ''
            )
        );

    if (
        !preg_match(
            '/^[a-f0-9]{64}$/',
            $saveToken
        )
    ) {
        throw new RuntimeException(
            'This Place form does not have a valid save token. Reload the form and try again.'
        );
    }

    $name = trim((string) ($formData['name'] ?? ''));
    $name = $name !== '' ? mb_substr($name, 0, 200) : 'Untitled Place';
    $formJson = json_encode(
        $formData,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $lockName =
        'llama_place_draft_'
        . $userId
        . '_'
        . substr(
            hash(
                'sha256',
                $saveToken
            ),
            0,
            32
        );

    $lockStmt =
        $db->prepare(
            'SELECT GET_LOCK(?, 5)'
        );

    $lockStmt->execute([
        $lockName,
    ]);

    if (
        (int) $lockStmt->fetchColumn()
        !== 1
    ) {
        throw new RuntimeException(
            'This Place is already being saved. Please wait a moment.'
        );
    }

    try {
        if ($draftId < 1) {
            $existingStmt =
                $db->prepare(
                    "SELECT id
                     FROM place_drafts
                     WHERE user_id = ?
                       AND JSON_UNQUOTE(
                            JSON_EXTRACT(
                                form_data,
                                '$.draft_save_token'
                            )
                       ) = ?
                     ORDER BY id DESC
                     LIMIT 1"
                );

            $existingStmt->execute([
                $userId,
                $saveToken,
            ]);

            $existingDraftId =
                (int) (
                    $existingStmt->fetchColumn()
                    ?: 0
                );

            if ($existingDraftId > 0) {
                /*
                 * The browser can legitimately POST an older Add Place page
                 * without draft_id after this save token has already created a
                 * draft. That is especially common after Save for Later + Back.
                 *
                 * Returning here used to skip the entire update/photo snapshot
                 * path. The visible form looked saved, but newly staged photos
                 * stayed orphaned in /uploads/staging and the draft kept zero
                 * photos. Reattach this save to the existing draft instead.
                 */
                $draftId = $existingDraftId;
            }
        }

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

        /*
         * A duplicate/stale POST can arrive after an earlier successful save
         * already consumed the staging token. In that case, keep the photos
         * already attached to the draft instead of replacing them with an
         * empty list or throwing a misleading upload-session error.
         *
         * When a live stage still exists, always snapshot it. That is the path
         * that recovers photos added from a browser page which no longer has a
         * draft_id but does still have the original draft_save_token.
         */
        $currentDraft = llama_place_draft_for_user($db, $userId, $draftId);
        $currentDraftPhotos =
            is_array($currentDraft['photos'] ?? null)
                ? $currentDraft['photos']
                : [];

        $stageHasManifest = false;
        if ($stageToken !== '') {
            try {
                $stageToken = llama_photo_stage_token($stageToken);
                $stageHasManifest =
                    llama_photo_stage_manifest_exists(
                        'add-place',
                        $userId,
                        $stageToken
                    );
            } catch (Throwable) {
                $stageHasManifest = false;
            }
        }

        /*
         * A saved draft's permanent photo snapshot is authoritative whenever
         * there is no live staging manifest. Missing client state, an expired
         * token, a stale browser recovery token, or JavaScript failing to
         * initialize must never mean "delete all saved photos".
         *
         * An intentional remove-all is still supported: the uploader leaves an
         * existing staging manifest containing zero photos. In that case
         * $stageHasManifest is true and snapshot_photos() deliberately clears
         * the permanent draft photo directory.
         */
        if (!$stageHasManifest && $currentDraftPhotos) {
            $savedPhotos = $currentDraftPhotos;
        } elseif (!$stageHasManifest && $submittedPhotos) {
            throw new RuntimeException(
                'The photo upload session is no longer available. Reload the Place and try saving again.'
            );
        } else {
            $savedPhotos = llama_place_draft_snapshot_photos(
                $userId,
                $draftId,
                $stageToken,
                $submittedPhotos
            );
        }

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

    } finally {
        try {
            $releaseStmt =
                $db->prepare(
                    'SELECT RELEASE_LOCK(?)'
                );

            $releaseStmt->execute([
                $lockName,
            ]);
        } catch (Throwable) {
        }
    }
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
