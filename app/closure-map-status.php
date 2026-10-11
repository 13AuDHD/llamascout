<?php

declare(strict_types=1);

/**
 * Attach verified operating status to already-authorized map records.
 * This reads only Place-level closures: a closed campsite does not close
 * the campground. Unknown and non-closed records remain visible by default.
 */
function llama_closure_enrich_map_places(PDO $db, array $places): array
{
    if ($places === []) {
        return $places;
    }

    $indices = [];
    foreach ($places as $index => $place) {
        if (!is_array($place)) {
            continue;
        }
        $id = (int) ($place['id'] ?? 0);
        if ($id > 0) {
            $indices[$id][] = $index;
        }
        $places[$index]['operating_status'] = 'open';
        $places[$index]['is_closed'] = false;
        $places[$index]['closure_reason'] = null;
        $places[$index]['expected_reopening'] = null;
    }

    if ($indices === []) {
        return $places;
    }

    $ids = array_keys($indices);
    // A bounded batch prevents placeholders and query sizes from growing
    // excessively for the full national map.
    foreach (array_chunk($ids, 400) as $batch) {
        $placeholder = implode(',', array_fill(0, count($batch), '?'));
        $stmt = $db->prepare(
            'SELECT place_id, status, reason, expected_reopening '
            . 'FROM place_operating_closures '
            . 'WHERE campsite_id IS NULL AND status IN (\'temporary\',\'seasonal\',\'permanent\') '
            . 'AND place_id IN (' . $placeholder . ')'
        );
        $stmt->execute($batch);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['place_id'];
            foreach ($indices[$id] ?? [] as $index) {
                $places[$index]['operating_status'] = (string) $row['status'];
                $places[$index]['is_closed'] = true;
                $places[$index]['closure_reason'] = (string) $row['reason'];
                $places[$index]['expected_reopening'] = $row['expected_reopening'];
            }
        }
    }

    return $places;
}
