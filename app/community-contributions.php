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
    return llama_contribution_role_at_time(
        db(),
        $userId
    );
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
    require_once __DIR__ . '/place-report.php';
    return llama_place_report_place_types();
}

function community_new_place_submission_for_user(
    int $userId,
    int $submissionId
): ?array {
    if ($userId < 1 || $submissionId < 1) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT
            id,
            user_id,
            place_name,
            status,
            submission_data,
            submitted_at,
            reviewed_at,
            reviewed_by,
            review_notes
         FROM place_submissions
         WHERE id = ?
           AND user_id = ?
         LIMIT 1'
    );

    $stmt->execute([$submissionId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    $data = json_decode(
        (string) ($row['submission_data'] ?? '{}'),
        true
    );

    $row['data'] = is_array($data) ? $data : [];
    return $row;
}


function community_place_update_field_definitions(): array
{
    require_once __DIR__ . '/place-report.php';
    $definitions = llama_place_report_published_definitions();
    foreach ($definitions as &$definition) {
        $definition['group'] = llama_place_report_sections()[$definition['section']]['label'] ?? 'Other';
    }
    unset($definition);
    return $definitions;
}

function community_update_current_values(PDO $db, int $placeId): array
{
    require_once __DIR__ . '/place-report.php';
    return llama_place_report_current_values($db, $placeId);
}

function community_find_place_for_update(string $slug): ?array
{
    $stmt = db()->prepare(
        "SELECT *
         FROM places
         WHERE slug = :slug
           AND status IN ('active', 'featured')
         LIMIT 1"
    );

    $stmt->execute([
        ':slug' => $slug,
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function community_open_update_for_user(
    int $userId,
    int $placeId
): ?array {
    $stmt = db()->prepare(
        "SELECT
            id,
            status,
            submitted_at,
            reviewed_at,
            review_notes,
            visited_at,
            proposed_changes,
            original_values,
            photos,
            contributor_notes
         FROM place_update_submissions
         WHERE user_id = :user_id
           AND place_id = :place_id
           AND status IN ('pending', 'needs-changes')
         ORDER BY submitted_at DESC
         LIMIT 1"
    );

    $stmt->execute([
        ':user_id' => $userId,
        ':place_id' => $placeId,
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function community_parse_update_value(
    mixed $raw,
    string $type
): mixed {
    if ($raw === '__NULL__') {
        return null;
    }

    if ($type === 'bool') {
        if ((string) $raw === '1') {
            return true;
        }

        if ((string) $raw === '0') {
            return false;
        }

        return null;
    }

    if ($type === 'permission') {
        $value = (int) $raw;

        return in_array($value, [0, 1, 2], true)
            ? $value
            : null;
    }

    if ($type === 'rating') {
        $value = (int) $raw;

        return $value >= 1 && $value <= 5
            ? $value
            : null;
    }

    if ($type === 'int') {
        return community_clean_int(
            $raw,
            -100000,
            100000
        );
    }

    if ($type === 'float') {
        return community_clean_float(
            $raw,
            -1000000,
            1000000
        );
    }

    return community_clean_text(
        $raw,
        $type === 'textarea'
            ? 5000
            : 500
    );
}

function community_resubmit_place_update(int $userId, array $place, int $updateId, array $input): int
{
    require_once __DIR__ . '/place-update.php';
    $input = llama_place_report_legacy_update_input($input);
    return llama_place_update_resubmit($userId, $place, $updateId, $input);
}


function submit_place_update(int $userId, array $place, array $input): int
{
    require_once __DIR__ . '/place-update.php';
    $input = llama_place_report_legacy_update_input($input);
    return llama_place_update_submit($userId, $place, $input);
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
