<?php

declare(strict_types=1);

/* =========================================================
   LLAMA SCOUT CONTRIBUTION LEVELS

   Membership access and contribution trust are separate.

   Public contribution levels:
   - community: signed-in Free Member
   - member: paid Member
   - scout: trained Llama Scout
   - master-scout: senior Scout / moderator
   - admin: Admin or Owner (Owner is never exposed publicly)

   This file centralizes the language and capability model so
   pages do not invent their own role checks.
   ========================================================= */

const LLAMA_CONTRIBUTION_LEVEL_COMMUNITY = 'community';
const LLAMA_CONTRIBUTION_LEVEL_MEMBER = 'member';
const LLAMA_CONTRIBUTION_LEVEL_SCOUT = 'scout';
const LLAMA_CONTRIBUTION_LEVEL_MASTER = 'master-scout';
const LLAMA_CONTRIBUTION_LEVEL_ADMIN = 'admin';

function llama_contribution_level_definitions(): array
{
    return [
        LLAMA_CONTRIBUTION_LEVEL_COMMUNITY => [
            'rank' => 10,
            'label' => 'Community Contributed',
            'short_label' => 'Community',
            'icon' => 'users',
            'description' => 'Contributed by a signed-in Llama Scout community member.',
        ],
        LLAMA_CONTRIBUTION_LEVEL_MEMBER => [
            'rank' => 20,
            'label' => 'Member Contributed',
            'short_label' => 'Member',
            'icon' => 'user',
            'description' => 'Contributed by a paid Llama Scout member.',
        ],
        LLAMA_CONTRIBUTION_LEVEL_SCOUT => [
            'rank' => 30,
            'label' => 'Scout Contributed',
            'short_label' => 'Scout',
            'icon' => 'binoculars',
            'description' => 'Contributed in the field by a trained Llama Scout.',
        ],
        LLAMA_CONTRIBUTION_LEVEL_MASTER => [
            'rank' => 40,
            'label' => 'Master Scout Contributed',
            'short_label' => 'Master Scout',
            'icon' => 'compass',
            'description' => 'Contributed in the field by a Master Scout.',
        ],
        LLAMA_CONTRIBUTION_LEVEL_ADMIN => [
            'rank' => 50,
            'label' => 'Admin Contributed',
            'short_label' => 'Admin',
            'icon' => 'shield-check',
            'description' => 'Contributed directly by Llama Scout administration.',
        ],
    ];
}

function llama_contribution_level_normalize(string $level): string
{
    $level = strtolower(trim($level));

    if ($level === 'master_scout') {
        $level = LLAMA_CONTRIBUTION_LEVEL_MASTER;
    }

    if (!isset(llama_contribution_level_definitions()[$level])) {
        return LLAMA_CONTRIBUTION_LEVEL_COMMUNITY;
    }

    return $level;
}

function llama_contribution_level_from_role(string $role): string
{
    $role = strtolower(trim($role));

    return match ($role) {
        'owner', 'admin' => LLAMA_CONTRIBUTION_LEVEL_ADMIN,
        'master-scout', 'master_scout' => LLAMA_CONTRIBUTION_LEVEL_MASTER,
        'scout' => LLAMA_CONTRIBUTION_LEVEL_SCOUT,
        'member' => LLAMA_CONTRIBUTION_LEVEL_MEMBER,
        default => LLAMA_CONTRIBUTION_LEVEL_COMMUNITY,
    };
}

function llama_contribution_level_rank(string $level): int
{
    $definition = llama_contribution_level_definitions()[
        llama_contribution_level_normalize($level)
    ] ?? null;

    return (int) ($definition['rank'] ?? 0);
}

function llama_contribution_level_label(string $level): string
{
    $definition = llama_contribution_level_definitions()[
        llama_contribution_level_normalize($level)
    ] ?? null;

    return (string) ($definition['label'] ?? 'Community Contributed');
}

