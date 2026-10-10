<?php

declare(strict_types=1);

/**
 * Read-only, evidence-backed camping fee statuses for public map output.
 *
 * Only the latest verified campground-wide decision is authoritative here.
 * Campsite decisions are NOT extrapolated to the parent Place: even a dozen
 * known paid campsites cannot establish that every campsite is paid.
 *
 * If the fee evidence migration has not run, the map remains available and
 * returns unknown for every Place rather than implying that they are free.
 */

/**
 * Conservative source-text classifier for overnight camping charges.
 * It intentionally ignores entrance, parking, reservation, and amenity fees
 * unless the same text explicitly ties a positive amount to camping/site use.
 */
function llama_camping_fee_source_text_status(string $description): string
{
    $text = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);

    if ($text === '') {
        return 'unknown';
    }

    $hasPositiveAmount = preg_match(
        '/(?:\$\s*[1-9]\d{0,3}(?:\.\d{1,2})?|\b[1-9]\d{0,3}(?:\.\d{1,2})?\s*(?:USD|dollars?)\b)/i',
        $text
    ) === 1;

    $hasCampingContext = preg_match(
        '/\b(?:camp(?:ing|ground)?|campsites?|sites?|overnight|per\s+night|nightly)\b/i',
        $text
    ) === 1;

    $hasFeeContext = preg_match(
        '/\b(?:fee|rate|cost|charge|price|per\s+night|nightly)\b|\$/i',
        $text
    ) === 1;

    if (!$hasPositiveAmount || !$hasCampingContext || !$hasFeeContext) {
        return 'unknown';
    }

    $mentionsFreeCamping = preg_match(
        '/\b(?:free\s+(?:camping|campsites?|sites?)|(?:camping|campsites?|sites?)\s+(?:(?:is|are)\s+)?free|some\s+(?:camping|campsites?|sites?)\s+(?:(?:is|are)\s+)?free)\b/i',
        $text
    ) === 1;

    if ($mentionsFreeCamping) {
        return 'mixed';
    }

    return 'paid';
}

