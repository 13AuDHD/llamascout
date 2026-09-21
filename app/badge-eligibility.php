<?php

declare(strict_types=1);

require_once __DIR__ . '/contribution-levels.php';

const LLAMA_BADGE_SCOPE_ALL_MEMBERS = 'all-members';
const LLAMA_BADGE_SCOPE_COMMUNITY = 'community';
const LLAMA_BADGE_SCOPE_MEMBER = 'member';
const LLAMA_BADGE_SCOPE_SCOUT = 'scout';
const LLAMA_BADGE_SCOPE_MASTER_SCOUT = 'master-scout';
const LLAMA_BADGE_SCOPE_ADMIN = 'admin';
const LLAMA_BADGE_SCOPE_CREDENTIAL = 'credential';

const LLAMA_BADGE_RECOGNITION_PERMANENT = 'permanent';
const LLAMA_BADGE_RECOGNITION_CURRENT_ROLE = 'current-role';

function llama_badge_scope_labels(): array
{
    return [
        LLAMA_BADGE_SCOPE_ALL_MEMBERS => 'All members',
        LLAMA_BADGE_SCOPE_COMMUNITY => 'Community contributors',
        LLAMA_BADGE_SCOPE_MEMBER => 'Paid Member contributors',
        LLAMA_BADGE_SCOPE_SCOUT => 'Scouts and Master Scouts',
        LLAMA_BADGE_SCOPE_MASTER_SCOUT => 'Master Scouts',
        LLAMA_BADGE_SCOPE_ADMIN => 'Admin / Owner',
        LLAMA_BADGE_SCOPE_CREDENTIAL => 'Credential based',
    ];
}

function llama_badge_scope_descriptions(): array
{
    return [
        LLAMA_BADGE_SCOPE_ALL_MEMBERS =>
            'Available to any active member. Contribution thresholds count approved activity from every contribution level.',
        LLAMA_BADGE_SCOPE_COMMUNITY =>
            'Counts activity performed while the contributor was a Free Community member.',
        LLAMA_BADGE_SCOPE_MEMBER =>
            'Counts activity performed while the contributor was a paid Member contributor.',
        LLAMA_BADGE_SCOPE_SCOUT =>
            'Counts trained Scout service only. Community and paid Member activity from before Scout training does not count.',
        LLAMA_BADGE_SCOPE_MASTER_SCOUT =>
            'Reserved for Master Scout activity or current Master Scout recognition.',
        LLAMA_BADGE_SCOPE_ADMIN =>
            'Reserved for Admin or Owner activity. Owner is still displayed publicly as Admin.',
        LLAMA_BADGE_SCOPE_CREDENTIAL =>
            'Eligibility comes from an approved credential or training submission, independent of contribution level.',
    ];
}

function llama_badge_recognition_labels(): array
{
    return [
        LLAMA_BADGE_RECOGNITION_PERMANENT => 'Permanent achievement',
    ];
}

function llama_badge_recognition_descriptions(): array
{
    return [
        LLAMA_BADGE_RECOGNITION_PERMANENT =>
            'Once legitimately earned, the badge remains part of the member history even if their membership or Scout role later changes. Explicit disciplinary revocation is handled separately.',
    ];
}

function llama_badge_scope_normalize(mixed $scope): string
{
    $scope = strtolower(trim((string) $scope));

    if ($scope === 'master_scout') {
        $scope = LLAMA_BADGE_SCOPE_MASTER_SCOUT;
    }

    return isset(llama_badge_scope_labels()[$scope])
        ? $scope
        : LLAMA_BADGE_SCOPE_ALL_MEMBERS;
}

function llama_badge_recognition_normalize(mixed $mode): string
{
    /*
     * Badges are trophies, not live role indicators. Keep the legacy column for
     * migration compatibility, but normalize every badge to permanent. Current
     * authority is represented by the member's live role/profile state instead.
     */
    return LLAMA_BADGE_RECOGNITION_PERMANENT;
}

function llama_badge_scope_label(mixed $scope): string
{
    $scope = llama_badge_scope_normalize($scope);
    return (string) (llama_badge_scope_labels()[$scope] ?? 'All members');
}

