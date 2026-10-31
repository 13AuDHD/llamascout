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

// Ownership check by reporting target type. Inactive targets remain valid
// historical references; only missing or cross-Place identities are flagged.
$targets = $db->query(
    "SELECT
       SUM(CASE WHEN v.target_scope = 'area'
                 AND (v.target_source <> 'map_feature' OR a.id IS NULL)
                 THEN 1 ELSE 0 END) AS invalid_area,
       SUM(CASE WHEN v.target_scope = 'site'
                 AND v.target_source = 'map_feature' AND m.id IS NULL
                 THEN 1 ELSE 0 END) AS missing_mapped_site,
       SUM(CASE WHEN v.target_scope = 'site'
                 AND v.target_source = 'campsite_record' AND c.id IS NULL
                 THEN 1 ELSE 0 END) AS missing_campsite,
       SUM(CASE WHEN v.target_scope = 'site'
                 AND v.target_source NOT IN ('map_feature', 'campsite_record')
                 THEN 1 ELSE 0 END) AS unsupported_site_source
     FROM place_report_current_answers v
     LEFT JOIN place_map_features a
       ON a.id = v.target_id AND a.place_id = v.place_id
       AND a.feature_type IN ('camping_area', 'parking_area')
     LEFT JOIN place_map_features m
       ON m.id = v.target_id AND m.place_id = v.place_id
       AND m.feature_type = 'camping_site'
     LEFT JOIN place_campsites c
       ON c.id = v.target_id AND c.place_id = v.place_id"
)->fetch(PDO::FETCH_ASSOC);

// Extra V3 scoped answers can be intentional new questions, so count them
// separately rather than mislabeling them as migration failures.
$v3Only = $db->query(
    "SELECT COUNT(*)
     FROM place_report_current_answers v
     LEFT JOIN place_report_scoped_answers s
       ON s.place_id = v.place_id AND s.scope_type = v.target_scope
       AND s.scope_source = v.target_source AND s.scope_id = v.target_id
       AND s.field_key = v.question_key
     WHERE v.target_scope IN ('area', 'site') AND s.field_key IS NULL"
)->fetchColumn();

// Validate the canonical registry relationship independently of legacy data.
// A new V3-only question is fine; an unknown key or wrong owner is not.
$registryIssues = [];
$allFields = llama_place_report_fields();
$retiredKeys = array_fill_keys(llama_place_report_retired_question_keys(), true);
$retiredCount = 0;
$canonicalRows = $db->query(
    'SELECT place_id, target_scope, target_source, target_id, question_key, answer_state, value_json
     FROM place_report_current_answers'
);
while ($row = $canonicalRows->fetch(PDO::FETCH_ASSOC)) {
    if (isset($retiredKeys[(string) $row['question_key']])) {
        $retiredCount++;
        continue;
    }
    $field = $allFields[(string) $row['question_key']] ?? null;
    $scope = (string) $row['target_scope'];
    if (!is_array($field)) {
        $problem = 'Unregistered question';
    } elseif (!in_array($scope, (array) ($field['report_scopes'] ?? []), true)) {
        $problem = 'Wrong question scope';
    } elseif (!empty($field['derived'])) {
        $problem = 'Derived question stored as an answer';
    } elseif (!in_array((string) $row['answer_state'], ['answered', 'unknown'], true)) {
        $problem = 'Invalid answer state';
    } else {
        continue;
    }
    $registryIssues[] = [
        'place' => (int) $row['place_id'],
        'scope' => $scope,
        'target' => (string) $row['target_source'] . ':' . (int) $row['target_id'],
        'question' => (string) $row['question_key'],
        'reason' => $problem,
        'value' => (string) ($row['value_json'] ?? 'null'),
    ];
}

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
<p>Missing Area targets: <?= (int) ($targets['invalid_area'] ?? 0) ?>;
missing mapped Site targets: <?= (int) ($targets['missing_mapped_site'] ?? 0) ?>;
missing campsite records: <?= (int) ($targets['missing_campsite'] ?? 0) ?>;
unsupported Site sources: <?= (int) ($targets['unsupported_site_source'] ?? 0) ?>.</p>
<p>Site/Area answers held only in V3: <?= (int) $v3Only ?>.
These can be legitimate new observations and are not automatically errors.</p>
<p>V3-only records are allowed. Inactive but existing targets are retained.</p>
<h2>Canonical registry validation</h2>
<p>Invalid canonical question assignments: <strong><?= count($registryIssues) ?></strong>;
retired historical answer records: <?= $retiredCount ?>.</p>
<?php if ($registryIssues): ?>
<table><thead><tr><th>Place</th><th>Scope</th><th>Target</th><th>Question</th><th>Issue</th><th>Stored value</th></tr></thead>
<tbody><?php foreach ($registryIssues as $item): ?><tr>
<td><?= $item['place'] ?></td><td><?= $h($item['scope']) ?></td>
<td><?= $h($item['target']) ?></td><td><?= $h($item['question']) ?></td>
<td><?= $h($item['reason']) ?></td><td><?= $h($item['value']) ?></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
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
