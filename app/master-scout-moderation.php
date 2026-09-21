<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/moderation.php';
require_once __DIR__ . '/place-report.php';
require_once __DIR__ . '/place-submission-history.php';
require_once __DIR__ . '/place-update.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/admin-users.php';

/* =========================================================
   MASTER SCOUT MODERATION

   Master Scouts are community moderators for Place submissions
   and structured Place updates. They do not receive full
   Basecamp access and cannot edit, delete, feature, or moderate
   their own contributions through this surface.
   ========================================================= */

function llama_master_moderator_context(
    PDO $db,
    int $userId
): ?array {
    if ($userId < 1) {
        return null;
    }

    if (
        user_has_role('owner', $userId)
        || user_has_role('admin', $userId)
    ) {
        return [
            'user_id' => $userId,
            'role' => 'admin',
            'label' => 'Admin',
        ];
    }

    if (!user_has_role('master-scout', $userId)) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT id, status, active_through
         FROM scout_profiles
         WHERE user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile || (string) ($profile['status'] ?? '') !== 'active') {
        return null;
    }

    $activeThrough = trim((string) ($profile['active_through'] ?? ''));
    if ($activeThrough !== '') {
        try {
            $ends = new DateTimeImmutable(
                $activeThrough,
                new DateTimeZone('UTC')
            );

            if ($ends->getTimestamp() < time()) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }
    }

    return [
        'user_id' => $userId,
        'role' => 'master-scout',
        'label' => 'Master Scout',
        'scout_profile_id' => (int) ($profile['id'] ?? 0),
    ];
}

function llama_master_moderator_can_access(
    PDO $db,
    int $userId
): bool {
    return llama_master_moderator_context($db, $userId) !== null;
}

function llama_master_moderation_assert_not_self(
    int $moderatorUserId,
    int $contributorUserId
): void {
    if (
        $moderatorUserId > 0
        && $contributorUserId > 0
        && $moderatorUserId === $contributorUserId
    ) {
        throw new DomainException(
            'You cannot moderate your own contribution.'
        );
    }
}

