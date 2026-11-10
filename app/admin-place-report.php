<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-places.php';
require_once __DIR__ . '/admin-users.php';
require_once __DIR__ . '/place-report.php';
require_once __DIR__ . '/photo-upload.php';
require_once __DIR__ . '/admin-place-sites.php';
require_once __DIR__ . '/place-campsite-media.php';
require_once __DIR__ . '/place-area-media.php';
require_once __DIR__ . '/location-photo-policy.php';
require_once __DIR__ . '/ridb-facility-details.php';
require_once __DIR__ . '/place-report/contact-links.php';

/**
 * Shared Admin Place Report service.
 *
 * Owns:
 * - Loading the published Place Report into canonical report data.
 * - Saving the shared Place-level report.
 * - Photo attachment/removal helpers used by the atomic report save.
 * - Background-save response helpers.
 *
 * Site-specific Admin logic lives in app/admin-place-sites.php.
 * Page rendering and POST routing remain outside this service.
 */
function admin_place_shared_report_data(
    PDO $db,
    int $placeId
): array {
    $place =
        admin_place_get(
            $db,
            $placeId
        );

    if (!$place) {
        throw new RuntimeException(
            'Place not found.'
        );
    }

    $place['amenities'] =
        admin_place_row(
            $db,
            'place_amenities',
            $placeId
        );

    $place['connectivity'] =
        admin_place_row(
            $db,
            'place_connectivity',
            $placeId
        );

    $place['details'] =
        admin_place_row(
            $db,
            'place_details',
            $placeId
        );

    $place['rules'] =
        admin_place_row(
            $db,
            'place_rules',
            $placeId
        );

    $place['experience'] =
        admin_place_row(
            $db,
            'place_experience',
            $placeId
        );

    $place['sensory'] = [
        'daytime' =>
            admin_place_sensory_period(
                $db,
                $placeId,
                'daytime'
            ),

        'nighttime' =>
            admin_place_sensory_period(
                $db,
                $placeId,
                'nighttime'
            ),
    ];

    $place['sensory_details'] =
        admin_place_row(
            $db,
            'place_sensory_details',
            $placeId
        );

    $data = llama_place_report_data_from_published_place(
        $place,
        llama_place_report_published_answer_state($db, $placeId)
    );

    // The public page already projects V3 current answers. Admin must also
    // reload an approved contact correction from that same canonical store,
    // even though imported official facts are held in separate source tables.
    $contactFields = array_fill_keys(
        array_keys(llama_place_report_official_contact_baselines([])),
        true
    );
    $current = llama_place_report_current_place_answers($db, $placeId);
    $unknown = array_fill_keys(llama_place_report_unknown_fields($data), true);
    foreach ($contactFields as $key => $_) {
        $answer = $current[$key] ?? null;
        if (!is_array($answer) || empty($answer['recorded'])) {
            continue;
        }
        $field = llama_report_question($key);
        if (!is_array($field)) {
            continue;
        }
        if (($answer['state'] ?? '') === 'unknown') {
            $unknown[$key] = true;
            llama_place_report_set_path($data, (string) $field['storage'], null);
        } else {
            unset($unknown[$key]);
            llama_place_report_set_path($data, (string) $field['storage'], $answer['value']);
        }
    }
    $data['_answer_state'] = array_keys($unknown);
    return $data;
}


