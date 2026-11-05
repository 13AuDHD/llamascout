<?php

declare(strict_types=1);

/**
 * M4-001: Place-defined recurring operating seasons.
 *
 * Seasons select a view of EXISTING canonical answers. They are not a second
 * Scout Report, do not describe verified closures, and do not change rates.
 * Until M4-002/M4-003, no public or admin reader consumes these records.
 */

/** Parse a recurring MM-DD date in a leap-year calendar. */
function llama_season_mmdd(mixed $value): ?string
{
    $date = trim((string) ($value ?? ''));
    if ($date === '') {
        return null;
    }
    if (!preg_match('/^\d{2}-\d{2}$/D', $date)) {
        throw new InvalidArgumentException('Season dates must use MM-DD.');
    }
    [$month, $day] = array_map('intval', explode('-', $date));
    if (!checkdate($month, $day, 2000)) {
        throw new InvalidArgumentException('Season date is not a valid month and day.');
    }
    return $date;
}

/** Ordinals 1..366 allow a single recurring date range to cross New Year. */
function llama_season_day_number(string $mmdd): int
{
    $date = llama_season_mmdd($mmdd);
    if ($date === null) {
        throw new InvalidArgumentException('Season date is required.');
    }
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', '2000-' . $date);
    if (!$day) {
        throw new InvalidArgumentException('Season date could not be parsed.');
    }
    return (int) $day->format('z') + 1;
}

/** Inclusive interval(s) on a leap-year calendar. No dates = manual selection only. */
function llama_season_intervals(array $season): array
{
    $start = llama_season_mmdd($season['start_mmdd'] ?? null);
    $end = llama_season_mmdd($season['end_mmdd'] ?? null);
    if (($start === null) !== ($end === null)) {
        throw new InvalidArgumentException('A season needs both start and end dates, or neither.');
    }
    if ($start === null) {
        return [];
    }
    $a = llama_season_day_number($start);
    $b = llama_season_day_number($end);
    return $a <= $b
        ? [[$a, $b]]
        : [[$a, 366], [1, $b]];
}

function llama_season_contains(array $season, string $mmdd): bool
{
    $day = llama_season_day_number($mmdd);
    foreach (llama_season_intervals($season) as [$from, $to]) {
        if ($from <= $day && $day <= $to) {
            return true;
        }
    }
    return false;
}

function llama_seasons_overlap(array $left, array $right): bool
{
    foreach (llama_season_intervals($left) as [$a, $b]) {
        foreach (llama_season_intervals($right) as [$c, $d]) {
            if (max($a, $c) <= min($b, $d)) {
                return true;
            }
        }
    }
    return false;
}

/** Normalize one active or archived season for validation and persistence. */
function llama_season_definition(array $input): array
{
    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 100) {
        throw new InvalidArgumentException('Season name must be 1 to 100 characters.');
    }

    $start = llama_season_mmdd($input['start_mmdd'] ?? null);
    $end = llama_season_mmdd($input['end_mmdd'] ?? null);
    $activeRaw = $input['is_active'] ?? 1;
    $defaultRaw = $input['is_default'] ?? 0;
    $allowedFlags = [0, 1, '0', '1', false, true];
    if (!in_array($activeRaw, $allowedFlags, true)
        || !in_array($defaultRaw, $allowedFlags, true)) {
        throw new InvalidArgumentException('Invalid season status.');
    }
    $active = (int) $activeRaw;
    $default = (int) $defaultRaw;
    if ($active === 0) {
        $default = 0;
    }

    $notes = trim((string) ($input['notes'] ?? ''));
    if (mb_strlen($notes) > 3000) {
        throw new InvalidArgumentException('Season notes exceed 3000 characters.');
    }
    $source = trim((string) ($input['source_url'] ?? ''));
    if ($source !== '' && (strlen($source) > 2048
        || !filter_var($source, FILTER_VALIDATE_URL)
        || !in_array(strtolower((string) parse_url($source, PHP_URL_SCHEME)), ['https', 'http'], true))) {
        throw new InvalidArgumentException('Enter a valid season source URL.');
    }

    $definition = [
        'id' => (int) ($input['id'] ?? 0),
        'name' => $name,
        'start_mmdd' => $start,
        'end_mmdd' => $end,
        'is_default' => $default,
        'is_active' => $active,
        'sort_order' => (int) ($input['sort_order'] ?? 0),
        'notes' => $notes !== '' ? $notes : null,
        'source_url' => $source !== '' ? $source : null,
    ];
    llama_season_intervals($definition); // Check paired dates.
    return $definition;
}

