<?php

declare(strict_types=1);

/** Verified administrative operating status, distinct from Scout observations. */
function llama_closure_target_key(int $placeId, ?int $campsiteId): string
{
    return $campsiteId === null ? 'place:' . $placeId : 'site:' . $placeId . ':' . $campsiteId;
}

function llama_closure_validate_target(PDO $db, int $placeId, ?int $campsiteId): void
{
    if ($placeId < 1) {
        throw new InvalidArgumentException('Select a valid Place.');
    }
    $stmt = $db->prepare('SELECT 1 FROM places WHERE id = ? LIMIT 1');
    $stmt->execute([$placeId]);
    if (!$stmt->fetchColumn()) {
        throw new InvalidArgumentException('Place not found.');
    }
    if ($campsiteId !== null) {
        if ($campsiteId < 1) {
            throw new InvalidArgumentException('Invalid campsite ID.');
        }
        $stmt = $db->prepare('SELECT 1 FROM place_campsites WHERE id = ? AND place_id = ? LIMIT 1');
        $stmt->execute([$campsiteId, $placeId]);
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('Campsite does not belong to that Place.');
        }
    }
}

function llama_closure_date(mixed $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Invalid date: ' . $value);
    }
    return $value;
}

/** Changes only the confirmed status; no scheduled process can reopen locations. */
function llama_closure_save(PDO $db, array $data, int $actorId): void
{
    $placeId = (int) ($data['place_id'] ?? 0);
    $campsiteRaw = trim((string) ($data['campsite_id'] ?? ''));
    $campsiteId = $campsiteRaw === '' ? null : (int) $campsiteRaw;
    llama_closure_validate_target($db, $placeId, $campsiteId);

    $status = (string) ($data['status'] ?? '');
    $statuses = ['open', 'temporary', 'seasonal', 'permanent'];
    if (!in_array($status, $statuses, true)) {
        throw new InvalidArgumentException('Invalid operating status.');
    }
    $reason = (string) ($data['reason'] ?? 'unknown');
    $reasons = ['maintenance', 'construction', 'weather', 'fire', 'flooding', 'road_damage', 'administrative', 'other', 'unknown'];
    if (!in_array($reason, $reasons, true)) {
        throw new InvalidArgumentException('Invalid closure reason.');
    }
    $began = llama_closure_date($data['closure_began'] ?? '');
    $reopens = llama_closure_date($data['expected_reopening'] ?? '');
    $review = llama_closure_date($data['review_date'] ?? '');
    $source = trim((string) ($data['source_url'] ?? ''));
    if ($source !== '' && (strlen($source) > 2048 || !filter_var($source, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($source, PHP_URL_SCHEME)), ['https', 'http'], true))) {
        throw new InvalidArgumentException('Enter a valid HTTP(S) source URL.');
    }
    $notes = trim((string) ($data['notes'] ?? ''));
    if (mb_strlen($notes) > 10000) {
        throw new InvalidArgumentException('Notes are too long.');
    }
    if ($status === 'open') {
        $reason = 'unknown';
        $began = $reopens = $review = null;
    }
    if ($status === 'permanent') {
        $reopens = null;
    }
    $stmt = $db->prepare(
        'INSERT INTO place_operating_closures
            (place_id, campsite_id, target_key, status, reason, closure_began,
             expected_reopening, review_date, source_url, notes, verified_at, verified_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
         ON DUPLICATE KEY UPDATE
            status = VALUES(status), reason = VALUES(reason),
            closure_began = VALUES(closure_began),
            expected_reopening = VALUES(expected_reopening),
            review_date = VALUES(review_date), source_url = VALUES(source_url),
            notes = VALUES(notes), verified_at = NOW(), verified_by = VALUES(verified_by)'
    );
    $stmt->execute([
        $placeId, $campsiteId, llama_closure_target_key($placeId, $campsiteId),
        $status, $reason, $began, $reopens, $review,
        $source !== '' ? $source : null, $notes !== '' ? $notes : null, $actorId,
    ]);
}

/** Overdue first, then today, upcoming, and closures without review dates. */
function llama_closure_admin_list(PDO $db): array
{
    $stmt = $db->query(
        "SELECT c.*, p.name AS place_name, p.state, cs.site_code, cs.site_name
           FROM place_operating_closures c
           INNER JOIN places p ON p.id = c.place_id
           LEFT JOIN place_campsites cs ON cs.id = c.campsite_id AND cs.place_id = c.place_id
          WHERE c.status <> 'open'
          ORDER BY (c.review_date IS NULL) ASC, c.review_date ASC,
                   p.name ASC, c.campsite_id ASC LIMIT 1000"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
