<?php

declare(strict_types=1);

require_once __DIR__ . '/place-report.php';
require_once __DIR__ . '/place-report/scoped-answers.php';
require_once __DIR__ . '/location-photo-policy.php';
require_once __DIR__ . '/photo-staging.php';

/**
 * Independent Site submissions. A Site is created only on approval.
 * The parent Place is a verified association, never a publication target.
 */
function llama_site_submissions_ready(PDO $db): bool
{
    try {
        $db->query('SELECT id FROM place_site_submissions LIMIT 0');
        return true;
    } catch (PDOException) {
        return false;
    }
}

/** Optional migration: published-parent submissions remain usable without it. */
function llama_site_pending_parent_ready(PDO $db): bool
{
    try {
        $db->query('SELECT parent_submission_id FROM place_site_submissions LIMIT 0');
        return true;
    } catch (PDOException) {
        return false;
    }
}

/** Site questions are projected solely from the approved canonical registry. */
function llama_site_submission_fields(): array
{
    return array_filter(
        llama_report_questions_for_scope('site', true),
        static fn (array $field): bool =>
            empty($field['derived'])
            && empty($field['location_field'])
            && empty($field['pricing_managed'])
    );
}

/**
 * @return array<string,mixed> Keyed by canonical question ID, not storage path.
 * Omitted/Unanswered means no new observation. No, 0, [] and Unknown are recorded.
 */
/**
 * Resolve the first alphabetically sorted, selectable parent Places.
 * Each query searches the database, not the first page of a select list.
 * Eligibility matches the real Site submission parent validator.
 */