/** Validates the entire active Place schedule. Never silently chooses a winner. */
function llama_seasons_validate_schedule(array $seasons): void
{
    $names = [];
    $active = [];
    $defaults = 0;
    foreach ($seasons as $season) {
        $item = llama_season_definition($season);
        if (!$item['is_active']) {
            continue;
        }
        $key = mb_strtolower($item['name'], 'UTF-8');
        if (isset($names[$key])) {
            throw new InvalidArgumentException('Duplicate active season name: ' . $item['name']);
        }
        $names[$key] = true;
        $defaults += $item['is_default'];
        $active[] = $item;
    }
    if ($defaults > 1) {
        throw new InvalidArgumentException('Only one default season is allowed per Place.');
    }
    for ($i = 0; $i < count($active); $i++) {
        for ($j = $i + 1; $j < count($active); $j++) {
            if (llama_seasons_overlap($active[$i], $active[$j])) {
                throw new InvalidArgumentException(
                    'Season date ranges overlap: ' . $active[$i]['name']
                    . ' and ' . $active[$j]['name']
                );
            }
        }
    }
}

function llama_seasons_for_place(
    PDO $db,
    int $placeId,
    bool $includeInactive = false
): array {
    if ($placeId < 1) {
        throw new InvalidArgumentException('A Place is required.');
    }
    $query = $db->prepare(
        'SELECT id, place_id, name, start_mmdd, end_mmdd,
                is_default, is_active, sort_order, notes, source_url
         FROM place_seasons WHERE place_id = ?'
        . ($includeInactive ? '' : ' AND is_active = 1')
        . ' ORDER BY sort_order, id'
    );
    $query->execute([$placeId]);
    return $query->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Current-season choice is a VIEW preference, never an operating-status claim. */
function llama_season_for_date(array $seasons, string $date): ?array
{
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$day || $day->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Use a valid YYYY-MM-DD date.');
    }
    $default = null;
    foreach ($seasons as $season) {
        if (!(int) ($season['is_active'] ?? 1)) {
            continue;
        }
        if ((int) ($season['is_default'] ?? 0) === 1) {
            $default = $season;
        }
        if (llama_season_contains($season, $day->format('m-d'))) {
            return $season;
        }
    }
    return $default;
}

/**
 * Admin service: atomic Place-level upsert, with a row lock serializing edits.
 * The caller must enforce Admin/Owner access and CSRF before calling.
 * Archived seasons retain identity for future audit/history.
 */
function llama_season_save(PDO $db, int $placeId, int $actorId, array $input): int
{
    if ($placeId < 1 || $actorId < 1) {
        throw new InvalidArgumentException('A valid Place and Admin are required.');
    }
    $candidate = llama_season_definition($input);
    $seasonId = $candidate['id'];
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) {
        $db->beginTransaction();
    }
    try {
        $lock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
        $lock->execute([$placeId]);
        if (!$lock->fetchColumn()) {
            throw new InvalidArgumentException('Place not found.');
        }

        $query = $db->prepare(
            'SELECT id, name, start_mmdd, end_mmdd, is_default,
                    is_active, sort_order, notes, source_url
             FROM place_seasons WHERE place_id = ? FOR UPDATE'
        );
        $query->execute([$placeId]);
        $existing = $query->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $found = $seasonId === 0;
        $schedule = [];
        foreach ($existing as $row) {
            if ((int) $row['id'] === $seasonId) {
                $found = true;
                continue;
            }
            if ($candidate['is_default'] === 1) {
                $row['is_default'] = 0;
            }
            $schedule[] = $row;
        }
        if (!$found) {
            throw new InvalidArgumentException('Season does not belong to this Place.');
        }
        $schedule[] = $candidate;
        llama_seasons_validate_schedule($schedule);

        if ($candidate['is_default'] === 1) {
            $clear = $db->prepare(
                'UPDATE place_seasons SET is_default = 0
                 WHERE place_id = ? AND is_default = 1'
            );
            $clear->execute([$placeId]);
        }

        $values = [
            $candidate['name'], $candidate['start_mmdd'], $candidate['end_mmdd'],
            $candidate['is_default'], $candidate['is_active'], $candidate['sort_order'],
            $candidate['notes'], $candidate['source_url'], $actorId,
        ];
        if ($seasonId > 0) {
            $update = $db->prepare(
                'UPDATE place_seasons SET name = ?, start_mmdd = ?, end_mmdd = ?,
                    is_default = ?, is_active = ?, sort_order = ?, notes = ?,
                    source_url = ?, updated_by = ?
                 WHERE id = ? AND place_id = ?'
            );
            $update->execute([...$values, $seasonId, $placeId]);
        } else {
            $insert = $db->prepare(
                'INSERT INTO place_seasons
                    (name, start_mmdd, end_mmdd, is_default, is_active,
                     sort_order, notes, source_url, updated_by, created_by, place_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([...$values, $actorId, $placeId]);
            $seasonId = (int) $db->lastInsertId();
        }

        if ($ownsTransaction) {
            $db->commit();
        }
        return $seasonId;
    } catch (Throwable $error) {
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}
