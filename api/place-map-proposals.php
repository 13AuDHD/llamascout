<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-map-proposals.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

function llama_map_proposal_api_response(array $data, int $status): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    llama_map_proposal_api_response(['ok' => false, 'error' => 'POST required.'], 405);
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$db = db();
if ($userId < 1 || !llama_contributor_can($db, $userId, 'field_report')) {
    llama_map_proposal_api_response(['ok' => false, 'error' => 'Scout access required.'], 403);
}
$length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > 200000) {
    llama_map_proposal_api_response(['ok' => false, 'error' => 'Perimeter is too large.'], 413);
}
try {
    $input = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new InvalidArgumentException('Invalid request.');
    $csrf = (string) ($input['csrf_token'] ?? '');
    if (!community_verify_csrf($csrf)) {
        llama_map_proposal_api_response(['ok' => false, 'error' => 'Session expired. Reload and try again.'], 403);
    }
    $slug = trim((string) ($input['slug'] ?? ''));
    $place = $slug !== '' ? place_member_by_slug($slug) : null;
    $placeId = (int) ($place['id'] ?? 0);
    if ($placeId < 1 || !user_has_place_complete_access($placeId, $userId)) {
        llama_map_proposal_api_response(['ok' => false, 'error' => 'This Place is not available to your account.'], 403);
    }
    $id = llama_map_proposal_submit($db, $placeId, $userId, $input);
    llama_map_proposal_api_response(['ok' => true, 'pending' => true, 'proposal_id' => $id, 'message' => 'Map proposal submitted for moderation. The published map has not changed.'], 202);
} catch (InvalidArgumentException | JsonException $error) {
    llama_map_proposal_api_response(['ok' => false, 'error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    llama_log_caught_exception($error, 'map.proposal.submit', ['user_id' => $userId]);
    llama_map_proposal_api_response(['ok' => false, 'error' => 'Could not submit this mapped-area proposal.'], 500);
}