function admin_place_save_shared_report(PDO $db, int $actorUserId, int $placeId, array $input): array
{
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) { $db->beginTransaction(); }
    try {
        $lock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
        $lock->execute([$placeId]);
        if (!$lock->fetchColumn()) { throw new RuntimeException('Place not found.'); }
        $place = admin_place_get($db, $placeId);
        $baseData = admin_place_shared_report_data($db, $placeId);
        // Admin edits must compare against the same effective answers that
        // Place, Area and Site readers use. Otherwise a V3-only question
        // can be lost when saving an unrelated legacy-column field.
        $unknown = array_fill_keys(llama_place_report_unknown_fields($baseData), true);
        foreach (llama_place_report_current_place_answers($db, $placeId) as $key => $answer) {
            if (empty($answer['recorded'])) {
                continue;
            }
            $field = llama_place_report_fields()[$key] ?? null;
            if (!is_array($field) || !in_array('place', (array) ($field['report_scopes'] ?? []), true)) {
                continue;
            }
            if (($answer['state'] ?? '') === 'unknown') {
                $unknown[$key] = true;
                llama_place_report_set_path($baseData, (string) $field['storage'], null);
            } else {
                unset($unknown[$key]);
                llama_place_report_set_path($baseData, (string) $field['storage'], $answer['value']);
            }
        }
        $baseData['_answer_state'] = array_keys($unknown);
        $placeLevelInput = admin_place_without_campsite_fields(llama_place_report_expand_presence($input));
        $allowed = llama_place_report_fields_for_scope('place');
        $placeLevelInput = array_intersect_key($placeLevelInput, $allowed);
        // Do not silently promote displayed RIDB facts into user-authored
        // answers when Admin saves an unrelated field. A changed value is
        // handled by the ordinary canonical current-answer publisher.
        $placeLevelInput = llama_place_report_contact_strip_unchanged_official(
            $placeLevelInput,
            llama_place_report_form_input_from_data($baseData),
            llama_place_facility_public_data($db, $placeId)
        );
        if (isset($input['_report_touched'])) {
            $placeLevelInput['_report_touched'] = array_intersect_key((array) $input['_report_touched'], $allowed);
        }
        $reportData = llama_place_report_build_data($placeLevelInput, $baseData);
        $changedKeys = llama_place_report_changed_keys(
            $baseData, $reportData, array_keys($placeLevelInput)
        );
        admin_place_save_report_metadata($db, $placeId, $place, $input);
        llama_place_report_publish_data($db, $placeId, $reportData, $changedKeys);
        admin_users_audit($db, $actorUserId, null, 'place.shared_report_updated',
            'Updated the shared Place Report.', ['place_id' => $placeId, 'field_keys' => $changedKeys]);
        if ($ownsTransaction) { $db->commit(); }
        return $reportData;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $db->inTransaction()) { $db->rollBack(); }
        throw $exception;
    }
}


function admin_place_photo_url(
    mixed $photo
): string {
    if (is_array($photo)) {
        $src =
            trim(
                (string) (
                    $photo['src']
                    ?? $photo['path']
                    ?? ''
                )
            );
    } else {
        $src =
            trim(
                (string) $photo
            );
    }

    if ($src === '') {
        return '';
    }

    if (
        preg_match(
            '#^https?://#i',
            $src
        )
    ) {
        return $src;
    }

    return
        'https://llamascout.com/'
        . ltrim(
            $src,
            '/'
        );
}


/**
 * Resolve the currently selected photo owner.
 *
 * Place photos remain in place_images.
 * Site photos use the existing place_campsite_images table.
 * Area photos are not given a second ad-hoc persistence system.
 */
