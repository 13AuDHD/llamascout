<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/registration-source.php';

$acquisitionRows = [];
$acquisitionTotal = 0;
$acquisitionCaptured = 0;
$acquisitionLast30 = 0;
$acquisitionError = false;

try {
    $acquisitionTotal = (int) $db->query(
        "SELECT COUNT(*)
         FROM users
         WHERE status <> 'deleted'"
    )->fetchColumn();

    $acquisitionCaptured = (int) $db->query(
        "SELECT COUNT(*)
         FROM users
         WHERE status <> 'deleted'
           AND registration_source IS NOT NULL
           AND registration_source <> ''"
    )->fetchColumn();

    $acquisitionLast30 = (int) $db->query(
        "SELECT COUNT(*)
         FROM users
         WHERE status <> 'deleted'
           AND registration_source IS NOT NULL
           AND registration_source <> ''
           AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)"
    )->fetchColumn();

    $stmt = $db->query(
        "SELECT
            registration_source,
            COUNT(*) AS source_count
         FROM users
         WHERE status <> 'deleted'
           AND registration_source IS NOT NULL
           AND registration_source <> ''
         GROUP BY registration_source
         ORDER BY source_count DESC, registration_source ASC"
    );

    $acquisitionRows =
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
} catch (Throwable $exception) {
    $acquisitionError = true;

    if (function_exists('llama_log_caught_exception')) {
        llama_log_caught_exception(
            $exception,
            'admin.acquisition_summary'
        );
    } else {
        error_log(
            'Llama Scout acquisition summary error: ' .
            $exception->getMessage()
        );
    }
}

$acquisitionCaptureRate =
    $acquisitionTotal > 0
        ? round(
            ($acquisitionCaptured / $acquisitionTotal) * 100,
            1
        )
        : 0.0;
?>

<section class="admin-panel admin-acquisition-panel">

<header class="admin-panel-header">
    <div>
        <p>Growth</p>
        <h2>How People Find Llama Scout</h2>
    </div>

    <?php if (!$acquisitionError): ?>
        <span>
            <?= number_format($acquisitionLast30) ?>
            last 30 days
        </span>
    <?php endif; ?>
</header>

<?php if ($acquisitionError): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-chart-column"
        aria-hidden="true"
    ></i>

    <h3>Acquisition data unavailable.</h3>

    <p>
        Check that the registration_source migration has been
        applied to the users table.
    </p>
</div>

<?php elseif (!$acquisitionRows): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-compass"
        aria-hidden="true"
    ></i>

    <h3>No acquisition responses yet.</h3>

    <p>
        New registrations will begin filling this panel
        automatically.
    </p>
</div>

<?php else: ?>

<div class="admin-acquisition-summary">

<div>
    <span>Responses</span>
    <strong>
        <?= number_format($acquisitionCaptured) ?>
    </strong>
</div>

<div>
    <span>Capture rate</span>
    <strong>
        <?= moderation_e(
            number_format(
                $acquisitionCaptureRate,
                1
            )
        ) ?>%
    </strong>
</div>

</div>

<div class="admin-acquisition-list">

<?php foreach ($acquisitionRows as $row): ?>
<?php
$sourceKey =
    trim(
        (string) (
            $row['registration_source']
            ?? ''
        )
    );

$sourceCount =
    max(
        0,
        (int) (
            $row['source_count']
            ?? 0
        )
    );

$sourcePercent =
    $acquisitionCaptured > 0
        ? ($sourceCount / $acquisitionCaptured) * 100
        : 0;
?>

<div class="admin-acquisition-row">

<div class="admin-acquisition-row-copy">
    <strong>
        <?= moderation_e(
            llama_registration_source_label(
                $sourceKey
            )
        ) ?>
    </strong>

    <span>
        <?= moderation_e(
            number_format(
                $sourcePercent,
                1
            )
        ) ?>%
    </span>
</div>

<progress
    class="admin-acquisition-bar"
    max="100"
    value="<?= moderation_e(
        number_format(
            min(100, max(0, $sourcePercent)),
            2,
            '.',
            ''
        )
    ) ?>"
    aria-label="<?= moderation_e(
        llama_registration_source_label(
            $sourceKey
        )
    ) ?> <?= moderation_e(
        number_format(
            $sourcePercent,
            1
        )
    ) ?> percent"
></progress>

<span class="admin-acquisition-count">
    <?= number_format($sourceCount) ?>
</span>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>
