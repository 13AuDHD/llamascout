<?php

declare(strict_types=1);

require_once __DIR__ . '/points.php';
require_once __DIR__ . '/scout-policy.php';
require_once __DIR__ . '/contribution-levels.php';

/* =========================================================
   LLAMA SCOUT PLACE CHECK INS

   Check In is a geographically constrained community action.
   Every signed-in, verified contributor may check in.

   Contribution level is captured at the moment of check-in:
   - community
   - member
   - scout
   - master-scout
   - admin

   Exact device coordinates are used only for the live distance
   calculation. They are never stored in the database or session.
   The durable audit record keeps only distance from the stored
   Place pin and the device-reported accuracy.
   ========================================================= */

function llama_place_checkin_csrf_token(): string
{
    start_llama_session();

    if (empty($_SESSION['place_checkin_csrf'])) {
        $_SESSION['place_checkin_csrf'] =
            bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['place_checkin_csrf'];
}

function llama_place_checkin_verify_csrf(
    string $token
): bool {
    $expected =
        llama_place_checkin_csrf_token();

    return
        $token !== ''
        && hash_equals(
            $expected,
            $token
        );
}

function llama_place_checkin_user_access(
    PDO $db,
    int $userId,
    int $placeId = 0
): array {
    if ($userId < 1) {
        return [
            'allowed' => false,
            'role_at_time' => 'user',
            'level' => LLAMA_CONTRIBUTION_LEVEL_COMMUNITY,
            'level_label' => llama_contribution_level_label(
                LLAMA_CONTRIBUTION_LEVEL_COMMUNITY
            ),
            'short_label' => llama_contribution_level_short_label(
                LLAMA_CONTRIBUTION_LEVEL_COMMUNITY
            ),
            'complete_access' => false,
        ];
    }

    $roleAtTime =
        llama_contribution_role_at_time(
            $db,
            $userId
        );

    $level =
        llama_contribution_level_from_role(
            $roleAtTime
        );

    return [
        'allowed' =>
            llama_contributor_can(
                $db,
                $userId,
                'check_in'
            ),
        'role_at_time' =>
            $roleAtTime,
        'level' =>
            $level,
        'level_label' =>
            llama_contribution_level_label($level),
        'short_label' =>
            llama_contribution_level_short_label($level),
        'complete_access' =>
            $placeId > 0
                ? user_has_place_complete_access(
                    $placeId,
                    $userId
                )
                : user_has_member_access($userId),
    ];
}

function llama_place_checkin_has_coordinates(
    PDO $db,
    int $placeId
): bool {
    if ($placeId < 1) {
        return false;
    }

    $stmt = $db->prepare(
        'SELECT latitude, longitude
         FROM places
         WHERE id = ?
           AND status IN ("active", "featured")
         LIMIT 1'
    );

    $stmt->execute([$placeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return false;
    }

    if (
        !is_numeric($row['latitude'] ?? null)
        || !is_numeric($row['longitude'] ?? null)
    ) {
        return false;
    }

    return llama_place_checkin_coordinates_valid(
        (float) $row['latitude'],
        (float) $row['longitude']
    );
}

function llama_place_checkin_place_by_slug(
    PDO $db,
    string $slug
): ?array {
    $slug = trim($slug);

    if ($slug === '') {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT
            id,
            slug,
            name,
            status,
            latitude,
            longitude,
            last_field_checked_on
         FROM places
         WHERE slug = ?
           AND status IN ("active", "featured")
         LIMIT 1'
    );

    $stmt->execute([$slug]);

    $place =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return $place ?: null;
}

function llama_place_checkin_policy(
    PDO $db
): array {
    return [
        'points' =>
            max(
                0,
                llama_points_policy_required(
                    $db,
                    'place_checkin'
                )
            ),
        'cooldown_days' =>
            max(
                1,
                llama_scout_policy_int(
                    $db,
                    'place_checkin_cooldown_days'
                )
            ),
        'radius_meters' =>
            max(
                25,
                llama_scout_policy_int(
                    $db,
                    'place_checkin_radius_meters'
                )
            ),
        'max_accuracy_meters' =>
            max(
                10,
                llama_scout_policy_int(
                    $db,
                    'place_checkin_max_accuracy_meters'
                )
            ),
    ];
}

function llama_place_checkin_coordinates_valid(
    float $latitude,
    float $longitude
): bool {
    return
        is_finite($latitude)
        && is_finite($longitude)
        && $latitude >= -90.0
        && $latitude <= 90.0
        && $longitude >= -180.0
        && $longitude <= 180.0;
}

function llama_place_checkin_distance_meters(
    float $latitudeA,
    float $longitudeA,
    float $latitudeB,
    float $longitudeB
): float {
    $earthRadiusMeters =
        6371008.8;

    $lat1 =
        deg2rad($latitudeA);
    $lat2 =
        deg2rad($latitudeB);
    $deltaLat =
        deg2rad(
            $latitudeB
            - $latitudeA
        );
    $deltaLon =
        deg2rad(
            $longitudeB
            - $longitudeA
        );

    $a =
        sin($deltaLat / 2) ** 2
        + cos($lat1)
        * cos($lat2)
        * sin($deltaLon / 2) ** 2;

    $c =
        2 * atan2(
            sqrt($a),
            sqrt(max(0.0, 1.0 - $a))
        );

    return
        $earthRadiusMeters
        * $c;
}

function llama_place_checkin_cooldown_status(
    PDO $db,
    int $userId,
    int $placeId,
    int $cooldownDays
): array {
    $stmt = $db->prepare(
        'SELECT last_checkin_at
         FROM place_checkin_cooldowns
         WHERE user_id = ?
           AND place_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $userId,
        $placeId,
    ]);

    $lastCheckinAt =
        $stmt->fetchColumn();

    if (
        $lastCheckinAt === false
        || trim((string) $lastCheckinAt) === ''
    ) {
        return [
            'eligible' => true,
            'last_checkin_at' => null,
            'next_eligible_at' => null,
        ];
    }

    try {
        $last =
            new DateTimeImmutable(
                (string) $lastCheckinAt,
                new DateTimeZone('UTC')
            );

        $next =
            $last->modify(
                '+' .
                max(1, $cooldownDays) .
                ' days'
            );

        $now =
            new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC')
            );

        return [
            'eligible' =>
                $now >= $next,
            'last_checkin_at' =>
                $last->format(
                    'Y-m-d H:i:s'
                ),
            'next_eligible_at' =>
                $next->format(
                    'Y-m-d H:i:s'
                ),
        ];
    } catch (Throwable) {
        return [
            'eligible' => false,
            'last_checkin_at' =>
                (string) $lastCheckinAt,
            'next_eligible_at' => null,
        ];
    }
}

