<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/master-scout-moderation.php';

require_verified_email();

$db = db();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$moderator = llama_master_moderator_context($db, $userId);

if (!$moderator) {
    http_response_code(403);
    exit('Active Master Scout status is required to moderate contributions.');
}

$type = strtolower(
    trim(
        (string) (
            $_GET['type']
            ?? $_POST['type']
            ?? ''
        )
    )
);
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if (!in_array($type, ['new-place', 'update'], true) || $id < 1) {
    http_response_code(404);
    exit('Contribution not found.');
}

$item = $type === 'new-place'
    ? moderation_submission($db, $id)
    : moderation_update($db, $id);

if (!$item) {
    http_response_code(404);
    exit('Contribution not found.');
}

try {
    llama_master_moderation_assert_not_self(
        $userId,
        (int) ($item['user_id'] ?? 0)
    );
} catch (DomainException $exception) {
    http_response_code(403);
    exit($exception->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException(
                'Your session could not be verified. Reload the page and try again.'
            );
        }

        $action = (string) ($_POST['action'] ?? '');
        $notes = trim((string) ($_POST['review_notes'] ?? ''));

        $result = $type === 'new-place'
            ? llama_master_moderate_new_place(
                $db,
                $userId,
                $id,
                $action,
                $notes
            )
            : llama_master_moderate_place_update(
                $db,
                $userId,
                $id,
                $action,
                $notes
            );

        $notice = match ((string) ($result['action'] ?? '')) {
            'approve' => 'approved',
            'needs-changes' => 'changes',
            'rejected' => 'rejected',
            default => '',
        };

        header(
            'Location: /master-moderation.php'
            . ($notice !== '' ? '?notice=' . rawurlencode($notice) : ''),
            true,
            303
        );
        exit;
    } catch (Throwable $exception) {
        $reference = llama_log_caught_exception(
            $exception,
            'master_scout.moderation',
            [
                'moderator_user_id' => $userId,
                'type' => $type,
                'id' => $id,
                'action' => (string) ($_POST['action'] ?? ''),
            ],
            [
                InvalidArgumentException::class,
                RuntimeException::class,
                DomainException::class,
            ]
        );

        $error = $reference === null
            ? $exception->getMessage()
            : llama_error_message_with_reference(
                'The moderation action could not be completed.',
                $reference
            );

        $item = $type === 'new-place'
            ? moderation_submission($db, $id)
            : moderation_update($db, $id);
    }
}

$csrfToken = moderation_csrf_token();
$contributorName = trim(
    (string) (
        ($item['display_name'] ?? '')
        ?: ($item['username'] ?? '')
        ?: 'Contributor'
    )
);
$contributorLevel = llama_contribution_level_from_role(
    (string) ($item['role_at_submission'] ?? 'user')
);

$pageTitle = ($type === 'new-place' ? 'Review New Place' : 'Review Place Update') . ' | Llama Scout';
require dirname(__DIR__) . '/partials/header.php';
?>

<link rel="stylesheet" href="https://llamascout.com/css/account/features/master-moderation.css">
<link rel="stylesheet" href="https://llamascout.com/css/scout-report-cards.css">
<link rel="stylesheet" href="https://llamascout.com/css/site/features/place-report-form.css">

<section class="master-moderation-page">
<div class="master-moderation-shell">

<header class="master-moderation-review-header">
    <div>
        <p class="eyebrow">Master Scout Moderation</p>
        <h1><?= $type === 'new-place' ? 'Review New Place' : 'Review Place Update' ?></h1>
        <p>
            Submitted by
            <strong><?= htmlspecialchars($contributorName, ENT_QUOTES, 'UTF-8') ?></strong>
            · <?= htmlspecialchars(llama_contribution_level_short_label($contributorLevel), ENT_QUOTES, 'UTF-8') ?> contributor
        </p>
    </div>

    <a class="master-moderation-button is-secondary" href="/master-moderation.php">
        <?= llama_icon('arrow-left') ?>
        Moderation Queue
    </a>
</header>

