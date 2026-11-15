<?php
declare(strict_types=1);

/** Canonical Area submissions. A map location is never a prerequisite. */
require_once __DIR__ . '/place-report.php';
require_once __DIR__ . '/place-report/report-targets.php';
require_once __DIR__ . '/place-report/scoped-answers.php';
require_once __DIR__ . '/place-map-features.php';

function llama_area_submissions_ready(PDO $db): bool
{
    try {
        $db->query('SELECT id FROM place_area_submissions LIMIT 0');
        return true;
    } catch (PDOException) {
        return false;
    }
}

function llama_area_place_choices(PDO $db, string $needle = '', int $selectedId = 0): array
{
    $needle = trim(mb_substr($needle, 0, 120));
    $sql = 'SELECT id, name, state FROM places
            WHERE status NOT IN ("removed", "archived")';
    $args = [];
    if ($needle !== '') {
        $sql .= ' AND (name LIKE ? OR CAST(id AS CHAR) = ?)';
        $args[] = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $needle) . '%';
        $args[] = $needle;
    }
    if ($selectedId > 0) {
        $sql .= $needle !== '' ? ' OR (id = ? AND status NOT IN ("removed","archived"))' : ' AND id > 0';
        if ($needle !== '') $args[] = $selectedId;
    }
    $sql .= ' ORDER BY name ASC, id ASC LIMIT 80';
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($selectedId > 0 && !in_array($selectedId, array_map(
        static fn(array $row): int => (int) $row['id'], $rows
    ), true)) {
        $stmt = $db->prepare('SELECT id, name, state FROM places
            WHERE id = ? AND status NOT IN ("removed","archived")');
        $stmt->execute([$selectedId]);
        $selected = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($selected) $rows[] = $selected;
    }
    return $rows;
}

/**
 * Optional Area coordinates are location metadata, not Scout Report answers.
 * The same latitude/longitude order and >=5 decimal accuracy as Add Place.
 * Return a normalized string or an empty string for an unmapped Area.
 */
function llama_area_coordinates_normalize(mixed $raw): string
{
    if (!is_scalar($raw) || is_bool($raw)) {
        throw new InvalidArgumentException('Enter Area coordinates as latitude, longitude.');
    }
    $value = trim(str_replace('−', '-', (string) $raw));
    if ($value === '') return '';
    if (!preg_match(
        '/^([+-]?\\d{1,2}\\.\\d{5,})\\s*(?:,\\s*|\\s+)([+-]?\\d{1,3}\\.\\d{5,})$/D',
        $value, $matches
    )) {
        throw new InvalidArgumentException(
            'Use latitude, longitude with at least 5 decimals, e.g. 37.2522200, -107.2192000.'
        );
    }
    $latitude = (float) $matches[1];
    $longitude = (float) $matches[2];
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        throw new InvalidArgumentException('Area coordinates fall outside valid latitude/longitude ranges.');
    }
    return sprintf('%.7f, %.7f', $latitude, $longitude);
}

/**
 * One canonical Area payload validator for existing Places AND an Area
 * bundled into a pending new Place. No map coordinates are ever required.
 */
