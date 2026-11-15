<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/area-submissions.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$actorId = (int) ($adminUser['id'] ?? 0);
$error = '';
$ready = llama_area_submissions_ready($db);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Session expired. Reload before reviewing the Area.';
    } elseif (!$ready) {
        $error = 'Install the unmapped Areas database migration first.';
    } else {
        try {
            $id = (int) ($_POST['submission_id'] ?? 0);
            $decision = (string) ($_POST['area_decision'] ?? '');
            $notes = (string) ($_POST['review_notes'] ?? '');
            if ($decision === 'rejected' && trim($notes) === '') {
                throw new InvalidArgumentException('Explain why the Area was rejected.');
            }
            $featureId = llama_area_submission_moderate($db, $id, $actorId, $decision, $notes);
            header('Location: /area-submissions.php?reviewed=' . rawurlencode($decision)
                . ($featureId ? '&area=' . $featureId : ''), true, 303);
            exit;
        } catch (Throwable $exception) {
            $error = $exception instanceof InvalidArgumentException
                ? $exception->getMessage() : 'The Area review could not be saved.';
            if (!$exception instanceof InvalidArgumentException) {
                llama_log_caught_exception($exception, 'admin.area.review', ['actor_id'=>$actorId]);
            }
        }
    }
}
$items = $ready ? llama_area_submission_queue($db, 100) : [];
$adminPageTitle = 'Area Submissions';
$adminPageEyebrow = 'Moderation';
$adminActiveNav = 'moderation';
require __DIR__ . '/_header.php';
$escape = static fn(mixed $value): string => moderation_e((string) $value);
?>
<section class="admin-page-heading">
    <div><p class="eyebrow">Area reviews</p><h1>New Areas</h1>
    <p>Approve an Area’s name, type and Scout Report without creating map geometry.
    Its purple map location can be added later.</p></div>
</section>
<?php if (!$ready): ?>
    <section class="admin-panel" role="alert">
        <p>Run <code>tools/migrations/20261114_unmapped_areas.sql</code> in phpMyAdmin
        before reviewing new Areas.</p>
    </section>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="admin-notice is-error" role="alert"><?= $escape($error) ?></div>
<?php endif; ?>
<?php if (isset($_GET['reviewed'])): ?>
    <div class="admin-notice" role="status">
        Area review saved. <?= isset($_GET['area']) ? 'Published Area #' . (int) $_GET['area'] : '' ?>
    </div>
<?php endif; ?>
<?php if (!$items): ?>
    <section class="admin-panel"><p>No Area submissions are awaiting review.</p></section>
<?php else: ?>
    <?php foreach ($items as $item): ?>
        <?php
        $answers = json_decode((string) ($item['answers_json'] ?? '{}'), true);
        $answers = is_array($answers) ? $answers : [];
        $target = [
            'scope' => 'area', 'source' => 'map_feature',
            'feature_type' => $item['feature_type'],
            'facility_kind' => $item['facility_kind'],
        ];
        $fields = llama_report_questions_for_area_target($target);
        ?>
        <article class="admin-panel" id="submission-<?= (int) $item['id'] ?>">
            <header class="admin-panel-header">
                <div><p>Area submission #<?= (int) $item['id'] ?>
                    · <?= $escape($item['contributor_name'] ?? 'Member') ?></p>
                    <h2><?= $escape($item['area_name']) ?></h2>
                    <p>Within <?= $escape($item['place_name']) ?> (Place #<?= (int) $item['place_id'] ?>)
                        · <?= $escape(str_replace('_',' ',(string) $item['feature_type'])) ?>
                        <?php if (!empty($item['facility_kind'])): ?>
                            · <?= $escape($item['facility_kind']) ?>
                        <?php endif; ?>
                    </p>
                    <p>No map marker or boundary requested.</p>
                </div>
            </header>
            <details>
                <summary>Review <?= count($answers) ?> recorded Area answers</summary>
                <dl>
                    <?php foreach ($answers as $key => $value): ?>
                        <?php if (!isset($fields[$key])) continue; ?>
                        <dt><strong><?= $escape($fields[$key]['label']) ?></strong></dt>
                        <dd><?= $escape($value === llama_place_report_unknown_token()
                            ? '? Unknown'
                            : (is_array($value) ? implode(', ', array_map('strval', $value)) : $value)) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </details>
            <form method="post" action="/area-submissions.php#submission-<?= (int) $item['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape(moderation_csrf_token()) ?>">
                <input type="hidden" name="submission_id" value="<?= (int) $item['id'] ?>">
                <label>
                    Review notes (required to reject)
                    <textarea name="review_notes" rows="3" maxlength="4000"></textarea>
                </label>
                <div class="admin-actions">
                    <button class="admin-button" type="submit"
                        name="area_decision" value="approved">Approve &amp; publish Area</button>
                    <button class="admin-button is-muted" type="submit"
                        name="area_decision" value="rejected">Reject Area</button>
                </div>
            </form>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
