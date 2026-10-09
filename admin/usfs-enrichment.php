<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/usfs-enrichment.php';
require_once __DIR__ . '/_dashboard.php';

$admin = moderation_require_admin();
$db = db();

function usfs_admin_h(mixed $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/** Strict input matching; do not silently overwrite a different place. */
function usfs_admin_place(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, name, type, city, state FROM places WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (($_GET['action'] ?? '') === 'search-places') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    $query = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($query) < 2 || mb_strlen($query) > 120) {
        echo json_encode(['places' => []]);
        exit;
    }
    $stmt = $db->prepare(
        "SELECT id, name, type, city, state FROM places
         WHERE name LIKE ? AND status NOT IN ('removed','archived')
         ORDER BY name LIMIT 20"
    );
    $stmt->execute(['%' . $query . '%']);
    echo json_encode(['places' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['action'] ?? '') === 'search-usfs') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    try {
        $name = trim((string) ($_GET['name'] ?? ''));
        echo json_encode(['results' => llama_usfs_find_campgrounds($name)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $exception) {
        http_response_code(502);
        echo json_encode(['error' => 'Forest Service lookup failed: ' . $exception->getMessage()]);
    }
    exit;
}

$error = '';
$saved = null;
$selectedPlace = null;
$selectedUsfs = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Session token expired. Reload and try again.';
    } else {
        try {
            $placeId = filter_var($_POST['place_id'] ?? null, FILTER_VALIDATE_INT);
            $siteId = filter_var($_POST['site_id'] ?? null, FILTER_VALIDATE_INT);
            if ($placeId === false || $siteId === false || $placeId < 1 || $siteId < 1) {
                throw new InvalidArgumentException('Select a Llama Scout Place and a Forest Service match.');
            }
            $selectedPlace = usfs_admin_place($placeId);
            if ($selectedPlace === null) {
                throw new InvalidArgumentException('Selected Place no longer exists.');
            }
            // Re-fetch the official site ID; never trust browser-provided details.
            $selectedUsfs = llama_usfs_site_by_id($siteId);
            if (!$selectedUsfs || strtoupper((string) ($selectedUsfs['site_type'] ?? '')) !== 'CAMPGROUND') {
                throw new InvalidArgumentException('USFS record is missing or is not a campground.');
            }
            $confirm = (string) ($_POST['confirm_match'] ?? '');
            if ($confirm !== '1') {
                throw new InvalidArgumentException('Confirm the matching campground before saving.');
            }
            $existingStmt = $db->prepare(
                'SELECT place_id, usfs_site_id FROM place_usfs_enrichment
                 WHERE place_id = ? OR usfs_site_id = ? LIMIT 2'
            );
            $existingStmt->execute([$placeId, $siteId]);
            foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $link) {
                if ((int) $link['place_id'] !== $placeId || (int) $link['usfs_site_id'] !== $siteId) {
                    throw new RuntimeException('One of these records is already linked elsewhere. Review the existing match before changing it.');
                }
            }
            $saved = llama_usfs_enrichment_save($db, $placeId, $siteId, (int) $admin['id']);
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
            <h2>Match a Llama Scout campground to the Forest Service</h2>
            <p>Search by name. Both record identifiers are filled in for you. Confirm the match before saving.</p>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <p role="alert"><?= usfs_admin_h($error) ?></p>
    <?php endif; ?>
    <?php if ($saved !== null): ?>
        <p role="status">
            Saved USFS source data for <?= usfs_admin_h($saved['place_name']) ?>.
            Fee candidate: <?= usfs_admin_h($saved['candidate']['status']) ?>.
            Public map status has not changed.
        </p>
    <?php endif; ?>

    <div class="admin-form-group">
        <label for="usfs-place-search">Find a Llama Scout Place</label>
        <input id="usfs-place-search" type="search" placeholder="Start typing a campground name" value="Target Tree" autocomplete="off">
        <div id="usfs-place-results" aria-live="polite"></div>
    </div>

    <div class="admin-form-group" id="usfs-matches-panel" hidden>
        <h3>Forest Service matches</h3>
        <p>Confirm that the campground name and location correspond to the selected Llama Scout Place.</p>
        <div id="usfs-match-results" aria-live="polite"></div>
    </div>

    <form method="post" id="usfs-confirm-form" hidden>
        <input type="hidden" name="csrf_token" value="<?= usfs_admin_h(moderation_csrf_token()) ?>">
        <input type="hidden" id="usfs-confirm-place-id" name="place_id">
        <input type="hidden" id="usfs-confirm-site-id" name="site_id">
        <h3>Review selected records</h3>
        <p id="usfs-confirm-summary"></p>
        <label>
            <input type="checkbox" name="confirm_match" value="1" required>
            I checked these two records and confirm they refer to the same campground.
        </label>
        <button type="submit" class="admin-button">Save confirmed match</button>
    </form>
</section>
<script src="/js/admin-usfs-enrichment.js" defer></script>
<?php require __DIR__ . '/_footer.php'; ?>
