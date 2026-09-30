<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';

$adminUser = moderation_require_admin();
$db = db();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

function place_access_api_reply(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

function place_access_api_report(PDO $db, int $reportId): array
{
    if ($reportId < 1) {
        throw new InvalidArgumentException('Problem report not found.');
    }

    $stmt = $db->prepare(
        'SELECT
            pr.id,
            pr.place_id,
            pr.problem_type,
            pr.status AS report_status,
            p.name AS place_name,
            p.slug AS place_slug,
            p.status AS place_status
         FROM place_reports pr
         INNER JOIN places p
            ON p.id = pr.place_id
         WHERE pr.id = ?
         LIMIT 1'
    );
    $stmt->execute([$reportId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$row) {
        throw new InvalidArgumentException('Problem report not found.');
    }

    if (!llama_place_access_alert_is_report_type((string) $row['problem_type'])) {
        throw new InvalidArgumentException(
            'This problem report is not an access-related report.'
        );
    }

    return $row;
}

try {
    $reportId = (int) (
        $_GET['report_id']
        ?? $_POST['report_id']
        ?? 0
    );

    $report = place_access_api_report($db, $reportId);
    $placeId = (int) $report['place_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        place_access_api_reply([
            'ok' => true,
            'report' => $report,
            'alert' => llama_place_access_alert($db, $placeId),
            'states' => llama_place_access_alert_states(),
            'reasons' => llama_place_access_alert_reasons(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        place_access_api_reply([
            'ok' => false,
            'message' => 'Method not allowed.',
        ], 405);
    }

    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        throw new RuntimeException(
            'Your session could not be verified. Reload the report and try again.'
        );
    }

    $action = trim((string) ($_POST['action'] ?? 'save'));
    $actorUserId = (int) ($adminUser['id'] ?? 0);

    if ($action === 'clear') {
        $note = trim((string) ($_POST['internal_note'] ?? ''));

        llama_place_access_alert_clear(
            $db,
            $placeId,
            $actorUserId,
            $reportId,
            $note
        );

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.access_alert_cleared',
            'Cleared public access warning for Place #' . $placeId . '.',
            [
                'place_id' => $placeId,
                'report_id' => $reportId,
            ]
        );

        place_access_api_reply([
            'ok' => true,
            'message' => 'Public access warning cleared.',
            'alert' => null,
        ]);
    }

    if ($action !== 'save') {
        throw new InvalidArgumentException('Invalid access alert action.');
    }

    $alert = llama_place_access_alert_save(
        $db,
        $placeId,
        $actorUserId,
        trim((string) ($_POST['state'] ?? 'reported')),
        trim((string) ($_POST['reason'] ?? 'unknown')),
        $reportId,
        (string) ($_POST['public_note'] ?? ''),
        (string) ($_POST['internal_note'] ?? ''),
        isset($_POST['review_after'])
            ? (string) $_POST['review_after']
            : null
    );

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.access_alert_updated',
        'Updated public access warning for Place #' . $placeId . '.',
        [
            'place_id' => $placeId,
            'report_id' => $reportId,
            'state' => $alert['state'] ?? null,
            'reason' => $alert['reason'] ?? null,
        ]
    );

    place_access_api_reply([
        'ok' => true,
        'message' => 'Public access warning saved.',
        'alert' => $alert,
    ]);
} catch (Throwable $exception) {
    $reference = llama_log_caught_exception(
        $exception,
        'admin.place_access_alert_api',
        ['report_id' => $reportId ?? 0],
        [InvalidArgumentException::class, RuntimeException::class]
    );

    place_access_api_reply([
        'ok' => false,
        'message' => $reference === null
            ? $exception->getMessage()
            : llama_error_message_with_reference(
                'The access warning could not be updated.',
                $reference
            ),
    ], 400);
}
