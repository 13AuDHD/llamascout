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


/*
 * Add Place photos while an outer database transaction is already active.
 *
 * The shared admin_place_add_photos() helper owns its own transaction,
 * which is correct for standalone photo actions but cannot be nested inside
 * the all-or-nothing Place Report save. This local helper performs the same
 * database work without starting or committing a second transaction.
 *
 * $committedForCleanup receives the permanent photo paths that were moved
 * from staging. If the outer database transaction later fails, the caller
 * removes those moved files so the filesystem does not claim a save that
 * the database rolled back.
 */
function admin_place_add_photos_in_report_transaction(
    PDO $db,
    int $actorUserId,
    int $placeId,
    string $photoToken,
    array $photos,
    array &$committedForCleanup
): int {
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Place Report photo attachment requires an active database transaction.'
        );
    }

    $existing =
        admin_place_images(
            $db,
            $placeId
        );

    $remaining =
        max(
            0,
            30 - count($existing)
        );

    if ($remaining < 1) {
        throw new RuntimeException(
            'This Place already has the maximum of 30 photos.'
        );
    }

    if (count($photos) > $remaining) {
        throw new RuntimeException(
            'You can add only '
            . $remaining
            . ' more photos.'
        );
    }

    $committed =
        llama_photo_commit_stage(
            'add-place',
            $actorUserId,
            $photoToken,
            $photos,
            '/uploads/places/'
            . $placeId
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
             WHERE place_id = ?'
        );

    $orderStmt->execute([
        $placeId,
    ]);

    $sortOrder =
        (int) $orderStmt->fetchColumn()
        + 1;

    $hasFeatured =
        false;

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
            'One or more Place photos could not be attached.'
        );
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.photos_added',
        'Added Place photos.',
        [
            'place_id' =>
                $placeId,

            'count' =>
                $inserted,
        ]
    );

    return
        $inserted;
}


/*
 * Remove a Place image record inside the outer Place Report transaction,
 * but deliberately leave the physical file alone until AFTER commit.
 *
 * Returning the path lets the caller perform the irreversible filesystem
 * deletion only after every database part of Save Place Report succeeds.
 */
function admin_place_delete_image_in_report_transaction(
    PDO $db,
    int $actorUserId,
    int $placeId,
    int $imageId
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
            'place_id' =>
                $placeId,

            'image_id' =>
                $imageId,
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