function llama_master_moderation_new_place_queue(
    PDO $db,
    int $moderatorUserId
): array {
    $stmt = $db->prepare(
        'SELECT
            ps.*,
            u.username,
            u.display_name,
            u.status AS user_status
         FROM place_submissions ps
         INNER JOIN users u
            ON u.id = ps.user_id
         WHERE ps.status IN ("pending", "needs-changes")
           AND ps.user_id <> ?
         ORDER BY ps.submitted_at ASC, ps.id ASC'
    );
    $stmt->execute([$moderatorUserId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_master_moderation_update_queue(
    PDO $db,
    int $moderatorUserId
): array {
    $stmt = $db->prepare(
        'SELECT
            pus.*,
            p.name AS place_name,
            p.slug AS place_slug,
            p.status AS place_status,
            u.username,
            u.display_name
         FROM place_update_submissions pus
         INNER JOIN places p
            ON p.id = pus.place_id
         INNER JOIN users u
            ON u.id = pus.user_id
         WHERE pus.status IN ("pending", "needs-changes")
           AND pus.user_id <> ?
         ORDER BY pus.submitted_at ASC, pus.id ASC'
    );
    $stmt->execute([$moderatorUserId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_master_moderation_counts(
    PDO $db,
    int $moderatorUserId
): array {
    return [
        'new_places' => count(
            llama_master_moderation_new_place_queue(
                $db,
                $moderatorUserId
            )
        ),
        'updates' => count(
            llama_master_moderation_update_queue(
                $db,
                $moderatorUserId
            )
        ),
    ];
}

function llama_master_moderation_audit(
    PDO $db,
    int $moderatorUserId,
    int $contributorUserId,
    string $action,
    string $summary,
    array $metadata = []
): void {
    $metadata['moderation_scope'] = 'master-scout';

    admin_users_audit(
        $db,
        $moderatorUserId,
        $contributorUserId,
        $action,
        $summary,
        $metadata
    );
}

function llama_master_moderate_new_place(
    PDO $db,
    int $moderatorUserId,
    int $submissionId,
    string $action,
    string $notes
): array {
    if (!llama_master_moderator_can_access($db, $moderatorUserId)) {
        throw new DomainException(
            'Active Master Scout status is required to moderate contributions.'
        );
    }

    $action = strtolower(trim($action));
    $notes = trim($notes);

    if (!in_array($action, ['approve', 'needs-changes', 'rejected'], true)) {
        throw new InvalidArgumentException(
            'Choose Approve, Request Changes, or Not Approved.'
        );
    }

    if (
        in_array($action, ['needs-changes', 'rejected'], true)
        && $notes === ''
    ) {
        throw new InvalidArgumentException(
            $action === 'needs-changes'
                ? 'Explain what the contributor needs to change.'
                : 'Explain why the submission was not approved.'
        );
    }

    $item = moderation_submission($db, $submissionId);
    if (!$item) {
        throw new RuntimeException('The Place submission could not be found.');
    }

    llama_master_moderation_assert_not_self(
        $moderatorUserId,
        (int) ($item['user_id'] ?? 0)
    );

    if (
        !in_array(
            (string) ($item['status'] ?? ''),
            ['pending', 'needs-changes'],
            true
        )
    ) {
        throw new RuntimeException(
            'This Place submission is no longer awaiting moderation.'
        );
    }

    $data = is_array($item['data'] ?? null)
        ? $item['data']
        : [];

    $pointEstimate = llama_points_estimate_new_place(
        $db,
        llama_place_report_scoring_input_from_data($data),
        count(is_array($data['photos'] ?? null) ? $data['photos'] : [])
    );
    $points = (int) ($pointEstimate['estimated_points'] ?? 0);

    $copiedPhotoPaths = [];
    $placeId = null;

    try {
        $db->beginTransaction();

        $locked = moderation_submission($db, $submissionId, true);
        if (!$locked) {
            throw new RuntimeException('The Place submission could not be found.');
        }

        llama_master_moderation_assert_not_self(
            $moderatorUserId,
            (int) ($locked['user_id'] ?? 0)
        );

        if ($action === 'approve') {
            llama_place_submission_record_terminal_review(
                $db,
                $submissionId,
                $moderatorUserId,
                'approved',
                $notes
            );

            $placeId = moderation_approve_new_place(
                $db,
                $submissionId,
                $moderatorUserId,
                'active',
                $notes,
                $points,
                $copiedPhotoPaths
            );

            llama_place_report_publish_answer_state(
                $db,
                $placeId,
                $data
            );

            llama_master_moderation_audit(
                $db,
                $moderatorUserId,
                (int) $locked['user_id'],
                'master_scout.place_submission_approved',
                'Master Scout approved new Place submission #' . $submissionId . '.',
                [
                    'submission_id' => $submissionId,
                    'place_id' => $placeId,
                    'points_awarded' => $points,
                ]
            );
        } else {
            if ($action === 'needs-changes') {
                llama_place_submission_record_change_request(
                    $db,
                    $submissionId,
                    $moderatorUserId,
                    $notes,
                    $data
                );
            } else {
                llama_place_submission_record_terminal_review(
                    $db,
                    $submissionId,
                    $moderatorUserId,
                    'rejected',
                    $notes
                );
            }

            moderation_set_submission_status(
                $db,
                $submissionId,
                $moderatorUserId,
                $action,
                $notes
            );

            llama_master_moderation_audit(
                $db,
                $moderatorUserId,
                (int) $locked['user_id'],
                $action === 'needs-changes'
                    ? 'master_scout.place_submission_changes_requested'
                    : 'master_scout.place_submission_rejected',
                $action === 'needs-changes'
                    ? 'Master Scout requested changes to new Place submission #' . $submissionId . '.'
                    : 'Master Scout rejected new Place submission #' . $submissionId . '.',
                [
                    'submission_id' => $submissionId,
                    'review_notes' => $notes,
                ]
            );
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if ($copiedPhotoPaths) {
            moderation_cleanup_copied_place_photos($copiedPhotoPaths);
        }

        throw $exception;
    }

    if ($action === 'approve' || $action === 'rejected') {
        llama_place_submission_remove_files($submissionId);
    }

    try {
        $placeName = trim(
            (string) (
                $data['name']
                ?? $item['place_name']
                ?? 'this Place'
            )
        );

        $placeUrl = '';
        if ($placeId !== null && $placeId > 0) {
            $stmt = $db->prepare(
                'SELECT slug
                 FROM places
                 WHERE id = ?
                 LIMIT 1'
            );
            $stmt->execute([$placeId]);
            $slug = trim((string) $stmt->fetchColumn());
            if ($slug !== '') {
                $placeUrl = 'https://llamascout.com/place.php?place=' . rawurlencode($slug);
            }
        }

        send_contribution_review_email(
            $db,
            (int) $item['user_id'],
            $action,
            'new Place submission',
            $submissionId,
            $placeName,
            $notes,
            $action === 'approve' ? $points : 0,
            'https://account.llamascout.com/contributions.php',
            $placeUrl
        );
    } catch (Throwable $emailException) {
        llama_log_caught_exception(
            $emailException,
            'email.master_scout_contribution_review',
            [
                'submission_id' => $submissionId,
                'review_status' => $action,
            ]
        );
    }

    return [
        'action' => $action,
        'place_id' => $placeId,
        'points' => $action === 'approve' ? $points : 0,
    ];
}

function llama_master_moderate_place_update(
    PDO $db,
    int $moderatorUserId,
    int $updateId,
    string $action,
    string $notes
): array {
    if (!llama_master_moderator_can_access($db, $moderatorUserId)) {
        throw new DomainException(
            'Active Master Scout status is required to moderate contributions.'
        );
    }

    $action = strtolower(trim($action));
    $notes = trim($notes);

    if (!in_array($action, ['approve', 'needs-changes', 'rejected'], true)) {
        throw new InvalidArgumentException(
            'Choose Approve, Request Changes, or Not Approved.'
        );
    }

    if (
        in_array($action, ['needs-changes', 'rejected'], true)
        && $notes === ''
    ) {
        throw new InvalidArgumentException(
            $action === 'needs-changes'
                ? 'Explain what the contributor needs to change.'
                : 'Explain why the update was not approved.'
        );
    }

    $item = moderation_update($db, $updateId);
    if (!$item) {
        throw new RuntimeException('The Place update could not be found.');
    }

    llama_master_moderation_assert_not_self(
        $moderatorUserId,
        (int) ($item['user_id'] ?? 0)
    );

    if (
        !in_array(
            (string) ($item['status'] ?? ''),
            ['pending', 'needs-changes'],
            true
        )
    ) {
        throw new RuntimeException(
            'This Place update is no longer awaiting moderation.'
        );
    }

    $points = 0;
    $contributionId = null;
    $copiedPhotoPaths = [];

    try {
        $db->beginTransaction();

        $locked = moderation_update($db, $updateId, true);
        if (!$locked) {
            throw new RuntimeException('The Place update could not be found.');
        }

        llama_master_moderation_assert_not_self(
            $moderatorUserId,
            (int) ($locked['user_id'] ?? 0)
        );

        if ($action === 'approve') {
            $contributionId = llama_place_update_approve(
                $db,
                $updateId,
                $moderatorUserId,
                $notes,
                $copiedPhotoPaths
            );

            $fresh = moderation_update($db, $updateId, true) ?: $locked;
            $points = (int) ($fresh['points_awarded'] ?? 0);

            llama_place_update_record_review(
                $db,
                $updateId,
                $moderatorUserId,
                'approved',
                $notes,
                $locked
            );

            llama_master_moderation_audit(
                $db,
                $moderatorUserId,
                (int) $locked['user_id'],
                'master_scout.place_update_approved',
                'Master Scout approved Place update #' . $updateId . '.',
                [
                    'update_id' => $updateId,
                    'place_id' => (int) ($locked['place_id'] ?? 0),
                    'contribution_id' => $contributionId,
                    'points_awarded' => $points,
                ]
            );
        } else {
            llama_place_update_record_review(
                $db,
                $updateId,
                $moderatorUserId,
                $action === 'needs-changes'
                    ? 'changes-requested'
                    : 'rejected',
                $notes,
                $locked
            );

            moderation_set_update_status(
                $db,
                $updateId,
                $moderatorUserId,
                $action,
                $notes
            );

            llama_master_moderation_audit(
                $db,
                $moderatorUserId,
                (int) $locked['user_id'],
                $action === 'needs-changes'
                    ? 'master_scout.place_update_changes_requested'
                    : 'master_scout.place_update_rejected',
                $action === 'needs-changes'
                    ? 'Master Scout requested changes to Place update #' . $updateId . '.'
                    : 'Master Scout rejected Place update #' . $updateId . '.',
                [
                    'update_id' => $updateId,
                    'place_id' => (int) ($locked['place_id'] ?? 0),
                    'review_notes' => $notes,
                ]
            );
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if ($copiedPhotoPaths) {
            moderation_cleanup_copied_place_photos($copiedPhotoPaths);
        }

        throw $exception;
    }

    if ($action === 'approve' || $action === 'rejected') {
        llama_place_update_remove_files($updateId);
    }

    try {
        $placeSlug = trim((string) ($item['place_slug'] ?? ''));

        send_contribution_review_email(
            $db,
            (int) $item['user_id'],
            $action,
            'Place update',
            $updateId,
            (string) ($item['place_name'] ?? 'this Place'),
            $notes,
            $action === 'approve' ? $points : 0,
            'https://account.llamascout.com/contributions.php',
            $placeSlug !== ''
                ? 'https://llamascout.com/place.php?place=' . rawurlencode($placeSlug)
                : ''
        );
    } catch (Throwable $emailException) {
        llama_log_caught_exception(
            $emailException,
            'email.master_scout_update_review',
            [
                'update_id' => $updateId,
                'review_status' => $action,
            ]
        );
    }

    return [
        'action' => $action,
        'contribution_id' => $contributionId,
        'points' => $action === 'approve' ? $points : 0,
    ];
}
