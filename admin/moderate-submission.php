<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$adminUser = moderation_require_admin();
$db = db();
$csrfToken = moderation_csrf_token();
require_once __DIR__ . '/_dashboard.php';

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Review New Place';
$adminPageEyebrow = 'Moderation';
$adminActiveNav = 'submissions';

require __DIR__ . '/_header.php';

$submissionId =
    (int) (
        $_GET['id']
        ?? $_POST['id']
        ?? 0
    );

$item =
    moderation_submission(
        $db,
        $submissionId
    );

$error = '';
$notice = '';

if (!$item) {
    http_response_code(404);

    echo '<div class="admin-moderation-notice">Submission not found.</div>';

    require __DIR__ . '/_footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            !moderation_verify_csrf(
                (string) ($_POST['csrf_token'] ?? '')
            )
        ) {
            throw new RuntimeException(
                'Your session could not be verified. Reload the page and try again.'
            );
        }

        $action =
            (string) ($_POST['action'] ?? '');

        $notes =
            trim(
                (string) (
                    $_POST['review_notes']
                    ?? ''
                )
            );

        $points =
            max(
                0,
                (int) (
                    $_POST['points']
                    ?? 0
                )
            );

        $db->beginTransaction();

        if ($action === 'approve') {
            $status =
                (string) (
                    $_POST['publish_status']
                    ?? 'active'
                );

            $placeId =
                moderation_approve_new_place(
                    $db,
                    $submissionId,
                    (int) $adminUser['id'],
                    $status,
                    $notes,
                    $points
                );

            $db->commit();

            header(
                'Location: /submissions.php?approved=' .
                $placeId
            );

            exit;
        }

        if ($action === 'rejected') {
            if ($notes === '') {
                throw new InvalidArgumentException(
                    'Add review notes explaining why the submission was not approved.'
                );
            }

            moderation_set_submission_status(
                $db,
                $submissionId,
                (int) $adminUser['id'],
                $action,
                $notes
            );

            $db->commit();

            header(
                'Location: /submissions.php?updated=1'
            );

            exit;
        }

        throw new InvalidArgumentException(
            'Choose a moderation action.'
        );
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $error =
            $exception->getMessage();
    }

    $item =
        moderation_submission(
            $db,
            $submissionId
        );
}

$data = $item['data'];

$photos =
    is_array($data['photos'] ?? null)
        ? $data['photos']
        : [];

function submission_review_label(
    string $key
): string {
    $special = [
        't_mobile' => 'T-Mobile',
        'att' => 'AT&T',
        'rv_suitable' => 'RV suitable',
        'four_wheel_drive_recommended' => '4WD recommended',
        'starlink' => 'Starlink',
        'starlink_tested' => 'Starlink tested',
        'public_data_verified' => 'Public data verified',
    ];

    if (isset($special[$key])) {
        return $special[$key];
    }

    return ucwords(
        str_replace(
            '_',
            ' ',
            $key
        )
    );
}

function submission_review_value(
    mixed $value,
    string $key = ''
): string {
    if (is_bool($value)) {
        return $value
            ? 'Yes'
            : 'No';
    }

    if ($value === null || $value === '') {
        return 'Unknown';
    }

    if (
        is_int($value)
        && $value >= 1
        && $value <= 5
        && preg_match(
            '/(risk|noise|traffic|crowds|privacy|comfort|difficulty|stress|rocks|washboards|potholes|grades|exposure|cover|shade|sky|view|scenery|stargazing|work|predictability|activity|odors)/',
            $key
        )
    ) {
        return $value . '/5';
    }

    return (string) $value;
}

