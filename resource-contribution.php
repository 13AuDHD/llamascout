<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/resource-submissions.php';

require_verified_email();
$userId = (int) (current_user()['id'] ?? 0);
$db = db();
if (!llama_contributor_can($db, $userId, 'submit_place')) {
    http_response_code(403);
    exit('Your account cannot submit Resources.');
}

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$contributionReturnPlace = (string) ($_POST['return_place'] ?? $_GET['return_place'] ?? '');
$contributionReturnDraft = (int) ($_POST['return_draft'] ?? $_GET['return_draft'] ?? 0);
$back = $contributionReturnPlace !== ''
    ? 'https://account.llamascout.com/update-place.php?slug=' . rawurlencode($contributionReturnPlace) . '&mode=resource'
    : '/add-place.php?mode=resource' . ($contributionReturnDraft > 0 ? '&draft=' . $contributionReturnDraft : '');
$contributionResourceId = max(0, (int) ($_POST['resource_record_id'] ?? 0));
$contributionResource = $contributionResourceId > 0 && admin_resources_tables_ready($db)
    ? llama_resource_active_record($db, $contributionResourceId)
    : [];
$contributionResourceSnapshot = (string) ($_POST['resource_snapshot'] ?? '');
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if (!community_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
            throw new InvalidArgumentException('Your session expired. Reload the page and try again.');
        }
        $contributionResourceInput = llama_resource_submission_input($_POST);
        llama_resource_submit(
            $db, $userId, $contributionResourceInput, $contributionResourceId, $contributionResourceSnapshot
        );
        header('Location: /resource-contribution.php?' . http_build_query([
            'submitted' => '1',
            'return_place' => $contributionReturnPlace,
            'return_draft' => $contributionReturnDraft,
        ]), true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        llama_log_caught_exception($exception, 'resource.contribution');
        $error = 'The Resource could not be submitted. Try again later.';
    }
}

$pageTitle = 'Resource contribution | Llama Scout';
$pageStyles = ['site/pages/add-place.css', 'site/features/place-report-form.css', 'site/features/resource-form.css'];
require __DIR__ . '/partials/header.php';
?>
<section class="contribution-page">
    <header class="contribution-header">
        <h1>Resource contribution</h1>
        <a href="<?= $e($back) ?>">Back to the Resource tab</a>
    </header>
    <?php if (($_GET['submitted'] ?? '') === '1' && $error === ''): ?>
        <p class="contribution-message">Resource submitted for review.</p>
    <?php else: ?>
        <?php if ($error !== ''): ?><p class="contribution-message is-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
        <?php $standaloneResourceForm = true; require __DIR__ . '/partials/resources/contribution.php'; ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