function admin_place_report_photo_target(
    PDO $db,
    int $placeId,
    array $input
): array {
    $siteTarget =
        trim(
            (string) (
                $input['selected_campsite_id']
                ?? ''
            )
        );

    if ($siteTarget !== '') {
        $parsed =
            admin_place_parse_site_target(
                $siteTarget
            );

        $verified =
            llama_report_verified_target(
                $db,
                $placeId,
                'site',
                (string) $parsed['source'],
                (int) $parsed['id']
            );

        $source =
            (string) (
                $verified['source']
                ?? $parsed['source']
            );

        $targetId =
            (int) (
                $verified['id']
                ?? $parsed['id']
            );

        if ($source === 'campsite_record') {
            $campsiteId =
                $targetId;
        } else {
            $featureId =
                (int) (
                    $verified['map_feature_id']
                    ?? $targetId
                );

            $stmt =
                $db->prepare(
                    'SELECT id
                     FROM place_campsites
                     WHERE place_id = ?
                       AND map_feature_id = ?
                       AND is_active = 1
                     ORDER BY id ASC'
                );

            $stmt->execute([
                $placeId,
                $featureId,
            ]);

            $ids =
                array_values(
                    array_map(
                        'intval',
                        $stmt->fetchAll(
                            PDO::FETCH_COLUMN
                        )
                        ?: []
                    )
                );

            if (count($ids) !== 1) {
                throw new InvalidArgumentException(
                    'This Site could not be prepared for editing. Save the Site again and try once more.'
                );
            }

            $campsiteId =
                $ids[0];
        }

        if (
            !llama_place_campsite(
                $db,
                $placeId,
                $campsiteId
            )
        ) {
            throw new InvalidArgumentException(
                'The selected Site is not available for this Place.'
            );
        }

        return [
            'scope' => 'site',
            'source' => 'campsite_record',
            'id' => $campsiteId,
            'label' =>
                (string) (
                    $verified['label']
                    ?? ('Site #' . $campsiteId)
                ),
        ];
    }

    $areaTarget =
        trim(
            (string) (
                $input['selected_area_id']
                ?? ''
            )
        );

    if ($areaTarget !== '') {
        if (
            preg_match(
                '/^map_feature:([1-9][0-9]*)$/D',
                $areaTarget,
                $matches
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid Area reporting target.'
            );
        }

        $verified =
            llama_report_verified_target(
                $db,
                $placeId,
                'area',
                'map_feature',
                (int) $matches[1]
            );

        return [
            'scope' => 'area',
            'source' => 'map_feature',
            'id' => (int) $matches[1],
            'label' =>
                (string) (
                    $verified['label']
                    ?? ('Area #' . (int) $matches[1])
                ),
        ];
    }

    return [
        'scope' => 'place',
        'source' => 'place',
        'id' => $placeId,
        'label' => 'Entire Place',
    ];
}


/**
 * Return the gallery owned by one reporting target.
 */
function admin_place_report_target_images(
    PDO $db,
    int $placeId,
    array $target
): array {
    $scope =
        (string) (
            $target['scope']
            ?? ''
        );

    if ($scope === 'site') {
        return
            llama_place_campsite_images(
                $db,
                $placeId,
                (int) (
                    $target['id']
                    ?? 0
                )
            );
    }

    if ($scope === 'area') {
        return
            llama_place_area_images(
                $db,
                $placeId,
                (int) (
                    $target['id']
                    ?? 0
                )
            );
    }

    if ($scope === 'place') {
        return
            admin_place_images(
                $db,
                $placeId
            );
    }

    return [];
}


/**
 * Physical-file cleanup is safe only when no other gallery row still
 * references the same path. Imported photos can legitimately be shared.
 * A failed check retains the file rather than breaking someone else's image.
 */