function llama_place_checkin_prune_location_proofs(): void
{
    start_llama_session();

    $proofs =
        is_array(
            $_SESSION['place_checkin_location_proofs']
            ?? null
        )
            ? $_SESSION['place_checkin_location_proofs']
            : [];

    $cutoff =
        time() - 600;

    foreach ($proofs as $token => $proof) {
        $issuedAt =
            (int) (
                $proof['issued_at']
                ?? 0
            );

        if ($issuedAt < $cutoff) {
            unset($proofs[$token]);
        }
    }

    if (count($proofs) > 5) {
        uasort(
            $proofs,
            static fn(array $a, array $b): int =>
                (int) ($b['issued_at'] ?? 0)
                <=>
                (int) ($a['issued_at'] ?? 0)
        );

        $proofs =
            array_slice(
                $proofs,
                0,
                5,
                true
            );
    }

    $_SESSION['place_checkin_location_proofs'] =
        $proofs;
}

function llama_place_checkin_issue_location_proof(
    int $userId,
    int $placeId,
    float $distanceMeters,
    float $accuracyMeters,
    int $radiusMeters
): string {
    llama_place_checkin_prune_location_proofs();

    $token =
        bin2hex(
            random_bytes(32)
        );

    $_SESSION['place_checkin_location_proofs'][$token] = [
        'user_id' => $userId,
        'place_id' => $placeId,
        'distance_meters' =>
            round($distanceMeters, 2),
        'accuracy_meters' =>
            round($accuracyMeters, 2),
        'radius_meters' =>
            $radiusMeters,
        'issued_at' =>
            time(),
    ];

    return $token;
}

