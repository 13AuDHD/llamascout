<?php

declare(strict_types=1);

/*
 * =========================================================
 * LLAMA SCOUT
 * PLACE VERIFICATIONS
 * =========================================================
 */

function llama_place_verification_types(): array
{
    return [
        'field-verified' => [
            'label' => 'Llama Scout field visit',
            'description' =>
                'Personally visited and checked by a Llama Scout.',
            'requires_visit_date' => true,
        ],
        'source-verified' => [
            'label' => 'Official source verified',
            'description' =>
                'Checked against an official land manager, agency, or other authoritative source.',
            'requires_visit_date' => false,
        ],
        'community-confirmed' => [
            'label' => 'Community confirmed',
            'description' =>
                'Current information corroborated by a trusted community contribution.',
            'requires_visit_date' => false,
        ],
    ];
}

function llama_place_verification_type_label(
    string $type
): string {
    $types =
        llama_place_verification_types();

    if (isset($types[$type])) {
        return
            (string) $types[$type]['label'];
    }

    return
        ucwords(
            str_replace(
                ['-', '_'],
                ' ',
                $type
            )
        );
}

function llama_place_add_verification(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $data
): int {
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

    $type =
        trim(
            (string) (
                $data['verification_type']
                ?? ''
            )
        );

    $types =
        llama_place_verification_types();

    if (!isset($types[$type])) {
        throw new RuntimeException(
            'Choose a valid verification type.'
        );
    }

    $visitedAt =
        trim(
            (string) (
                $data['visited_at']
                ?? ''
            )
        );

    if (
        !empty(
            $types[$type]['requires_visit_date']
        )
        && $visitedAt === ''
    ) {
        throw new RuntimeException(
            'Date visited is required for a Llama Scout field visit.'
        );
    }

    $source =
        trim(
            (string) (
                $data['source']
                ?? ''
            )
        );

    if ($type === 'field-verified') {
        $source =
            'Llama Scouted';
    }

    if (
        $type === 'source-verified'
        && $source === ''
    ) {
        throw new RuntimeException(
            'Enter the official source that was checked.'
        );
    }

    $notes =
        trim(
            (string) (
                $data['notes']
                ?? ''
            )
        );

    $publicDataVerified =
        isset(
            $data['public_data_verified']
        )
            ? 1
            : 0;

    $db->beginTransaction();

    try {
        $stmt =
            $db->prepare(
                'INSERT INTO place_verifications (
                    place_id,
                    verification_type,
                    visited_at,
                    verified_by,
                    source,
                    public_data_verified,
                    notes
                 ) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

        $stmt->execute([
            $placeId,
            $type,
            $visitedAt !== ''
                ? $visitedAt
                : null,
            $actorUserId,
            $source !== ''
                ? $source
                : null,
            $publicDataVerified,
            $notes !== ''
                ? $notes
                : null,
        ]);

        $verificationId =
            (int) $db->lastInsertId();

        if ($type === 'field-verified') {
            $db->prepare(
                'UPDATE places
                 SET
                    last_verified_at = NOW(),
                    last_field_checked_on = CASE
                        WHEN last_field_checked_on IS NULL
                             OR last_field_checked_on < DATE(?)
                            THEN DATE(?)
                        ELSE last_field_checked_on
                    END
                 WHERE id = ?'
            )->execute([
                $visitedAt,
                $visitedAt,
                $placeId,
            ]);
        } else {
            $db->prepare(
                'UPDATE places
                 SET last_verified_at = NOW()
                 WHERE id = ?'
            )->execute([
                $placeId,
            ]);
        }

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.verification_added',
            'Added Place verification.',
            [
                'place_id' =>
                    $placeId,
                'verification_id' =>
                    $verificationId,
                'verification_type' =>
                    $type,
                'visited_at' =>
                    $visitedAt !== ''
                        ? $visitedAt
                        : null,
                'source' =>
                    $source !== ''
                        ? $source
                        : null,
            ]
        );

        $db->commit();

        return $verificationId;

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function llama_place_delete_verification(
    PDO $db,
    int $actorUserId,
    int $placeId,
    int $verificationId
): void {
    if ($verificationId < 1) {
        throw new RuntimeException(
            'Verification not found.'
        );
    }

    $db->beginTransaction();

    try {
        $stmt =
            $db->prepare(
                'SELECT *
                 FROM place_verifications
                 WHERE id = ?
                   AND place_id = ?
                 LIMIT 1
                 FOR UPDATE'
            );

        $stmt->execute([
            $verificationId,
            $placeId,
        ]);

        $verification =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$verification) {
            throw new RuntimeException(
                'Verification not found.'
            );
        }

        $checkinTable = $db->prepare(
            'SELECT 1
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
             LIMIT 1'
        );
        $checkinTable->execute(['place_checkins']);
        $hasCheckinTable = $checkinTable->fetchColumn() !== false;

        if ($hasCheckinTable) {
            $linkedCheckin = $db->prepare(
                'SELECT id
                 FROM place_checkins
                 WHERE verification_id = ?
                 LIMIT 1'
            );
            $linkedCheckin->execute([$verificationId]);

            if ((int) ($linkedCheckin->fetchColumn() ?: 0) > 0) {
                throw new RuntimeException(
                    'This verification belongs to a geofenced Place check-in and cannot be deleted as a standalone verification.'
                );
            }
        }

        $db->prepare(
            'DELETE FROM place_verifications
             WHERE id = ?
               AND place_id = ?'
        )->execute([
            $verificationId,
            $placeId,
        ]);

        $latest =
            $db->prepare(
                'SELECT MAX(verified_at)
                 FROM place_verifications
                 WHERE place_id = ?'
            );

        $latest->execute([
            $placeId,
        ]);

        $lastVerifiedAt =
            $latest->fetchColumn();

        $fieldLatest = $db->prepare(
            'SELECT MAX(
                COALESCE(
                    visited_at,
                    DATE(verified_at)
                )
             )
             FROM place_verifications
             WHERE place_id = ?
               AND verification_type = "field-verified"'
        );
        $fieldLatest->execute([$placeId]);
        $lastFieldCheckedOn = $fieldLatest->fetchColumn();

        if ($hasCheckinTable) {
            $checkinLatest = $db->prepare(
                'SELECT MAX(DATE(checked_in_at))
                 FROM place_checkins
                 WHERE place_id = ?'
            );
            $checkinLatest->execute([$placeId]);
            $checkinFieldDate = $checkinLatest->fetchColumn();

            if (
                $checkinFieldDate !== false
                && trim((string) $checkinFieldDate) !== ''
                && (
                    $lastFieldCheckedOn === false
                    || trim((string) $lastFieldCheckedOn) === ''
                    || (string) $checkinFieldDate > (string) $lastFieldCheckedOn
                )
            ) {
                $lastFieldCheckedOn = $checkinFieldDate;
            }
        }

        $db->prepare(
            'UPDATE places
             SET
                last_verified_at = ?,
                last_field_checked_on = ?
             WHERE id = ?'
        )->execute([
            $lastVerifiedAt !== false
                ? $lastVerifiedAt
                : null,
            $lastFieldCheckedOn !== false
                && trim((string) $lastFieldCheckedOn) !== ''
                    ? $lastFieldCheckedOn
                    : null,
            $placeId,
        ]);

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.verification_deleted',
            'Deleted Place verification.',
            [
                'place_id' =>
                    $placeId,
                'verification_id' =>
                    $verificationId,
                'verification_type' =>
                    $verification['verification_type']
                    ?? null,
                'visited_at' =>
                    $verification['visited_at']
                    ?? null,
                'source' =>
                    $verification['source']
                    ?? null,
                'notes' =>
                    $verification['notes']
                    ?? null,
            ]
        );

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}
