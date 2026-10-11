<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

function supplemental_rules_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    supplemental_rules_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$adminUser = moderation_require_admin();

if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
    supplemental_rules_response([
        'ok' => false,
        'error' => 'Your session expired. Reload the page and try again.',
    ], 403);
}

$placeId = (int) ($_POST['place_id'] ?? 0);
if ($placeId < 1) {
    supplemental_rules_response(['ok' => false, 'error' => 'Invalid Place.'], 422);
}

$db = db();
$stmt = $db->prepare('SELECT 1 FROM places WHERE id = ? LIMIT 1');
$stmt->execute([$placeId]);
if (!$stmt->fetchColumn()) {
    supplemental_rules_response(['ok' => false, 'error' => 'Place not found.'], 404);
}

$unknownTokens = [
    '',
    '__LLAMA_UNKNOWN__',
    '__LLAMA_UNANSWERED__',
];

$nullable = static function (string $key) use ($unknownTokens): ?string {
    $value = trim((string) ($_POST[$key] ?? ''));
    return in_array($value, $unknownTokens, true) ? null : $value;
};

$tri = static function (string $key) use ($nullable): ?int {
    $value = $nullable($key);
    if ($value === null) {
        return null;
    }
    return in_array($value, ['0', '1'], true) ? (int) $value : null;
};

$conditional = static function (string $key) use ($nullable): ?int {
    $value = $nullable($key);
    if ($value === null) {
        return null;
    }
    return in_array($value, ['0', '1', '3'], true) ? (int) $value : null;
};

$text = static function (string $key, int $max = 10000) use ($nullable): ?string {
    $value = $nullable($key);
    return $value === null ? null : mb_substr($value, 0, $max);
};

$url = static function (string $key) use ($text): ?string {
    $value = $text($key, 2048);
    if ($value === null) {
        return null;
    }
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('A rules URL is invalid.');
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('A rules URL must use http or https.');
    }
    return $value;
};

try {
    $values = [
        'generator_prohibited' => $tri('generator_prohibited'),
        'generator_run_restrictions' => $tri('generator_run_restrictions'),
        'generator_max_run_hours' => $text('generator_max_run_hours', 24),
        'generator_restriction_details' => $text('generator_restriction_details', 4000),
        'place_charcoal_grills_allowed' => $conditional('place_charcoal_grills_allowed'),
        'place_propane_fire_pits_allowed' => $conditional('place_propane_fire_pits_allowed'),
        'place_quiet_hours' => $tri('place_quiet_hours'),
        'place_quiet_hours_begin' => $text('place_quiet_hours_begin', 8),
        'place_quiet_hours_end' => $text('place_quiet_hours_end', 8),
        'place_alcohol_permitted' => $conditional('place_alcohol_permitted'),
        'fire_activities_conditional_details' => $text('fire_activities_conditional_details', 4000),
        'fire_activity_permit_url' => $url('fire_activity_permit_url'),
        'place_additional_rules' => $text('place_additional_rules', 10000),
    ];

    $columns = array_keys($values);
    $quoted = array_map(
        static fn (string $column): string => '`' . $column . '`',
        $columns
    );

    $updates = array_map(
        static fn (string $column): string => '`' . $column . '` = VALUES(`' . $column . '`)',
        $columns
    );

    $sql = 'INSERT INTO place_rules (place_id, '
        . implode(', ', $quoted)
        . ') VALUES (?, '
        . implode(', ', array_fill(0, count($columns), '?'))
        . ') ON DUPLICATE KEY UPDATE '
        . implode(', ', $updates);

    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$placeId], array_values($values)));

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            (int) ($adminUser['id'] ?? 0),
            null,
            'place.rules_supplemental_updated',
            'Updated Place-wide rules and restrictions.',
            ['place_id' => $placeId]
        );
    }
} catch (InvalidArgumentException $exception) {
    supplemental_rules_response(['ok' => false, 'error' => $exception->getMessage()], 422);
} catch (PDOException $exception) {
    supplemental_rules_response([
        'ok' => false,
        'error' => 'Supplemental Place rules could not be saved. Make sure the new place_rules columns have been installed.',
    ], 500);
}

supplemental_rules_response(['ok' => true]);
