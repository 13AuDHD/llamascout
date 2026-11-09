<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/resource-submissions.php';

require_verified_email();
$userId = (int) (current_user()['id'] ?? 0);
$db = db();
$posted = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$contributionRevisionId = max(0, (int) ($_POST['resource_revision_id'] ?? $_GET['revision'] ?? 0));
$contributionResourceId = max(0, (int) ($_POST['resource_record_id'] ?? $_GET['resource_id'] ?? 0));
$revision = null;
if ($contributionRevisionId > 0) {
    if (!llama_resource_review_notes_ready($db)) {
        http_response_code(404);
        exit('Resource revision unavailable.');
    }
    $statement = $db->prepare(
        "SELECT * FROM resource_submissions WHERE id = ? AND submitted_by = ? AND status = 'needs-changes'"
    );
    $statement->execute([$contributionRevisionId, $userId]);
    $revision = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$revision) {
        http_response_code(404);
        exit('Resource revision unavailable.');
    }
    $contributionResourceId = (int) $revision['target_resource_id'];
}

$contributionReturnPlace = (string) ($_POST['return_place'] ?? '');
$contributionReturnDraft = (int) ($_POST['return_draft'] ?? 0);
$isUpdate = $contributionResourceId > 0;
if (!llama_contributor_can($db, $userId, $isUpdate ? 'submit_update' : 'submit_place')) {
    http_response_code(403);
    exit('Your account cannot submit this Resource contribution.');
}
if (!$isUpdate && $contributionReturnPlace !== '') {
    http_response_code(400);
    exit('Use Add a Place to submit a new Resource.');
}
if (!$posted && !$isUpdate && !$revision) {
    header('Location: /add-place.php?mode=resource', true, 303);
    exit;
}

$contributionResource = $isUpdate && admin_resources_tables_ready($db)
    ? llama_resource_active_record($db, $contributionResourceId)
    : [];
if ($isUpdate && !$contributionResource) {
    http_response_code(404);
    exit('Resource not found.');
}
$contributionResourceSnapshot = (string) (
    $revision['base_snapshot'] ?? $_POST['resource_snapshot']
    ?? ($contributionResource ? llama_resource_snapshot($contributionResource) : '')
);
if ($revision) {
    $contributionResourceInput = json_decode($revision['form_data'], true, 512, JSON_THROW_ON_ERROR);
}
$error = '';

if ($posted) {
    try {
        if (!community_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
            throw new InvalidArgumentException('Your session expired. Reload the page and try again.');
        }
        $contributionResourceInput = llama_resource_submission_input($_POST);
        llama_resource_submit(
            $db, $userId, $contributionResourceInput, $contributionResourceId, $contributionResourceSnapshot, $contributionRevisionId
        );
        $kind = $revision ? 'resource-resubmitted' : ($isUpdate ? 'resource-update' : 'resource');
        header('Location: https://account.llamascout.com/contributions.php?submitted=' . $kind, true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        llama_log_caught_exception($exception, 'resource.contribution');
        $error = 'The Resource could not be submitted. Try again later.';
    }
}

$back = $revision
    ? 'https://account.llamascout.com/contributions.php'
    : ($isUpdate
        ? '/resource.php?slug=' . rawurlencode((string) $contributionResource['slug'])
        : '/add-place.php?mode=resource' . ($contributionReturnDraft > 0 ? '&draft=' . $contributionReturnDraft : ''));
$pageTitle = $revision ? 'Revise Resource Submission' : ($isUpdate ? 'Update Resource' : 'Add a Resource');
$pageStyles = ['site/pages/add-place.css', 'site/features/place-report-form.css', 'site/features/resource-form.css'];
$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/partials/header.php';
?>
<section class="contribution-page">
    <header class="contribution-header">
        <h1><?= $e($pageTitle) ?></h1>
        <a href="<?= $e($back) ?>"><?= $revision ? 'My contributions' : ($isUpdate ? 'Back to Resource' : 'Back to Add a Place') ?></a>
    </header>
    <?php if ($revision && !empty($revision['review_notes'])): ?>
        <p class="contribution-message"><?= nl2br($e($revision['review_notes'])) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?><p class="contribution-message is-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
    <?php $standaloneResourceForm = true; require __DIR__ . '/partials/resources/contribution.php'; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
