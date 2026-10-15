<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-places.php';
require_once __DIR__ . '/admin-users.php';
require_once __DIR__ . '/place-report.php';
require_once __DIR__ . '/photo-upload.php';
require_once __DIR__ . '/admin-place-sites.php';

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


function admin_place_save_shared_report(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $input
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

    $baseData =
        admin_place_shared_report_data(
            $db,
            $placeId
        );

    $placeLevelInput =
        admin_place_without_campsite_fields(
            $input
        );

    $reportData =
        llama_place_report_build_data(
            $placeLevelInput,
            $baseData
        );

    $coreData = [
        'name' =>
            $reportData['name']
            ?? $place['name']
            ?? '',

        'type' =>
            $reportData['type']
            ?? $place['type']
            ?? 'other',

        'slug' =>
            trim(
                (string) (
                    $input['admin_slug']
                    ?? $place['slug']
                    ?? ''
                )
            ),

        'source_type' =>
            trim(
                (string) (
                    $input['admin_source_type']
                    ?? $place['source_type']
                    ?? 'llama-scouted'
                )
            ),

        'description' =>
            $reportData['description']
            ?? null,

        'public_summary' =>
            trim(
                (string) (
                    $input['admin_public_summary']
                    ?? $place['public_summary']
                    ?? ''
                )
            ),

        'public_location_label' =>
            trim(
                (string) (
                    $input['admin_public_location_label']
                    ?? $place['public_location_label']
                    ?? ''
                )
            ),

        'latitude' =>
            $reportData['latitude']
            ?? null,

        'longitude' =>
            $reportData['longitude']
            ?? null,

        'public_latitude' =>
            $place['public_latitude']
            ?? null,

        'public_longitude' =>
            $place['public_longitude']
            ?? null,

        'elevation_feet' =>
            $reportData['elevation_feet']
            ?? null,

        'road' =>
            $reportData['road']
            ?? null,

        'city' =>
            $reportData['city']
            ?? null,

        'county' =>
            $reportData['county']
            ?? null,

        'state' =>
            $reportData['state']
            ?? null,

        'region' =>
            $reportData['region']
            ?? null,

        'land_manager' =>
            $reportData['land_manager']
            ?? null,

        'land_type' =>
            $reportData['land_type']
            ?? null,

        'sensory_summary' =>
            $reportData['sensory_summary']
            ?? null,

        'access_summary' =>
            $reportData['access_summary']
            ?? null,
    ];

    $amenities =
        is_array(
            $reportData['amenities']
            ?? null
        )
            ? $reportData['amenities']
            : [];

    $connectivity =
        is_array(
            $reportData['connectivity']
            ?? null
        )
            ? $reportData['connectivity']
            : [];

    $details =
        is_array(
            $reportData['details']
            ?? null
        )
            ? $reportData['details']
            : [];

    $rules =
        is_array(
            $reportData['rules']
            ?? null
        )
            ? $reportData['rules']
            : [];

    $experience =
        is_array(
            $reportData['experience']
            ?? null
        )
            ? $reportData['experience']
            : [];

    $sensory =
        is_array(
            $reportData['sensory']
            ?? null
        )
            ? $reportData['sensory']
            : [];

    $sensoryPayload =
        is_array(
            $sensory['details']
            ?? null
        )
            ? $sensory['details']
            : [];

    foreach (
        [
            'daytime',
            'nighttime',
        ]
        as $period
    ) {
        $periodData =
            is_array(
                $sensory[$period]
                ?? null
            )
                ? $sensory[$period]
                : [];

        foreach (
            [
                'noise',
                'traffic',
                'crowds',
                'privacy',
                'light_pollution',
                'sensory_comfort',
                'social_interaction_likelihood',
            ]
            as $field
        ) {
            $sensoryPayload[
                $period
                . '_'
                . $field
            ] =
                $periodData[$field]
                ?? null;
        }
    }

    $ownsTransaction =
        !$db->inTransaction();

    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        admin_place_save_core(
            $db,
            $actorUserId,
            $placeId,
            $coreData
        );

        admin_place_save_amenities(
            $db,
            $actorUserId,
            $placeId,
            $amenities
        );

        admin_place_save_connectivity(
            $db,
            $actorUserId,
            $placeId,
            $connectivity
        );

        admin_place_save_details(
            $db,
            $actorUserId,
            $placeId,
            $details
        );

        admin_place_save_sensory_details(
            $db,
            $actorUserId,
            $placeId,
            $sensoryPayload
        );

        admin_place_save_rules(
            $db,
            $actorUserId,
            $placeId,
            $rules
        );

        admin_place_save_experience(
            $db,
            $actorUserId,
            $placeId,
            $experience
        );

        llama_place_report_publish_answer_state(
            $db,
            $placeId,
            $reportData
        );

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.shared_report_updated',
            'Updated the shared Place Report.',
            [
                'place_id' =>
                    $placeId,
            ]
        );

        if ($ownsTransaction) {
            $db->commit();
        }

    } catch (Throwable $exception) {
        if (
            $ownsTransaction
            && $db->inTransaction()
        ) {
            $db->rollBack();
        }

        throw $exception;
    }

    return $reportData;
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
 * Resolve the canonical photo target from the currently selected reporting
 * target. Site wins when both selectors are populated because the photo
 * uploader lives in the Site section.
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

        return
            llama_report_verified_target(
                $db,
                $placeId,
                'site',
                (string) $parsed['source'],
                (int) $parsed['id']
            );
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
                'Invalid Area photo target.'
            );
        }

        return
            llama_report_verified_target(
                $db,
                $placeId,
                'area',
                'map_feature',
                (int) $matches[1]
            );
    }

    return [
        'scope' => 'place',
        'source' => 'place',
        'id' => $placeId,
        'parent_area_id' => null,
        'map_feature_id' => null,
        'label' => 'Entire Place',
    ];
}


