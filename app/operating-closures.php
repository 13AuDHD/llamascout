<?php

declare(strict_types=1);

/** Verified administrative operating status. */
function llama_closure_target_key(
    int $placeId,
    ?int $campsiteId
): string {
    return $campsiteId === null
        ? 'place:' . $placeId
        : 'site:' . $placeId . ':' . $campsiteId;
}

function llama_closure_validate_target(
    PDO $db,
    int $placeId,
    ?int $campsiteId
): void {
    if ($placeId < 1) {
        throw new InvalidArgumentException(
            'Select a valid Place.'
        );
    }

    $stmt = $db->prepare(
        'SELECT 1 FROM places WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$placeId]);

    if (!$stmt->fetchColumn()) {
        throw new InvalidArgumentException(
            'Place not found.'
        );
    }

    if ($campsiteId !== null) {
        if ($campsiteId < 1) {
            throw new InvalidArgumentException(
                'Invalid campsite ID.'
            );
        }

        $stmt = $db->prepare(
            'SELECT 1
             FROM place_campsites
             WHERE id = ?
               AND place_id = ?
             LIMIT 1'
        );
        $stmt->execute([
            $campsiteId,
            $placeId,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException(
                'Campsite does not belong to that Place.'
            );
        }
    }
}

function llama_closure_date(mixed $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $date =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

    if (
        !$date
        || $date->format('Y-m-d') !== $value
    ) {
        throw new InvalidArgumentException(
            'Invalid date: ' . $value
        );
    }

    return $value;
}

/**
 * Changes only the confirmed public operating status.
 * No scheduled process can reopen a location.
 */
function llama_closure_save(
    PDO $db,
    array $data,
    int $actorId
): void {
    $placeId =
        (int) ($data['place_id'] ?? 0);

    $campsiteRaw =
        trim((string) ($data['campsite_id'] ?? ''));

    $campsiteId =
        $campsiteRaw === ''
            ? null
            : (int) $campsiteRaw;

    llama_closure_validate_target(
        $db,
        $placeId,
        $campsiteId
    );

    $status =
        (string) ($data['status'] ?? '');

    $statuses = [
        'open',
        'temporary',
        'seasonal',
        'permanent',
    ];

    if (!in_array($status, $statuses, true)) {
        throw new InvalidArgumentException(
            'Invalid operating status.'
        );
    }

    $reason =
        (string) ($data['reason'] ?? 'unknown');

    $reasons = [
        'maintenance',
        'construction',
        'weather',
        'fire',
        'flooding',
        'road_damage',
        'administrative',
        'other',
        'unknown',
    ];

    if (!in_array($reason, $reasons, true)) {
        throw new InvalidArgumentException(
            'Invalid closure reason.'
        );
    }

    $began =
        llama_closure_date(
            $data['closure_began'] ?? ''
        );

    $reopens =
        llama_closure_date(
            $data['expected_reopening'] ?? ''
        );

    $review =
        llama_closure_date(
            $data['review_date'] ?? ''
        );

    $source =
        trim((string) ($data['source_url'] ?? ''));

    if (
        $source !== ''
        && (
            strlen($source) > 2048
            || !filter_var(
                $source,
                FILTER_VALIDATE_URL
            )
            || !in_array(
                strtolower(
                    (string) parse_url(
                        $source,
                        PHP_URL_SCHEME
                    )
                ),
                ['https', 'http'],
                true
            )
        )
    ) {
        throw new InvalidArgumentException(
            'Enter a valid HTTP(S) source URL.'
        );
    }

    $notes =
        trim((string) ($data['notes'] ?? ''));

    if (mb_strlen($notes) > 10000) {
        throw new InvalidArgumentException(
            'Notes are too long.'
        );
    }

    if ($status === 'open') {
        $reason = 'unknown';
        $began = null;
        $reopens = null;
        $review = null;
    }

    if ($status === 'permanent') {
        $reopens = null;
    }

    $stmt = $db->prepare(
        'INSERT INTO place_operating_closures
            (
                place_id,
                campsite_id,
                target_key,
                status,
                reason,
                closure_began,
                expected_reopening,
                review_date,
                source_url,
                notes,
                verified_at,
                verified_by
            )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            reason = VALUES(reason),
            closure_began = VALUES(closure_began),
            expected_reopening = VALUES(expected_reopening),
            review_date = VALUES(review_date),
            source_url = VALUES(source_url),
            notes = VALUES(notes),
            verified_at = NOW(),
            verified_by = VALUES(verified_by)'
    );

    $stmt->execute([
        $placeId,
        $campsiteId,
        llama_closure_target_key(
            $placeId,
            $campsiteId
        ),
        $status,
        $reason,
        $began,
        $reopens,
        $review,
        $source !== '' ? $source : null,
        $notes !== '' ? $notes : null,
        $actorId,
    ]);
}

/** Overdue first, then today, upcoming, and closures without review dates. */
function llama_closure_admin_list(
    PDO $db
): array {
    $stmt = $db->query(
        "SELECT
            c.*,
            p.name AS place_name,
            p.state,
            cs.site_code,
            cs.site_name
         FROM place_operating_closures c
         INNER JOIN places p
            ON p.id = c.place_id
         LEFT JOIN place_campsites cs
            ON cs.id = c.campsite_id
           AND cs.place_id = c.place_id
         WHERE c.status <> 'open'
         ORDER BY
            (c.review_date IS NULL) ASC,
            c.review_date ASC,
            p.name ASC,
            c.campsite_id ASC
         LIMIT 1000"
    );

    return
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}

/**
 * Place Report observations which differ from the currently verified public
 * status. These are evidence awaiting admin verification, not public closures.
 *
 * A routine "Open" observation does not create noise when the Place has never
 * been verified closed. An "Open" report appears only when it conflicts with
 * a verified closure and therefore represents a possible reopening.
 */
function llama_closure_reported_review_list(
    PDO $db
): array {
    $stmt = $db->query(
        "SELECT
            p.id AS place_id,
            p.name AS place_name,
            p.state,
            pr.place_reported_operating_status AS reported_status,
            pr.place_closure_reason AS reported_reason,
            pr.place_closure_began AS reported_began,
            pr.place_expected_reopening AS reported_reopening,
            pr.place_closure_review_date AS reported_review_date,
            pr.place_closure_source_url AS reported_source_url,
            pr.place_closure_notes AS reported_notes,
            c.status AS verified_status,
            c.reason AS verified_reason,
            c.closure_began AS verified_began,
            c.expected_reopening AS verified_reopening,
            c.verified_at
         FROM place_rules pr
         INNER JOIN places p
            ON p.id = pr.place_id
         LEFT JOIN place_operating_closures c
            ON c.place_id = p.id
           AND c.campsite_id IS NULL
         WHERE p.status NOT IN ('removed', 'archived')
           AND pr.place_reported_operating_status
                IN ('open', 'temporary', 'seasonal', 'permanent')
           AND (
                (
                    pr.place_reported_operating_status = 'open'
                    AND c.id IS NOT NULL
                    AND c.status <> 'open'
                )
                OR
                (
                    pr.place_reported_operating_status
                        IN ('temporary', 'seasonal', 'permanent')
                    AND (
                        c.id IS NULL
                        OR c.status <> pr.place_reported_operating_status
                        OR COALESCE(c.reason, 'unknown')
                            <> COALESCE(
                                NULLIF(pr.place_closure_reason, ''),
                                'unknown'
                            )
                        OR COALESCE(c.closure_began, '')
                            <> COALESCE(pr.place_closure_began, '')
                        OR COALESCE(c.expected_reopening, '')
                            <> COALESCE(pr.place_expected_reopening, '')
                    )
                )
           )
         ORDER BY
            CASE
                WHEN pr.place_reported_operating_status = 'open'
                    THEN 0
                ELSE 1
            END,
            p.name ASC,
            p.id ASC
         LIMIT 1000"
    );

    return
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}