function admin_place_gallery_photo_path_in_use(PDO $db, string $path): bool
{
    $path = trim($path);
    if ($path === '') {
        return true;
    }
    foreach (['place_images', 'place_campsite_images', 'place_area_images'] as $table) {
        try {
            $stmt = $db->prepare('SELECT 1 FROM ' . $table . ' WHERE src = ? LIMIT 1');
            $stmt->execute([$path]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        } catch (PDOException $exception) {
            // An older deployment may not have Area gallery storage yet.
            // Missing tables cannot own additional images. Fail closed for
            // any other database or schema error.
            if (!in_array((string) ($exception->errorInfo[1] ?? ''), ['1146', '42S02'], true)) {
                error_log('Llama Scout photo reference check failed: ' . $exception->getMessage());
                return true;
            }
        }
    }
    return false;
}

/**
 * Persist the in-form gallery order and captions on its existing owner.
 * This runs after requested removals and before attaching staged new photos,
 * so a newly uploaded photo simply appends to the ordered gallery.
 *
 * Reject stale, duplicated or foreign photo IDs. Never create a second
 * photo list or infer the owner from an image path.
 */
function admin_place_save_gallery_in_report_transaction(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $target,
    string $raw
): void {
    if ($raw === '') {
        return;
    }
    if (!$db->inTransaction()) {
        throw new RuntimeException('Gallery changes require the report transaction.');
    }
    if (strlen($raw) > 65536) {
        throw new InvalidArgumentException('The photo gallery update is too large.');
    }
    $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)
        || !isset($payload['target'], $payload['items'])
        || !is_string($payload['target'])
        || !is_array($payload['items'])
        || !array_is_list($payload['items'])) {
        throw new InvalidArgumentException('Invalid gallery order or captions.');
    }

    $scope = (string) ($target['scope'] ?? '');
    $source = (string) ($target['source'] ?? '');
    $targetId = (int) ($target['id'] ?? 0);
    $expectedOwner = $scope . ':' . $source . ':' . $targetId;
    if (!hash_equals($expectedOwner, $payload['target'])
        || !in_array($scope, ['place', 'site', 'area'], true)
        || $targetId < 1) {
        throw new InvalidArgumentException('The photo gallery belongs to a different Place or Site.');
    }
    $current = admin_place_report_target_images($db, $placeId, $target);
    $existingIds = array_map(static fn (array $image): int => (int) $image['id'], $current);

    $byId = [];
    foreach ($payload['items'] as $item) {
        if (!is_array($item)
            || !isset($item['id'])
            || (!is_int($item['id']) && !ctype_digit((string) $item['id']))
            || (int) $item['id'] < 1
            || !isset($item['alt'])
            || !is_string($item['alt'])) {
            throw new InvalidArgumentException('Invalid photo ID or caption.');
        }
        $id = (int) $item['id'];
        if (isset($byId[$id])) {
            throw new InvalidArgumentException('The same photo appeared twice in the gallery.');
        }
        $caption = trim($item['alt']);
        if (mb_strlen($caption) > 300) {
            throw new InvalidArgumentException('A photo caption exceeds 300 characters.');
        }
        $byId[$id] = $caption;
    }

    $requestedIds = array_keys($byId);
    $sortedExisting = $existingIds;
    $sortedRequested = $requestedIds;
    sort($sortedExisting);
    sort($sortedRequested);
    if ($sortedExisting !== $sortedRequested) {
        throw new InvalidArgumentException(
            'The photo gallery changed since you opened it. Reload before saving its order.'
        );
    }
    $needsChanges = false;
    foreach ($current as $index => $image) {
        $id = (int) $image['id'];
        $newIndex = array_search($id, $requestedIds, true);
        $oldCaption = trim((string) ($image['alt_text'] ?? ''));
        if ($newIndex !== $index
            || $oldCaption !== $byId[$id]
            || (int) ($image['is_featured'] ?? 0) !== ($index === 0 ? 1 : 0)
            || (int) ($image['sort_order'] ?? -1) !== $index) {
            $needsChanges = true;
            break;
        }
    }
    if (!$needsChanges) {
        return;
    }

    $table = match ($scope) {
        'place' => 'place_images',
        'site' => 'place_campsite_images',
        'area' => 'place_area_images',
    };
    $ownerColumn = match ($scope) {
        'place' => null,
        'site' => 'campsite_id',
        'area' => 'area_feature_id',
    };
    $sql = 'UPDATE ' . $table . '
            SET alt_text = ?, sort_order = ?, is_featured = ?
            WHERE id = ? AND place_id = ?';
    if ($ownerColumn !== null) {
        $sql .= ' AND ' . $ownerColumn . ' = ?';
    }
    $stmt = $db->prepare($sql);
    foreach ($requestedIds as $index => $id) {
        $args = [$byId[$id] !== '' ? $byId[$id] : null,
            $index, $index === 0 ? 1 : 0, $id, $placeId];
        if ($ownerColumn !== null) {
            $args[] = $targetId;
        }
        $stmt->execute($args);
    }

    admin_users_audit(
        $db, $actorUserId, null, 'place.photo_gallery_updated',
        'Reordered photos or updated captions.',
        ['place_id' => $placeId, 'scope' => $scope,
            'target_id' => $targetId, 'photo_ids' => $requestedIds]
    );
}


/**
 * Add photos while the outer Place Report transaction is active.
 *
 * Site photos use the existing campsite-media persistence layer.
 * Place photos continue using place_images.
 */
