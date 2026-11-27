<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-place-report.php';
require_once __DIR__ . '/nearest-services.php';

/** Convert the shared calculated results to existing canonical distance choices. */
function admin_place_nearest_missing_inputs(array $payload, array $data, array $current): array
{
    if (($payload['state'] ?? '') !== 'ready') return [];
    $input = [];
    $options = llama_place_report_distance_options();
    foreach ((array) ($payload['services'] ?? []) as $result) {
        $key = (string) ($result['key'] ?? '');
        if (!isset(llama_nearest_service_categories()[$key])
            || !empty($current[$key]['recorded'])
            || llama_place_report_answer_state($data, $key) !== 'unanswered') continue;
        $miles = $result['match']['distance_miles'] ?? null;
        if (!is_numeric($miles) || !is_finite((float) $miles) || $miles < 0) continue;
        $rounded = max(1, (int) round((float) $miles));
        $value = $miles > 20 ? 'Over 20 miles' : $rounded . ($rounded === 1 ? ' mile' : ' miles');
        if (isset($options[$value])) $input[$key] = $value;
    }
    return $input;
}

/**
 * A post-import enrichment, in its own transaction. Use the persisted Place
 * coordinates, not raw RIDB coordinates that may have been superseded.
 * No Site writes, points, or overwriting recorded/explicit Unknown answers.
 */
function admin_place_fill_nearest_services(PDO $db, int $placeId, int $actorUserId): array
{
    if ($db->inTransaction()) throw new LogicException('Nearest enrichment requires its own transaction.');
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT latitude, longitude FROM places WHERE id = ? FOR UPDATE');
        $stmt->execute([$placeId]);
        $point = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$point) throw new RuntimeException('Place not found.');
        $payload = llama_nearest_services($db, $point['latitude'], $point['longitude']);
        $keys = [];
        if ($payload['state'] === 'ready') {
            $base = admin_place_shared_report_data($db, $placeId);
            $current = llama_place_report_current_place_answers($db, $placeId);
            $input = admin_place_nearest_missing_inputs($payload, $base, $current);
            // Only the eligible Nearest fields can be published. Reuse the
            // canonical Admin save path (scope, validation, legacy + V3).
            if ($input) {
                $effective = array_replace(llama_place_report_form_input_from_data($base), $input);
                $input = array_filter($input, static fn ($value, $key) =>
                    llama_place_report_question_applicable($effective, $key), ARRAY_FILTER_USE_BOTH);
                if ($input) {
                    admin_place_save_shared_report($db, $actorUserId, $placeId, $input);
                    $keys = array_keys($input);
                }
            }
        }
        $db->commit();
        return ['state'=>$payload['state'], 'filled'=>count($keys), 'keys'=>$keys];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }
}

/** Optional enrichment must not mark a successfully imported Place failed. */
function admin_place_nearest_after_import(PDO $db, int $placeId, int $actorUserId): array
{
    try {
        return admin_place_fill_nearest_services($db, $placeId, $actorUserId);
    } catch (Throwable $exception) {
        llama_log_caught_exception($exception, 'admin.place.nearest_import', ['place_id'=>$placeId]);
        return ['state'=>'unavailable', 'filled'=>0, 'keys'=>[]];
    }
}