function submission_review_section(
    string $title,
    array $values
): void {
    $hasValues = false;

    foreach ($values as $value) {
        if (
            $value !== null
            && $value !== ''
        ) {
            $hasValues = true;
            break;
        }
    }

    if (!$hasValues) {
        return;
    }
    ?>
    <section class="admin-moderation-detail">
        <h2><?= moderation_e($title) ?></h2>

        <div class="admin-moderation-grid">
            <?php foreach ($values as $key => $value): ?>
                <?php if ($value === null || $value === '') continue; ?>

                <div class="admin-moderation-field">
                    <span>
                        <?= moderation_e(
                            submission_review_label(
                                (string) $key
                            )
                        ) ?>
                    </span>

                    <?= nl2br(
                        moderation_e(
                            submission_review_value(
                                $value,
                                (string) $key
                            )
                        )
                    ) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

$core = [];

foreach (
    [
        'name',
        'type',
        'description',
        'latitude',
        'longitude',
        'elevation_feet',
        'road',
        'city',
        'county',
        'state',
        'region',
        'land_manager',
        'land_type',
        'access_summary',
        'sensory_summary',
        'visited_at',
        'contributor_notes',
    ] as $key
) {
    if (array_key_exists($key, $data)) {
        $core[$key] = $data[$key];
    }
}
?>

<?php if ($error !== ''): ?>
    <div class="admin-moderation-notice">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>


<div class="admin-moderation-detail">
    <h2><?= moderation_e($item['place_name']) ?></h2>

    <p>
        Submitted by
        <strong>
            <?= moderation_e(
                $item['display_name']
                ?: $item['username']
            ) ?>
        </strong>
        on
        <?= moderation_e($item['submitted_at']) ?>.
    </p>
</div>


<?php submission_review_section('Location + Core Details', $core); ?>

<?php
submission_review_section(
    'Site, Vehicle, Road, Accessibility + Safety',
    is_array($data['details'] ?? null)
        ? $data['details']
        : []
);
?>

<?php
submission_review_section(
    'Amenities',
    is_array($data['amenities'] ?? null)
        ? $data['amenities']
        : []
);
?>

<?php
submission_review_section(
    'Connectivity',
    is_array($data['connectivity'] ?? null)
        ? $data['connectivity']
        : []
);
?>

<?php
$sensory =
    is_array($data['sensory'] ?? null)
        ? $data['sensory']
        : [];

submission_review_section(
    'Sensory - Daytime',
    is_array($sensory['daytime'] ?? null)
        ? $sensory['daytime']
        : []
);

submission_review_section(
    'Sensory - Nighttime',
    is_array($sensory['nighttime'] ?? null)
        ? $sensory['nighttime']
        : []
);

submission_review_section(
    'Sensory - Specific Conditions',
    is_array($sensory['details'] ?? null)
        ? $sensory['details']
        : []
);
?>

<?php
submission_review_section(
    'Rules, Seasons + Nearby Services',
    is_array($data['rules'] ?? null)
        ? $data['rules']
        : []
);
?>

<?php
submission_review_section(
    'Experience + Recommendations',
    is_array($data['experience'] ?? null)
        ? $data['experience']
        : []
);
?>


<?php if ($photos): ?>
    <div class="admin-moderation-detail">
        <h2>Submitted Photos</h2>

        <div class="admin-moderation-photo-grid">
            <?php foreach ($photos as $photo): ?>
                <?php $src = moderation_photo_path($photo); ?>

                <?php if ($src !== ''): ?>
                    <img
                        src="https://llamascout.com<?= moderation_e($src) ?>"
                        alt="<?= moderation_e($photo['alt'] ?? '') ?>"
                    >
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>


<div class="admin-moderation-detail">
    <h2>Decision</h2>

    <form
        method="post"
        class="admin-moderation-form"
    >
        <input
            type="hidden"
            name="id"
            value="<?= $submissionId ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e($csrfToken) ?>"
        >

        <label>
            Publish status

            <select name="publish_status">
                <option value="active">
                    Active
                </option>

                <option value="featured">
                    Featured
                </option>
            </select>
        </label>

        <label>
            Contribution points

            <input
                type="number"
                name="points"
                min="0"
                step="1"
                value="0"
            >
        </label>

        <label>
            Review notes

            <textarea
                name="review_notes"
                rows="5"
                placeholder="Required when not approving. Also useful for documenting anything you corrected or verified."
            ></textarea>
        </label>

        <div class="admin-moderation-actions">
            <button
                class="admin-moderation-button is-primary"
                type="submit"
                name="action"
                value="approve"
            >
                Approve and Publish
            </button>

            <button
                class="admin-moderation-button is-danger"
                type="submit"
                name="action"
                value="rejected"
            >
                Not Approved
            </button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
