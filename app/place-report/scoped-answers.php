<?php

declare(strict_types=1);

/**
 * Phase 3D: scoped-answer persistence service.
 *
 * This service deliberately does not alter the legacy Place Report JSON,
 * scoring, moderation, or public display. Its SQL table must be installed
 * before using it. Callers are responsible for authentication, authorization,
 * CSRF and verifying the selected Area/Site belongs to the Place.
 */
function llama_scoped_report_validate_target(string $scope, int $scopeId): void
{
    if (!in_array($scope, ['area', 'site'], true) || $scopeId < 1) {
        throw new InvalidArgumentException('A valid Area or Site ID is required.');
    }
}

/**
 * A target may be a drawn map feature or an imported canonical campsite.
 * Explicit source identity prevents collisions between their ID sequences.
 */
function llama_scoped_report_validate_source(string $scope, string $source): void
{
    $allowed = $scope === 'area'
        ? ['map_feature']
        : ['map_feature', 'campsite_record'];
    if (!in_array($source, $allowed, true)) {
        throw new InvalidArgumentException('Invalid reporting target source.');
    }
}

function llama_scoped_report_field(string $key, string $scope): array
{
    $field = llama_place_report_fields()[$key] ?? null;
    if (!is_array($field) || !in_array($scope, (array) ($field['report_scopes'] ?? []), true)) {
        throw new InvalidArgumentException('This question is not available at the selected reporting level.');
    }
    if (!empty($field['derived']) || !empty($field['location_field'])) {
        throw new InvalidArgumentException('Derived or location fields cannot be overridden.');
    }
    return $field;
}

/**
 * Caller must validate ownership of scopeId and the authenticated Scout's
 * ability to edit placeId. This is a storage service, not a public endpoint.
 * Null, false, zero, empty lists and Unknown are stored as explicit answers.
 */
function llama_scoped_report_save(
    PDO $db,
    int $placeId,
    string $scope,
    int $scopeId,
    string $source,
    string $fieldKey,
    mixed $value
): void {
    if ($placeId < 1) {
        throw new InvalidArgumentException('Invalid Place ID.');
    }
    llama_scoped_report_validate_target($scope, $scopeId);
    llama_scoped_report_validate_source($scope, $source);
    llama_scoped_report_field($fieldKey, $scope);

    $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare(
        'INSERT INTO place_report_scoped_answers
            (place_id, scope_type, scope_source, scope_id, field_key, value_json)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            value_json = VALUES(value_json), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([$placeId, $scope, $source, $scopeId, $fieldKey, $json]);
}

/** @return array<string, mixed> Canonical field keys mapped to explicit values. */
function llama_scoped_report_load(
    PDO $db,
    int $placeId,
    string $scope,
    int $scopeId,
    string $source
): array {
    if ($placeId < 1) {
        throw new InvalidArgumentException('Invalid Place ID.');
    }
    llama_scoped_report_validate_target($scope, $scopeId);
    llama_scoped_report_validate_source($scope, $source);
    $stmt = $db->prepare(
        'SELECT field_key, value_json FROM place_report_scoped_answers
         WHERE place_id = ? AND scope_type = ? AND scope_source = ? AND scope_id = ?'
    );
    $stmt->execute([$placeId, $scope, $source, $scopeId]);
    $answers = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $answers[(string) $row['field_key']] = json_decode(
            (string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR
        );
    }
    return $answers;
}

/** Explicitly remove a scoped override so the field inherits again. */
function llama_scoped_report_clear_override(
    PDO $db,
    int $placeId,
    string $scope,
    int $scopeId,
    string $source,
    string $fieldKey
): void {
    if ($placeId < 1) {
        throw new InvalidArgumentException('Invalid Place ID.');
    }
    llama_scoped_report_validate_target($scope, $scopeId);
    llama_scoped_report_validate_source($scope, $source);
    llama_scoped_report_field($fieldKey, $scope);
    $stmt = $db->prepare(
        'DELETE FROM place_report_scoped_answers
         WHERE place_id = ? AND scope_type = ? AND scope_source = ?
           AND scope_id = ? AND field_key = ?'
    );
    $stmt->execute([$placeId, $scope, $source, $scopeId, $fieldKey]);
}
