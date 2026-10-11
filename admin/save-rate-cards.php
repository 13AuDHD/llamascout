<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

function rate_cards_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    rate_cards_json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$adminUser = moderation_require_admin();

if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
    rate_cards_json_response(['ok' => false, 'error' => 'Your session expired. Reload the page and try again.'], 403);
}

$placeId = (int) ($_POST['place_id'] ?? 0);
$raw = trim((string) ($_POST['rate_cards_json'] ?? ''));

if ($placeId < 1) {
    rate_cards_json_response(['ok' => false, 'error' => 'Invalid Place.'], 422);
}

if (strlen($raw) > 60000) {
    rate_cards_json_response(['ok' => false, 'error' => 'Rate card data is too large.'], 422);
}

$db = db();
$stmt = $db->prepare('SELECT 1 FROM places WHERE id = ? LIMIT 1');
$stmt->execute([$placeId]);
if (!$stmt->fetchColumn()) {
    rate_cards_json_response(['ok' => false, 'error' => 'Place not found.'], 404);
}

$cards = [];
if ($raw !== '') {
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        rate_cards_json_response(['ok' => false, 'error' => 'Rate card data is invalid.'], 422);
    }
    if (!is_array($decoded) || !array_is_list($decoded)) {
        rate_cards_json_response(['ok' => false, 'error' => 'Rate cards must be a list.'], 422);
    }
    if (count($decoded) > 50) {
        rate_cards_json_response(['ok' => false, 'error' => 'A Place can have at most 50 rate cards.'], 422);
    }

    $allowedScopes = ['entire_place', 'site_category', 'individual_site', 'mixed_other'];
    $allowedUnits = ['night', 'week', 'month', 'stay', 'season'];
    $allowedSeasons = ['year_round', 'peak', 'off_peak', 'seasonal', 'custom'];
    $allowedParking = ['', 'pull_through', 'back_in', 'pull_in', 'parallel', 'other'];

    foreach ($decoded as $card) {
        if (!is_array($card)) {
            continue;
        }
        $scope = trim((string) ($card['scope'] ?? 'entire_place'));
        $unit = trim((string) ($card['unit'] ?? 'night'));
        $season = trim((string) ($card['season'] ?? 'year_round'));
        $parking = trim((string) ($card['parking_style'] ?? ''));
        if (!in_array($scope, $allowedScopes, true)) $scope = 'entire_place';
        if (!in_array($unit, $allowedUnits, true)) $unit = 'night';
        if (!in_array($season, $allowedSeasons, true)) $season = 'year_round';
        if (!in_array($parking, $allowedParking, true)) $parking = '';

        $price = $card['price'] ?? null;
        $price = ($price === null || $price === '') ? null : (float) $price;
        if ($price !== null && $price < 0) $price = 0.0;

        $people = $card['max_people'] ?? null;
        $people = ($people === null || $people === '') ? null : max(0, min(999, (int) $people));
        $vehicles = $card['max_vehicles'] ?? null;
        $vehicles = ($vehicles === null || $vehicles === '') ? null : max(0, min(999, (int) $vehicles));

        $source = trim((string) ($card['source_url'] ?? ''));
        if ($source !== '' && (!filter_var($source, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($source, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            rate_cards_json_response(['ok' => false, 'error' => 'One of the pricing source URLs is invalid.'], 422);
        }

        $verified = trim((string) ($card['verified_on'] ?? ''));
        if ($verified !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $verified);
            if (!$date || $date->format('Y-m-d') !== $verified) {
                rate_cards_json_response(['ok' => false, 'error' => 'One of the price verification dates is invalid.'], 422);
            }
        }

        $cards[] = [
            'name' => mb_substr(trim((string) ($card['name'] ?? '')), 0, 120),
            'scope' => $scope,
            'category' => $scope === 'site_category' ? mb_substr(trim((string) ($card['category'] ?? '')), 0, 120) : '',
            'site' => $scope === 'individual_site' ? mb_substr(trim((string) ($card['site'] ?? '')), 0, 120) : '',
            'parking_style' => $parking,
            'price' => $price,
            'unit' => $unit,
            'season' => $season,
            'max_people' => $people,
            'max_vehicles' => $vehicles,
            'source_url' => mb_substr($source, 0, 2048),
            'verified_on' => $verified,
        ];
    }
}

$encoded = $cards
    ? json_encode($cards, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    : null;

try {
    $stmt = $db->prepare(
        'INSERT INTO place_rules (place_id, rate_cards_json) VALUES (?, ?) '
        . 'ON DUPLICATE KEY UPDATE rate_cards_json = VALUES(rate_cards_json)'
    );
    $stmt->execute([$placeId, $encoded]);

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            (int) ($adminUser['id'] ?? 0),
            null,
            'place.rate_cards_updated',
            'Updated Place pricing rate cards.',
            ['place_id' => $placeId, 'rate_card_count' => count($cards)]
        );
    }
} catch (PDOException $exception) {
    rate_cards_json_response([
        'ok' => false,
        'error' => 'Rate cards could not be saved. Make sure the rate_cards_json database column has been installed.',
    ], 500);
}

rate_cards_json_response(['ok' => true, 'count' => count($cards)]);
