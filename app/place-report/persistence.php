<?php

declare(strict_types=1);

/** SQL storage adapters. They do not grant permission or own endpoint CSRF. */
function llama_place_report_database_value(array $field, mixed $value): mixed
{
    if ($value === llama_place_report_unknown_token() || $value === llama_place_report_unanswered_token()) {
        throw new InvalidArgumentException('Answer-state tokens must not be stored as Place facts.');
    }
    if (($field['type'] ?? '') === 'multiselect') {
        $values = llama_place_report_multiselect_values($value);
        return $value === null ? null : json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    if (is_array($value) || is_object($value)) {
        throw new InvalidArgumentException('Invalid scalar Place answer.');
    }
    return is_bool($value) ? ($value ? 1 : 0) : $value;
}

/** Read all legacy storage paths once per table, including historical Site facts. */
function llama_place_report_current_values(PDO $db, int $placeId, bool $lock = false): array
{
    $values = [];
    $rows = [];
    foreach (llama_place_report_published_definitions() as $path => $definition) {
        $table = (string) $definition['table'];
        $column = (string) $definition['column'];
        if (!llama_place_report_valid_storage_identifier($table)
            || !llama_place_report_valid_storage_identifier($column)) {
            throw new LogicException('Invalid canonical storage definition.');
        }
        $period = $definition['period'] ?? null;
        $cacheKey = $table . ':' . ($period ?? '');
        if (!array_key_exists($cacheKey, $rows)) {
            $where = $table === 'places' ? 'id = ?' : 'place_id = ?';
            $params = [$placeId];
            if ($period !== null) {
                $where .= ' AND period = ?';
                $params[] = $period;
            }
            $statement = $db->prepare("SELECT * FROM `$table` WHERE $where LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
            $statement->execute($params);
            $rows[$cacheKey] = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        }
        $values[$path] = llama_place_report_normalize_storage_value($rows[$cacheKey][$column] ?? null, $definition);
    }
    return $values;
}

function llama_place_report_current_value(PDO $db, int $placeId, string $path, bool $lock = false): mixed
{
    $definition = llama_place_report_published_definitions()[$path] ?? null;
    if (!$definition) { throw new InvalidArgumentException('Unsupported Place answer: ' . $path); }
    $table = $definition['table'];
    $column = $definition['column'];
    if (!llama_place_report_valid_storage_identifier($table) || !llama_place_report_valid_storage_identifier($column)) {
        throw new LogicException('Invalid canonical storage definition.');
    }
    $where = $table === 'places' ? 'id = ?' : 'place_id = ?';
    $params = [$placeId];
    if (isset($definition['period'])) { $where .= ' AND period = ?'; $params[] = $definition['period']; }
    // fetch() distinguishes a stored false/zero from an absent row.
    $statement = $db->prepare("SELECT `$column` FROM `$table` WHERE $where LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute($params);
    $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    return llama_place_report_normalize_storage_value($row[$column] ?? null, $definition);
}

/** Publish only supplied registered Place keys. Missing keys never clear data. */
function llama_place_report_publish_data(PDO $db, int $placeId, array $data, array $keys): void
{
    if (!$db->inTransaction()) { throw new RuntimeException('Report publication requires an active transaction.'); }
    $fields = llama_place_report_fields();
    $paths = [];
    foreach (array_unique($keys) as $key) {
        $field = $fields[$key] ?? null;
        if (!$field || !in_array('place', (array) $field['report_scopes'], true)
            || !llama_place_report_storage_definition($field)) { continue; }
        $path = (string) $field['storage'];
        $paths[$path] = llama_place_report_get_path($data, $path);
        llama_place_report_apply_published_field($db, $placeId, $path, $paths[$path]);
    }
    llama_place_report_apply_published_answer_state($db, $placeId, $paths, llama_place_report_unknown_fields($data));
}

/** Split one Add Place payload using the same ownership rules as publication. */
function llama_place_report_submission_answers(array $data): array
{
    $placeAnswers = [];
    $placeKeys = [];
    $siteAnswers = [];

    foreach (llama_place_report_fields() as $key => $field) {
        $path = (string) $field['storage'];
        if (!llama_place_report_has_path($data, $path) || !empty($field['derived'])) { continue; }

        $state = llama_place_report_answer_state($data, $key);
        if ($state === 'unanswered') { continue; }

        $value = $state === 'unknown'
            ? llama_place_report_unknown_token()
            : llama_place_report_get_path($data, $path);

        if (in_array('place', (array) $field['report_scopes'], true)) {
            $placeKeys[] = $key;
            $placeAnswers[$key] = $value;
        } elseif (in_array('site', (array) $field['report_scopes'], true)
        ) {
            $siteAnswers[$key] = $value;
        }
    }

    return [
        'place_answers' => $placeAnswers,
        'place_keys' => $placeKeys,
        'site_answers' => $siteAnswers,
    ];
}

/** Create the required first Site identity without publishing its answers yet. */
function llama_place_report_create_submission_site(
    PDO $db,
    int $placeId,
    array $siteAnswers
): array {
    if (!$db->inTransaction()) { throw new RuntimeException('Report publication requires an active transaction.'); }

    $number = trim((string) ($siteAnswers['site_number'] ?? ''));
    if ($number === llama_place_report_unknown_token()) { $number = ''; }

    $label = $number !== '' ? 'Site ' . $number : 'Reported Site';

    $statement = $db->prepare('INSERT INTO place_campsites
        (place_id, site_code, site_name, raw_site_type, source_provider, source_external_id, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)');
    $statement->execute([$placeId, $number !== '' ? $number : null,
        $label, 'Llama Scout Site',
        'Llama Scout', 'place-submission:' . $placeId]);

    $siteId = (int) $db->lastInsertId();
    if ($siteId < 1) { throw new RuntimeException('The first Site could not be created.'); }

    return [
        'id' => $siteId,
        'label' => $label,
        'source' => 'campsite_record',
    ];
}

/**
 * Publish one Add Place payload after its immutable Place + first Site bundle
 * has been recorded. Never copy Site answers onto every Site or invent geometry.
 */
function llama_place_report_publish_submission(
    PDO $db,
    int $placeId,
    array $data,
    ?int $siteId = null
): int {
    if (!$db->inTransaction()) { throw new RuntimeException('Report publication requires an active transaction.'); }

    $answers = llama_place_report_submission_answers($data);
    $siteAnswers = $answers['site_answers'];

    if ($siteId === null) {
        $site = llama_place_report_create_submission_site($db, $placeId, $siteAnswers);
        $siteId = (int) $site['id'];
    }

    if ($siteId < 1) { throw new InvalidArgumentException('A valid first Site is required.'); }

    llama_place_report_publish_data($db, $placeId, $data, $answers['place_keys']);

    require_once __DIR__ . '/scoped-answers.php';
    foreach ($siteAnswers as $key => $value) {
        llama_scoped_report_save($db, $placeId, 'site', $siteId, 'campsite_record', $key, $value);
    }

    return $siteId;
}

function llama_place_report_has_path(array $data, string $path): bool
{
    $value = $data;
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) { return false; }
        $value = $value[$part];
    }
    return true;
}

/** Normalize nested published data using the same storage adapters as updates. */
function llama_place_report_normalize_data(array $data): array
{
    foreach (llama_place_report_published_definitions() as $path => $definition) {
        if (llama_place_report_has_path($data, $path)) {
            llama_place_report_set_path($data, $path,
                llama_place_report_normalize_storage_value(llama_place_report_get_path($data, $path), $definition));
        }
    }
    return $data;
}

function llama_place_report_publish_mirror(PDO $db, int $placeId, array $field, mixed $value): void
{
    $mirror = $field['compatibility_mirror'] ?? null;
    if (!$mirror) { return; }
    $definition = llama_place_report_storage_definition(['storage' => $mirror['storage']]);
    if (!$definition) { throw new LogicException('Invalid compatibility mirror.'); }
    $table = $definition['table']; $column = $definition['column'];
    if (!llama_place_report_valid_storage_identifier($table) || !llama_place_report_valid_storage_identifier($column)) {
        throw new LogicException('Invalid compatibility storage.');
    }
    $value = llama_place_report_mirror_value($value, $mirror['transform']);
    $db->prepare("UPDATE `$table` SET `$column` = ? WHERE place_id = ?")
        ->execute([llama_place_report_database_value($field, $value), $placeId]);
}
