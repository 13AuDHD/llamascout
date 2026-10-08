<?php

declare(strict_types=1);

/**
 * Llama Scout camping fee verification.
 *
 * An unverified or ambiguous fee is UNKNOWN, never FREE. A reservation fee,
 * entrance fee, parking charge or incidental amenity fee cannot establish a
 * camping charge. Only verified, attributable evidence is publishable.
 *
 * This module is deliberately independent of the RIDB import transport. It
 * accepts evidence from RIDB, agency websites or a human verifier and leaves
 * the original source payloads untouched.
 */

function llama_camping_fee_statuses(): array
{
    return ['paid', 'free', 'mixed', 'unknown'];
}

function llama_camping_fee_validate_status(string $status, bool $isCampsite = false): string
{
    $status = strtolower(trim($status));
    if (!in_array($status, llama_camping_fee_statuses(), true)) {
        throw new InvalidArgumentException('Unsupported camping fee status.');
    }
    if ($isCampsite && $status === 'mixed') {
        throw new InvalidArgumentException('Mixed is only valid for a parent Place.');
    }
    return $status;
}

/**
 * Evidence-based classification. Caller must validate that the evidence
 * actually pertains to overnight camping at the indicated Place/campsite.
 * Plain prose is not parsed into paid/free claims automatically.
 */
function llama_camping_fee_verified_status(
    string $claimedStatus,
    string $evidenceUrl,
    string $evidenceText,
    ?string $verifiedAt = null,
    bool $isCampsite = false
): string {
    $status = llama_camping_fee_validate_status($claimedStatus, $isCampsite);
    if ($status === 'unknown') {
        return 'unknown';
    }
    $url = trim($evidenceUrl);
    $text = trim($evidenceText);
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (
        !in_array($scheme, ['https', 'http'], true)
        || filter_var($url, FILTER_VALIDATE_URL) === false
        || $text === ''
        || $verifiedAt === null
        || DateTimeImmutable::createFromFormat('!Y-m-d', $verifiedAt) === false
    ) {
        throw new InvalidArgumentException('A confirmed status requires a valid evidence URL, evidence text and YYYY-MM-DD verification date.');
    }
    return $status;
}

/**
 * Infer only what the confirmed statuses of ALL known child campsites allow.
 * Unknown child statuses prevent a blanket free/paid conclusion, but do not
 * erase a demonstrably mixed result.
 */
function llama_camping_fee_aggregate_sites(array $siteStatuses): string
{
    if (!$siteStatuses) {
        return 'unknown';
    }
    $found = [];
    foreach ($siteStatuses as $status) {
        $status = llama_camping_fee_validate_status((string) $status, true);
        $found[$status] = true;
    }
    if (isset($found['paid'], $found['free'])) {
        return 'mixed';
    }
    if (isset($found['unknown'])) {
        return 'unknown';
    }
    if (isset($found['paid'])) {
        return 'paid';
    }
    if (isset($found['free'])) {
        return 'free';
    }
    return 'unknown';
}

/**
 * Persist a manually evaluated, explicitly sourced classification.
 * place_campsite_id NULL denotes campground-wide evidence.
 * The evidence row is append-only, preserving older decisions for audit.
 */
function llama_camping_fee_record_evidence(
    PDO $db,
    int $placeId,
    ?int $campsiteId,
    string $status,
    string $sourceName,
    string $sourceUrl,
    string $evidenceText,
    string $verifiedDate,
    ?int $verifiedBy = null
): int {
    if ($placeId < 1 || ($campsiteId !== null && $campsiteId < 1)) {
        throw new InvalidArgumentException('Invalid Place or campsite ID.');
    }
    $status = llama_camping_fee_verified_status(
        $status, $sourceUrl, $evidenceText, $verifiedDate, $campsiteId !== null
    );
    if ($status === 'unknown') {
        throw new InvalidArgumentException('Unknown requires no evidence record; leave the Place unclassified.');
    }
    $sourceName = trim($sourceName);
    if ($sourceName === '') {
        throw new InvalidArgumentException('Evidence source name is required.');
    }
    if ($campsiteId !== null) {
        $check = $db->prepare('SELECT 1 FROM place_campsites WHERE id = ? AND place_id = ? LIMIT 1');
        $check->execute([$campsiteId, $placeId]);
        if (!$check->fetchColumn()) {
            throw new InvalidArgumentException('Campsite does not belong to the Place.');
        }
    }
    $stmt = $db->prepare(
        'INSERT INTO place_camping_fee_evidence
        (place_id, place_campsite_id, status, source_name, source_url,
         evidence_text, verified_on, verified_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $placeId, $campsiteId, $status, $sourceName,
        trim($sourceUrl), trim($evidenceText), $verifiedDate, $verifiedBy
    ]);
    return (int) $db->lastInsertId();
}

/** Latest verified campground-wide fee decision; UNKNOWN when missing. */
function llama_camping_fee_for_place(PDO $db, int $placeId): array
{
    $stmt = $db->prepare(
        'SELECT status, source_name, source_url, evidence_text, verified_on
         FROM place_camping_fee_evidence
         WHERE place_id = ? AND place_campsite_id IS NULL
         ORDER BY verified_on DESC, id DESC LIMIT 1'
    );
    $stmt->execute([$placeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: [
        'status' => 'unknown', 'source_name' => null,
        'source_url' => null, 'evidence_text' => null, 'verified_on' => null,
    ];
}

/** A map shows $ only for confirmed paid overnight camping. */
function llama_camping_fee_marker_status(PDO $db, int $placeId): string
{
    $place = llama_camping_fee_for_place($db, $placeId);
    $status = (string) $place['status'];
    return in_array($status, llama_camping_fee_statuses(), true)
        ? $status
        : 'unknown';
}
