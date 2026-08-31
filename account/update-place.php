<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_verified_email();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$slug = trim((string) ($_GET['slug'] ?? $_POST['slug'] ?? ''));
$place = $slug !== '' ? community_find_place_for_update($slug) : null;

if (!$place) {
    http_response_code(404);
    $pageTitle = 'Place not found | Llama Scout';
    require dirname(__DIR__) . '/partials/header.php';
    echo '<section class="contribution-page"><h1>Place not found</h1><p>This place is not available for updates.</p></section>';
    require dirname(__DIR__) . '/partials/footer.php';
    exit;
}

$error = null;
$openUpdate = community_open_update_for_user($userId, (int) $place['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$openUpdate) {
    if (!community_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            submit_place_update($userId, $place, $_POST);
            header('Location: /contributions.php?submitted=update', true, 303);
            exit;
        } catch (Throwable $e) {
            $error = ($e instanceof InvalidArgumentException || $e instanceof RuntimeException)
                ? $e->getMessage()
                : 'The update could not be submitted. Please try again.';
        }
    }
}

$fields = community_editable_place_fields($place);
$pageTitle = 'Suggest an Update | Llama Scout';
require dirname(__DIR__) . '/partials/header.php';
?>

<section class="contribution-page">
    <header class="contribution-header">
        <p class="eyebrow">Community contribution</p>
        <h1>Suggest an update</h1>
        <p><?= htmlspecialchars((string) $place['name'], ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <?php if ($openUpdate): ?>
        <div class="contribution-message">
            <i class="fa-solid fa-clock" aria-hidden="true"></i>
            You already have an open update for this place. You can track it from My contributions.
        </div>
        <p><a class="contribution-submit" href="/contributions.php">View my contributions</a></p>
    <?php else: ?>
        <?php if ($error): ?><div class="contribution-message is-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <form method="post" class="contribution-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(community_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="slug" value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">

            <fieldset>
                <legend>Change only what needs changing</legend>
                <div class="contribution-grid">
                    <?php foreach ($fields as $key => $meta): ?>
                        <?php $isLong = in_array($key, ['description', 'access_summary', 'sensory_summary'], true); ?>
                        <label class="contribution-field<?= $isLong ? ' contribution-field-wide' : '' ?>">
                            <span><?= htmlspecialchars((string) $meta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($isLong): ?>
                                <textarea name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" rows="4"><?= htmlspecialchars((string) ($_POST[$key] ?? $meta['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            <?php else: ?>
                                <input name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) ($_POST[$key] ?? $meta['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>Your visit</legend>
                <div class="contribution-grid">
                    <label class="contribution-field"><span>Date visited</span><input type="date" name="visited_at" value="<?= htmlspecialchars((string) ($_POST['visited_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                    <label class="contribution-field contribution-field-wide"><span>Notes for the reviewer</span><textarea name="contributor_notes" rows="3"><?= htmlspecialchars((string) ($_POST['contributor_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></label>
                </div>
            </fieldset>

            <div class="contribution-actions">
                <button class="contribution-submit" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Submit update</button>
                <a href="https://llamascout.com/place.php?slug=<?= rawurlencode($slug) ?>">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
