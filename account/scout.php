<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/scout-stats.php';
require_once dirname(__DIR__) . '/app/place-contributions.php';
require_once dirname(__DIR__) . '/app/master-scout.php';

require_login();

$db = db();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);

$summary =
    llama_scout_summary(
        $db,
        $userId
    );

if (
    !$summary
    || empty($summary['active'])
) {
    header(
        'Location: /',
        true,
        303
    );
    exit;
}

$period =
    is_array(
        $summary['period']
        ?? null
    )
        ? $summary['period']
        : [];

$rank =
    (string) (
        $summary['rank']
        ?? 'Llama Scout'
    );

$isMasterScout =
    $rank === 'Master Scout';

$displayName =
    trim(
        (string) (
            $user['display_name']
            ?: $user['username']
            ?: $user['email']
            ?: 'Scout'
        )
    );

$requiredPlaces =
    (int) (
        $period['required_new_places']
        ?? 0
    );

$acceptedPlaces =
    (int) (
        $period['accepted_new_places']
        ?? 0
    );

$remainingPlaces =
    max(
        0,
        (int) (
            $period['remaining_new_places']
            ?? (
                $requiredPlaces
                - $acceptedPlaces
            )
        )
    );

$requirementMet =
    !empty(
        $period['requirement_met']
    );

$progressPercent =
    max(
        0,
        min(
            100,
            (float) (
                $period['progress_percent']
                ?? (
                    $requiredPlaces > 0
                        ? (
                            $acceptedPlaces
                            / $requiredPlaces
                        ) * 100
                        : 0
                )
            )
        )
    );

$lifetimePoints =
    (int) (
        $summary['lifetime_points']
        ?? 0
    );

$lifetimeNewPlaces =
    (int) (
        $summary['lifetime_new_places']
        ?? 0
    );

$scoutStartedAt =
    (string) (
        $summary['scout_started_at']
        ?? ''
    );

$activeThrough =
    (string) (
        $summary['active_through']
        ?? ''
    );

$periodStart =
    (string) (
        $period['start']
        ?? ''
    );

$periodEnd =
    (string) (
        $period['end']
        ?? ''
    );

$periodLabel =
    (string) (
        $period['label']
        ?? 'Current Scout period'
    );

$isReactivation =
    (string) (
        $period['type']
        ?? ''
    ) === 'reactivation';


function scout_basecamp_date(
    string $value
): string {
    $value = trim($value);

    if ($value === '') {
        return 'Not set';
    }

    $timestamp =
        strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return
        date(
            'M j, Y',
            $timestamp
        );
}


function scout_basecamp_contribution_label(
    string $type
): string {
    return match ($type) {
        'new_place' => 'New Place',
        'update' => 'Place Update',
        'correction' => 'Correction',
        'field_report' => 'Field Report',
        default =>
            ucwords(
                str_replace(
                    [
                        '_',
                        '-',
                    ],
                    ' ',
                    $type
                )
            ),
    };
}


function scout_basecamp_submission_label(
    string $status
): string {
    return match ($status) {
        'approved' => 'Approved',
        'pending' => 'Pending Review',
        'needs-changes' => 'Needs Changes',
        'rejected' => 'Not Approved',
        default =>
            ucwords(
                str_replace(
                    [
                        '_',
                        '-',
                    ],
                    ' ',
                    $status
                )
            ),
    };
}


/* =========================================================
   CONTRIBUTION COUNTS
   ========================================================= */

llama_ensure_place_contributions_table(
    $db
);

$stmt =
    $db->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(
                CASE
                    WHEN contribution_type = "update"
                    THEN 1
                    ELSE 0
                END
            ) AS updates,
            SUM(
                CASE
                    WHEN contribution_type = "correction"
                    THEN 1
                    ELSE 0
                END
            ) AS corrections
         FROM place_contributions
         WHERE user_id = ?
           AND status = "approved"'
    );

$stmt->execute([
    $userId,
]);

$contributionStats =
    $stmt->fetch(PDO::FETCH_ASSOC)
    ?: [];

$totalApproved =
    (int) (
        $contributionStats['total']
        ?? 0
    );

$totalUpdates =
    (int) (
        $contributionStats['updates']
        ?? 0
    );

$totalCorrections =
    (int) (
        $contributionStats['corrections']
        ?? 0
    );