function admin_place_add_photos_in_report_transaction(
    PDO $db,
    int $actorUserId,
    int $placeId,
    string $photoToken,
    array $photos,
    array $target,
    array &$committedForCleanup
): int {
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Place Report photo attachment requires an active database transaction.'
        );
    }

    $scope =
        (string) (
            $target['scope']
            ?? ''
        );

    $existing =
        admin_place_report_target_images(
            $db,
            $placeId,
            $target
        );

    $limit = $scope === 'area'
        ? 10
        : llama_location_gallery_photo_limit($scope);

    $remaining =
        max(
            0,
            $limit - count($existing)
        );

    if ($remaining < 1) {
        throw new RuntimeException(
            match ($scope) {
                'site' =>
                    'This Site already has the maximum of 10 photos.',
                'area' =>
                    'This Area already has the maximum of 10 photos.',
                default =>
                    'This Place already has the maximum of 10 photos.',
            }
        );
    }

    if (count($photos) > $remaining) {
        throw new RuntimeException(
            'You can add only '
            . $remaining
            . ' more photos here.'
        );
    }

    if ($scope === 'site') {
        $campsiteId =
            (int) (
                $target['id']
                ?? 0
            );

        $areaFeatureId = 0;

        $destination =
            '/uploads/places/'
            . $placeId
            . '/sites/'
            . $campsiteId;
    } elseif ($scope === 'area') {
        $campsiteId = 0;

        $areaFeatureId =
            (int) (
                $target['id']
                ?? 0
            );

        $destination =
            '/uploads/places/'
            . $placeId
            . '/areas/'
            . $areaFeatureId;
    } else {
        $campsiteId = 0;
        $areaFeatureId = 0;

        $destination =
            '/uploads/places/'
            . $placeId;
    }

    $committed =
        llama_photo_commit_stage(
            'admin-place',
            $actorUserId,
            $photoToken,
            $photos,
            $destination
        );

    $committedForCleanup =
        $committed;

    $inserted = 0;

    if ($scope === 'site') {
        foreach ($committed as $photo) {
            $path =
                trim(
                    (string) (
                        $photo['path']
                        ?? ''
                    )
                );

            if ($path === '') {
                continue;
            }

            llama_place_campsite_image_insert(
                $db,
                $placeId,
                $campsiteId,
                $path,
                trim(
                    (string) (
                        $photo['alt']
                        ?? ''
                    )
                ) ?: null,
                null,
                $actorUserId,
                'Llama Scout'
            );

            $inserted++;
        }

        $auditAction =
            'place.campsite_photos_added';

        $auditMessage =
            'Added Site photos.';

        $auditMeta = [
            'place_id' => $placeId,
            'campsite_id' => $campsiteId,
            'count' => $inserted,
        ];

    } elseif ($scope === 'area') {
        foreach ($committed as $photo) {
            $path =
                trim(
                    (string) (
                        $photo['path']
                        ?? ''
                    )
                );

            if ($path === '') {
                continue;
            }

            llama_place_area_image_insert(
                $db,
                $placeId,
                $areaFeatureId,
                $path,
                trim(
                    (string) (
                        $photo['alt']
                        ?? ''
                    )
                ) ?: null,
                null,
                $actorUserId,
                'Llama Scout'
            );

            $inserted++;
        }

        $auditAction =
            'place.area_photos_added';

        $auditMessage =
            'Added Area photos.';

        $auditMeta = [
            'place_id' => $placeId,
            'area_feature_id' => $areaFeatureId,
            'count' => $inserted,
        ];

    } else {
        $orderStmt =
            $db->prepare(
                'SELECT COALESCE(MAX(sort_order), -1)
                 FROM place_images
                 WHERE place_id = ?'
            );

        $orderStmt->execute([
            $placeId,
        ]);

        $sortOrder =
            (int) $orderStmt->fetchColumn()
            + 1;

        $hasFeatured = false;

        foreach ($existing as $image) {
            if (
                (int) (
                    $image['is_featured']
                    ?? 0
                ) === 1
            ) {
                $hasFeatured = true;
                break;
            }
        }

        $insert =
            $db->prepare(
                'INSERT INTO place_images (
                    place_id,
                    src,
                    alt_text,
                    is_featured,
                    sort_order,
                    uploaded_by
                 ) VALUES (?, ?, ?, ?, ?, ?)'
            );

        foreach ($committed as $photo) {
            $path =
                trim(
                    (string) (
                        $photo['path']
                        ?? ''
                    )
                );

            if ($path === '') {
                continue;
            }

            $isFeatured =
                !$hasFeatured
                && $inserted === 0;

            $insert->execute([
                $placeId,
                $path,
                trim(
                    (string) (
                        $photo['alt']
                        ?? ''
                    )
                ) ?: null,
                $isFeatured ? 1 : 0,
                $sortOrder++,
                $actorUserId,
            ]);

            $inserted++;
        }

        $auditAction =
            'place.photos_added';

        $auditMessage =
            'Added Place photos.';

        $auditMeta = [
            'place_id' => $placeId,
            'count' => $inserted,
        ];
    }

    if ($inserted !== count($committed)) {
        throw new RuntimeException(
            'One or more photos could not be attached.'
        );
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        $auditAction,
        $auditMessage,
        $auditMeta
    );

    return $inserted;
}


