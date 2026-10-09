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
                $status = strtolower((string) ($row['status'] ?? ''));
                if (in_array($status, ['paid', 'free', 'mixed'], true)) {
                    $result[(int) $row['place_id']] = $status;
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
