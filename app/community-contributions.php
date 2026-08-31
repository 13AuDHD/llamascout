<?php

declare(strict_types=1);

function community_csrf_token(): string
{
    if (empty($_SESSION['community_csrf_token'])) {
        $_SESSION['community_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['community_csrf_token'];
}

function community_verify_csrf(string $token): bool
{
    $stored = (string) ($_SESSION['community_csrf_token'] ?? '');
    return $stored !== '' && $token !== '' && hash_equals($stored, $token);
}

function community_role_at_submission(int $userId): string
{
    $roles = user_roles($userId);

    foreach (['admin', 'master-scout', 'master_scout', 'scout'] as $role) {
        if (in_array($role, $roles, true)) {
            return str_replace('_', '-', $role);
        }
    }

    if (function_exists('user_has_member_access') && user_has_member_access($userId)) {
        return 'member';
    }

    return 'user';
}

function community_clean_text(mixed $value, int $maxLength = 5000): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function community_clean_float(mixed $value, float $min, float $max): ?float
{
    $value = trim((string) $value);
    if ($value === '' || !is_numeric($value)) {
        return null;
    }

    $number = (float) $value;
    if ($number < $min || $number > $max) {
        return null;
    }

    return $number;
}

function community_clean_int(mixed $value, int $min, int $max): ?int
{
    $value = trim((string) $value);
    if ($value === '' || filter_var($value, FILTER_VALIDATE_INT) === false) {
        return null;
    }

    $number = (int) $value;
    if ($number < $min || $number > $max) {
        return null;
    }

    return $number;
}

function community_place_types(): array
{
    return [
        'dispersed-camping' => 'Dispersed camping',
        'developed-campground' => 'Developed campground',
        'vehicle-pulloff' => 'Vehicle pull-off',
        'trailhead' => 'Trailhead',
        'day-use' => 'Day-use area',
        'other' => 'Other',
    ];
}

function submit_new_place(int $userId, array $input): int
{
    $name = community_clean_text($input['name'] ?? null, 200);
    if ($name === null) {
        throw new InvalidArgumentException('Place name is required.');
    }

    $type = (string) ($input['type'] ?? 'other');
    if (!array_key_exists($type, community_place_types())) {
        $type = 'other';
    }

    $latitude = community_clean_float($input['latitude'] ?? null, -90, 90);
    $longitude = community_clean_float($input['longitude'] ?? null, -180, 180);

    if (($latitude === null) !== ($longitude === null)) {
        throw new InvalidArgumentException('Enter both latitude and longitude, or leave both blank.');
    }

    $data = [
        'type' => $type,
        'description' => community_clean_text($input['description'] ?? null),
        'latitude' => $latitude,
        'longitude' => $longitude,
        'elevation_feet' => community_clean_int($input['elevation_feet'] ?? null, -1500, 30000),
        'road' => community_clean_text($input['road'] ?? null, 255),
        'city' => community_clean_text($input['city'] ?? null, 120),
        'county' => community_clean_text($input['county'] ?? null, 120),
        'state' => community_clean_text($input['state'] ?? null, 120),
        'region' => community_clean_text($input['region'] ?? null, 160),
        'land_manager' => community_clean_text($input['land_manager'] ?? null, 180),
        'land_type' => community_clean_text($input['land_type'] ?? null, 180),
        'access_summary' => community_clean_text($input['access_summary'] ?? null),
        'sensory_summary' => community_clean_text($input['sensory_summary'] ?? null),
        'contributor_notes' => community_clean_text($input['contributor_notes'] ?? null),
        'visited_at' => community_clean_text($input['visited_at'] ?? null, 30),
    ];

    $stmt = db()->prepare(
        'INSERT INTO place_submissions
            (user_id, role_at_submission, place_name, source_type, status, submission_data)
         VALUES
            (:user_id, :role_at_submission, :place_name, :source_type, :status, :submission_data)'
    );

    $stmt->execute([
        ':user_id' => $userId,
        ':role_at_submission' => community_role_at_submission($userId),
        ':place_name' => $name,
        ':source_type' => 'community-scouted',
        ':status' => 'pending',
        ':submission_data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);

    return (int) db()->lastInsertId();
}

function community_editable_place_fields(array $place): array
{
    return [
        'name' => ['label' => 'Place name', 'value' => $place['name'] ?? null],
        'description' => ['label' => 'Description', 'value' => $place['description'] ?? null],
        'latitude' => ['label' => 'Latitude', 'value' => $place['latitude'] ?? null],
        'longitude' => ['label' => 'Longitude', 'value' => $place['longitude'] ?? null],
        'elevation_feet' => ['label' => 'Elevation (ft)', 'value' => $place['elevation_feet'] ?? null],
        'road' => ['label' => 'Road', 'value' => $place['road'] ?? null],
        'city' => ['label' => 'City', 'value' => $place['city'] ?? null],
        'county' => ['label' => 'County', 'value' => $place['county'] ?? null],
        'state' => ['label' => 'State', 'value' => $place['state'] ?? null],
        'region' => ['label' => 'Region / district', 'value' => $place['region'] ?? null],
        'land_manager' => ['label' => 'Land manager', 'value' => $place['land_manager'] ?? null],
        'land_type' => ['label' => 'Land type', 'value' => $place['land_type'] ?? null],
        'access_summary' => ['label' => 'Access summary', 'value' => $place['access_summary'] ?? null],
        'sensory_summary' => ['label' => 'Sensory summary', 'value' => $place['sensory_summary'] ?? null],
    ];
}

function community_find_place_for_update(string $slug): ?array
{
    $stmt = db()->prepare(
        "SELECT id, slug, name, status, description, latitude, longitude, elevation_feet,
                road, city, county, state, region, land_manager, land_type,
                access_summary, sensory_summary
         FROM places
         WHERE slug = :slug
           AND status IN ('active', 'featured')
         LIMIT 1"
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function community_open_update_for_user(int $userId, int $placeId): ?array
{
    $stmt = db()->prepare(
        "SELECT id, status, submitted_at
         FROM place_update_submissions
         WHERE user_id = :user_id
           AND place_id = :place_id
           AND status IN ('pending', 'needs-changes')
         ORDER BY submitted_at DESC
         LIMIT 1"
    );
    $stmt->execute([':user_id' => $userId, ':place_id' => $placeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function submit_place_update(int $userId, array $place, array $input): int
{
    $placeId = (int) ($place['id'] ?? 0);
    if ($placeId < 1) {
        throw new InvalidArgumentException('Invalid place.');
    }

    if (community_open_update_for_user($userId, $placeId)) {
        throw new RuntimeException('You already have an open update for this place.');
    }

    $fields = community_editable_place_fields($place);
    $proposed = [];
    $original = [];

    foreach ($fields as $key => $meta) {
        if (!array_key_exists($key, $input)) {
            continue;
        }

        $newValue = match ($key) {
            'latitude' => community_clean_float($input[$key], -90, 90),
            'longitude' => community_clean_float($input[$key], -180, 180),
            'elevation_feet' => community_clean_int($input[$key], -1500, 30000),
            'name' => community_clean_text($input[$key], 200),
            'road' => community_clean_text($input[$key], 255),
            'city', 'county', 'state' => community_clean_text($input[$key], 120),
            'region' => community_clean_text($input[$key], 160),
            'land_manager', 'land_type' => community_clean_text($input[$key], 180),
            default => community_clean_text($input[$key]),
        };

        $oldValue = $meta['value'];
        $oldComparable = $oldValue === null ? null : (string) $oldValue;
        $newComparable = $newValue === null ? null : (string) $newValue;

        if ($oldComparable === $newComparable) {
            continue;
        }

        $proposed[$key] = $newValue;
        $original[$key] = $oldValue;
    }

    if (!$proposed) {
        throw new InvalidArgumentException('Change at least one field before submitting.');
    }

    $visitedAt = community_clean_text($input['visited_at'] ?? null, 30);
    $notes = community_clean_text($input['contributor_notes'] ?? null);

    $stmt = db()->prepare(
        'INSERT INTO place_update_submissions
            (place_id, user_id, update_type, status, role_at_submission, visited_at,
             proposed_changes, original_values, contributor_notes)
         VALUES
            (:place_id, :user_id, :update_type, :status, :role_at_submission, :visited_at,
             :proposed_changes, :original_values, :contributor_notes)'
    );

    $stmt->execute([
        ':place_id' => $placeId,
        ':user_id' => $userId,
        ':update_type' => 'update',
        ':status' => 'pending',
        ':role_at_submission' => community_role_at_submission($userId),
        ':visited_at' => $visitedAt !== null ? $visitedAt . (strlen($visitedAt) === 10 ? ' 00:00:00' : '') : null,
        ':proposed_changes' => json_encode($proposed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ':original_values' => json_encode($original, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ':contributor_notes' => $notes,
    ]);

    return (int) db()->lastInsertId();
}

function community_submissions_for_user(int $userId): array
{
    $newStmt = db()->prepare(
        "SELECT id, place_name AS name, status, submitted_at, reviewed_at, review_notes
         FROM place_submissions
         WHERE user_id = :user_id
         ORDER BY submitted_at DESC"
    );
    $newStmt->execute([':user_id' => $userId]);
    $newPlaces = $newStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($newPlaces as &$row) {
        $row['kind'] = 'new-place';
        $row['label'] = 'New place';
        $row['slug'] = null;
    }
    unset($row);

    $updateStmt = db()->prepare(
        "SELECT u.id, p.name, p.slug, u.status, u.submitted_at, u.reviewed_at, u.review_notes
         FROM place_update_submissions u
         JOIN places p ON p.id = u.place_id
         WHERE u.user_id = :user_id
         ORDER BY u.submitted_at DESC"
    );
    $updateStmt->execute([':user_id' => $userId]);
    $updates = $updateStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($updates as &$row) {
        $row['kind'] = 'update';
        $row['label'] = 'Place update';
    }
    unset($row);

    $all = array_merge($newPlaces, $updates);
    usort($all, static fn (array $a, array $b): int => strcmp((string) $b['submitted_at'], (string) $a['submitted_at']));

    return $all;
}

function community_submission_counts(int $userId): array
{
    $all = community_submissions_for_user($userId);
    $counts = ['total' => count($all), 'open' => 0];

    foreach ($all as $item) {
        if (in_array((string) ($item['status'] ?? ''), ['pending', 'needs-changes', 'investigating'], true)) {
            $counts['open']++;
        }
    }

    return $counts;
}