function llama_site_parent_place_choices(PDO $db, string $needle = '', int $userId = 0): array
{
    $needle = trim(mb_substr($needle, 0, 120));
    $sql = 'SELECT id, name, state FROM places
            WHERE status IN ("active", "featured")';
    $params = [];
    if ($needle !== '') {
        $sql .= ' AND (name LIKE ? OR CAST(id AS CHAR) = ?)';
        $params[] = '%' . str_replace(
            ['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle
        ) . '%';
        $params[] = $needle;
    }
    $sql .= ' ORDER BY name ASC, id ASC LIMIT 6';
    $statement = $db->prepare($sql);
    $statement->execute($params);
    $published = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($userId < 1 || !llama_site_pending_parent_ready($db)) {
        return $published;
    }
    // Pending records have their own ID namespace. Never expose another
    // Scout's unpublished Place, even if a name or submission ID matches.
    $sql = 'SELECT id, place_name AS name, status, submission_data
            FROM place_submissions
            WHERE user_id = ? AND status IN ("pending", "needs-changes")
              AND (place_id IS NULL OR place_id = 0)';
    $params = [$userId];
    if ($needle !== '') {
        $sql .= ' AND (place_name LIKE ? OR CAST(id AS CHAR) = ?)';
        $params[] = '%' . str_replace(
            ['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle
        ) . '%';
        $params[] = $needle;
    }
    $sql .= ' ORDER BY place_name ASC, id ASC LIMIT 6';
    $statement = $db->prepare($sql);
    $statement->execute($params);
    $pending = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $data = json_decode((string) $row['submission_data'], true);
        if (!is_array($data) || empty($data['type'])) continue;
        $pending[] = [
            'id' => 'pending:' . (int) $row['id'],
            'name' => (string) $row['name'],
            'state' => (string) ($data['state'] ?? ''),
            'status' => (string) $row['status'],
        ];
    }
    return array_merge($pending, $published);
}

/** Resolve a typed parent reference, checking ownership server-side. */
function llama_site_parent_reference(PDO $db, string $reference, int $userId = 0, bool $lock = false): array
{
    if (preg_match('/^pending:([1-9][0-9]*)$/D', $reference, $match)) {
        if ($userId < 1 || !llama_site_pending_parent_ready($db)) {
            throw new InvalidArgumentException('The selected parent Place is not available.');
        }
        $stmt = $db->prepare(
            'SELECT id, user_id, place_id, place_name, status, submission_data
             FROM place_submissions WHERE id = ? AND user_id = ? LIMIT 1'
            . ($lock ? ' FOR UPDATE' : '')
        );
        $stmt->execute([(int) $match[1], $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('The selected parent Place is not available.');
        }
        if ($row['status'] === 'approved' && (int) $row['place_id'] > 0) {
            return llama_site_parent_reference($db, (string) $row['place_id'], $userId, $lock);
        }
        if (!in_array($row['status'], ['pending', 'needs-changes'], true)
            || (int) $row['place_id'] > 0) {
            throw new InvalidArgumentException('The parent Place is no longer awaiting review. Choose an available Place.');
        }
        $data = json_decode((string) $row['submission_data'], true);
        if (!is_array($data) || !is_string($data['type'] ?? null) || $data['type'] === '') {
            throw new InvalidArgumentException('The selected parent Place details are unavailable.');
        }
        $context = ['type' => $data['type']];
        foreach (['amenity_wifi', 'amenity_toilets'] as $key) {
            if (!array_key_exists($key, $data)) continue;
            $field = llama_report_questions()[$key];
            $answer = llama_place_report_submission_answer($field, $data[$key]);
            if ($answer['recorded']) {
                $context[$key] = llama_place_report_answer_form_value($field, $answer['scoped_value']);
            }
        }
        return [
            'id' => $reference, 'place_id' => null, 'parent_submission_id' => (int) $row['id'],
            'name' => (string) $row['place_name'], 'state' => (string) ($data['state'] ?? ''),
            'status' => (string) $row['status'], 'context' => $context,
        ];
    }
    if (!preg_match('/^[1-9][0-9]*$/D', $reference)) {
        throw new InvalidArgumentException('Select a parent Place first.');
    }
    $placeId = (int) $reference;
    $stmt = $db->prepare(
        'SELECT id, name, state FROM places WHERE id = ? AND status IN ("active","featured") LIMIT 1'
        . ($lock ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$placeId]);
    $place = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$place) {
        throw new InvalidArgumentException('The selected parent Place is not available.');
    }
    return $place + [
        'place_id' => $placeId, 'parent_submission_id' => null, 'status' => 'published',
        'context' => llama_site_parent_report_context($db, $placeId),
    ];
}

/** Caller holds the parent submission lock in the Place approval transaction. */
function llama_site_link_approved_parent(PDO $db, int $submissionId, int $userId, int $placeId): void
{
    if (!llama_site_pending_parent_ready($db)) return;
    $stmt = $db->prepare(
        'UPDATE place_site_submissions SET place_id = ?
         WHERE parent_submission_id = ? AND submitted_by = ? AND place_id IS NULL'
    );
    $stmt->execute([$placeId, $submissionId, $userId]);
}

/** Keep a stable typed identity for revisions and moderation. */
function llama_site_submission_parent_reference(array $row): string
{
    return (int) ($row['place_id'] ?? 0) > 0
        ? (string) $row['place_id']
        : ((int) ($row['parent_submission_id'] ?? 0) > 0
            ? 'pending:' . (int) $row['parent_submission_id'] : '');
}

/**
 * Resolve only the read-only parent facts needed by Site applicability.
 * Never trust submitted Place type or amenity values.
 */
function llama_site_parent_report_context(PDO $db, int $placeId): array
{
    $stmt = $db->prepare(
        'SELECT type FROM places WHERE id = ? AND status IN ("active","featured") LIMIT 1'
    );
    $stmt->execute([$placeId]);
    $type = $stmt->fetchColumn();
    if (!is_string($type) || $type === '') {
        throw new InvalidArgumentException('The selected parent Place is not available.');
    }
    $values = llama_place_report_current_answer_values(
        llama_place_report_current_place_answers($db, $placeId)
    );
    $context = ['type' => $type];
    foreach (['amenity_wifi', 'amenity_toilets'] as $key) {
        if (array_key_exists($key, $values)) {
            $context[$key] = llama_place_report_answer_form_value(
                llama_report_questions()[$key], $values[$key]
            );
        }
    }
    return $context;
}

/** Ignore inapplicable new observations, not historical stored answers. */
function llama_site_submission_applicable_answers(array $answers, array $parentContext): array
{
    $input = $parentContext;
    $fields = llama_site_submission_fields();
    foreach ($answers as $key => $value) {
        if (isset($fields[$key])) {
            $input[$key] = llama_place_report_answer_form_value($fields[$key], $value);
        }
    }
    return array_filter($answers, static fn ($value, $key): bool =>
        isset($fields[$key]) && llama_place_report_question_applicable($input, $key),
        ARRAY_FILTER_USE_BOTH
    );
}

function llama_site_submission_parse_answers(array $rawAnswers): array
{
    if (count($rawAnswers) > 200 || array_is_list($rawAnswers) && $rawAnswers !== []) {
        throw new InvalidArgumentException('Invalid Site question answers.');
    }
    $fields = llama_site_submission_fields();
    $answers = [];
    foreach ($rawAnswers as $key => $raw) {
        if (!is_string($key) || !isset($fields[$key])) {
            throw new InvalidArgumentException('A Site submission contains a question belonging to another target.');
        }
        $answer = llama_place_report_submission_answer($fields[$key], $raw);
        if ($answer['recorded']) {
            $answers[$key] = $answer['scoped_value'];
        }
    }
    if (strlen(json_encode($answers, JSON_THROW_ON_ERROR)) > 200000) {
        throw new InvalidArgumentException('Shorten the Site answers before submitting.');
    }
    return $answers;
}

/** Validate a staged or committed Site photo against this exact submission. */
function llama_site_submission_photos(array $data, int $submissionId): array
{
    $photos = $data['photos'] ?? [];
    if (!is_array($photos) || !array_is_list($photos)
        || count($photos) > llama_location_gallery_photo_limit('site')) {
        throw new InvalidArgumentException('Site photos must be a list of up to ten.');
    }
    $prefix = '/uploads/site-submissions/' . $submissionId . '/photos/';
    $seen = [];
    foreach ($photos as $photo) {
        if (!is_array($photo)) {
            throw new InvalidArgumentException('Invalid Site photo.');
        }
        $path = trim((string) ($photo['path'] ?? ''));
        $filename = substr($path, strlen($prefix));
        if ($submissionId < 1 || !str_starts_with($path, $prefix)
            || $filename === '' || str_contains($filename, '..')
            || !preg_match('/^[a-zA-Z0-9._-]+$/D', $filename)
            || isset($seen[$path])) {
            throw new InvalidArgumentException('A Site photo does not belong to this submission.');
        }
        $seen[$path] = true;
    }
    return $photos;
}

/** All fields except Site questions are identity/metadata, never parent answers. */
function llama_site_submission_prepare(PDO $db, array $input, int $userId = 0, bool $lock = false): array
{
    $place = llama_site_parent_reference($db, (string) ($input['site_place_id'] ?? ''), $userId, $lock);
    $placeId = $place['place_id'];

    $name = trim((string) ($input['site_name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) {
        throw new InvalidArgumentException('Enter a Site name of up to 120 characters.');
    }
    $rawAnswers = $input['site_answers'] ?? null;
    if (!is_array($rawAnswers)) {
        throw new InvalidArgumentException('Site answers are missing.');
    }
    $answers = llama_site_submission_applicable_answers(
        llama_site_submission_parse_answers($rawAnswers),
        $place['context']
    );

    $visitDate = trim((string) ($input['visited_at'] ?? $answers['visited_at'] ?? ''));
    if ($visitDate !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $visitDate);
        if (!$parsed || $parsed->format('Y-m-d') !== $visitDate) {
            throw new InvalidArgumentException('Enter a valid Site visit date.');
        }
    }
    if ($visitDate === '') {
        throw new InvalidArgumentException('Enter the date you visited this Site.');
    }
    // The visit date is also a canonical Site observation, not a Place answer.
    $answers['visited_at'] = $visitDate;

    $coordinates = llama_place_report_parse_first_site_coordinates(
        $input['site_coordinates'] ?? ''
    );
    return [
        'place_id' => $placeId,
        'parent_submission_id' => $place['parent_submission_id'],
        'place_name' => (string) $place['name'],
        'site_name' => $name,
        'visit_date' => $visitDate,
        'coordinates' => $coordinates,
        'answers' => $answers,
    ];
}

function llama_site_submission_submit(
    PDO $db,
    int $userId,
    array $input,
    string $photoStageToken = '',
    array $submittedPhotos = []
): int {
    if ($userId < 1 || !llama_contributor_can($db, $userId, 'submit_place')) {
        throw new RuntimeException('Your account cannot submit new Sites.');
    }
    if (!llama_site_submissions_ready($db)) {
        throw new RuntimeException('The Site submission database migration is not installed.');
    }

    if (count($submittedPhotos) > llama_location_gallery_photo_limit('site')) {
        throw new InvalidArgumentException('A Site can have up to ten photos.');
    }
    if ($submittedPhotos && trim($photoStageToken) === '') {
        throw new InvalidArgumentException('The Site photo upload session is missing.');
    }
    $submissionId = 0;
    try {
        $db->beginTransaction();
        // Lock the parent submission before inserting the Site so approval
        // cannot race past a newly linked Site.
        $data = llama_site_submission_prepare($db, $input, $userId, true);
        $observations = $data['answers'];
        unset($observations['visited_at']);
        if (!$observations && !$submittedPhotos) {
            throw new InvalidArgumentException('Report at least one Site observation or photograph.');
        }
        $params = [
            $data['place_id'], $userId, community_role_at_submission($userId),
            $data['site_name'],
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
        if (llama_site_pending_parent_ready($db)) {
            $stmt = $db->prepare(
                'INSERT INTO place_site_submissions
                    (place_id, submitted_by, role_at_submission, site_name, status, submission_data, parent_submission_id)
                 VALUES (?, ?, ?, ?, "pending", ?, ?)'
            );
            $params[] = $data['parent_submission_id'];
        } else {
            $stmt = $db->prepare(
                'INSERT INTO place_site_submissions
                    (place_id, submitted_by, role_at_submission, site_name, status, submission_data)
                 VALUES (?, ?, ?, ?, "pending", ?)'
            );
        }
        $stmt->execute($params);
        $submissionId = (int) $db->lastInsertId();
        if ($submissionId < 1) {
            throw new RuntimeException('The Site submission could not be created.');
        }

        if ($submittedPhotos) {
            $data['photos'] = llama_photo_commit_stage(
                'add-site', $userId, trim($photoStageToken), $submittedPhotos,
                '/uploads/site-submissions/' . $submissionId . '/photos'
            );
            llama_site_submission_photos($data, $submissionId);
            $update = $db->prepare(
                'UPDATE place_site_submissions SET submission_data = ? WHERE id = ?'
            );
            $update->execute([
                json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $submissionId
            ]);
        }
        $db->commit();
        return $submissionId;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($submissionId > 0) {
            llama_photo_remove_tree(dirname(__DIR__) . '/uploads/site-submissions/' . $submissionId);
        }
        throw $exception;
    }
}

/**
 * Only the original contributor may recover a Site submission for revision.
 * Review notes remain attached to the original submission ID.
 */
function llama_site_submission_for_user(
    PDO $db,
    int $userId,
    int $submissionId
): ?array {
    if ($userId < 1 || $submissionId < 1 || !llama_site_submissions_ready($db)) {
        return null;
    }
    $stmt = $db->prepare(
        'SELECT * FROM place_site_submissions WHERE id = ? AND submitted_by = ? LIMIT 1'
    );
    $stmt->execute([$submissionId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }
    $data = json_decode((string) ($row['submission_data'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('The saved Site report is invalid.');
    }
    $row['data'] = $data;
    return $row;
}

/**
 * Return a needs-changes Site to the same pending review queue.
 * Lock target identity, retain other existing photos and never rewrite a Place.
 */
function llama_site_submission_resubmit(
    PDO $db,
    int $userId,
    int $submissionId,
    array $input,
    string $photoStageToken = '',
    array $submittedPhotos = [],
    array $removePhotoPaths = []
): int {
    if ($userId < 1 || $submissionId < 1
        || !llama_contributor_can($db, $userId, 'submit_place')) {
        throw new RuntimeException('Your account cannot resubmit this Site.');
    }
    $addedPaths = [];
    $removedPaths = [];
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'SELECT * FROM place_site_submissions
             WHERE id = ? AND submitted_by = ? AND status = "needs-changes"
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$submissionId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('That Site is not awaiting your revisions.');
        }
        $parentRef = llama_site_submission_parent_reference($row);
        $submittedRef = (string) ($input['site_place_id'] ?? '');
        // A Place might have been approved while the revision was open.
        $originalPendingId = (int) ($row['parent_submission_id'] ?? 0);
        if ($submittedRef !== $parentRef
            && !($originalPendingId > 0 && $submittedRef === 'pending:' . $originalPendingId)) {
            throw new InvalidArgumentException(
                'The parent Place cannot change during a Site revision.'
            );
        }
        $previous = json_decode(
            (string) $row['submission_data'], true, 512, JSON_THROW_ON_ERROR
        );
        if (!is_array($previous)) {
            throw new InvalidArgumentException('The saved Site report is invalid.');
        }
        $existingPhotos = llama_site_submission_photos($previous, $submissionId);
        $existingPaths = array_column($existingPhotos, 'path');
        if (!array_is_list($removePhotoPaths)) {
            throw new InvalidArgumentException('Invalid photo removals.');
        }
        $removePhotoPaths = array_values(array_unique(array_map('strval', $removePhotoPaths)));
        foreach ($removePhotoPaths as $path) {
            if (!in_array($path, $existingPaths, true)) {
                throw new InvalidArgumentException('You cannot remove another submission’s photo.');
            }
        }
        $keptPhotos = array_values(array_filter(
            $existingPhotos,
            static fn (array $photo): bool =>
                !in_array((string) $photo['path'], $removePhotoPaths, true)
        ));
        if (count($keptPhotos) + count($submittedPhotos)
            > llama_location_gallery_photo_limit('site')) {
            throw new InvalidArgumentException('A Site can have up to ten photos.');
        }
        if ($submittedPhotos && trim($photoStageToken) === '') {
            throw new InvalidArgumentException('The new Site photo session is missing.');
        }

        $input['site_place_id'] = $parentRef;
        $data = llama_site_submission_prepare($db, $input, $userId);
        $data['parent_submission_id'] = $originalPendingId ?: null;
        $observations = $data['answers'];
        unset($observations['visited_at']);
        if (!$observations && !$keptPhotos && !$submittedPhotos) {
            throw new InvalidArgumentException('Report an observation or photo.');
        }

        $newPhotos = [];
        if ($submittedPhotos) {
            $newPhotos = llama_photo_commit_stage(
                'add-site', $userId, trim($photoStageToken), $submittedPhotos,
                '/uploads/site-submissions/' . $submissionId . '/photos'
            );
            $addedPaths = array_column($newPhotos, 'path');
        }
        $data['photos'] = array_merge($keptPhotos, $newPhotos);
        llama_site_submission_photos($data, $submissionId);
        $update = $db->prepare(
            'UPDATE place_site_submissions
             SET site_name = ?, submission_data = ?, status = "pending",
                 reviewed_by = NULL, reviewed_at = NULL
             WHERE id = ? AND submitted_by = ? AND status = "needs-changes"'
        );
        $update->execute([
            $data['site_name'],
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $submissionId, $userId,
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('The Site revision could not be saved.');
        }
        $db->commit();
        $removedPaths = $removePhotoPaths;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        foreach ($addedPaths as $path) {
            llama_photo_delete_owned_permanent_path(
                (string) $path, ['uploads/site-submissions/' . $submissionId . '/photos']
            );
        }
        throw $exception;
    }
    foreach ($removedPaths as $path) {
        llama_photo_delete_owned_permanent_path(
            (string) $path, ['uploads/site-submissions/' . $submissionId . '/photos']
        );
    }
    return $submissionId;
}

/** Moderator queue is separate from Place submissions to prevent type mixing. */
function llama_site_submission_queue(PDO $db, int $limit = 100): array
{
    if (!llama_site_submissions_ready($db)) {
        return [];
    }
    if (llama_site_pending_parent_ready($db)) {
        $stmt = $db->query(
            'SELECT s.*, COALESCE(p.name, ps.place_name, "Unavailable Place") AS place_name,
                ps.status AS parent_submission_status,
                COALESCE(NULLIF(u.display_name, ""), u.username, "Member") AS contributor_name
             FROM place_site_submissions s
             LEFT JOIN places p ON p.id = s.place_id
             LEFT JOIN place_submissions ps ON ps.id = s.parent_submission_id AND ps.user_id = s.submitted_by
             LEFT JOIN users u ON u.id = s.submitted_by
             WHERE s.status = "pending"
             ORDER BY s.created_at ASC, s.id ASC
             LIMIT ' . max(1, min(200, $limit))
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    $stmt = $db->query(
        'SELECT s.*, p.name AS place_name,
            COALESCE(NULLIF(u.display_name, ""), u.username, "Member") AS contributor_name
         FROM place_site_submissions s
         INNER JOIN places p ON p.id = s.place_id
         LEFT JOIN users u ON u.id = s.submitted_by
         WHERE s.status = "pending"
         ORDER BY s.created_at ASC, s.id ASC
         LIMIT ' . max(1, min(200, $limit))
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Approve one new Site under an existing Place. The existing Place record,
 * Site siblings, and Place answers are never modified. Caller may use this
 * from a dedicated Admin review action after CSRF + moderator authorization.
 */
function llama_site_submission_moderate(
    PDO $db,
    int $submissionId,
    int $reviewerId,
    string $decision,
    string $reviewNotes = ''
): ?int {
    if ($submissionId < 1 || $reviewerId < 1
        || !in_array($decision, ['approved', 'rejected', 'needs-changes'], true)) {
        throw new InvalidArgumentException('Invalid Site review action.');
    }
    if (in_array($decision, ['rejected', 'needs-changes'], true)
        && trim($reviewNotes) === '') {
        throw new InvalidArgumentException(
            'Explain what needs to change before this Site can be reviewed again.'
        );
    }
    // Photo publication is the only operation that needs the moderation
    // filesystem copying service. Answer-only approvals use canonical writers.
    $copiedPaths = [];
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'SELECT * FROM place_site_submissions
             WHERE id = ? AND status = "pending" FOR UPDATE'
        );
        $stmt->execute([$submissionId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new InvalidArgumentException('This Site submission is no longer awaiting review.');
        }
        $siteId = null;
        if ($decision === 'approved') {
            if ((int) ($item['place_id'] ?? 0) < 1) {
                throw new InvalidArgumentException('Approve the parent Place before publishing this Site.');
            }
            $saved = json_decode((string) $item['submission_data'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($saved)) {
                throw new InvalidArgumentException('Invalid submitted Site report.');
            }
            $data = llama_site_submission_prepare($db, [
                'site_place_id' => (int) $item['place_id'],
                'site_name' => (string) $item['site_name'],
                'site_coordinates' => $saved['coordinates'] === null
                    ? '' : implode(', ', array_values($saved['coordinates'])),
                'site_answers' => (array) ($saved['answers'] ?? []),
                'visited_at' => (string) ($saved['visit_date'] ?? ''),
            ]);
            if (array_diff_key((array) ($saved['answers'] ?? []), $data['answers'])) {
                throw new InvalidArgumentException('Some Site answers are no longer applicable. Request changes before approval.');
            }
            $photos = llama_site_submission_photos($saved, $submissionId);
            $placeId = (int) $item['place_id'];
            $siteCode = trim((string) ($data['answers']['site_number'] ?? ''));
            if ($siteCode === llama_place_report_unknown_token()) {
                $siteCode = '';
            }
            if ($siteCode !== '') {
                $existing = $db->prepare(
                    'SELECT id FROM place_campsites
                     WHERE place_id = ? AND site_code = ? AND is_active = 1 LIMIT 1 FOR UPDATE'
                );
                $existing->execute([$placeId, $siteCode]);
                if ($existing->fetchColumn()) {
                    throw new RuntimeException(
                        'A Site with this number already exists at the parent Place. Resolve that duplicate first.'
                    );
                }
            }
            $insert = $db->prepare(
                'INSERT INTO place_campsites
                    (place_id, site_code, site_name, latitude, longitude, raw_site_type,
                     source_provider, source_external_id, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $insert->execute([
                $placeId, $siteCode !== '' ? $siteCode : null,
                $data['site_name'], $data['coordinates']['latitude'] ?? null,
                $data['coordinates']['longitude'] ?? null,
                'Llama Scout Site', 'Llama Scout', 'site-submission:' . $submissionId,
            ]);
            $siteId = (int) $db->lastInsertId();
            if ($siteId < 1) {
                throw new RuntimeException('The Site could not be created.');
            }

            foreach ($data['answers'] as $key => $answer) {
                llama_scoped_report_save(
                    $db, $placeId, 'site', $siteId, 'campsite_record', $key, $answer
                );
            }

            $permanentPaths = [];
            if ($photos) {
                require_once __DIR__ . '/moderation.php';
                require_once __DIR__ . '/place-campsite-media.php';
            }
            foreach ($photos as $photo) {
                $copied = moderation_copy_photo_file(
                    (string) $photo['path'],
                    '/uploads/places/' . $placeId . '/sites/' . $siteId,
                    (string) ($photo['filename'] ?? '')
                );
                if ($copied === null) {
                    throw new RuntimeException('A submitted Site photo is missing.');
                }
                $copiedPaths[] = $copied;
                $permanentPaths[] = $copied;
                llama_place_campsite_image_insert(
                    $db, $placeId, $siteId, $copied,
                    trim((string) ($photo['alt'] ?? '')) ?: null,
                    null, (int) $item['submitted_by'], 'Llama Scout'
                );
            }

            $bundleKey = 'site-submission:' . $submissionId;
            llama_scout_report_record_approved(
                $db,
                [
                    'place_id' => $placeId,
                    'contributor_id' => (int) $item['submitted_by'],
                    'role_at_submission' => (string) $item['role_at_submission'],
                    'visit_date' => $data['visit_date'],
                    'submitted_at' => (string) $item['created_at'],
                    'logical_report_key' => $bundleKey . ':site',
                    'submission_bundle_key' => $bundleKey,
                    'client_submission_key' => 'web:' . $bundleKey . ':site',
                    'source_channel' => 'web',
                ],
                [
                    'scope' => 'site', 'source' => 'campsite_record',
                    'id' => $siteId, 'label' => $data['site_name'],
                ],
                $data['answers'], $reviewerId, $reviewNotes,
                $photos, $permanentPaths,
                ['site_submission_id' => $submissionId]
            );
        }

        $update = $db->prepare(
            'UPDATE place_site_submissions
             SET status = ?, published_site_id = ?, reviewed_by = ?,
                 reviewed_at = UTC_TIMESTAMP(), review_notes = ?
             WHERE id = ?'
        );
        $update->execute([
            $decision, $siteId, $reviewerId, trim(mb_substr($reviewNotes, 0, 4000)) ?: null,
            $submissionId
        ]);
        $db->commit();
        return $siteId;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        foreach ($copiedPaths as $path) {
            llama_photo_delete_owned_permanent_path((string) $path, ['uploads/places']);
        }
        throw $e;
    }
}
