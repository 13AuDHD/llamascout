<?php

declare(strict_types=1);

/**
 * M3-004 read-only comparison of migrated current answers.
 * No changes to either storage system. Admin access is required.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/moderation.php';

moderation_require_admin();
$db = db();
if (!llama_report_current_store_available($db)) {
    http_response_code(503);
    exit('M3-001 current answer table is missing.');
}
$h = static fn (mixed $v): string =>
    htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$placeId = 0; // Full verification always scans every Place.
$placeCount = 0;
$invalid = 0;
$items = [];
$checked = 0;
$missing = 0;
$different = 0;
$matching = 0;
$fields = llama_place_report_fields();

$placeIds = $placeId > 0
    ? [$placeId]
    : array_map('intval', $db->query('SELECT id FROM places ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));

foreach ($placeIds as $currentPlaceId) {
    $placeCount++;
    // Compare one Place at a time, without rewriting or reinterpreting its
    // canonical records. Legacy-compatible values come from the same reader
    // used during M3-003.
    $data = [];
    foreach (llama_place_report_current_values($db, $currentPlaceId) as $path => $value) {
        llama_place_report_set_path($data, $path, $value);
    }
    $data = llama_place_report_derive_compatibility($data);
    $data['_answer_state'] = llama_place_report_published_answer_state($db, $currentPlaceId);
    $canonical = llama_report_current_store_load($db, $currentPlaceId, 'place', 'place', $currentPlaceId);

    foreach ($fields as $key => $field) {
        if (!in_array('place', (array) ($field['report_scopes'] ?? []), true)
            || !empty($field['derived']) || !llama_place_report_storage_definition($field)) {
            continue;
        }
        $state = llama_place_report_answer_state($data, $key);
        if ($state === 'unanswered') {
            continue;
        }
        $raw = $state === 'unknown'
            ? llama_place_report_unknown_token()
            : llama_place_report_get_path($data, (string) $field['storage']);
        try {
            $parsed = llama_place_report_submission_answer($field, $raw);
        } catch (InvalidArgumentException $exception) {
            $invalid++;
            $items[] = ['place' => $currentPlaceId, 'key' => $key, 'status' => 'invalid legacy value',
                'legacy' => $raw, 'canonical' => $canonical[$key]['value'] ?? null];
            continue;
        }
        if (!$parsed['recorded']) {
            continue;
        }
        $checked++;
        if (!isset($canonical[$key])) {
            $missing++;
            $items[] = ['place' => $currentPlaceId, 'key' => $key, 'status' => 'missing canonical',
                'legacy' => $parsed['value'], 'canonical' => null];
            continue;
        }
        $new = $canonical[$key];
        if ($new['state'] !== $parsed['state']
            || ($parsed['state'] === 'answered'
                && !llama_place_report_values_equal(
                    $parsed['value'], $new['value'], $field
                ))) {
            $different++;
            $items[] = ['place' => $currentPlaceId, 'key' => $key, 'status' => 'different',
                'legacy' => $parsed['value'], 'canonical' => $new['value']];
        } else {
            $matching++;
        }
    }
}

// Scoped comparison uses the stored legacy JSON directly, including
// explicit false, zero, [] and the Unknown transport token.
$scoped = $db->query(
    "SELECT COUNT(*) AS total,
        SUM(CASE WHEN v.question_key IS NULL THEN 1 ELSE 0 END) AS missing,
        SUM(CASE WHEN v.question_key IS NOT NULL AND
          (v.answer_state <> CASE
            WHEN JSON_UNQUOTE(s.value_json) = '__LLAMA_UNKNOWN__'
            THEN 'unknown' ELSE 'answered' END
          OR NOT (v.value_json <=> CASE
            WHEN JSON_UNQUOTE(s.value_json) = '__LLAMA_UNKNOWN__'
            THEN JSON_EXTRACT('null', '$') ELSE s.value_json END))
         THEN 1 ELSE 0 END) AS different
     FROM place_report_scoped_answers s
     LEFT JOIN place_report_current_answers v
       ON v.place_id = s.place_id
      AND v.target_scope = s.scope_type
      AND v.target_source = s.scope_source
      AND v.target_id = s.scope_id
      AND v.question_key = s.field_key
     WHERE s.scope_type IN ('area', 'site')"
)->fetch(PDO::FETCH_ASSOC);
// Reverse direction: newer V3-only records are legitimate, but rows attached
// to missing Places or invalid reporting targets require investigation.
$reverse = $db->query(
    "SELECT COUNT(*) AS total,
      SUM(CASE WHEN p.id IS NULL THEN 1 ELSE 0 END) AS missing_place,
      SUM(CASE WHEN v.target_scope = 'place'
                 AND (v.target_source <> 'place' OR v.target_id <> v.place_id)
               THEN 1 ELSE 0 END) AS invalid_place_target,
      SUM(CASE WHEN v.target_scope NOT IN ('place', 'area', 'site')
               THEN 1 ELSE 0 END) AS invalid_scope
     FROM place_report_current_answers v
     LEFT JOIN places p ON p.id = v.place_id"
)->fetch(PDO::FETCH_ASSOC);

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>V3 Current Answer Verification</title>
<style>
body{font:16px/1.5 system-ui,sans-serif;max-width:55rem;margin:2rem auto;padding:0 1rem}
input,button{font:inherit;padding:.5rem}table{border-collapse:collapse;width:100%}
td,th{border:1px solid #aaa;padding:.5rem;text-align:left;overflow-wrap:anywhere}
</style></head><body>
<h1>M3-004 Current Answer Verification</h1>
<p>Read-only comparison. No answers are changed. A difference is flagged for
review, not automatically reconciled.</p>
<h2>Area and Site records</h2>
<p>Legacy: <?= (int) ($scoped['total'] ?? 0) ?>;
missing canonical: <?= (int) ($scoped['missing'] ?? 0) ?>;
different values/states: <?= (int) ($scoped['different'] ?? 0) ?>.</p>
<h2>Canonical storage integrity</h2>
<p>All V3 current-answer rows: <?= (int) ($reverse['total'] ?? 0) ?>;
missing parent Place: <?= (int) ($reverse['missing_place'] ?? 0) ?>;
invalid Place target: <?= (int) ($reverse['invalid_place_target'] ?? 0) ?>;
invalid scope: <?= (int) ($reverse['invalid_scope'] ?? 0) ?>.</p>
<p>V3-only records are allowed. This integrity check does not imply that every
Site or Area target exists; that requires a target ownership check.</p>
<h2>Place records</h2>
<p><strong><?= $placeCount ?> Places checked</strong>;
answers checked <?= $checked ?>; matching <?= $matching ?>;
missing <?= $missing ?>; different <?= $different ?>; invalid legacy <?= $invalid ?>.</p>
<p>The report checks every Place automatically. Only mismatches are listed.</p>
<?php if ($items): ?>
<table><thead><tr><th>Place</th><th>Question</th><th>Status</th><th>Legacy value</th><th>Canonical value</th></tr></thead>
<tbody><?php foreach ($items as $item): ?><tr>
<td><?= (int) $item['place'] ?></td>
<td><?= $h($item['key']) ?></td><td><?= $h($item['status']) ?></td>
<td><?= $h(json_encode($item['legacy'], JSON_UNESCAPED_UNICODE)) ?></td>
<td><?= $h(json_encode($item['canonical'], JSON_UNESCAPED_UNICODE)) ?></td>
</tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
</body></html>
