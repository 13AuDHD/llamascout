<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb-import.php';

$adminUser = moderation_require_admin();

$adminPageTitle = 'RIDB Import';
$adminPageEyebrow = 'Reference Data';
$adminActiveNav = 'reference-data';

$facilityId = trim(
    (string) (
        $_GET['id']
        ?? $_POST['facility_id']
        ?? ''
    )
);

$error = '';
$result = null;

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
) {
    @set_time_limit(0);

    if ($facilityId === '') {
        $error = 'RIDB facility ID is required.';
    } else {
        try {
            $result = llama_ridb_import_facility(
                db(),
                ridb_db(),
                (int) ($adminUser['id'] ?? 0),
                $facilityId
            );
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

require __DIR__ . '/_header.php';
?>

<?php if ($error !== ''): ?>
<section class="admin-panel">
    <strong>Import failed</strong>
    <p><?= moderation_e($error) ?></p>
</section>
<?php endif; ?>

<?php if (is_array($result)): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>
                <?= !empty($result['created'])
                    ? 'Imported'
                    : 'Synchronized' ?>
            </p>

            <h2>
                RIDB facility
                <?= moderation_e($facilityId) ?>
            </h2>
        </div>
    </header>

    <dl class="admin-user-definition-list">
        <div>
            <dt>Place ID</dt>
            <dd>
                <?= number_format(
                    (int) ($result['place_id'] ?? 0)
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Campsites</dt>
            <dd>
                <?= number_format(
                    (int) ($result['campsites'] ?? 0)
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Attributes normalized</dt>
            <dd>
                <?= number_format(
                    (int) ($result['attributes'] ?? 0)
                ) ?>
            </dd>
        </div>
    </dl>

    <?php if (
        trim(
            (string) ($result['slug'] ?? '')
        ) !== ''
    ): ?>
        <div class="admin-user-form-actions">
            <a
                class="admin-button"
                href="https://llamascout.com/place.php?slug=<?= rawurlencode(
                    (string) $result['slug']
                ) ?>"
                target="_blank"
                rel="noopener"
            >
                Open imported Place
            </a>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Production import</p>
            <h2>Import Recreation.gov campground</h2>
        </div>
    </header>

    <p>
        This writes directly to Llama Scout. Re-importing the same
        RIDB facility synchronizes the existing Place and campsite
        records instead of creating duplicates.
    </p>

    <form method="post">
        <label>
            <span>RIDB facility ID</span>

            <input
                type="text"
                name="facility_id"
                value="<?= moderation_e($facilityId) ?>"
                inputmode="numeric"
                required
            >
        </label>

        <div class="admin-user-form-actions">
            <button
                class="admin-button"
                type="submit"
            >
                Import now
            </button>

            <?php if ($facilityId !== ''): ?>
                <a
                    class="admin-button is-muted"
                    href="/ridb-facility.php?id=<?= rawurlencode(
                        $facilityId
                    ) ?>"
                >
                    Open RIDB source
                </a>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