/**
 * Remove a Place or Site image record inside the outer transaction.
 * Physical-file deletion remains deferred until after commit.
 */
function admin_place_delete_image_in_report_transaction(
    PDO $db,
    int $actorUserId,
    int $placeId,
    int $imageId,
    array $target
): string {
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Place Report photo removal requires an active database transaction.'
        );
    }

    $scope =
        (string) (
            $target['scope']
            ?? ''
        );

    if ($scope === 'site') {
        $campsiteId =
            (int) (
                $target['id']
                ?? 0
            );

        $image =
            llama_place_campsite_image(
                $db,
                $placeId,
                $campsiteId,
                $imageId
            );

        if (!$image) {
            throw new RuntimeException(
                'Site photo not found.'
            );
        }

        $path =
            trim(
                (string) (
                    $image['src']
                    ?? ''
                )
            );

        $wasFeatured =
            (int) (
                $image['is_featured']
                ?? 0
            ) === 1;

        $delete =
            $db->prepare(
                'DELETE FROM place_campsite_images
                 WHERE id = ?
                   AND place_id = ?
                   AND campsite_id = ?'
            );

        $delete->execute([
            $imageId,
            $placeId,
            $campsiteId,
        ]);

        if ($delete->rowCount() !== 1) {
            throw new RuntimeException(
                'Site photo could not be removed.'
            );
        }

        if ($wasFeatured) {
            $next =
                $db->prepare(
                    'SELECT id
                     FROM place_campsite_images
                     WHERE place_id = ?
                       AND campsite_id = ?
                     ORDER BY
                        sort_order ASC,
                        id ASC
                     LIMIT 1'
                );

            $next->execute([
                $placeId,
                $campsiteId,
            ]);

            $nextId =
                (int) (
                    $next->fetchColumn()
                    ?: 0
                );

            if ($nextId > 0) {
                llama_place_campsite_image_set_featured(
                    $db,
                    $placeId,
                    $campsiteId,
                    $nextId
                );
            }
        }

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.campsite_photo_deleted',
            'Deleted a Site photo.',
            [
                'place_id' => $placeId,
                'campsite_id' => $campsiteId,
                'image_id' => $imageId,
            ]
        );

        return $path;
    }

    if ($scope === 'area') {
        $areaFeatureId =
            (int) (
                $target['id']
                ?? 0
            );

        $image =
            llama_place_area_image(
                $db,
                $placeId,
                $areaFeatureId,
                $imageId
            );

        if (!$image) {
            throw new RuntimeException(
                'Area photo not found.'
            );
        }

        $path =
            trim(
                (string) (
                    $image['src']
                    ?? ''
                )
            );

        $wasFeatured =
            (int) (
                $image['is_featured']
                ?? 0
            ) === 1;

        $delete =
            $db->prepare(
                'DELETE FROM place_area_images
                 WHERE id = ?
                   AND place_id = ?
                   AND area_feature_id = ?'
            );

        $delete->execute([
            $imageId,
            $placeId,
            $areaFeatureId,
        ]);

        if ($delete->rowCount() !== 1) {
            throw new RuntimeException(
                'Area photo could not be removed.'
            );
        }

        if ($wasFeatured) {
            $next =
                $db->prepare(
                    'SELECT id
                     FROM place_area_images
                     WHERE place_id = ?
                       AND area_feature_id = ?
                     ORDER BY
                        sort_order ASC,
                        id ASC
                     LIMIT 1'
                );

            $next->execute([
                $placeId,
                $areaFeatureId,
            ]);

            $nextId =
                (int) (
                    $next->fetchColumn()
                    ?: 0
                );

            if ($nextId > 0) {
                llama_place_area_image_set_featured(
                    $db,
                    $placeId,
                    $areaFeatureId,
                    $nextId
                );
            }
        }

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.area_photo_deleted',
            'Deleted an Area photo.',
            [
                'place_id' => $placeId,
                'area_feature_id' => $areaFeatureId,
                'image_id' => $imageId,
            ]
        );

        return $path;
    }

    if ($scope !== 'place') {
        throw new RuntimeException(
            'Unsupported photo target.'
        );
    }

    $stmt =
        $db->prepare(
            'SELECT *
             FROM place_images
             WHERE id = ?
               AND place_id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $imageId,
        $placeId,
    ]);

    $image =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$image) {
        throw new RuntimeException(
            'Place image not found.'
        );
    }

    $path =
        trim(
            (string) (
                $image['src']
                ?? ''
            )
        );

    $wasFeatured =
        (int) (
            $image['is_featured']
            ?? 0
        ) === 1;

    $delete =
        $db->prepare(
            'DELETE FROM place_images
             WHERE id = ?
               AND place_id = ?'
        );

    $delete->execute([
        $imageId,
        $placeId,
    ]);

    if ($delete->rowCount() !== 1) {
        throw new RuntimeException(
            'Place image could not be removed.'
        );
    }

    if ($wasFeatured) {
        $next =
            $db->prepare(
                'SELECT id
                 FROM place_images
                 WHERE place_id = ?
                 ORDER BY
                    sort_order ASC,
                    id ASC
                 LIMIT 1'
            );

        $next->execute([
            $placeId,
        ]);

        $nextId =
            (int) (
                $next->fetchColumn()
                ?: 0
            );

        if ($nextId > 0) {
            $db->prepare(
                'UPDATE place_images
                 SET is_featured = 1
                 WHERE id = ?'
            )->execute([
                $nextId,
            ]);
        }
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.image_deleted',
        'Deleted a Place image.',
        [
            'place_id' => $placeId,
            'image_id' => $imageId,
        ]
    );

    return $path;
}