/* =========================================================
   SUBMISSION COUNTS
   ========================================================= */

$stmt =
    $db->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(
                CASE
                    WHEN status = "pending"
                    THEN 1
                    ELSE 0
                END
            ) AS pending,
            SUM(
                CASE
                    WHEN status = "needs-changes"
                    THEN 1
                    ELSE 0
                END
            ) AS needs_changes
         FROM place_submissions
         WHERE user_id = ?'
    );

$stmt->execute([
    $userId,
]);

$submissionStats =
    $stmt->fetch(PDO::FETCH_ASSOC)
    ?: [];

$totalSubmissions =
    (int) (
        $submissionStats['total']
        ?? 0
    );

$pendingSubmissions =
    (int) (
        $submissionStats['pending']
        ?? 0
    );

$needsChanges =
    (int) (
        $submissionStats['needs_changes']
        ?? 0
    );


/* =========================================================
   RECENT CONTRIBUTIONS
   ========================================================= */

$stmt =
    $db->prepare(
        'SELECT
            pc.id,
            pc.place_id,
            pc.contribution_type,
            pc.points_awarded,
            pc.submitted_at,
            pc.approved_at,
            p.name AS place_name,
            p.slug AS place_slug
         FROM place_contributions pc
         LEFT JOIN places p
            ON p.id = pc.place_id
         WHERE pc.user_id = ?
           AND pc.status = "approved"
         ORDER BY
            COALESCE(
                pc.approved_at,
                pc.submitted_at,
                pc.created_at
            ) DESC,
            pc.id DESC
         LIMIT 8'
    );

$stmt->execute([
    $userId,
]);

$recentContributions =
    $stmt->fetchAll(PDO::FETCH_ASSOC)
    ?: [];


/* =========================================================
   RECENT SUBMISSIONS
   ========================================================= */

$stmt =
    $db->prepare(
        'SELECT
            id,
            place_name,
            status,
            submitted_at,
            reviewed_at
         FROM place_submissions
         WHERE user_id = ?
         ORDER BY
            submitted_at DESC,
            id DESC
         LIMIT 6'
    );

$stmt->execute([
    $userId,
]);

$recentSubmissions =
    $stmt->fetchAll(PDO::FETCH_ASSOC)
    ?: [];


/* =========================================================
   MASTER SCOUT PROGRESS
   ========================================================= */

$masterQualification =
    llama_master_scout_qualification(
        $db,
        $userId
    );

$masterRequirements =
    is_array(
        $masterQualification['requirements']
        ?? null
    )
        ? $masterQualification['requirements']
        : [];

$masterEligible =
    !empty(
        $masterQualification['eligible']
    );

$masterEnabled =
    !empty(
        $masterQualification['enabled']
    );


$pageTitle =
    'Scout Basecamp | Llama Scout';

require dirname(__DIR__) . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/account-scout-dashboard.css"
>

<section class="scout-basecamp-page">

<div class="scout-basecamp-shell">

