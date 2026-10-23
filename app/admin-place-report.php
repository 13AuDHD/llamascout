<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-places.php';
require_once __DIR__ . '/admin-users.php';
require_once __DIR__ . '/place-report.php';
require_once __DIR__ . '/photo-upload.php';
require_once __DIR__ . '/admin-place-sites.php';
require_once __DIR__ . '/place-campsite-media.php';

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

    return
        llama_place_report_data_from_published_place(
            $place,
            llama_place_report_published_answer_state(
                $db,
                $placeId
            )
        );
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
        $placeLevelInput = admin_place_without_campsite_fields(llama_place_report_expand_presence($input));
        $allowed = llama_place_report_fields_for_scope('place');
        $placeLevelInput = array_intersect_key($placeLevelInput, $allowed);
        if (isset($input['_report_touched'])) {
            $placeLevelInput['_report_touched'] = array_intersect_key((array) $input['_report_touched'], $allowed);
        }
        $reportData = llama_place_report_build_data($placeLevelInput, $baseData);
        admin_place_save_report_metadata($db, $placeId, $place, $input);
        llama_place_report_publish_data($db, $placeId, $reportData,
            llama_place_report_changed_keys($baseData, $reportData, array_keys($placeLevelInput)));
        admin_users_audit($db, $actorUserId, null, 'place.shared_report_updated',
            'Updated the shared Place Report.', ['place_id' => $placeId, 'field_keys' => array_keys($placeLevelInput)]);
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

    if ($scope === 'place') {
        return
            admin_place_images(
                $db,
                $placeId
            );
    }

    /*
     * Area photo persistence is intentionally not invented here. A proper
     * Area-media owner can be added later without polluting
     * place_images or place_campsite_images.
     */
    return [];
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

    if ($scope === 'area') {
        throw new RuntimeException(
            'Photos are not available for Areas yet.'
        );
    }

    $existing =
        admin_place_report_target_images(
            $db,
            $placeId,
            $target
        );

    $limit =
        $scope === 'site'
            ? 10
            : 30;

    $remaining =
        max(
            0,
            $limit - count($existing)
        );

    if ($remaining < 1) {
        throw new RuntimeException(
            $scope === 'site'
                ? 'This Site already has the maximum of 10 photos.'
                : 'This Place already has the maximum of 30 photos.'
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

        $destination =
            '/uploads/places/'
            . $placeId
            . '/sites/'
            . $campsiteId;
    } else {
        $campsiteId = 0;

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

    if ($scope !== 'place') {
        throw new RuntimeException(
            'Photos are not available for Areas yet.'
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
