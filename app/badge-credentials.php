<?php

declare(strict_types=1);

/* =========================================================
   LLAMA SCOUT BADGE CREDENTIALS

   Credential evidence is intentionally stored outside the
   public document root. Database rows keep the review state,
   file fingerprint, and permanent decision history.
   ========================================================= */

function llama_badge_credentials_storage_ready(PDO $db): bool
{
    static $readyByConnection = [];

    $key = spl_object_id($db);

    if (array_key_exists($key, $readyByConnection)) {
        return $readyByConnection[$key];
    }

    try {
        $stmt = $db->query(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN (
                    "badge_credential_submissions",
                    "badge_credential_events"
               )'
        );

        $readyByConnection[$key] =
            (int) $stmt->fetchColumn() === 2;
    } catch (Throwable) {
        $readyByConnection[$key] = false;
    }

    return $readyByConnection[$key];
}

function llama_badge_credentials_award_link_ready(PDO $db): bool
{
    static $readyByConnection = [];

    $key = spl_object_id($db);

    if (array_key_exists($key, $readyByConnection)) {
        return $readyByConnection[$key];
    }

    try {
        $stmt = $db->query(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = "user_badges"
               AND COLUMN_NAME = "credential_submission_id"'
        );

        $readyByConnection[$key] =
            (int) $stmt->fetchColumn() === 1;
    } catch (Throwable) {
        $readyByConnection[$key] = false;
    }

    return $readyByConnection[$key];
}

function llama_badge_credential_csrf_token(): string
{
    start_llama_session();

    if (empty($_SESSION['badge_credential_csrf'])) {
        $_SESSION['badge_credential_csrf'] =
            bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['badge_credential_csrf'];
}

function llama_badge_credential_verify_csrf(string $submitted): bool
{
    $expected = llama_badge_credential_csrf_token();

    return $submitted !== ''
        && hash_equals($expected, $submitted);
}

function llama_badge_credential_storage_root(bool $create = false): string
{
    $root =
        dirname(__DIR__, 2)
        . '/private/badge-credentials';

    if ($create && !is_dir($root)) {
        if (!@mkdir($root, 0700, true) && !is_dir($root)) {
            throw new RuntimeException(
                'Private credential storage could not be created.'
            );
        }

        @chmod($root, 0700);
    }

    if (is_link($root)) {
        throw new RuntimeException(
            'Private credential storage path is not allowed.'
        );
    }

    if ($create && !is_writable($root)) {
        throw new RuntimeException(
            'Private credential storage is not writable.'
        );
    }

    return $root;
}

function llama_badge_credential_allowed_mimes(): array
{
    return [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heic-sequence' => 'heic',
        'image/heif' => 'heif',
        'image/heif-sequence' => 'heif',
    ];
}

function llama_badge_credential_normalize_date(
    mixed $value,
    string $label
): ?string {
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value
    );

    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new RuntimeException(
            'Enter a valid ' . $label . ' date.'
        );
    }

    return $value;
}

function llama_badge_credential_clean_filename(string $filename): string
{
    $filename = basename(str_replace('\\', '/', $filename));
    $filename = preg_replace('/[\x00-\x1F\x7F]+/u', '', $filename) ?? '';
    $filename = trim($filename);

    if ($filename === '') {
        $filename = 'credential';
    }

    return mb_substr($filename, 0, 240);
}

function llama_badge_credential_upload_error(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE =>
            'The credential file is larger than the server allows.',
        UPLOAD_ERR_PARTIAL =>
            'The credential file only uploaded partially. Try again.',
        UPLOAD_ERR_NO_FILE =>
            'Choose a certificate or credential file to upload.',
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION =>
            'The server could not safely receive the credential file.',
        default =>
            'The credential file could not be uploaded.',
    };
}