function llama_place_checkin_location_proof(
    string $token,
    int $userId,
    int $placeId
): ?array {
    llama_place_checkin_prune_location_proofs();

    $token = trim($token);

    if ($token === '') {
        return null;
    }

    $proof =
        $_SESSION['place_checkin_location_proofs'][$token]
        ?? null;

    if (!is_array($proof)) {
        return null;
    }

    if (
        (int) ($proof['user_id'] ?? 0)
        !== $userId
        ||
        (int) ($proof['place_id'] ?? 0)
        !== $placeId
        ||
        (int) ($proof['issued_at'] ?? 0)
        < time() - 600
    ) {
        return null;
    }

    return $proof;
}

function llama_place_checkin_consume_location_proof(
    string $token
): void {
    $token = trim($token);

    if ($token === '') {
        return;
    }

    unset(
        $_SESSION['place_checkin_location_proofs'][$token]
    );
}

function llama_place_checkin_preflight(
    PDO $db,
    int $userId,
    array $place,
    float $deviceLatitude,
    float $deviceLongitude,
    float $accuracyMeters
): array {
    $access =
        llama_place_checkin_user_access(
            $db,
            $userId,
            (int) ($place['id'] ?? 0)
        );

    if (empty($access['allowed'])) {
        throw new RuntimeException(
            'Your account is not eligible to check in to this Place.'
        );
    }

    $placeLatitude =
        isset($place['latitude'])
        && is_numeric($place['latitude'])
            ? (float) $place['latitude']
            : null;

    $placeLongitude =
        isset($place['longitude'])
        && is_numeric($place['longitude'])
            ? (float) $place['longitude']
            : null;

    if (
        $placeLatitude === null
        || $placeLongitude === null
        || !llama_place_checkin_coordinates_valid(
            $placeLatitude,
            $placeLongitude
        )
    ) {
        throw new RuntimeException(
            'This Place does not have valid exact coordinates for a check-in.'
        );
    }

    if (
        !llama_place_checkin_coordinates_valid(
            $deviceLatitude,
            $deviceLongitude
        )
    ) {
        throw new InvalidArgumentException(
            'Your device returned an invalid location.'
        );
    }

    if (
        !is_finite($accuracyMeters)
        || $accuracyMeters <= 0
    ) {
        throw new InvalidArgumentException(
            'Your device did not provide a usable location accuracy.'
        );
    }

    $policy =
        llama_place_checkin_policy($db);

    $cooldown =
        llama_place_checkin_cooldown_status(
            $db,
            $userId,
            (int) $place['id'],
            (int) $policy['cooldown_days']
        );

    if (empty($cooldown['eligible'])) {
        return [
            'status' => 'cooldown',
            'next_eligible_at' =>
                $cooldown['next_eligible_at'],
            'cooldown_days' =>
                (int) $policy['cooldown_days'],
        ];
    }

    if (
        $accuracyMeters
        > (float) $policy['max_accuracy_meters']
    ) {
        return [
            'status' => 'retry',
            'reason' => 'accuracy',
            'accuracy_meters' =>
                round($accuracyMeters),
            'max_accuracy_meters' =>
                (int) $policy['max_accuracy_meters'],
        ];
    }

    $distanceMeters =
        llama_place_checkin_distance_meters(
            $deviceLatitude,
            $deviceLongitude,
            $placeLatitude,
            $placeLongitude
        );

    $radiusMeters =
        (int) $policy['radius_meters'];

    /*
     * Device accuracy describes uncertainty around the reported point.
     * Add that uncertainty to the base geofence, but never beyond the
     * separately configured maximum accepted accuracy.
     */
    $effectiveRadius =
        $radiusMeters
        + min(
            $accuracyMeters,
            (float) $policy['max_accuracy_meters']
        );

    if ($distanceMeters > $effectiveRadius) {
        return [
            'status' => 'too_far',
            'distance_meters' =>
                round($distanceMeters),
            'radius_meters' =>
                $radiusMeters,
        ];
    }

    $proofToken =
        llama_place_checkin_issue_location_proof(
            $userId,
            (int) $place['id'],
            $distanceMeters,
            $accuracyMeters,
            $radiusMeters
        );

    return [
        'status' => 'allowed',
        'proof_token' =>
            $proofToken,
        'distance_meters' =>
            round($distanceMeters),
        'accuracy_meters' =>
            round($accuracyMeters),
        'points' =>
            (int) $policy['points'],
        'contribution_level' =>
            (string) $access['level'],
        'contribution_label' =>
            (string) $access['level_label'],
        'complete_access' =>
            !empty($access['complete_access']),
    ];
}

