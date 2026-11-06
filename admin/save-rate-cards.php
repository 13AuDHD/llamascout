<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-place-report.php';
require_once dirname(__DIR__) . '/app/place-pricing.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
function rate_cards_json_response(array $payload, int $status = 200): never {
    http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') rate_cards_json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
$user = moderation_require_admin();
if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) rate_cards_json_response(['ok' => false, 'error' => 'Reload the page and try again.'], 403);
$db = db(); $id = (int) ($_POST['place_id'] ?? 0);
$q = $db->prepare('SELECT * FROM places WHERE id = ?'); $q->execute([$id]); $place = $q->fetch(PDO::FETCH_ASSOC);
if (!$place) rate_cards_json_response(['ok' => false, 'error' => 'Place not found.'], 404);
try {
    $doc = llama_pricing_validate((string) ($_POST['rate_cards_json'] ?? ''));
    if (($_POST['action'] ?? '') === 'preview') {
        $date = llama_pricing_date((string) ($_POST['date'] ?? ''));
        $counts = ['priced' => 0, 'review' => 0, 'unknown' => 0]; $examples = [];
        foreach (llama_pricing_enrich_sites($db, $place, llama_place_campsites($db, $id), $doc, $date) as $site) {
            $p = $site['pricing'];
            $counts[$p['state'] === 'priced' ? 'priced' : ($p['state'] === 'unknown' ? 'unknown' : 'review')]++;
            if (count($examples) < 12 || $p['state'] !== 'priced') $examples[] = ['name' => $site['display_name'], 'price' => $p['label']];
        }
        rate_cards_json_response(['ok' => true, ...$counts, 'sites' => $examples]);
    }
    // Compatibility callers publish through the same atomic canonical save,
    // permission check and audit as Edit Place. No direct JSON-column writer.
    admin_place_save_shared_report($db, (int) $user['id'], $id, ['place_rate_cards_json' => json_encode($doc, JSON_THROW_ON_ERROR)]);
    rate_cards_json_response(['ok' => true, 'count' => count($doc['rows'])]);
} catch (InvalidArgumentException $e) {
    rate_cards_json_response(['ok' => false, 'error' => $e->getMessage()], 422);
}
 catch (Throwable $e) {
    llama_log_caught_exception($e, 'place.pricing', ['place_id' => $id]);
    rate_cards_json_response(['ok' => false, 'error' => 'Pricing could not be saved. Check the pricing database migration and error log.'], 500);
}