function llama_camping_fee_map_statuses(PDO $db, array $placeIds): array
{
    $placeIds = array_values(array_unique(array_filter(
        array_map('intval', $placeIds),
        static fn (int $id): bool => $id > 0
    )));

    $result = array_fill_keys($placeIds, 'unknown');

    if ($placeIds === []) {
        return $result;
    }

    /*
     * The Scout Report's Camping fee is the canonical manual answer.
     * A positive saved camping fee must immediately classify the Place as
     * paid on the public map. This was previously omitted entirely, which
     * meant an Admin could save a real nightly price and still get no $
     * marker unless a separate fee-evidence/USFS/RIDB source happened to
     * classify the Place too.
     *
     * Only the camping fee column is considered here. Entrance/day-use,
     * parking, reservation, membership and other charges do not establish
     * paid camping. A stored zero is treated as free; NULL remains unknown.
     */
    foreach (array_chunk($placeIds, 400) as $chunk) {
        $placeholders = implode(', ', array_fill(0, count($chunk), '?'));

        try {
            $stmt = $db->prepare(
                "SELECT place_id, fee
                 FROM place_rules
                 WHERE place_id IN ($placeholders)
                   AND fee IS NOT NULL"
            );
            $stmt->execute($chunk);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int) ($row['place_id'] ?? 0);

                if ($id < 1) {
                    continue;
                }

                $fee = $row['fee'] ?? null;

                if ($fee === null || $fee === '' || !is_numeric($fee)) {
                    continue;
                }

                $result[$id] = (float) $fee > 0.0
                    ? 'paid'
                    : 'free';
            }
        } catch (PDOException $exception) {
            $code = (string) ($exception->errorInfo[1] ?? '');

            if ($code !== '1146' && $code !== '42S02' && $code !== '1054' && $code !== '42S22') {
                throw $exception;
            }
        }
    }

    // Chunk requests to avoid giant IN clauses on large map inventories.
    foreach (array_chunk($placeIds, 400) as $chunk) {
        $placeholders = implode(', ', array_fill(0, count($chunk), '?'));
        $sql = <<<SQL
SELECT e.place_id, e.status
FROM place_camping_fee_evidence AS e
WHERE e.place_id IN ($placeholders)
  AND e.place_campsite_id IS NULL
  AND e.id = (
      SELECT newest.id
      FROM place_camping_fee_evidence AS newest
      WHERE newest.place_id = e.place_id
        AND newest.place_campsite_id IS NULL
      ORDER BY newest.verified_on DESC, newest.id DESC
      LIMIT 1
  )
SQL;

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int) ($row['place_id'] ?? 0);

                if ($id < 1 || ($result[$id] ?? 'unknown') !== 'unknown') {
                    continue;
                }

                $status = strtolower((string) ($row['status'] ?? ''));
                if (in_array($status, ['paid', 'free', 'mixed'], true)) {
                    $result[$id] = $status;
                }
            }
        } catch (PDOException $exception) {
            // No fee-evidence table yet: retain unknown classifications.
            // Other database errors should surface rather than hide an outage.
            $code = (string) ($exception->errorInfo[1] ?? '');
            if ($code !== '1146' && $code !== '42S02') {
                throw $exception;
            }
            return $result;
        }
    }

    /*
     * A human-reviewed USFS link is usable evidence for an explicitly
     * priced overnight campground. Do not override a verified manual
     * decision. Missing fee description or a generic fee flag is unknown.
     */
    foreach (array_chunk($placeIds, 400) as $chunk) {
        $placeholders = implode(', ', array_fill(0, count($chunk), '?'));
        try {
            $stmt = $db->prepare(
                "SELECT place_id, fee_charged, fee_description,
                        camping_fee_candidate, camping_rate_candidate
                 FROM place_usfs_enrichment
                 WHERE place_id IN ($placeholders)"
            );
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int) $row['place_id'];
                if (($result[$id] ?? 'unknown') !== 'unknown') {
                    continue;
                }
                $description = (string) ($row['fee_description'] ?? '');
                $amount = (float) ($row['camping_rate_candidate'] ?? 0);
                if (
                    strtoupper(trim((string) ($row['fee_charged'] ?? ''))) !== 'Y'
                    || (string) ($row['camping_fee_candidate'] ?? '') !== 'paid_candidate'
                    || $amount <= 0
                ) {
                    continue;
                }

                $status = llama_camping_fee_source_text_status($description);

                /*
                 * The USFS enrichment parser has already identified this as
                 * a positive camping-rate candidate. Preserve that signal even
                 * when the source prose is terse, such as "$22/night".
                 */
                $result[$id] = $status === 'mixed' ? 'mixed' : 'paid';
            }
        } catch (PDOException $exception) {
            $code = (string) ($exception->errorInfo[1] ?? '');
            if ($code !== '1146' && $code !== '42S02') {
                throw $exception;
            }
        }
    }

    /*
     * Recreation.gov / RIDB exposes FacilityUseFeeDescription separately
     * from reservation and entrance fields. Use it only when the text itself
     * clearly identifies a positive overnight camping/site charge.
     */
    foreach (array_chunk($placeIds, 400) as $chunk) {
        $placeholders = implode(', ', array_fill(0, count($chunk), '?'));

        try {
            $stmt = $db->prepare(
                "SELECT place_id, fee_description, source_provider
                 FROM place_facility_facts
                 WHERE place_id IN ($placeholders)"
            );
            $stmt->execute($chunk);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int) ($row['place_id'] ?? 0);

                if ($id < 1 || ($result[$id] ?? 'unknown') !== 'unknown') {
                    continue;
                }

                $provider = strtolower(trim((string) ($row['source_provider'] ?? '')));

                if (
                    $provider !== ''
                    && !str_contains($provider, 'recreation.gov')
                    && !str_contains($provider, 'ridb')
                ) {
                    continue;
                }

                $status = llama_camping_fee_source_text_status(
                    (string) ($row['fee_description'] ?? '')
                );

                if ($status !== 'unknown') {
                    $result[$id] = $status;
                }
            }
        } catch (PDOException $exception) {
            $code = (string) ($exception->errorInfo[1] ?? '');
            if ($code !== '1146' && $code !== '42S02') {
                throw $exception;
            }
        }
    }

    return $result;
}

/** Enrich existing map rows without exposing the underlying audit evidence. */
function llama_camping_fee_enrich_map_places(PDO $db, array $places): array
{
    $ids = array_map(
        static fn (array $place): int => (int) ($place['id'] ?? 0),
        $places
    );
    $statuses = llama_camping_fee_map_statuses($db, $ids);

    foreach ($places as &$place) {
        $place['camping_fee_status'] =
            $statuses[(int) ($place['id'] ?? 0)] ?? 'unknown';
    }
    unset($place);

    return $places;
}