function llama_contribution_level_short_label(string $level): string
{
    $definition = llama_contribution_level_definitions()[
        llama_contribution_level_normalize($level)
    ] ?? null;

    return (string) ($definition['short_label'] ?? 'Community');
}

function llama_contribution_level_icon(string $level): string
{
    $definition = llama_contribution_level_definitions()[
        llama_contribution_level_normalize($level)
    ] ?? null;

    return (string) ($definition['icon'] ?? 'users');
}

function llama_contribution_level_description(string $level): string
{
    $definition = llama_contribution_level_definitions()[
        llama_contribution_level_normalize($level)
    ] ?? null;

    return (string) ($definition['description'] ?? 'Community contribution.');
}

/* =========================================================
   INTERNAL ROLE SNAPSHOT

   This is what gets stored with a contribution. Owner remains
   Owner internally, but maps to Admin for public display.
   ========================================================= */

function llama_contribution_role_at_time(
    PDO $db,
    int $userId
): string {
    if ($userId < 1) {
        return 'user';
    }

    $roles = user_roles($userId);

    foreach (
        [
            'owner',
            'admin',
            'master-scout',
            'master_scout',
            'scout',
        ] as $role
    ) {
        if (in_array($role, $roles, true)) {
            return $role === 'master_scout'
                ? 'master-scout'
                : $role;
        }
    }

    if (user_has_paid_membership($userId)) {
        return 'member';
    }

    return 'user';
}

function llama_user_contribution_level(
    PDO $db,
    int $userId
): string {
    return llama_contribution_level_from_role(
        llama_contribution_role_at_time($db, $userId)
    );
}

/* =========================================================
   CAPABILITIES

   These are contribution permissions, not content-access
   permissions. Paid access is still handled separately.
   ========================================================= */

function llama_contributor_capabilities(
    PDO $db,
    int $userId
): array {
    $level = llama_user_contribution_level($db, $userId);
    $rank = llama_contribution_level_rank($level);

    return [
        'level' => $level,
        'submit_place' => $userId > 0,
        'submit_update' => $userId > 0,
        'submit_photo' => $userId > 0,
        'report_problem' => $userId > 0,
        'check_in' => $userId > 0,
        'field_report' => $rank >= llama_contribution_level_rank(
            LLAMA_CONTRIBUTION_LEVEL_SCOUT
        ),
        'moderate_places' => $rank >= llama_contribution_level_rank(
            LLAMA_CONTRIBUTION_LEVEL_MASTER
        ),
    ];
}

function llama_contributor_can(
    PDO $db,
    int $userId,
    string $capability
): bool {
    $capabilities = llama_contributor_capabilities($db, $userId);

    return !empty($capabilities[$capability]);
}

/* =========================================================
   RECORD LEVEL

   Existing Owner-created submissions were historically stored
   as "member" because the old submission helper did not check
   the Owner role. For public classification only, repair that
   known legacy omission when the contributor is still Owner.
   The stored audit row is not rewritten here.
   ========================================================= */

function llama_contribution_level_for_record(
    PDO $db,
    array $record
): string {
    $role = strtolower(trim((string) ($record['role_at_time'] ?? 'user')));
    $userId = (int) ($record['user_id'] ?? 0);

    if (
        $role === 'member'
        && $userId > 0
        && user_has_role('owner', $userId)
    ) {
        return LLAMA_CONTRIBUTION_LEVEL_ADMIN;
    }

    return llama_contribution_level_from_role($role);
}

/* =========================================================
   PLACE DOCUMENTATION LEVEL

   A Place moves upward only when a contributor at that level
   actually created it or performed an approved contribution
   tied to an in-person visit. Moderation by itself never raises
   the public contribution level.
   ========================================================= */

