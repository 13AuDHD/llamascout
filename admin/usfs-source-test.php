<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once __DIR__ . '/_dashboard.php';

moderation_require_admin();

$adminPageTitle = 'USFS Source Test';
$adminPageEyebrow = 'Reference Data';
$adminActiveNav = 'reference-data';
$adminUsesSplitCss = true;
$adminPageStyles = [];
$adminFeatureStyles = [];

$name = trim((string) ($_GET['name'] ?? 'Target Tree'));
$name = mb_substr($name, 0, 80);
if ($name === '') {
    $name = 'Target Tree';
}

$fields = [
    'site_name', 'site_type', 'site_id', 'site_cn', 'nrrs_id',
    'fee_charged', 'fee_type', 'fee_description',
    'open_season', 'seasonal_operational_status',
    'op_status_reason', 'operational_hours', 'usda_portal_url',
];

$layerUrl = 'https://apps.fs.usda.gov/fsgisx05/rest/services/wo_nfs_gtac/IVMRecreation/MapServer/0';
$records = [];
$error = null;
$queriedAt = gmdate('Y-m-d H:i:s') . ' UTC';

$escapedName = str_replace("'", "''", strtoupper($name));
$query = http_build_query([
    'where' => "UPPER(site_name) LIKE '%{$escapedName}%'",
    'outFields' => '*',
    'returnGeometry' => 'false',
    'resultRecordCount' => '25',
    'f' => 'json',
], '', '&', PHP_QUERY_RFC3986);

$ch = curl_init($layerUrl . '/query?' . $query);
if ($ch === false) {
    $error = 'Unable to initialize the USFS request.';
} else {
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'LlamaScout-USFS-source-evaluation/1.0',
    ]);
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($body) || $httpCode !== 200) {
        $error = 'USFS request failed (HTTP ' . $httpCode . '). ' . $curlError;
    } else {
        $parsed = json_decode($body, true);
        if (!is_array($parsed)) {
            $error = 'USFS returned an invalid JSON response.';
        } elseif (isset($parsed['error'])) {
            $error = 'USFS query error: ' . (string) ($parsed['error']['message'] ?? 'Unknown error');
        } else {
            foreach (($parsed['features'] ?? []) as $feature) {
                if (is_array($feature['attributes'] ?? null)) {
                    $records[] = $feature['attributes'];
                }
            }
        }
    }
}

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

require __DIR__ . '/_header.php';
?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <h2>Forest Service recreation records</h2>
            <p>Read-only test of the official USFS Recreation Site layer. Nothing is imported or changed.</p>
        </div>
    </header>
    <form method="get" action="/admin/usfs-source-test.php">
        <label for="usfs-name">Campground name</label>
        <input id="usfs-name" name="name" type="search" value="<?= $h($name) ?>" maxlength="80" required>
        <button type="submit" class="admin-button">Search USFS</button>
    </form>
    <p>Checked: <?= $h($queriedAt) ?>. Results: <?= count($records) ?>. This does not confirm matches to Llama Scout records.</p>
    <p><a href="<?= $h($layerUrl) ?>" target="_blank" rel="noopener noreferrer">Official USFS layer documentation</a></p>
    <?php if ($error !== null): ?>
        <p role="alert"><?= $h($error) ?></p>
    <?php elseif (!$records): ?>
        <p>No matching USFS records found. This is not evidence that the campground is free or open.</p>
    <?php else: ?>
        <?php foreach ($records as $index => $record): ?>
            <section class="admin-panel">
                <h3>USFS result <?= $index + 1 ?>: <?= $h($record['site_name'] ?? 'Unnamed') ?></h3>
                <dl>
                    <?php foreach ($fields as $field): ?>
                        <dt><strong><?= $h($field) ?></strong></dt>
                        <dd><?= $h(($record[$field] ?? null) === null || $record[$field] === '' ? 'Not supplied' : $record[$field]) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>
        <p>Fee indicators can include day-use or other charges. Review fee_type and fee_description before classifying camping as Paid. A seasonal field alone does not prove a current closure.</p>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