/**
 * Read only photos belonging to one canonical reporting target.
 */
function admin_place_report_target_images(
    PDO $db,
    int $placeId,
    array $target
): array {
    $stmt =
        $db->prepare(
            'SELECT *
             FROM place_images
             WHERE place_id = ?
               AND report_scope = ?
               AND scope_source = ?
               AND scope_id = ?
             ORDER BY
                is_featured DESC,
                sort_order ASC,
                id ASC'
        );

    $stmt->execute([
        $placeId,
        (string) $target['scope'],
        (string) $target['source'],
        (int) $target['id'],
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];
}


/*
 * Add photos while an outer database transaction is already active.
 *
 * The selected Place / Area / Site target is stored on the existing
 * place_images row. This keeps one image system while allowing individual
 * Areas and Sites to own their own photo galleries.
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

    $existing =
        admin_place_report_target_images(
            $db,
            $placeId,
            $target
        );

    $scope =
        (string) (
            $target['scope']
            ?? ''
        );

    $limit =
        $scope === 'place'
            ? 30
            : 10;

    $remaining =
        max(
            0,
            $limit - count($existing)
        );

    if ($remaining < 1) {
        throw new RuntimeException(
            $scope === 'place'
                ? 'This Place already has the maximum of 30 photos.'
                : 'This reporting target already has the maximum of 10 photos.'
        );
    }

    if (count($photos) > $remaining) {
        throw new RuntimeException(
            'You can add only '
            . $remaining
            . ' more photos to this reporting target.'
        );
    }

    $source =
        (string) (
            $target['source']
            ?? ''
        );

    $scopeId =
        (int) (
            $target['id']
            ?? 0
        );

    $destination =
        '/uploads/places/'
        . $placeId;

    if ($scope === 'area') {
        $destination .=
            '/areas/'
            . $source
            . '-'
            . $scopeId;
    } elseif ($scope === 'site') {
        $destination .=
            '/sites/'
            . $source
            . '-'
            . $scopeId;
    }

    $committed =
        llama_photo_commit_stage(
            'admin-place',
            $actorUserId,
            $photoToken,
            $photos,
            $destination
        );

    /*
     * Expose moved files immediately. Anything below this point can
     * still throw, including a DB insert or the outer transaction commit.
     */
    $committedForCleanup =
        $committed;

    $orderStmt =
        $db->prepare(
            'SELECT COALESCE(MAX(sort_order), -1)
             FROM place_images
             WHERE place_id = ?
               AND report_scope = ?
               AND scope_source = ?
               AND scope_id = ?'
        );

    $orderStmt->execute([
        $placeId,
        $scope,
        $source,
        $scopeId,
    ]);

    $sortOrder =
        (int) $orderStmt->fetchColumn()
        + 1;

    /*
     * Only Place-level photos participate in the Place featured-image system.
     * Area and Site images are target-specific evidence/gallery photos.
     */
    $hasFeatured =
        $scope !== 'place';

    if (!$hasFeatured) {
        foreach ($existing as $image) {
            if (
                (int) (
                    $image['is_featured']
                    ?? 0
                ) === 1
            ) {
                $hasFeatured =
                    true;

                break;
            }
        }
    }

    $insert =
        $db->prepare(
            'INSERT INTO place_images (
                place_id,
                report_scope,
                scope_source,
                scope_id,
                src,
                alt_text,
                is_featured,
                sort_order,
                uploaded_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

    $inserted =
        0;

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
            $scope,
            $source,
            $scopeId,
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

    if ($inserted !== count($committed)) {
        throw new RuntimeException(
            'One or more photos could not be attached.'
        );
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.report_target_photos_added',
        'Added photos to a reporting target.',
        [
            'place_id' =>
                $placeId,

            'report_scope' =>
                $scope,

            'scope_source' =>
                $source,

            'scope_id' =>
                $scopeId,

            'count' =>
                $inserted,
        ]
    );

    return
        $inserted;
}


/*
 * Remove one image record inside the outer Place Report transaction, but
 * deliberately leave the physical file alone until AFTER commit.
 *
 * The supplied target prevents a Site save from deleting a Place or Area photo
 * that merely happened to be visible in stale browser markup.
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

    $stmt =
        $db->prepare(
            'SELECT *
             FROM place_images
             WHERE id = ?
               AND place_id = ?
               AND report_scope = ?
               AND scope_source = ?
               AND scope_id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $imageId,
        $placeId,
        (string) $target['scope'],
        (string) $target['source'],
        (int) $target['id'],
    ]);

    $image =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$image) {
        throw new RuntimeException(
            'Photo not found for the selected reporting target.'
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
            'Photo could not be removed.'
        );
    }

    if (
        $wasFeatured
        && (string) $target['scope'] === 'place'
    ) {
        $next =
            $db->prepare(
                'SELECT id
                 FROM place_images
                 WHERE place_id = ?
                   AND report_scope = "place"
                   AND scope_source = "place"
                   AND scope_id = ?
                 ORDER BY
                    sort_order ASC,
                    id ASC
                 LIMIT 1'
            );

        $next->execute([
            $placeId,
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
        'place.report_target_photo_deleted',
        'Deleted a reporting-target photo.',
        [
            'place_id' =>
                $placeId,

            'image_id' =>
                $imageId,

            'report_scope' =>
                (string) $target['scope'],

            'scope_source' =>
                (string) $target['source'],

            'scope_id' =>
                (int) $target['id'],
        ]
    );

    return
        $path;
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