function llama_badge_credential_definition(
    PDO $db,
    int $badgeId
): ?array {
    if ($badgeId < 1) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT *
         FROM badge_definitions
         WHERE id = ?
           AND award_type = "credential"
         LIMIT 1'
    );

    $stmt->execute([$badgeId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function llama_badge_credential_badges_for_user(
    PDO $db,
    int $userId
): array {
    if ($userId < 1) {
        return [];
    }

    $badges = $db->query(
        'SELECT *
         FROM badge_definitions
         WHERE award_type = "credential"
           AND is_active = 1
         ORDER BY sort_order ASC, name ASC, id ASC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $earnedStmt = $db->prepare(
        'SELECT badge_id, id AS user_badge_id, awarded_at
         FROM user_badges
         WHERE user_id = ?
           AND review_status = "earned"'
    );
    $earnedStmt->execute([$userId]);

    $earnedByBadge = [];

    foreach ($earnedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $earnedByBadge[(int) $row['badge_id']] = $row;
    }

    $latestByBadge = [];

    if (llama_badge_credentials_storage_ready($db)) {
        $submissionStmt = $db->prepare(
            'SELECT s.*
             FROM badge_credential_submissions s
             INNER JOIN (
                 SELECT badge_id, MAX(id) AS latest_id
                 FROM badge_credential_submissions
                 WHERE user_id = ?
                 GROUP BY badge_id
             ) latest
                ON latest.latest_id = s.id
             WHERE s.user_id = ?'
        );
        $submissionStmt->execute([$userId, $userId]);

        foreach ($submissionStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $latestByBadge[(int) $row['badge_id']] = $row;
        }
    }

    foreach ($badges as &$badge) {
        $badgeId = (int) $badge['id'];
        $badge['earned_award'] = $earnedByBadge[$badgeId] ?? null;
        $badge['latest_submission'] = $latestByBadge[$badgeId] ?? null;
    }
    unset($badge);

    return $badges;
}

function llama_badge_credential_history_for_user(
    PDO $db,
    int $userId
): array {
    if (
        $userId < 1
        || !llama_badge_credentials_storage_ready($db)
    ) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT
            s.*,
            bd.name AS badge_name,
            bd.slug AS badge_slug,
            bd.source_organization
         FROM badge_credential_submissions s
         INNER JOIN badge_definitions bd
            ON bd.id = s.badge_id
         WHERE s.user_id = ?
         ORDER BY s.submitted_at DESC, s.id DESC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_badge_credential_submission_for_user(
    PDO $db,
    int $submissionId,
    int $userId
): ?array {
    if (
        $submissionId < 1
        || $userId < 1
        || !llama_badge_credentials_storage_ready($db)
    ) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT s.*, bd.name AS badge_name
         FROM badge_credential_submissions s
         INNER JOIN badge_definitions bd
            ON bd.id = s.badge_id
         WHERE s.id = ?
           AND s.user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$submissionId, $userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function llama_badge_credential_submission(
    PDO $db,
    int $submissionId
): ?array {
    if (
        $submissionId < 1
        || !llama_badge_credentials_storage_ready($db)
    ) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT
            s.*,
            bd.name AS badge_name,
            bd.slug AS badge_slug,
            bd.source_organization,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                u.email,
                CONCAT("User #", s.user_id)
            ) AS member_name,
            u.username,
            u.email,
            COALESCE(
                NULLIF(r.display_name, ""),
                NULLIF(r.username, ""),
                CASE
                    WHEN s.reviewed_by IS NULL THEN NULL
                    ELSE CONCAT("User #", s.reviewed_by)
                END
            ) AS reviewer_name
         FROM badge_credential_submissions s
         INNER JOIN badge_definitions bd
            ON bd.id = s.badge_id
         LEFT JOIN users u
            ON u.id = s.user_id
         LEFT JOIN users r
            ON r.id = s.reviewed_by
         WHERE s.id = ?
         LIMIT 1'
    );
    $stmt->execute([$submissionId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function llama_badge_credential_events(
    PDO $db,
    int $submissionId
): array {
    if (
        $submissionId < 1
        || !llama_badge_credentials_storage_ready($db)
    ) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT
            e.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                CASE
                    WHEN e.actor_user_id IS NULL THEN "System"
                    ELSE CONCAT("User #", e.actor_user_id)
                END
            ) AS actor_name
         FROM badge_credential_events e
         LEFT JOIN users u
            ON u.id = e.actor_user_id
         WHERE e.submission_id = ?
         ORDER BY e.created_at ASC, e.id ASC'
    );
    $stmt->execute([$submissionId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_badge_credential_add_event(
    PDO $db,
    int $submissionId,
    ?int $actorUserId,
    string $eventType,
    ?string $note = null,
    ?array $metadata = null
): void {
    if (
        $submissionId < 1
        || !llama_badge_credentials_storage_ready($db)
    ) {
        return;
    }

    $eventType = trim($eventType);

    if ($eventType === '') {
        return;
    }

    $metadataJson = null;

    if ($metadata !== null) {
        $metadataJson = json_encode(
            $metadata,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        );
    }

    $stmt = $db->prepare(
        'INSERT INTO badge_credential_events (
            submission_id,
            actor_user_id,
            event_type,
            event_note,
            metadata_json,
            created_at
         ) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );

    $stmt->execute([
        $submissionId,
        $actorUserId && $actorUserId > 0
            ? $actorUserId
            : null,
        mb_substr($eventType, 0, 40),
        $note !== null && trim($note) !== ''
            ? mb_substr(trim($note), 0, 1000)
            : null,
        $metadataJson,
    ]);
}

function llama_badge_credential_submit(
    PDO $db,
    int $userId,
    int $badgeId,
    array $upload,
    array $data = []
): int {
    if (!llama_badge_credentials_storage_ready($db)) {
        throw new RuntimeException(
            'Credential submissions are not installed yet.'
        );
    }

    if ($userId < 1 || $badgeId < 1) {
        throw new RuntimeException(
            'Choose a credential badge before submitting evidence.'
        );
    }

    $badge = llama_badge_credential_definition($db, $badgeId);

    if (!$badge || (int) $badge['is_active'] !== 1) {
        throw new RuntimeException(
            'That credential badge is not available for submission.'
        );
    }

    $earnedStmt = $db->prepare(
        'SELECT id
         FROM user_badges
         WHERE user_id = ?
           AND badge_id = ?
           AND review_status = "earned"
         LIMIT 1'
    );
    $earnedStmt->execute([$userId, $badgeId]);

    if ($earnedStmt->fetchColumn()) {
        throw new RuntimeException(
            'You already have this badge.'
        );
    }

    $pendingStmt = $db->prepare(
        'SELECT id
         FROM badge_credential_submissions
         WHERE user_id = ?
           AND badge_id = ?
           AND status = "pending"
         LIMIT 1'
    );
    $pendingStmt->execute([$userId, $badgeId]);

    if ($pendingStmt->fetchColumn()) {
        throw new RuntimeException(
            'This credential already has a submission waiting for review.'
        );
    }

    $errorCode =
        isset($upload['error'])
            ? (int) $upload['error']
            : UPLOAD_ERR_NO_FILE;

    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            llama_badge_credential_upload_error($errorCode)
        );
    }

    $tmpName = (string) ($upload['tmp_name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException(
            'The credential upload could not be verified.'
        );
    }

    $actualSize = @filesize($tmpName);

    if ($actualSize === false || $actualSize < 1) {
        throw new RuntimeException(
            'The credential file is empty.'
        );
    }

    $maxBytes = 12 * 1024 * 1024;

    if ($actualSize > $maxBytes) {
        throw new RuntimeException(
            'Credential files must be 12 MB or smaller.'
        );
    }

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower(trim((string) $finfo->file($tmpName)));
    } else {
        $mime = strtolower(
            trim((string) @mime_content_type($tmpName))
        );
    }

    $allowed = llama_badge_credential_allowed_mimes();

    if (!isset($allowed[$mime])) {
        throw new RuntimeException(
            'Upload a PDF, JPG, PNG, WebP, HEIC, or HEIF credential file.'
        );
    }

    $memberNote = trim((string) ($data['member_note'] ?? ''));
    $credentialIdentifier = trim(
        (string) ($data['credential_identifier'] ?? '')
    );

    if (mb_strlen($memberNote) > 1000) {
        throw new RuntimeException(
            'Credential notes must be 1,000 characters or fewer.'
        );
    }

    if (mb_strlen($credentialIdentifier) > 150) {
        throw new RuntimeException(
            'Credential or certificate number must be 150 characters or fewer.'
        );
    }

    $issuedOn = llama_badge_credential_normalize_date(
        $data['issued_on'] ?? null,
        'issued'
    );
    $expiresOn = llama_badge_credential_normalize_date(
        $data['expires_on'] ?? null,
        'expiration'
    );

    if (
        $issuedOn !== null
        && $expiresOn !== null
        && $expiresOn < $issuedOn
    ) {
        throw new RuntimeException(
            'Expiration date cannot be before the issued date.'
        );
    }

    $originalFilename = llama_badge_credential_clean_filename(
        (string) ($upload['name'] ?? 'credential')
    );
    $sha256 = hash_file('sha256', $tmpName);

    if (!is_string($sha256) || strlen($sha256) !== 64) {
        throw new RuntimeException(
            'The credential file fingerprint could not be created.'
        );
    }

    $root = llama_badge_credential_storage_root(true);
    $userDirectory = $root . '/' . $userId;

    if (!is_dir($userDirectory)) {
        if (
            !@mkdir($userDirectory, 0700, true)
            && !is_dir($userDirectory)
        ) {
            throw new RuntimeException(
                'Private credential storage could not be prepared.'
            );
        }

        @chmod($userDirectory, 0700);
    }

    if (is_link($userDirectory)) {
        throw new RuntimeException(
            'Private credential storage path is not allowed.'
        );
    }

    $storedFilename =
        bin2hex(random_bytes(24))
        . '.'
        . $allowed[$mime];
    $relativePath = $userId . '/' . $storedFilename;
    $absolutePath = $root . '/' . $relativePath;

    $db->beginTransaction();
    $fileMoved = false;

    try {
        $stmt = $db->prepare(
            'INSERT INTO badge_credential_submissions (
                user_id,
                badge_id,
                status,
                member_note,
                credential_identifier,
                issued_on,
                expires_on,
                original_filename,
                stored_path,
                mime_type,
                file_size,
                sha256,
                submitted_at,
                created_at,
                updated_at
             ) VALUES (
                ?, ?, "pending", ?, ?, ?, ?, ?, ?, ?, ?, ?,
                UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP()
             )'
        );

        $stmt->execute([
            $userId,
            $badgeId,
            $memberNote !== '' ? $memberNote : null,
            $credentialIdentifier !== ''
                ? $credentialIdentifier
                : null,
            $issuedOn,
            $expiresOn,
            $originalFilename,
            $relativePath,
            $mime,
            $actualSize,
            $sha256,
        ]);

        $submissionId = (int) $db->lastInsertId();

        if ($submissionId < 1) {
            throw new RuntimeException(
                'The credential submission could not be created.'
            );
        }

        llama_badge_credential_add_event(
            $db,
            $submissionId,
            $userId,
            'submitted',
            'Credential evidence submitted for review.',
            [
                'badge_id' => $badgeId,
                'file_sha256' => $sha256,
                'mime_type' => $mime,
                'file_size' => $actualSize,
            ]
        );

        if (!@move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException(
                'The credential file could not be moved into private storage.'
            );
        }

        $fileMoved = true;
        @chmod($absolutePath, 0600);

        $db->commit();

        return $submissionId;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if ($fileMoved && is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        throw $exception;
    }
}

function llama_badge_credential_submissions_for_badge(
    PDO $db,
    int $badgeId,
    int $limit = 100
): array {
    if (
        $badgeId < 1
        || !llama_badge_credentials_storage_ready($db)
    ) {
        return [];
    }

    $limit = max(1, min(250, $limit));

    $stmt = $db->prepare(
        'SELECT
            s.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                u.email,
                CONCAT("User #", s.user_id)
            ) AS member_name,
            u.username,
            u.email,
            COALESCE(
                NULLIF(r.display_name, ""),
                NULLIF(r.username, ""),
                CASE
                    WHEN s.reviewed_by IS NULL THEN NULL
                    ELSE CONCAT("User #", s.reviewed_by)
                END
            ) AS reviewer_name
         FROM badge_credential_submissions s
         LEFT JOIN users u
            ON u.id = s.user_id
         LEFT JOIN users r
            ON r.id = s.reviewed_by
         WHERE s.badge_id = ?
         ORDER BY s.submitted_at DESC, s.id DESC
         LIMIT ' . $limit
    );
    $stmt->execute([$badgeId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_badge_credential_pending_reviews(
    PDO $db,
    ?int $badgeId = null,
    int $limit = 100
): array {
    if (!llama_badge_credentials_storage_ready($db)) {
        return [];
    }

    $limit = max(1, min(250, $limit));

    $sql =
        'SELECT
            s.*,
            bd.name AS badge_name,
            bd.slug AS badge_slug,
            bd.source_organization,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                u.email,
                CONCAT("User #", s.user_id)
            ) AS member_name,
            u.username,
            u.email
         FROM badge_credential_submissions s
         INNER JOIN badge_definitions bd
            ON bd.id = s.badge_id
         LEFT JOIN users u
            ON u.id = s.user_id
         WHERE s.status = "pending"';

    $params = [];

    if ($badgeId !== null && $badgeId > 0) {
        $sql .= ' AND s.badge_id = ?';
        $params[] = $badgeId;
    }

    $sql .=
        ' ORDER BY s.submitted_at ASC, s.id ASC
          LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_badge_credential_pending_count(PDO $db): int
{
    if (!llama_badge_credentials_storage_ready($db)) {
        return 0;
    }

    return (int) (
        $db->query(
            'SELECT COUNT(*)
             FROM badge_credential_submissions
             WHERE status = "pending"'
        )->fetchColumn()
        ?: 0
    );
}

function llama_badge_credential_pending_counts_by_badge(PDO $db): array
{
    if (!llama_badge_credentials_storage_ready($db)) {
        return [];
    }

    $rows = $db->query(
        'SELECT badge_id, COUNT(*) AS pending_count
         FROM badge_credential_submissions
         WHERE status = "pending"
         GROUP BY badge_id'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $counts = [];

    foreach ($rows as $row) {
        $counts[(int) $row['badge_id']] =
            (int) $row['pending_count'];
    }

    return $counts;
}

function llama_badge_credential_review(
    PDO $db,
    int $actorUserId,
    int $submissionId,
    string $decision,
    string $reviewNote = ''
): array {
    if (!llama_badge_credentials_storage_ready($db)) {
        throw new RuntimeException(
            'Credential review storage is not installed.'
        );
    }

    if ($actorUserId < 1 || $submissionId < 1) {
        throw new RuntimeException('Credential review request is invalid.');
    }

    $decision = strtolower(trim($decision));

    if (!in_array($decision, ['approve', 'decline'], true)) {
        throw new RuntimeException('Choose approve or decline.');
    }

    $reviewNote = trim($reviewNote);

    if (mb_strlen($reviewNote) > 1000) {
        throw new RuntimeException(
            'Review notes must be 1,000 characters or fewer.'
        );
    }

    if ($decision === 'decline' && $reviewNote === '') {
        throw new RuntimeException(
            'Add a reason before declining a credential.'
        );
    }

    if (
        $decision === 'approve'
        && !llama_badge_credentials_award_link_ready($db)
    ) {
        throw new RuntimeException(
            'Run the credential badge database migration before approving submissions.'
        );
    }

    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'SELECT
                s.*,
                bd.name AS badge_name,
                bd.slug AS badge_slug,
                bd.award_type,
                bd.is_active
             FROM badge_credential_submissions s
             INNER JOIN badge_definitions bd
                ON bd.id = s.badge_id
             WHERE s.id = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$submissionId]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$submission) {
            throw new RuntimeException(
                'Credential submission not found.'
            );
        }

        if ((string) $submission['status'] !== 'pending') {
            throw new RuntimeException(
                'This credential submission has already been reviewed.'
            );
        }

        $status = $decision === 'approve'
            ? 'approved'
            : 'declined';
        $userBadgeId = null;
        $awardAlreadyExisted = false;

        if ($decision === 'approve') {
            if (
                (string) $submission['award_type'] !== 'credential'
                || (int) $submission['is_active'] !== 1
            ) {
                throw new RuntimeException(
                    'This badge is no longer an active credential badge.'
                );
            }

            $userStmt = $db->prepare(
                'SELECT id
                 FROM users
                 WHERE id = ?
                   AND anonymized_at IS NULL
                   AND status <> "disabled"
                 LIMIT 1'
            );
            $userStmt->execute([(int) $submission['user_id']]);

            if (!$userStmt->fetchColumn()) {
                throw new RuntimeException(
                    'The member account is no longer eligible for this badge.'
                );
            }

            $awardStmt = $db->prepare(
                'SELECT id, review_status
                 FROM user_badges
                 WHERE user_id = ?
                   AND badge_id = ?
                 ORDER BY id ASC
                 LIMIT 1
                 FOR UPDATE'
            );
            $awardStmt->execute([
                (int) $submission['user_id'],
                (int) $submission['badge_id'],
            ]);
            $existingAward = $awardStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingAward) {
                $userBadgeId = (int) $existingAward['id'];

                if ((string) $existingAward['review_status'] === 'earned') {
                    $awardAlreadyExisted = true;
                } else {
                    $updateAward = $db->prepare(
                        'UPDATE user_badges
                         SET
                            review_status = "earned",
                            awarded_by = ?,
                            awarded_at = UTC_TIMESTAMP(),
                            evidence_url = NULL,
                            note = ?,
                            credential_submission_id = ?
                         WHERE id = ?'
                    );
                    $updateAward->execute([
                        $actorUserId,
                        'Credential approved from submission #'
                            . $submissionId
                            . '.',
                        $submissionId,
                        $userBadgeId,
                    ]);
                }
            } else {
                $insertAward = $db->prepare(
                    'INSERT INTO user_badges (
                        user_id,
                        badge_id,
                        awarded_by,
                        awarded_at,
                        review_status,
                        evidence_url,
                        note,
                        credential_submission_id
                     ) VALUES (
                        ?, ?, ?, UTC_TIMESTAMP(), "earned", NULL, ?, ?
                     )'
                );
                $insertAward->execute([
                    (int) $submission['user_id'],
                    (int) $submission['badge_id'],
                    $actorUserId,
                    'Credential approved from submission #'
                        . $submissionId
                        . '.',
                    $submissionId,
                ]);
                $userBadgeId = (int) $db->lastInsertId();
            }
        }

        $update = $db->prepare(
            'UPDATE badge_credential_submissions
             SET
                status = ?,
                reviewed_at = UTC_TIMESTAMP(),
                reviewed_by = ?,
                review_note = ?,
                approved_user_badge_id = ?,
                updated_at = UTC_TIMESTAMP()
             WHERE id = ?'
        );
        $update->execute([
            $status,
            $actorUserId,
            $reviewNote !== '' ? $reviewNote : null,
            $userBadgeId,
            $submissionId,
        ]);

        llama_badge_credential_add_event(
            $db,
            $submissionId,
            $actorUserId,
            $status,
            $reviewNote !== ''
                ? $reviewNote
                : (
                    $status === 'approved'
                        ? 'Credential approved.'
                        : 'Credential declined.'
                ),
            [
                'badge_id' => (int) $submission['badge_id'],
                'user_id' => (int) $submission['user_id'],
                'user_badge_id' => $userBadgeId,
                'award_already_existed' => $awardAlreadyExisted,
            ]
        );

        if (function_exists('admin_users_audit')) {
            admin_users_audit(
                $db,
                $actorUserId,
                (int) $submission['user_id'],
                'badge.credential_' . $status,
                ucfirst($status)
                    . ' credential submission for badge "'
                    . (string) $submission['badge_name']
                    . '".',
                [
                    'submission_id' => $submissionId,
                    'badge_id' => (int) $submission['badge_id'],
                    'badge_slug' => (string) $submission['badge_slug'],
                    'user_badge_id' => $userBadgeId,
                    'review_note' => $reviewNote !== ''
                        ? $reviewNote
                        : null,
                ]
            );
        }

        $db->commit();

        return [
            'status' => $status,
            'user_badge_id' => $userBadgeId,
            'user_id' => (int) $submission['user_id'],
            'badge_id' => (int) $submission['badge_id'],
        ];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function llama_badge_credential_absolute_path(array $submission): string
{
    $relative = trim((string) ($submission['stored_path'] ?? ''));

    if (
        $relative === ''
        || !preg_match(
            '~^[1-9][0-9]*/[a-f0-9]{48}\.(?:pdf|jpg|png|webp|heic|heif)$~',
            $relative
        )
    ) {
        throw new RuntimeException(
            'Credential file path is invalid.'
        );
    }

    $root = llama_badge_credential_storage_root(false);
    $path = $root . '/' . $relative;

    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException(
            'Credential file is missing from private storage.'
        );
    }

    return $path;
}

function llama_badge_credential_send_file(
    array $submission,
    bool $allowInlineImage = false
): never {
    $path = llama_badge_credential_absolute_path($submission);
    $mime = strtolower(
        trim((string) ($submission['mime_type'] ?? 'application/octet-stream'))
    );
    $filename = llama_badge_credential_clean_filename(
        (string) ($submission['original_filename'] ?? 'credential')
    );

    $inline =
        $allowInlineImage
        && str_starts_with($mime, 'image/');

    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header(
        'Content-Disposition: '
        . ($inline ? 'inline' : 'attachment')
        . '; filename="credential-file"'
        . "; filename*=UTF-8''"
        . rawurlencode($filename)
    );

    readfile($path);
    exit;
}

function llama_badge_credential_format_bytes(int $bytes): string
{
    $bytes = max(0, $bytes);

    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format($bytes) . ' B';
}