function llama_badge_recognition_label(mixed $mode): string
{
    $mode = llama_badge_recognition_normalize($mode);
    return (string) (llama_badge_recognition_labels()[$mode] ?? 'Permanent achievement');
}

function llama_badge_scope_roles(string $scope): array
{
    return match (llama_badge_scope_normalize($scope)) {
        LLAMA_BADGE_SCOPE_COMMUNITY => ['user', 'community', 'free'],
        LLAMA_BADGE_SCOPE_MEMBER => ['member'],
        LLAMA_BADGE_SCOPE_SCOUT => ['scout', 'master-scout', 'master_scout'],
        LLAMA_BADGE_SCOPE_MASTER_SCOUT => ['master-scout', 'master_scout'],
        LLAMA_BADGE_SCOPE_ADMIN => ['admin', 'owner'],
        default => [],
    };
}

function llama_badge_scope_contribution_levels(string $scope): array
{
    return match (llama_badge_scope_normalize($scope)) {
        LLAMA_BADGE_SCOPE_COMMUNITY => ['community'],
        LLAMA_BADGE_SCOPE_MEMBER => ['member'],
        LLAMA_BADGE_SCOPE_SCOUT => ['scout', 'master-scout'],
        LLAMA_BADGE_SCOPE_MASTER_SCOUT => ['master-scout'],
        LLAMA_BADGE_SCOPE_ADMIN => ['admin'],
        default => [],
    };
}

function llama_badge_active_user(PDO $db, int $userId): bool
{
    if ($userId < 1) {
        return false;
    }

    $stmt = $db->prepare(
        'SELECT id
         FROM users
         WHERE id = ?
           AND status = "active"
           AND anonymized_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$userId]);

    return (bool) $stmt->fetchColumn();
}

function llama_badge_user_matches_current_scope(
    PDO $db,
    int $userId,
    string $scope
): bool {
    if (!llama_badge_active_user($db, $userId)) {
        return false;
    }

    $scope = llama_badge_scope_normalize($scope);

    if (
        $scope === LLAMA_BADGE_SCOPE_ALL_MEMBERS
        || $scope === LLAMA_BADGE_SCOPE_CREDENTIAL
    ) {
        return true;
    }

    $level = llama_user_contribution_level($db, $userId);

    return match ($scope) {
        LLAMA_BADGE_SCOPE_COMMUNITY =>
            $level === LLAMA_CONTRIBUTION_LEVEL_COMMUNITY,
        LLAMA_BADGE_SCOPE_MEMBER =>
            $level === LLAMA_CONTRIBUTION_LEVEL_MEMBER,
        LLAMA_BADGE_SCOPE_SCOUT =>
            in_array(
                $level,
                [
                    LLAMA_CONTRIBUTION_LEVEL_SCOUT,
                    LLAMA_CONTRIBUTION_LEVEL_MASTER,
                ],
                true
            ),
        LLAMA_BADGE_SCOPE_MASTER_SCOUT =>
            $level === LLAMA_CONTRIBUTION_LEVEL_MASTER,
        LLAMA_BADGE_SCOPE_ADMIN =>
            $level === LLAMA_CONTRIBUTION_LEVEL_ADMIN,
        default => false,
    };
}

