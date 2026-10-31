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
$placeId = max(0, (int) ($_GET['place_id'] ?? 0));
$items = [];
$checked = 0;
$missing = 0;
$different = 0;
$matching = 0;
$fields = llama_place_report_fields();

if ($placeId > 0) {
    // Compare one Place at a time, without rewriting or reinterpreting its
    // canonical records. Legacy-compatible values come from the same reader
    // used during M3-003.
    $data = [];
    foreach (llama_place_report_current_values($db, $placeId) as $path => $value) {
        llama_place_report_set_path($data, $path, $value);
    }
    $data = llama_place_report_derive_compatibility($data);
    $data['_answer_state'] = llama_place_report_published_answer_state($db, $placeId);
    $canonical = llama_report_current_store_load($db, $placeId, 'place', 'place', $placeId);

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
            $items[] = ['key' => $key, 'status' => 'invalid legacy value',
                'legacy' => $raw, 'canonical' => $canonical[$key]['value'] ?? null];
            continue;
        }
        if (!$parsed['recorded']) {
            continue;
        }
        $checked++;
        if (!isset($canonical[$key])) {
            $missing++;
            $items[] = ['key' => $key, 'status' => 'missing canonical',
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
            $items[] = ['key' => $key, 'status' => 'different',
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
<h2>Place records</h2>
<form method="get">
<label>Place ID <input type="number" min="1" name="place_id" value="<?= $placeId ?: '' ?>" required></label>
<button>Verify Place</button>
</form>
<?php if ($placeId > 0): ?>
<p>Place #<?= $placeId ?>: checked <?= $checked ?>;
matching <?= $matching ?>; missing <?= $missing ?>; different <?= $different ?>;
invalid legacy <?= count(array_filter($items, static fn ($x) => $x['status'] === 'invalid legacy value')) ?>.</p>
<?php if ($items): ?>
<table><thead><tr><th>Question</th><th>Status</th><th>Legacy value</th><th>Canonical value</th></tr></thead>
<tbody><?php foreach ($items as $item): ?><tr>
<td><?= $h($item['key']) ?></td><td><?= $h($item['status']) ?></td>
<td><?= $h(json_encode($item['legacy'], JSON_UNESCAPED_UNICODE)) ?></td>
<td><?= $h(json_encode($item['canonical'], JSON_UNESCAPED_UNICODE)) ?></td>
</tr><?php endforeach; ?></tbody></table>
<?php endif; endif; ?>
</body></html>