<?php if ($error !== ''): ?>
    <div class="master-moderation-notice is-error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($type === 'new-place'): ?>
    <?php
    $data = is_array($item['data'] ?? null) ? $item['data'] : [];
    $photos = is_array($data['photos'] ?? null) ? $data['photos'] : [];
    $fields = llama_place_report_fields();
    $answered = 0;
    foreach (array_keys($fields) as $fieldKey) {
        if (llama_place_report_answer_state($data, $fieldKey) !== 'unanswered') {
            $answered++;
        }
    }
    $totalFields = count($fields);
    $unknownCount = count(llama_place_report_unknown_fields($data));
    $estimate = llama_points_estimate_new_place(
        $db,
        llama_place_report_scoring_input_from_data($data),
        count($photos)
    );
    $estimatedPoints = (int) ($estimate['estimated_points'] ?? 0);
    $placeReportData = $data;
    $placeReportReadMode = 'moderation';
    ?>

    <section class="master-moderation-summary">
        <div>
            <span>Place</span>
            <strong><?= htmlspecialchars((string) ($item['place_name'] ?? $data['name'] ?? 'Unnamed Place'), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <div>
            <span>Questions answered</span>
            <strong><?= number_format($answered) ?> / <?= number_format($totalFields) ?></strong>
        </div>
        <div>
            <span>Explicit Unknown</span>
            <strong><?= number_format($unknownCount) ?></strong>
        </div>
        <div>
            <span>Photos</span>
            <strong><?= number_format(count($photos)) ?></strong>
        </div>
        <div>
            <span>Estimated points</span>
            <strong><?= number_format($estimatedPoints) ?></strong>
        </div>
    </section>

    <section class="scout-report master-moderation-report">
        <header class="scout-report-header">
            <p class="eyebrow">Submitted Place Report</p>
            <h2>Questions and answers</h2>
        </header>
        <?php require dirname(__DIR__) . '/partials/place-report/read-only.php'; ?>
    </section>

    <?php if ($photos): ?>
        <section class="master-moderation-panel">
            <header>
                <div>
                    <p class="eyebrow">Evidence</p>
                    <h2>Submitted Photos</h2>
                </div>
                <span><?= number_format(count($photos)) ?></span>
            </header>
            <div class="master-moderation-photo-grid">
                <?php foreach ($photos as $photo): ?>
                    <?php $photoUrl = llama_place_report_photo_url($photo); ?>
                    <?php if ($photoUrl !== ''): ?>
                        <img
                            src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars((string) (is_array($photo) ? ($photo['alt'] ?? '') : ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

<?php else: ?>
    <?php
    $proposed = is_array($item['proposed'] ?? null) ? $item['proposed'] : [];
    $original = is_array($item['original'] ?? null) ? $item['original'] : [];
    $photos = is_array($item['photo_list'] ?? null) ? $item['photo_list'] : [];
    $definitions = llama_place_update_definitions();
    $historyRow = llama_place_update_fetch_row($db, $id);
    $newUnknownLookup = array_fill_keys(
        $historyRow ? llama_place_update_latest_unknown_fields($historyRow) : [],
        true
    );
    $originalUnknownLookup = array_fill_keys(
        $historyRow ? llama_place_update_latest_original_unknown_fields($historyRow) : [],
        true
    );
    $estimate = llama_points_estimate_place_update($db, $proposed);
    $estimatedPoints = (int) ($estimate['estimated_points'] ?? 0);
    ?>

    <section class="master-moderation-summary">
        <div>
            <span>Place</span>
            <strong><?= htmlspecialchars((string) ($item['place_name'] ?? 'Place'), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <div>
            <span>Changed fields</span>
            <strong><?= number_format(count($proposed)) ?></strong>
        </div>
        <div>
            <span>Photos</span>
            <strong><?= number_format(count($photos)) ?></strong>
        </div>
        <div>
            <span>Estimated points</span>
            <strong><?= number_format($estimatedPoints) ?></strong>
        </div>
        <?php if (!empty($item['visited_at'])): ?>
            <div>
                <span>Visited</span>
                <strong><?= htmlspecialchars(llama_format_viewer_date((string) $item['visited_at'], 'M j, Y'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($item['contributor_notes'])): ?>
        <section class="master-moderation-note">
            <strong>Contributor notes</strong>
            <p><?= nl2br(htmlspecialchars((string) $item['contributor_notes'], ENT_QUOTES, 'UTF-8')) ?></p>
        </section>
    <?php endif; ?>

    <section class="master-moderation-panel">
        <header>
            <div>
                <p class="eyebrow">Submitted Update</p>
                <h2>What changed</h2>
            </div>
            <?php if (!empty($item['place_slug'])): ?>
                <a
                    class="master-moderation-button is-secondary"
                    href="https://llamascout.com/place.php?place=<?= rawurlencode((string) $item['place_slug']) ?>"
                    target="_blank"
                    rel="noopener"
                >
                    <?= llama_icon('external-link') ?>
                    Open live Place
                </a>
            <?php endif; ?>
        </header>

        <?php if (!$proposed): ?>
            <div class="master-moderation-empty is-compact">
                <p>No field changes are currently proposed. Review any submitted photos below.</p>
            </div>
        <?php else: ?>
            <div class="master-moderation-change-list">
                <?php foreach ($proposed as $path => $newValue): ?>
                    <?php
                    $definition = $definitions[$path] ?? [];
                    $fieldKey = (string) ($definition['key'] ?? '');
                    $label = (string) ($definition['label'] ?? ucwords(str_replace(['.', '_'], ' ', (string) $path)));
                    $oldValue = $original[$path] ?? null;
                    $oldText = llama_place_update_display_value(
                        (string) $path,
                        $oldValue,
                        $fieldKey !== '' && isset($originalUnknownLookup[$fieldKey])
                    );
                    $newText = llama_place_update_display_value(
                        (string) $path,
                        $newValue,
                        $fieldKey !== '' && isset($newUnknownLookup[$fieldKey])
                    );
                    ?>
                    <article>
                        <h3><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h3>
                        <div>
                            <span>
                                <small>Published</small>
                                <strong><?= htmlspecialchars($oldText, ENT_QUOTES, 'UTF-8') ?></strong>
                            </span>
                            <i aria-hidden="true"><?= llama_icon('arrow-right') ?></i>
                            <span>
                                <small>Proposed</small>
                                <strong><?= htmlspecialchars($newText, ENT_QUOTES, 'UTF-8') ?></strong>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($photos): ?>
        <section class="master-moderation-panel">
            <header>
                <div>
                    <p class="eyebrow">Evidence</p>
                    <h2>Submitted Photos</h2>
                </div>
                <span><?= number_format(count($photos)) ?></span>
            </header>
            <div class="master-moderation-photo-grid">
                <?php foreach ($photos as $photo): ?>
                    <?php $photoUrl = llama_place_report_photo_url($photo); ?>
                    <?php if ($photoUrl !== ''): ?>
                        <img
                            src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars((string) (is_array($photo) ? ($photo['alt'] ?? '') : ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<section class="master-moderation-decision">
    <header>
        <p class="eyebrow">Decision</p>
        <h2>Moderate this contribution</h2>
        <p>
            Approve only when the submitted information is suitable to publish.
            Add clear notes whenever the contributor needs to make changes or the contribution is not approved.
        </p>
    </header>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id" value="<?= $id ?>">

        <label>
            <span>Review notes</span>
            <textarea
                name="review_notes"
                rows="5"
                placeholder="Optional for approval. Required when requesting changes or marking not approved."
            ><?= htmlspecialchars((string) ($_POST['review_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </label>

        <div class="master-moderation-actions">
            <button class="master-moderation-button" type="submit" name="action" value="approve">
                <?= llama_icon('circle-check') ?>
                Approve
            </button>
            <button class="master-moderation-button is-attention" type="submit" name="action" value="needs-changes">
                <?= llama_icon('edit') ?>
                Request Changes
            </button>
            <button class="master-moderation-button is-danger" type="submit" name="action" value="rejected">
                <?= llama_icon('xbox-x') ?>
                Not Approved
            </button>
        </div>
    </form>
</section>

</div>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