function admin_place_background_save_requested(): bool
{
    return
        strtolower(
            trim(
                (string) (
                    $_SERVER['HTTP_X_LLAMA_ADMIN_SAVE']
                    ?? ''
                )
            )
        )
        === 'place-report';
}

function admin_place_background_save_respond(
    int $status,
    array $payload
): never {
    http_response_code(
        $status
    );

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

/** Admin-only metadata keeps its existing boundary, separate from report answers. */
function admin_place_save_report_metadata(PDO $db, int $placeId, array $place, array $input): void
{
    $sets = []; $params = [];
    if (array_key_exists('admin_slug', $input)) {
        $slug = strtolower(trim((string) $input['admin_slug']));
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '', '-');
        if ($slug === '' || strlen($slug) > 190) { throw new InvalidArgumentException('Enter a URL slug of 1 to 190 characters.'); }
        $statement = $db->prepare('SELECT id FROM places WHERE slug = ? AND id <> ? LIMIT 1');
        $statement->execute([$slug, $placeId]);
        if ($statement->fetchColumn()) { throw new InvalidArgumentException('That URL slug is already used by another Place.'); }
        $sets[] = 'slug = ?'; $params[] = $slug;
    }
    if (array_key_exists('admin_source_type', $input)) {
        $source = trim((string) $input['admin_source_type']);
        if (!in_array($source, ['llama-scouted','community-scouted','external','legacy'], true)) {
            throw new InvalidArgumentException('Choose a valid Place source.');
        }
        $sets[] = 'source_type = ?'; $params[] = $source;
    }
    foreach (['admin_public_summary'=>'public_summary','admin_public_location_label'=>'public_location_label'] as $key=>$column) {
        if (!array_key_exists($key, $input)) { continue; }
        $sets[] = "`$column` = ?"; $params[] = trim((string) $input[$key]) ?: null;
    }
    if (!$sets) { return; }
    $params[] = $placeId;
    $db->prepare('UPDATE places SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
}
