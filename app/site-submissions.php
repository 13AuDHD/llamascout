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
function llama_site_submission_prepare(PDO $db, array $input): array
{
    $placeId = (int) ($input['site_place_id'] ?? 0);
    if ($placeId < 1) {
        throw new InvalidArgumentException('Select an existing parent Place first.');
    }
    $stmt = $db->prepare(
        'SELECT id, name FROM places WHERE id = ? AND status IN ("active","featured") LIMIT 1'
    );
    $stmt->execute([$placeId]);
    $place = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$place) {
        throw new InvalidArgumentException('The selected parent Place is not available.');
    }

    $name = trim((string) ($input['site_name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) {
        throw new InvalidArgumentException('Enter a Site name of up to 120 characters.');
    }
    $rawAnswers = $input['site_answers'] ?? null;
    if (!is_array($rawAnswers)) {
        throw new InvalidArgumentException('Site answers are missing.');
    }
    $answers = llama_site_submission_parse_answers($rawAnswers);

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

    $data = llama_site_submission_prepare($db, $input);
    if (count($submittedPhotos) > llama_location_gallery_photo_limit('site')) {
        throw new InvalidArgumentException('A Site can have up to ten photos.');
    }
    if ($submittedPhotos && trim($photoStageToken) === '') {
        throw new InvalidArgumentException('The Site photo upload session is missing.');
    }
    $observations = $data['answers'];
    unset($observations['visited_at']);
    if (!$observations && !$submittedPhotos) {
        throw new InvalidArgumentException('Report at least one Site observation or photograph.');
    }

    $submissionId = 0;
    try {
        $db->beginTransaction();
        $stmt = $db->prepare(
            'INSERT INTO place_site_submissions
                (place_id, submitted_by, role_at_submission, site_name, status, submission_data)
             VALUES (?, ?, ?, ?, "pending", ?)'
        );
        $stmt->execute([
            $data['place_id'], $userId, community_role_at_submission($userId),
            $data['site_name'],
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
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

/** Moderator queue is separate from Place submissions to prevent type mixing. */
function llama_site_submission_queue(PDO $db, int $limit = 100): array
{
    if (!llama_site_submissions_ready($db)) {
        return [];
    }
    $stmt = $db->query(
        'SELECT s.*, p.name AS place_name,
            COALESCE(NULLIF(u.display_name, ""), u.username, "Member") AS contributor_name
         FROM place_site_submissions s
         INNER JOIN places p ON p.id = s.place_id
         LEFT JOIN users u ON u.id = s.submitted_by
         WHERE s.status IN ("pending","needs-changes")
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
    if ($decision === 'rejected' && trim($reviewNotes) === '') {
        throw new InvalidArgumentException('Explain why the Site was not approved.');
    }
    require_once __DIR__ . '/moderation.php';
    require_once __DIR__ . '/place-campsite-media.php';

    $copiedPaths = [];
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'SELECT * FROM place_site_submissions
             WHERE id = ? AND status IN ("pending","needs-changes") FOR UPDATE'
        );
        $stmt->execute([$submissionId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new InvalidArgumentException('This Site submission is no longer awaiting review.');
        }
        $siteId = null;
        if ($decision === 'approved') {
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
