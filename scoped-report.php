<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/place-report.php';
require_once __DIR__ . '/app/place-report/scoped-form-model.php';

require_verified_email();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
if ($userId < 1 || !llama_contributor_can(db(), $userId, 'submit_place')) {
    http_response_code(403);
    exit('Scout contribution permission required.');
}
$h = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$placeId = max(0, (int) ($_GET['place_id'] ?? $_POST['place_id'] ?? 0));
if ($placeId < 1) {
    http_response_code(400);
    exit('A valid place_id is required.');
}
$scope = (string) ($_GET['scope'] ?? $_POST['scope'] ?? 'place');
$source = (string) ($_GET['source'] ?? $_POST['source'] ?? 'place');
$scopeId = (int) ($_GET['scope_id'] ?? $_POST['scope_id'] ?? $placeId);
if (!in_array($scope, ['place', 'area', 'site'], true)) {
    http_response_code(400);
    exit('Invalid reporting scope.');
}
if ($scope === 'place') {
    $source = 'place';
    $scopeId = $placeId;
}
try {
    $model = llama_scoped_report_form_model(db(), $placeId, $scope, $source, $scopeId);
} catch (InvalidArgumentException $ex) {
    http_response_code(404);
    exit($h($ex->getMessage()));
}
$error = null;
$success = isset($_GET['saved']);
$fields = $model['fields'];
// Entire Place changes use the established Place submission workflow. This
// editor is intentionally scoped to Areas and Sites, never Place writes.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($scope === 'place') {
        $error = 'Use the existing Place update workflow for entire-Place answers.';
    } elseif (!community_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Session expired. Reload and try again.';
    } else {
        try {
            $selected = (array) $model['selected'];
            // Verify the selected target again immediately before writing.
            llama_report_verified_target(db(), $placeId, $scope, $source, $scopeId);
            $posted = is_array($_POST['answers'] ?? null) ? $_POST['answers'] : [];
            foreach ($posted as $key => $raw) {
                if (!is_string($key) || !isset($fields[$key])) {
                    throw new InvalidArgumentException('Unknown field in submission.');
                }
                if (!is_scalar($raw) && !is_array($raw)) {
                    throw new InvalidArgumentException('Invalid answer value.');
                }
            }
            $unknown = llama_place_report_unknown_token();
            $unanswered = llama_place_report_unanswered_token();
            foreach ($fields as $key => $field) {
                if (!array_key_exists($key, $posted)) {
                    continue; // Never overwrite unsubmitted answers.
                }
                $raw = $posted[$key];
                $kind = (string) ($field['type'] ?? '');
                if (is_array($raw) && $kind !== 'multiselect') {
                    throw new InvalidArgumentException('Invalid answer for ' . $key);
                }
                // Blank means remove an override. Explicit Unknown remains
                // a stored unknown, distinct from inheritance.
                if ($raw === '' || $raw === $unanswered) {
                    llama_scoped_report_clear_override(db(), $placeId, $scope, $scopeId, $source, $key);
                    continue;
                }
                if ($raw === $unknown) {
                    if (empty($field['allow_unknown'])) {
                        throw new InvalidArgumentException('Unknown is not supported for ' . $key);
                    }
                    llama_scoped_report_save(db(), $placeId, $scope, $scopeId, $source, $key, $unknown);
                    continue;
                }
                $parseUnknown = [];
                $value = llama_place_report_parse_field($field, $raw, $parseUnknown);
                if ($value === null && $raw !== '0') {
                    throw new InvalidArgumentException('Invalid value for ' . $key);
                }
                // Ensure select questions cannot persist arbitrary options.
                if ($kind === 'select' && !array_key_exists((string) $raw, (array) ($field['options'] ?? []))) {
                    throw new InvalidArgumentException('Invalid option for ' . $key);
                }
                if ($kind === 'permission' && !in_array((string) $raw, ['0','1','2'], true)) {
                    throw new InvalidArgumentException('Invalid permission for ' . $key);
                }
                if ($kind === 'tri' && !in_array((string) $raw, ['0','1'], true)) {
                    throw new InvalidArgumentException('Invalid answer for ' . $key);
                }
                llama_scoped_report_save(db(), $placeId, $scope, $scopeId, $source, $key, $value);
            }
            header('Location: /scoped-report.php?'.http_build_query([
                'place_id'=>$placeId,'scope'=>$scope,'source'=>$source,'scope_id'=>$scopeId,'saved'=>1,
            ]), true, 303);
            exit;
        } catch (Throwable $ex) {
            $error = 'Answers could not be saved. Please check the submitted values.';
            error_log('Scoped Scout Report save: ' . $ex->getMessage());
        }
    }
}
$explicit = (array) ($model['explicit_answers'] ?? []);
$parent = (array) ($model['parent_area_answers'] ?? []);
$sectionNames = llama_place_report_sections();
$groups = [];
foreach ($fields as $key => $field) {
    if (!empty($field['derived']) || !empty($field['location_field']) || !empty($field['hide_form'])) {
        continue;
    }
    $type = (string) ($field['type'] ?? '');
    if ($type === 'derived' || !in_array($type, ['text','textarea','number','url','date','tri','permission','select','rating','checkbox','multiselect'], true)) {
        continue;
    }
    $group = (string) ($field['display_section'] ?? $field['section'] ?? 'other');
    $groups[$group][$key] = $field;
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Scoped Scout Report | Llama Scout</title>
<link rel="stylesheet" href="/css/scoped-report.css">
</head><body><main class="scoped-report">
<header><a href="/place.php?id=<?= $placeId ?>">Back to Place</a><h1>Scout Report</h1><p>Report on an individual camping area or campsite without changing the entire Place.</p></header>
<form method="get" action="/scoped-report.php" class="scoped-target-form">
<input type="hidden" name="place_id" value="<?= $placeId ?>">
<label for="target-search">Find an Area or Site</label><input id="target-search" type="search" placeholder="Search sites and areas..." autocomplete="off">
<label for="target-select">Reporting target</label><select id="target-select" aria-label="Reporting target">
<?php foreach ($model['targets'] as $target):
    $option = http_build_query(['scope'=>$target['scope'], 'source'=>$target['source'], 'scope_id'=>$target['id']]);
    $isCurrent = $target['scope']===$scope && $target['source']===$source && (int)$target['id']===$scopeId;
?><option value="<?= $h($option) ?>" <?= $isCurrent?'selected':'' ?>><?= $h(ucfirst((string)$target['scope']).': '.$target['label']) ?></option>
<?php endforeach; ?></select><button type="submit" id="target-go">Open selected report</button>
</form>
<?php if ($error): ?><p role="alert" class="scoped-error"><?= $h($error) ?></p><?php endif; ?>
<?php if ($success): ?><p role="status" class="scoped-success">Area or Site answers saved.</p><?php endif; ?>
<?php if ($scope === 'place'): ?>
<p class="scoped-notice">Choose an Area or Site above. Continue using the existing Place Report for entire-Place edits.</p>
<?php else: ?>
<form method="post" action="/scoped-report.php" class="scoped-answer-form">
<input type="hidden" name="csrf_token" value="<?= $h(community_csrf_token()) ?>">
<input type="hidden" name="place_id" value="<?= $placeId ?>"><input type="hidden" name="scope" value="<?= $h($scope) ?>">
<input type="hidden" name="source" value="<?= $h($source) ?>"><input type="hidden" name="scope_id" value="<?= $scopeId ?>">
<p class="scoped-notice">Blank means inherit or unanswered. Unknown is an explicit answer. Only changed fields will be submitted.</p>
<?php foreach ($groups as $group => $groupFields):
    $title=(string) ($sectionNames[$group]['label'] ?? ucwords(str_replace('_',' ',$group)));
?><details><summary><?= $h($title) ?></summary><div class="scoped-grid">
<?php foreach ($groupFields as $key=>$field):
    $kind=(string)$field['type']; $value=$explicit[$key]??null;
    $isSet=array_key_exists($key,$explicit);
    $current=$isSet ? (is_scalar($value)?(string)$value:'') : '';
    $options=[];
    if($kind==='tri'){$options=['1'=>'Yes','0'=>'No'];}
    elseif($kind==='permission'){$options=['1'=>'Yes','0'=>'No','2'=>'Permit'];}
    elseif($kind==='rating'){$options=['1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5'];}
    elseif($kind==='checkbox'){$options=['1'=>'Yes','0'=>'No'];}
    elseif($kind==='select'){$options=(array)($field['options']??[]);}
    if(!empty($field['allow_unknown'])){$options=[llama_place_report_unknown_token()=>'Unknown']+$options;}
    $id='scope-'.preg_replace('/[^a-z0-9_-]/i','-',$key);
?><div class="scoped-field"><label for="<?= $h($id) ?>"><?= $h($field['label']??$key) ?></label>
<?php if($options): ?><select id="<?= $h($id) ?>" data-scoped-field="<?= $h($key) ?>"><option value="">Inherit / unanswered</option>
<?php foreach($options as $v=>$label): ?><option value="<?= $h($v) ?>" <?= $current===(string)$v?'selected':'' ?>><?= $h($label) ?></option><?php endforeach; ?></select>
<?php elseif($kind==='textarea'): ?><textarea id="<?= $h($id) ?>" data-scoped-field="<?= $h($key) ?>" rows="3"><?= $h($current) ?></textarea>
<?php elseif($kind==='multiselect'): ?>
<select id="<?= $h($id) ?>" data-scoped-field="<?= $h($key) ?>" multiple size="4">
<?php foreach((array)($field['options']??[]) as $v=>$label): ?><option value="<?= $h($v) ?>" <?= is_array($value)&&in_array((string)$v,array_map('strval',$value),true)?'selected':'' ?>><?= $h($label) ?></option><?php endforeach; ?></select>
<?php else: ?><input id="<?= $h($id) ?>" data-scoped-field="<?= $h($key) ?>" type="<?= in_array($kind,['number','url','date'],true)?$kind:'text' ?>" value="<?= $h($current) ?>" <?= $kind==='number'?'step="any"':'' ?>><?php endif; ?>
<?php if(!$isSet && array_key_exists($key,$parent)): ?><small>Area default available. Leave blank to inherit.</small><?php endif; ?>
<?php if(!empty($field['help'])): ?><small><?= $h($field['help']) ?></small><?php endif; ?>
</div><?php endforeach; ?></div></details><?php endforeach; ?>
<button type="submit" class="scoped-save">Save changed answers</button></form>
<?php endif; ?>
</main><script src="/js/scoped-report.js" defer></script></body></html>
