<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/usfs-enrichment.php';
require_once __DIR__ . '/_dashboard.php';

$admin = moderation_require_admin();
$error = '';
$saved = null;
$placeId = max(0, (int) ($_POST['place_id'] ?? $_GET['place_id'] ?? 0));
$siteId = max(0, (int) ($_POST['site_id'] ?? $_GET['site_id'] ?? 0));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Session token expired. Reload and try again.';
    } else {
        try {
            $saved = llama_usfs_enrichment_save(db(), $placeId, $siteId, (int) $admin['id']);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$adminPageTitle = 'USFS Enrichment';
$adminPageEyebrow = 'Reference Data';
$adminActiveNav = 'reference-data';
$adminPageStyles = [];
$adminFeatureStyles = [];
require __DIR__ . '/_header.php';
?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <h2>Link a USFS campground</h2>
            <p>Review the Place and its USFS site ID before saving. This stores source data only.</p>
        </div>
    </header>
    <?php if ($error !== ''): ?>
        <p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($saved !== null): ?>
        <p role="status">
            Saved USFS reference for <?= htmlspecialchars($saved['place_name'], ENT_QUOTES, 'UTF-8') ?>.
            Fee candidate: <?= htmlspecialchars($saved['candidate']['status'], ENT_QUOTES, 'UTF-8') ?>.
            No public status or marker was changed.
        </p>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(moderation_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <label for="place-id">Llama Scout Place ID</label>
        <input id="place-id" type="number" name="place_id" min="1" required value="<?= $placeId > 0 ? $placeId : '' ?>">
        <label for="site-id">Confirmed USFS site_id</label>
        <input id="site-id" type="number" name="site_id" min="1" required value="<?= $siteId > 0 ? $siteId : '' ?>">
        <button class="admin-button" type="submit">Save USFS source record</button>
    </form>
    <p>Target Tree USFS site ID: 3685. Confirm the matching Llama Scout Place ID before saving.</p>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
