<?php

declare(strict_types=1);

/*
 * M3-003: one-time, resumable Place answer backfill.
 * Uses the canonical question registry and the existing published Place reader.
 * Existing V3 current answers always take precedence (INSERT IGNORE).
 * Never edits historical Scout Reports or legacy Place tables.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/moderation.php';

moderation_require_admin();
$db = db();
if (!llama_report_current_store_available($db)) {
    http_response_code(503);
    exit('M3-001 place_report_current_answers table is not installed.');
}

$cursor = max(0, (int) ($_POST['after_id'] ?? $_GET['after_id'] ?? 0));
$batchSize = 10;
$processed = 0;
$inserted = 0;
$skipped = 0;
$nextCursor = $cursor;
$hasMore = false;
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    try {
        $places = $db->prepare(
            'SELECT id FROM places WHERE id > ? ORDER BY id ASC LIMIT ' . $batchSize
        );
        $places->execute([$cursor]);
        $placeIds = array_map('intval', $places->fetchAll(PDO::FETCH_COLUMN));

        // One Place per transaction, so a malformed legacy value cannot
        // commit half of that Place. Errors stop the batch for review.
        $insert = $db->prepare(
            'INSERT IGNORE INTO place_report_current_answers
                (place_id, target_scope, target_source, target_id,
                 question_key, answer_state, value_json)
             VALUES (?, \'place\', \'place\', ?, ?, ?, ?)'
        );
        $registry = llama_place_report_fields();
        foreach ($placeIds as $placeId) {
            $db->beginTransaction();
            try {
                $answers = llama_place_report_current_place_answers($db, $placeId);
                foreach ($answers as $key => $answer) {
                    $field = $registry[$key] ?? null;
                    if (!is_array($field)
                        || !empty($field['derived'])
                        || !in_array('place', (array) ($field['report_scopes'] ?? []), true)
                        || !llama_place_report_storage_definition($field)
                        || empty($answer['recorded'])) {
                        continue;
                    }

                    $state = (string) ($answer['state'] ?? 'unanswered');
                    if (!in_array($state, ['answered', 'unknown'], true)) {
                        continue;
                    }

                    $value = $state === 'unknown' ? null : ($answer['value'] ?? null);
                    $insert->execute([
                        $placeId, $placeId, $key, $state,
                        json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ]);
                    if ($insert->rowCount() === 1) {
                        $inserted++;
                    } else {
                        $skipped++;
                    }
                }

                $db->commit();
                $processed++;
                $nextCursor = $placeId;
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
        }
        $more = $db->prepare('SELECT id FROM places WHERE id > ? LIMIT 1');
        $more->execute([$nextCursor]);
        $hasMore = (bool) $more->fetchColumn();
    } catch (Throwable $exception) {
        $error = 'Stopped after Place #' . $nextCursor . ': ' . $exception->getMessage();
    }
} else {
    $more = $db->prepare('SELECT id FROM places WHERE id > ? LIMIT 1');
    $more->execute([$cursor]);
    $hasMore = (bool) $more->fetchColumn();
}

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>V3 Place Backfill</title>
<style>
body { font: 16px/1.5 system-ui, sans-serif; max-width: 44rem; margin: 2rem auto; padding: 0 1rem; }
button { padding: .8rem 1.2rem; font: inherit; cursor: pointer; }
code { overflow-wrap: anywhere; }
</style>
</head>
<body>
<h1>V3 Place answer backfill (M3-003)</h1>
<p>This copies legacy Place answers to canonical storage in batches of <?= $batchSize ?> Places.
Existing canonical answers are not replaced. No legacy data or report history is deleted.</p>
<p>Last completed Place ID: <strong><?= $nextCursor ?></strong></p>
<?php if ($processed): ?>
<p>Places processed: <?= $processed ?>;
new answers copied: <?= $inserted ?>;
existing answers skipped: <?= $skipped ?>.</p>
<?php endif; ?>
<?php if ($error !== ''): ?>
<p role="alert"><?= $h($error) ?></p>
<p>Stop and inspect this error before running another batch.</p>
<?php elseif ($hasMore): ?>
<form method="post">
<input type="hidden" name="after_id" value="<?= $nextCursor ?>">
<input type="hidden" name="csrf_token" value="<?= $h(moderation_csrf_token()) ?>">
<button type="submit">Process next <?= $batchSize ?> Places</button>
</form>
<?php else: ?>
<p><strong>No more Places remaining. Backfill complete.</strong></p>
<?php endif; ?>
</body>
</html>