function llama_place_checkin_submit(
    PDO $db,
    int $userId,
    array $place,
    string $proofToken,
    array $answers
): array {
    $access =
        llama_place_checkin_user_access(
            $db,
            $userId,
            (int) ($place['id'] ?? 0)
        );

    if (empty($access['allowed'])) {
        throw new RuntimeException(
            'Your account is not eligible to check in to this Place.'
        );
    }

    foreach (
        [
            'pin_confirmed',
            'place_exists_confirmed',
            'no_major_issue',
        ] as $answerKey
    ) {
        if (
            (string) (
                $answers[$answerKey]
                ?? ''
            ) !== '1'
        ) {
            throw new RuntimeException(
                'A check-in can only be submitted when the Place exists at the stored location and you did not encounter a major problem. Use Suggest Update or Report a Problem instead.'
            );
        }
    }

    $completeAccess =
        !empty($access['complete_access']);

    if ($completeAccess) {
        foreach (
            [
                'access_confirmed',
                'site_confirmed',
            ] as $answerKey
        ) {
            if (
                (string) (
                    $answers[$answerKey]
                    ?? ''
                ) !== '1'
            ) {
                throw new RuntimeException(
                    'Because you can see the Complete Place Report, this check-in can only be submitted when the road/access and site information still generally match. Submit an update if something changed.'
                );
            }
        }
    }

    $proof =
        llama_place_checkin_location_proof(
            $proofToken,
            $userId,
            (int) $place['id']
        );

    if (!$proof) {
        throw new RuntimeException(
            'Your location check expired. Check your location again before submitting.'
        );
    }

    $policy =
        llama_place_checkin_policy($db);

    $roleAtTime =
        (string) $access['role_at_time'];

    $level =
        llama_contribution_level_normalize(
            (string) $access['level']
        );

    $isScoutLevel =
        llama_contribution_level_rank($level)
        >= llama_contribution_level_rank(
            LLAMA_CONTRIBUTION_LEVEL_SCOUT
        );

    $verificationType =
        $isScoutLevel
            ? 'field-verified'
            : 'community-confirmed';

    $source =
        'Geofenced ' .
        llama_contribution_level_short_label($level) .
        ' Check In';

    $db->beginTransaction();

    try {
        $db->prepare(
            'INSERT IGNORE INTO place_checkin_cooldowns (
                user_id,
                place_id,
                last_checkin_at,
                updated_at
             ) VALUES (?, ?, NULL, UTC_TIMESTAMP())'
        )->execute([
            $userId,
            (int) $place['id'],
        ]);

        $lockStmt = $db->prepare(
            'SELECT last_checkin_at
             FROM place_checkin_cooldowns
             WHERE user_id = ?
               AND place_id = ?
             LIMIT 1
             FOR UPDATE'
        );

        $lockStmt->execute([
            $userId,
            (int) $place['id'],
        ]);

        $lastCheckinAt =
            $lockStmt->fetchColumn();

        if (
            $lastCheckinAt !== false
            && trim((string) $lastCheckinAt) !== ''
        ) {
            $last =
                new DateTimeImmutable(
                    (string) $lastCheckinAt,
                    new DateTimeZone('UTC')
                );

            $next =
                $last->modify(
                    '+' .
                    (int) $policy['cooldown_days'] .
                    ' days'
                );

            $now =
                new DateTimeImmutable(
                    'now',
                    new DateTimeZone('UTC')
                );

            if ($now < $next) {
                throw new RuntimeException(
                    'You already checked in to this Place recently. Your next rewarded check-in is available after ' .
                    $next->format('M j, Y') .
                    '.'
                );
            }
        }

        $checkinStmt = $db->prepare(
            'INSERT INTO place_checkins (
                place_id,
                user_id,
                role_at_time,
                contribution_level,
                complete_report_visible,
                distance_meters,
                accuracy_meters,
                radius_meters,
                pin_confirmed,
                place_exists_confirmed,
                access_confirmed,
                site_confirmed,
                no_major_issue,
                points_awarded,
                checked_in_at,
                created_at
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?, 1, 0,
                UTC_TIMESTAMP(), UTC_TIMESTAMP()
             )'
        );

        $checkinStmt->execute([
            (int) $place['id'],
            $userId,
            $roleAtTime,
            $level,
            $completeAccess ? 1 : 0,
            (float) $proof['distance_meters'],
            (float) $proof['accuracy_meters'],
            (int) $proof['radius_meters'],
            $completeAccess ? 1 : null,
            $completeAccess ? 1 : null,
        ]);

        $checkinId =
            (int) $db->lastInsertId();

        $verificationStmt =
            $db->prepare(
                'INSERT INTO place_verifications (
                    place_id,
                    verification_type,
                    visited_at,
                    verified_by,
                    source,
                    public_data_verified,
                    notes
                 ) VALUES (?, ?, UTC_DATE(), ?, ?, 0, ?)'
            );

        $verificationStmt->execute([
            (int) $place['id'],
            $verificationType,
            $userId,
            $source,
            'Check-in #' .
                $checkinId .
                '. Geofence passed at ' .
                number_format(
                    (float) $proof['distance_meters'],
                    0
                ) .
                ' m from the stored Place coordinates with device accuracy of ' .
                number_format(
                    (float) $proof['accuracy_meters'],
                    0
                ) .
                ' m. Contributor level: ' .
                llama_contribution_level_short_label($level) .
                '.'
        ]);

        $verificationId =
            (int) $db->lastInsertId();

        $db->prepare(
            'UPDATE place_checkins
             SET verification_id = ?
             WHERE id = ?'
        )->execute([
            $verificationId,
            $checkinId,
        ]);

        /*
         * last_field_checked_on means physical presence, regardless
         * of contributor level. Phase 5 derives Community and Scout
         * freshness separately from place_checkins.contribution_level.
         */
        $db->prepare(
            'UPDATE places
             SET
                last_field_checked_on = UTC_DATE(),
                last_verified_at = UTC_TIMESTAMP()
             WHERE id = ?'
        )->execute([
            (int) $place['id'],
        ]);

        $db->prepare(
            'UPDATE place_checkin_cooldowns
             SET
                last_checkin_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
             WHERE user_id = ?
               AND place_id = ?'
        )->execute([
            $userId,
            (int) $place['id'],
        ]);

        $points =
            (int) $policy['points'];

        if ($points > 0) {
            llama_points_record(
                $db,
                $userId,
                $points,
                'place_checkin',
                $checkinId,
                'Geofenced check-in at ' .
                    (string) $place['name'] .
                    '.',
                null,
                null
            );
        }

        $db->prepare(
            'UPDATE place_checkins
             SET points_awarded = ?
             WHERE id = ?'
        )->execute([
            $points,
            $checkinId,
        ]);

        $db->commit();

        llama_place_checkin_consume_location_proof(
            $proofToken
        );

        return [
            'checkin_id' => $checkinId,
            'verification_id' => $verificationId,
            'points_awarded' => $points,
            'checked_in_at' =>
                gmdate('Y-m-d H:i:s'),
            'contribution_level' =>
                $level,
            'contribution_label' =>
                llama_contribution_level_label($level),
        ];

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}