function llama_place_documentation_level(
    PDO $db,
    int $placeId
): array {
    $level = LLAMA_CONTRIBUTION_LEVEL_COMMUNITY;
    $hasQualifyingActivity = false;

    if ($placeId < 1) {
        return [
            'level' => $level,
            'rank' => llama_contribution_level_rank($level),
            'label' => llama_contribution_level_label($level),
            'short_label' => llama_contribution_level_short_label($level),
            'icon' => llama_contribution_level_icon($level),
            'description' => llama_contribution_level_description($level),
        ];
    }

    /*
     * Published contributions establish or raise documentation level only
     * when the contributor actually created the Place or supplied an
     * approved contribution tied to an in-person visit.
     */
    try {
        $stmt = $db->prepare(
            'SELECT
                pc.user_id,
                pc.role_at_time,
                pc.contribution_type,
                pc.visited_at
             FROM place_contributions pc
             WHERE pc.place_id = ?
               AND pc.status = "approved"
               AND pc.contribution_type <> "moderation"
               AND (
                    pc.contribution_type = "new_place"
                    OR pc.visited_at IS NOT NULL
               )
             ORDER BY pc.id ASC'
        );

        $stmt->execute([$placeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $hasQualifyingActivity = true;
            $candidate = llama_contribution_level_for_record($db, $row);

            if (
                llama_contribution_level_rank($candidate)
                > llama_contribution_level_rank($level)
            ) {
                $level = $candidate;
            }
        }
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout contribution-level lookup failed for Place #'
            . $placeId
            . ': '
            . $exception->getMessage()
        );
    }

    /*
     * Geofenced check-ins are also real field participation. They can raise
     * the public documentation level, but moderation alone cannot.
     *
     * The information_schema check keeps the Place page compatible while the
     * one-time Check In migration is being installed.
     */
    try {
        $tableStmt = $db->prepare(
            'SELECT 1
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
             LIMIT 1'
        );
        $tableStmt->execute(['place_checkins']);

        if ($tableStmt->fetchColumn() !== false) {
            $checkinStmt = $db->prepare(
                'SELECT contribution_level
                 FROM place_checkins
                 WHERE place_id = ?
                 ORDER BY id ASC'
            );
            $checkinStmt->execute([$placeId]);

            foreach (
                $checkinStmt->fetchAll(PDO::FETCH_ASSOC) ?: []
                as $checkin
            ) {
                $hasQualifyingActivity = true;
                $candidate = llama_contribution_level_normalize(
                    (string) ($checkin['contribution_level'] ?? '')
                );

                if (
                    llama_contribution_level_rank($candidate)
                    > llama_contribution_level_rank($level)
                ) {
                    $level = $candidate;
                }
            }
        }
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout check-in documentation-level lookup failed for Place #'
            . $placeId
            . ': '
            . $exception->getMessage()
        );
    }

    /*
     * Legacy provenance remains a fallback only when no qualifying modern
     * contribution or check-in activity exists.
     */
    if (!$hasQualifyingActivity) {
        try {
            $fallback = $db->prepare(
                'SELECT origin_type
                 FROM place_provenance
                 WHERE place_id = ?
                 LIMIT 1'
            );
            $fallback->execute([$placeId]);
            $origin = strtolower(trim((string) $fallback->fetchColumn()));

            if (in_array($origin, ['llama-scouted', 'scout'], true)) {
                $level = LLAMA_CONTRIBUTION_LEVEL_SCOUT;
            } elseif ($origin === 'admin') {
                $level = LLAMA_CONTRIBUTION_LEVEL_ADMIN;
            }
        } catch (Throwable $exception) {
            error_log(
                'Llama Scout legacy documentation-level lookup failed for Place #'
                . $placeId
                . ': '
                . $exception->getMessage()
            );
        }
    }

    return [
        'level' => $level,
        'rank' => llama_contribution_level_rank($level),
        'label' => llama_contribution_level_label($level),
        'short_label' => llama_contribution_level_short_label($level),
        'icon' => llama_contribution_level_icon($level),
        'description' => llama_contribution_level_description($level),
    ];
}
