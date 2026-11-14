<?php

declare(strict_types=1);

require_once __DIR__ . '/operating-closures.php';

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
    $query = 'SELECT c.campsite_id, c.status, c.reason, c.closure_began, '
        . 'c.expected_reopening, c.verified_at, p.state '
        . 'FROM place_operating_closures c '
        . 'INNER JOIN places p ON p.id = c.place_id '
        . 'WHERE c.place_id = ? AND c.campsite_id IN (' . $placeholders . ') '
        . "AND c.status IN ('temporary','seasonal','permanent')";
    $statement = $db->prepare($query);
    $statement->execute(array_merge([$placeId], array_keys($ids)));
    $closures = [];
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        if (!llama_closure_is_active(
            $row,
            llama_closure_local_today((string) ($row['state'] ?? ''))
        )) {
            continue;
        }
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