function llama_badge_user_has_historical_scope(
    PDO $db,
    int $userId,
    string $scope
): bool {
    if (!llama_badge_active_user($db, $userId)) {
        return false;
    }

    $scope = llama_badge_scope_normalize($scope);

    if (
        $scope === LLAMA_BADGE_SCOPE_ALL_MEMBERS
        || $scope === LLAMA_BADGE_SCOPE_CREDENTIAL
        || llama_badge_user_matches_current_scope($db, $userId, $scope)
    ) {
        return true;
    }

    $roles = llama_badge_scope_roles($scope);
    if ($roles) {
        $placeholders = implode(', ', array_fill(0, count($roles), '?'));
        $stmt = $db->prepare(
            'SELECT 1
             FROM place_contributions
             WHERE user_id = ?
               AND status = "approved"
               AND role_at_time IN (' . $placeholders . ')
             LIMIT 1'
        );
        $stmt->execute(array_merge([$userId], $roles));

        if ($stmt->fetchColumn()) {
            return true;
        }
    }

    /*
     * A check-in is also historical activity at a contribution level. This
     * matters when a member later changes level. For example, a Community
     * check-in remains Community activity after that person becomes Paid.
     */
    $levels = llama_badge_scope_contribution_levels($scope);
    if ($levels) {
        try {
            $placeholders = implode(', ', array_fill(0, count($levels), '?'));
            $stmt = $db->prepare(
                'SELECT 1
                 FROM place_checkins
                 WHERE user_id = ?
                   AND contribution_level IN (' . $placeholders . ')
                 LIMIT 1'
            );
            $stmt->execute(array_merge([$userId], $levels));

            if ($stmt->fetchColumn()) {
                return true;
            }
        } catch (Throwable) {
            // Check-in storage may not exist during a staged migration.
        }
    }

    if (
        $scope === LLAMA_BADGE_SCOPE_SCOUT
        || $scope === LLAMA_BADGE_SCOPE_MASTER_SCOUT
    ) {
        try {
            $ranks = $scope === LLAMA_BADGE_SCOPE_MASTER_SCOUT
                ? ['master-scout', 'master_scout']
                : ['scout', 'master-scout', 'master_scout'];
            $placeholders = implode(', ', array_fill(0, count($ranks), '?'));
            $stmt = $db->prepare(
                'SELECT 1
                 FROM scout_rank_history
                 WHERE user_id = ?
                   AND to_rank IN (' . $placeholders . ')
                 LIMIT 1'
            );
            $stmt->execute(array_merge([$userId], $ranks));

            if ($stmt->fetchColumn()) {
                return true;
            }
        } catch (Throwable) {
            // Older installations may not have rank history yet.
        }
    }

    return false;
}

function llama_badge_definition_scope(array $badge): string
{
    return llama_badge_scope_normalize(
        $badge['eligibility_scope'] ?? LLAMA_BADGE_SCOPE_ALL_MEMBERS
    );
}

function llama_badge_definition_recognition(array $badge): string
{
    return llama_badge_recognition_normalize(
        $badge['recognition_mode'] ?? LLAMA_BADGE_RECOGNITION_PERMANENT
    );
}

function llama_badge_user_eligible_for_manual_award(
    PDO $db,
    int $userId,
    array $badge
): bool {
    if (!llama_badge_active_user($db, $userId)) {
        return false;
    }

    $scope = llama_badge_definition_scope($badge);
    $recognition = llama_badge_definition_recognition($badge);

    if ($recognition === LLAMA_BADGE_RECOGNITION_CURRENT_ROLE) {
        return llama_badge_user_matches_current_scope(
            $db,
            $userId,
            $scope
        );
    }

    return llama_badge_user_has_historical_scope(
        $db,
        $userId,
        $scope
    );
}

function llama_badge_scope_activity_sql(
    string $scope,
    string $column
): array {
    $roles = llama_badge_scope_roles($scope);

    if (!$roles) {
        return [
            'sql' => '',
            'params' => [],
        ];
    }

    $placeholders = implode(', ', array_fill(0, count($roles), '?'));

    return [
        'sql' => ' AND ' . $column . ' IN (' . $placeholders . ')',
        'params' => $roles,
    ];
}

function llama_badge_scope_level_sql(
    string $scope,
    string $column
): array {
    $levels = llama_badge_scope_contribution_levels($scope);

    if (!$levels) {
        return [
            'sql' => '',
            'params' => [],
        ];
    }

    $placeholders = implode(', ', array_fill(0, count($levels), '?'));

    return [
        'sql' => ' AND ' . $column . ' IN (' . $placeholders . ')',
        'params' => $levels,
    ];
}