<header class="scout-basecamp-hero">

    <div>
        <p class="eyebrow">Scout Basecamp</p>

        <h1>
            <?= htmlspecialchars(
                $displayName,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </h1>

        <p>
            Your Scout status, current field-work requirement,
            contribution history, and Master Scout progress.
        </p>
    </div>

    <div class="scout-basecamp-rank">
        <i
            class="fa-solid <?= $isMasterScout
                ? 'fa-compass'
                : 'fa-binoculars' ?>"
            aria-hidden="true"
        ></i>

        <span>
            <?= htmlspecialchars(
                $rank,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </span>

        <small>
            Active through
            <?= htmlspecialchars(
                scout_basecamp_date(
                    $activeThrough
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </small>
    </div>

</header>


<?php if ($isReactivation): ?>

<div class="scout-basecamp-notice is-attention">
    <i
        class="fa-solid fa-triangle-exclamation"
        aria-hidden="true"
    ></i>

    <div>
        <strong>
            Reactivation period
        </strong>

        <span>
            Complete the required approved new Places before this
            reactivation window closes to restore normal Scout status.
        </span>
    </div>
</div>

<?php endif; ?>


<section class="scout-basecamp-stat-grid">

    <div>
        <span>Scout Since</span>
        <strong>
            <?= htmlspecialchars(
                scout_basecamp_date(
                    $scoutStartedAt
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </div>

    <div>
        <span>Lifetime Points</span>
        <strong>
            <?= number_format(
                $lifetimePoints
            ) ?>
        </strong>
    </div>

    <div>
        <span>New Places</span>
        <strong>
            <?= number_format(
                $lifetimeNewPlaces
            ) ?>
        </strong>
    </div>

    <div>
        <span>Approved Contributions</span>
        <strong>
            <?= number_format(
                $totalApproved
            ) ?>
        </strong>
    </div>

    <div>
        <span>Pending Reviews</span>
        <strong>
            <?= number_format(
                $pendingSubmissions
            ) ?>
        </strong>
    </div>

</section>


<section class="scout-basecamp-panel scout-basecamp-period">

<header>
    <div>
        <p class="eyebrow">
            <?= htmlspecialchars(
                $periodLabel,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

        <h2>
            <?= $isReactivation
                ? 'Complete Your Reactivation'
                : 'Maintain Llama Scout Status' ?>
        </h2>
    </div>

    <span>
        <?= htmlspecialchars(
            scout_basecamp_date(
                $periodStart
            ),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
        to
        <?= htmlspecialchars(
            scout_basecamp_date(
                $periodEnd
            ),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </span>
</header>

<div class="scout-basecamp-period-copy">
    <strong>
        <?= number_format(
            $acceptedPlaces
        ) ?>
        of
        <?= number_format(
            $requiredPlaces
        ) ?>
        approved new Places
    </strong>

    <span>
        <?php if ($requirementMet): ?>
            Current Scout-period requirement complete.
        <?php elseif ($remainingPlaces === 1): ?>
            One more approved new Place completes this Scout period.
        <?php else: ?>
            <?= number_format(
                $remainingPlaces
            ) ?>
            approved new Places remain.
        <?php endif; ?>
    </span>
</div>

<div
    class="scout-basecamp-progress"
    aria-label="Scout period progress"
>
    <span
        style="width: <?= htmlspecialchars(
            number_format(
                $progressPercent,
                2,
                '.',
                ''
            ),
            ENT_QUOTES,
            'UTF-8'
        ) ?>%;"
    ></span>
</div>

<div class="scout-basecamp-period-actions">
    <a
        class="scout-basecamp-button"
        href="/add-place.php"
    >
        <i
            class="fa-solid fa-location-dot"
            aria-hidden="true"
        ></i>
        Add a new Place
    </a>

    <a
        class="scout-basecamp-button is-secondary"
        href="https://llamascout.com/"
    >
        Browse Places
    </a>
</div>

</section>


<div class="scout-basecamp-main-grid">

<div class="scout-basecamp-main-column">

<section class="scout-basecamp-panel">

<header>
    <div>
        <p class="eyebrow">Field Work</p>
        <h2>Recent Approved Contributions</h2>
    </div>
</header>

<?php if (!$recentContributions): ?>

    <div class="scout-basecamp-empty">
        No approved contributions yet.
    </div>

<?php else: ?>

<div class="scout-basecamp-list">

<?php foreach ($recentContributions as $contribution): ?>

    <article>
        <div>
            <span>
                <?= htmlspecialchars(
                    scout_basecamp_contribution_label(
                        (string)
                        $contribution['contribution_type']
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>

            <strong>
                <?= htmlspecialchars(
                    (string) (
                        $contribution['place_name']
                        ?: 'Place contribution'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            <small>
                <?= htmlspecialchars(
                    scout_basecamp_date(
                        (string) (
                            $contribution['approved_at']
                            ?: $contribution['submitted_at']
                        )
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </small>
        </div>

        <div class="scout-basecamp-list-points">
            +<?= number_format(
                (int) (
                    $contribution['points_awarded']
                    ?? 0
                )
            ) ?>
        </div>

        <?php if (!empty($contribution['place_slug'])): ?>
            <a
                href="https://llamascout.com/place.php?slug=<?= rawurlencode(
                    (string)
                    $contribution['place_slug']
                ) ?>"
            >
                View
            </a>
        <?php endif; ?>
    </article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>


<section class="scout-basecamp-panel">

<header>
    <div>
        <p class="eyebrow">Review Queue</p>
        <h2>Your Recent Submissions</h2>
    </div>

    <span>
        <?= number_format(
            $totalSubmissions
        ) ?>
        total
    </span>
</header>

<?php if (!$recentSubmissions): ?>

    <div class="scout-basecamp-empty">
        No Place submissions yet.
    </div>

<?php else: ?>

<div class="scout-basecamp-list">

<?php foreach ($recentSubmissions as $submission): ?>

    <article>
        <div>
            <span>
                <?= htmlspecialchars(
                    scout_basecamp_submission_label(
                        (string)
                        $submission['status']
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>

            <strong>
                <?= htmlspecialchars(
                    (string) (
                        $submission['place_name']
                        ?: 'Place submission'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            <small>
                Submitted
                <?= htmlspecialchars(
                    scout_basecamp_date(
                        (string)
                        $submission['submitted_at']
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </small>
        </div>

        <?php if (
            (string) $submission['status']
            === 'needs-changes'
        ): ?>
            <strong class="scout-basecamp-attention">
                Needs changes
            </strong>
        <?php endif; ?>
    </article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

</div>


<aside class="scout-basecamp-side-column">


<section class="scout-basecamp-panel">

<header>
    <div>
        <p class="eyebrow">Contribution Mix</p>
        <h2>Lifetime Field Work</h2>
    </div>
</header>

<dl class="scout-basecamp-definition-list">
    <div>
        <dt>New Places</dt>
        <dd><?= number_format($lifetimeNewPlaces) ?></dd>
    </div>

    <div>
        <dt>Updates</dt>
        <dd><?= number_format($totalUpdates) ?></dd>
    </div>

    <div>
        <dt>Corrections</dt>
        <dd><?= number_format($totalCorrections) ?></dd>
    </div>

    <div>
        <dt>Needs changes</dt>
        <dd><?= number_format($needsChanges) ?></dd>
    </div>
</dl>

</section>


<?php if (!$isMasterScout): ?>

<section class="scout-basecamp-panel">

<header>
    <div>
        <p class="eyebrow">Rank Progress</p>
        <h2>Master Scout</h2>
    </div>

    <?php if ($masterEligible): ?>
        <span class="is-good">
            Ready
        </span>
    <?php endif; ?>
</header>

<?php if (!$masterEnabled): ?>

    <p class="scout-basecamp-muted">
        Master Scout qualification is not currently enabled.
    </p>

<?php elseif (!$masterRequirements): ?>

    <p class="scout-basecamp-muted">
        Master Scout requirements have not been configured.
    </p>

<?php else: ?>

<div class="scout-basecamp-requirements">

<?php foreach ($masterRequirements as $requirement): ?>

    <div class="<?= !empty($requirement['met'])
        ? 'is-met'
        : '' ?>"
    >
        <i
            class="fa-solid <?= !empty($requirement['met'])
                ? 'fa-circle-check'
                : 'fa-circle' ?>"
            aria-hidden="true"
        ></i>

        <span>
            <strong>
                <?= htmlspecialchars(
                    (string) (
                        $requirement['label']
                        ?? 'Requirement'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            <small>
                <?= number_format(
                    (int) (
                        $requirement['current']
                        ?? 0
                    )
                ) ?>
                /
                <?= number_format(
                    (int) (
                        $requirement['required']
                        ?? 0
                    )
                ) ?>
            </small>
        </span>
    </div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

<?php else: ?>

<section class="scout-basecamp-panel scout-basecamp-master-panel">
    <i
        class="fa-solid fa-compass"
        aria-hidden="true"
    ></i>

    <div>
        <p class="eyebrow">Current Rank</p>
        <h2>Master Scout</h2>
        <p>
            Master Scout recognizes sustained contribution across
            new Places, updates, corrections, and database stewardship.
        </p>
    </div>
</section>

<?php endif; ?>


<section class="scout-basecamp-panel">

<header>
    <div>
        <p class="eyebrow">Scout Access</p>
        <h2>Quick Links</h2>
    </div>
</header>

<nav class="scout-basecamp-links">
    <a href="/add-place.php">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        Add a Place
    </a>

    <a href="/">
        <i class="fa-solid fa-user" aria-hidden="true"></i>
        My account
    </a>

    <a href="/points.php">
        <i class="fa-solid fa-star" aria-hidden="true"></i>
        Points history
    </a>

    <a href="/badges.php">
        <i class="fa-solid fa-award" aria-hidden="true"></i>
        My badges
    </a>
</nav>

</section>


</aside>

</div>

</div>

</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