function llama_area_submission_prepare_bundle(array $input): array
{
    $type = trim((string) ($input['area_feature_type'] ?? $input['feature_type'] ?? ''));
    $name = trim((string) ($input['area_name'] ?? ''));
    $kind = trim((string) ($input['area_facility_kind'] ?? $input['facility_kind'] ?? ''));
    if (!in_array($type, ['camping_area','parking_area','facility_area'], true)) {
        throw new InvalidArgumentException('Choose Camping, Parking or Facility for this Area.');
    }
    if ($name === '' || mb_strlen($name) > 120) {
        throw new InvalidArgumentException('Give this Area a name of up to 120 characters.');
    }
    if ($type === 'facility_area') {
        if (!isset(llama_place_facility_kind_options()[$kind])) {
            throw new InvalidArgumentException('Choose the kind of facility.');
        }
    } else {
        $kind = '';
    }
    $target = [
        'scope' => 'area', 'source' => 'map_feature',
        'feature_type' => $type, 'facility_kind' => $kind,
    ];
    $fields = llama_report_questions_for_area_target($target);
    $inputAnswers = (array) ($input['area_answers'] ?? $input['answers'] ?? []);
    $unknown = (array) ($input['area_unknown'] ?? []);
    if (count($inputAnswers) > 200 || count($unknown) > 200) {
        throw new InvalidArgumentException('Too many Area answers.');
    }
    $answers = [];
    foreach (array_unique(array_merge(array_keys($inputAnswers), array_keys($unknown))) as $key) {
        if (!is_string($key) || !isset($fields[$key])) {
            continue;
        }
        $raw = !empty($unknown[$key])
            ? llama_place_report_unknown_token() : ($inputAnswers[$key] ?? null);
        $parsed = llama_place_report_parse_answer($fields[$key], $raw);
        if ($parsed['state'] !== 'unanswered') {
            $answers[$key] = $raw;
        }
    }
    if (strlen(json_encode($answers, JSON_THROW_ON_ERROR)) > 50000) {
        throw new InvalidArgumentException('Shorten the Area notes.');
    }
    return [
        'area_name' => $name, 'feature_type' => $type,
        'facility_kind' => $kind, 'answers' => $answers,
        'coordinates' => llama_area_coordinates_normalize(
            $input['area_coordinates'] ?? $input['coordinates'] ?? ''
        ),
    ];
}

/** Existing Place parent validation uses the exact same Area payload. */
function llama_area_submission_prepare(PDO $db, array $input): array
{
    $placeId = (int) ($input['area_place_id'] ?? 0);
    if ($placeId < 1) {
        throw new InvalidArgumentException('Select the parent Place first.');
    }
    $stmt = $db->prepare('SELECT id, name FROM places
        WHERE id = ? AND status NOT IN ("removed","archived") LIMIT 1');
    $stmt->execute([$placeId]);
    $place = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$place) {
        throw new InvalidArgumentException('The selected parent Place was not found.');
    }
    return [
        'place_id' => $placeId, 'place_name' => (string) $place['name'],
    ] + llama_area_submission_prepare_bundle($input);
}

