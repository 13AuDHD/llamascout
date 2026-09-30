<?php

declare(strict_types=1);

$placeAccessAlert =
    llama_place_access_alert(
        db(),
        (int) ($place['id'] ?? 0)
    );

if (!$placeAccessAlert) {
    return;
}

$accessState =
    (string) (
        $placeAccessAlert['state']
        ?? 'reported'
    );

$accessReason =
    (string) (
        $placeAccessAlert['reason']
        ?? 'unknown'
    );

$accessTitle =
    llama_place_access_alert_public_title(
        $accessState
    );

$accessCopy =
    llama_place_access_alert_public_copy(
        $placeAccessAlert
    );

$accessReasonLabel =
    llama_place_access_alert_reason_label(
        $accessReason
    );

$checkInUrl =
    '/check-in.php?place=' .
    rawurlencode(
        (string) ($place['slug'] ?? '')
    );

$reportUrl =
    '/place.php?slug=' .
    rawurlencode(
        (string) ($place['slug'] ?? '')
    ) .
    '&report=1#report-place';
?>

<section
    class="place-access-alert"
    role="status"
    aria-label="Place access warning"
>
    <i aria-hidden="true">
        <?= llama_icon('alert-triangle') ?>
    </i>

    <div class="place-access-alert-copy">
        <p class="place-access-alert-eyebrow">
            Recent access report
        </p>

        <h2>
            <?= place_h($accessTitle) ?>
        </h2>

        <p>
            <?= place_h($accessCopy) ?>
        </p>

        <span class="place-access-alert-meta">
            Reported issue:
            <?= place_h($accessReasonLabel) ?>
        </span>
    </div>

    <div class="place-access-alert-actions">
        <a href="<?= place_h($checkInUrl) ?>">
            There now? Check in
        </a>

        <?php if (!empty($canReportProblem)): ?>
            <a href="<?= place_h($reportUrl) ?>">
                Still blocked? Report a problem
            </a>
        <?php endif; ?>
    </div>
</section>
