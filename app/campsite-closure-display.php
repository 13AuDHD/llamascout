<?php

declare(strict_types=1);

/**
 * Add verified closure metadata to campground campsite results.
 * This never changes availability, published data or review status.
 * An individual closed campsite does not close its parent Place.
 */
function llama_campsite_closure_enrich(PDO $db, int $placeId, array $sites): array
{
    if ($placeId < 1 || !$sites) {
        return $sites;
    }

    $ids = [];
    foreach ($sites as $site) {
        $id = (int) ($site['id'] ?? 0);
        if ($id > 0) {
            $ids[$id] = true;
        }
    }
    if (!$ids) {
        return $sites;
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $query = 'SELECT campsite_id, status, reason, closure_began, '
        . 'expected_reopening, verified_at '
        . 'FROM place_operating_closures '
        . 'WHERE place_id = ? AND campsite_id IN (' . $placeholders . ') '
        . "AND status IN ('temporary','seasonal','permanent')";
    $statement = $db->prepare($query);
    $statement->execute(array_merge([$placeId], array_keys($ids)));
    $closures = [];
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $closures[(int) $row['campsite_id']] = $row;
    }

    foreach ($sites as &$site) {
        $id = (int) ($site['id'] ?? 0);
        $closure = $closures[$id] ?? null;
        $site['is_closed'] = $closure !== null;
        $site['operating_status'] = $closure['status'] ?? 'open';
        $site['closure_reason'] = $closure['reason'] ?? null;
        $site['closure_began'] = $closure['closure_began'] ?? null;
        $site['expected_reopening'] = $closure['expected_reopening'] ?? null;
        $site['closure_verified_at'] = $closure['verified_at'] ?? null;
    }
    unset($site);

    return $sites;
}