function llama_badge_definition_how_to_earn(array $badge): string
{
    $explicit = trim((string) ($badge['how_to_earn'] ?? ''));
    if ($explicit !== '') {
        return $explicit;
    }

    $scope = llama_badge_definition_scope($badge);
    $awardType = strtolower(trim((string) ($badge['award_type'] ?? 'manual')));
    $threshold = max(0, (int) ($badge['threshold_value'] ?? 0));
    $metric = trim((string) ($badge['threshold_metric'] ?? ''));

    if ($awardType === 'credential') {
        return 'Submit the required credential or training proof and have it approved by Llama Scout.';
    }

    if ($awardType === 'automatic' && $threshold > 0 && $metric !== '') {
        $metricLabel = function_exists('llama_badge_threshold_metric_labels')
            ? (llama_badge_threshold_metric_labels()[$metric] ?? $metric)
            : $metric;

        return 'Reach ' . number_format($threshold) . ' ' . strtolower((string) $metricLabel)
            . ' within the ' . strtolower(llama_badge_scope_label($scope)) . ' badge track.';
    }

    if ($scope === LLAMA_BADGE_SCOPE_SCOUT) {
        return 'Earned through trained Llama Scout field service.';
    }

    if ($scope === LLAMA_BADGE_SCOPE_MASTER_SCOUT) {
        return 'Earned through Master Scout service or recognition.';
    }

    if ($scope === LLAMA_BADGE_SCOPE_ADMIN) {
        return 'Reserved for qualifying Llama Scout administration activity.';
    }

    return 'Awarded by Llama Scout when the badge requirements are met.';
}

function llama_badge_award_system_by_slug(
    PDO $db,
    int $userId,
    string $slug,
    ?int $awardedBy = null,
    ?string $note = null
): int {
    $slug = strtolower(trim($slug));

    if ($userId < 1 || $slug === '') {
        return 0;
    }

    $stmt = $db->prepare(
        'SELECT *
         FROM badge_definitions
         WHERE slug = ?
           AND is_active = 1
         LIMIT 1'
    );
    $stmt->execute([$slug]);
    $badge = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$badge) {
        return 0;
    }

    $awardType = strtolower(trim((string) ($badge['award_type'] ?? 'manual')));

    if ($awardType === 'credential') {
        return 0;
    }

    if ($awardType === 'automatic') {
        if (function_exists('llama_badges_sync_user')) {
            llama_badges_sync_user($db, $userId);
        }
        return 0;
    }

    if (!llama_badge_user_eligible_for_manual_award($db, $userId, $badge)) {
        return 0;
    }

    $badgeId = (int) ($badge['id'] ?? 0);
    if ($badgeId < 1) {
        return 0;
    }

    $existing = $db->prepare(
        'SELECT id, review_status
         FROM user_badges
         WHERE user_id = ?
           AND badge_id = ?
         LIMIT 1'
    );
    $existing->execute([$userId, $badgeId]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);

    $note = trim((string) $note);
    $note = $note !== '' ? mb_substr($note, 0, 500) : null;

    if ($row) {
        $userBadgeId = (int) $row['id'];

        if ((string) ($row['review_status'] ?? '') === 'earned') {
            return $userBadgeId;
        }

        $update = $db->prepare(
            'UPDATE user_badges
             SET
                review_status = "earned",
                awarded_by = ?,
                awarded_at = UTC_TIMESTAMP(),
                note = ?
             WHERE id = ?'
        );
        $update->execute([
            $awardedBy && $awardedBy > 0 ? $awardedBy : null,
            $note,
            $userBadgeId,
        ]);

        return $userBadgeId;
    }

    $insert = $db->prepare(
        'INSERT INTO user_badges (
            user_id,
            badge_id,
            awarded_by,
            awarded_at,
            review_status,
            note
         ) VALUES (?, ?, ?, UTC_TIMESTAMP(), "earned", ?)'
    );
    $insert->execute([
        $userId,
        $badgeId,
        $awardedBy && $awardedBy > 0 ? $awardedBy : null,
        $note,
    ]);

    return (int) $db->lastInsertId();
}

function llama_badge_sync_current_role_recognition(
    PDO $db,
    int $userId
): int {
    /*
     * Phase 12B: no-op by design. Badge ownership is permanent after a
     * legitimate award. Role/profile changes are not badge revocation events.
     */
    return 0;
}