/** Approved unmapped Area. Caller owns the existing database transaction. */
function llama_area_publish_unmapped(
    PDO $db, int $placeId, int $contributorId, int $reviewerId, array $bundle
): int {
    if (!$db->inTransaction() || $placeId < 1 || $contributorId < 1 || $reviewerId < 1) {
        throw new RuntimeException('Area publication requires an authorized transaction.');
    }
    $data = llama_area_submission_prepare_bundle([
        'area_name' => $bundle['area_name'] ?? '',
        'area_feature_type' => $bundle['feature_type'] ?? '',
        'area_facility_kind' => $bundle['facility_kind'] ?? '',
        'area_answers' => $bundle['answers'] ?? [],
        'area_coordinates' => $bundle['coordinates'] ?? '',
    ]);
    $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+10
        FROM place_map_features WHERE place_id = ?');
    $stmt->execute([$placeId]);
    $sortOrder = (int) $stmt->fetchColumn();
    $meta = $data['facility_kind'] !== ''
        ? json_encode(['facility_kind'=>$data['facility_kind']], JSON_THROW_ON_ERROR)
        : null;
    $coordinates = $data['coordinates'];
    $wkt = null;
    if ($coordinates !== '') {
        [$latitude, $longitude] = array_map('floatval', explode(', ', $coordinates));
        $wkt = sprintf('POINT(%.7f %.7f)', $longitude, $latitude);
    }
    $insert = $db->prepare('INSERT INTO place_map_features
        (place_id, feature_type, label, geometry_type, geometry,
         source_type, metadata_json, created_by, updated_by, verified_at,
         sort_order, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ST_GeomFromText(?, 4326), "manual", ?, ?, ?, UTC_TIMESTAMP(),
                ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())');
    $insert->execute([
        $placeId, $data['feature_type'], $data['area_name'],
        $wkt === null ? 'unmapped' : 'point', $wkt,
        $meta, $contributorId, $reviewerId, $sortOrder,
    ]);
    $featureId = (int) $db->lastInsertId();
    if ($featureId < 1) {
        throw new RuntimeException('Could not create the Area identity.');
    }
    foreach ($data['answers'] as $key => $raw) {
        llama_scoped_report_save(
            $db, $placeId, 'area', $featureId, 'map_feature', $key, $raw
        );
    }
    return $featureId;
}

function llama_area_submission_submit(PDO $db, int $userId, array $input): int
{
    if ($userId < 1 || !llama_contributor_can($db, $userId, 'submit_place')) {
        throw new RuntimeException('Your account cannot submit new Areas.');
    }
    if (!llama_area_submissions_ready($db)) {
        throw new RuntimeException('Install the unmapped Areas SQL migration first.');
    }
    $data = llama_area_submission_prepare($db, $input);
    $stmt = $db->prepare('INSERT INTO place_area_submissions
        (place_id, submitted_by, area_name, feature_type, facility_kind, answers_json)
        VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $data['place_id'], $userId, $data['area_name'], $data['feature_type'],
        $data['facility_kind'] ?: null,
        // Submission metadata is separate from canonical question answers.
        // Older submissions with a flat answers_json remain readable.
        json_encode([
            'answers' => $data['answers'],
            'coordinates' => $data['coordinates'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);
    return (int) $db->lastInsertId();
}

function llama_area_submission_queue(PDO $db, int $limit = 60): array
{
    if (!llama_area_submissions_ready($db)) return [];
    $sql = 'SELECT a.*, p.name AS place_name,
            COALESCE(NULLIF(u.display_name, ""), u.username, "Member") AS contributor_name
            FROM place_area_submissions a
            INNER JOIN places p ON p.id = a.place_id
            LEFT JOIN users u ON u.id = a.submitted_by
            WHERE a.status IN ("pending", "needs-changes")
            ORDER BY a.created_at ASC, a.id ASC LIMIT ' . max(1, min(200, $limit));
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Place + Area identity, optional geometry and scoped report answers are
 * one transaction. The Area's ID is its existing map_feature identity.
 */
function llama_area_submission_moderate(
    PDO $db, int $submissionId, int $reviewerId, string $decision, string $notes = ''
): ?int {
    if ($submissionId < 1 || $reviewerId < 1
        || !in_array($decision, ['approved','rejected','needs-changes'], true)) {
        throw new InvalidArgumentException('Invalid Area review action.');
    }
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM place_area_submissions
            WHERE id = ? AND status IN ("pending","needs-changes") FOR UPDATE');
        $stmt->execute([$submissionId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new InvalidArgumentException('This Area submission is no longer pending.');
        }
        $featureId = null;
        if ($decision === 'approved') {
            $saved = json_decode(
                (string) $item['answers_json'], true, 512, JSON_THROW_ON_ERROR
            );
            if (!is_array($saved)) {
                throw new InvalidArgumentException('Invalid submitted Area answers.');
            }
            $hasEnvelope = isset($saved['answers']) && is_array($saved['answers']);
            $payload = [
                'area_place_id' => $item['place_id'],
                'area_name' => $item['area_name'],
                'area_feature_type' => $item['feature_type'],
                'area_facility_kind' => $item['facility_kind'],
                'area_answers' => $hasEnvelope ? $saved['answers'] : $saved,
                'area_coordinates' => $hasEnvelope ? ($saved['coordinates'] ?? '') : '',
            ];
            $data = llama_area_submission_prepare($db, $payload);
            $featureId = llama_area_publish_unmapped(
                $db, $data['place_id'], (int) $item['submitted_by'],
                $reviewerId, $data
            );
        }
        $update = $db->prepare('UPDATE place_area_submissions SET
            status = ?, published_feature_id = ?, reviewed_by = ?,
            reviewed_at = UTC_TIMESTAMP(), review_notes = ? WHERE id = ?');
        $update->execute([$decision, $featureId, $reviewerId,
            trim(mb_substr($notes, 0, 4000)) ?: null, $submissionId]);
        $db->commit();
        return $featureId;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
