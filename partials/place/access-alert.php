<?php

declare(strict_types=1);

$placeId = (int) ($place['id'] ?? 0);
$db = db();

/*
 * A verified operating closure is the public source of truth.
 * If one exists, show it instead of a separate access-report warning so the
 * Place page never displays two competing closure messages.
 */
$verifiedClosure = null;

if ($placeId > 0) {
    try {
        $closureStmt = $db->prepare(
            "SELECT status, reason, closure_began, expected_reopening,
                    review_date, source_url, notes, verified_at
             FROM place_operating_closures
             WHERE place_id = ?
               AND campsite_id IS NULL
               AND status IN ('temporary', 'seasonal', 'permanent')
             LIMIT 1"
        );
        $closureStmt->execute([$placeId]);
        $verifiedClosure = $closureStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable) {
        /* The Place page should still render if the closure table is absent. */
        $verifiedClosure = null;
    }
}

if ($verifiedClosure) {
    $status = (string) ($verifiedClosure['status'] ?? 'temporary');
    $reason = trim((string) ($verifiedClosure['reason'] ?? 'unknown'));

    $reasonLabels = [
        'maintenance' => 'maintenance',
        'construction' => 'construction',
        'weather' => 'weather conditions',
        'fire' => 'fire conditions',
        'flooding' => 'flooding',
        'road_damage' => 'road damage',
        'administrative' => 'an administrative closure',
        'other' => 'another reported reason',
        'unknown' => 'an unspecified reason',
    ];

    $statusLabel = match ($status) {
        'seasonal' => 'Seasonally closed',
        'permanent' => 'Permanently closed',
        default => 'Temporarily closed',
    };

    $reasonLabel = $reasonLabels[$reason] ?? str_replace('_', ' ', $reason);
    $expectedReopening = trim((string) ($verifiedClosure['expected_reopening'] ?? ''));
    $reopeningLabel = '';

    if ($expectedReopening !== '') {
        try {
            $reopeningLabel = (new DateTimeImmutable($expectedReopening))->format('F j, Y');
        } catch (Throwable) {
            $reopeningLabel = '';
        }
    }
    ?>

    <section
        class="place-access-alert is-verified-closure"
        role="alert"
        aria-label="Place closure warning"
    >
        <i class="place-access-alert-icon" aria-hidden="true">
            <?= llama_icon('alert-triangle') ?>
        </i>

        <div class="place-access-alert-copy">
            <p class="place-access-alert-eyebrow">
                <?= place_h($statusLabel) ?>
            </p>

            <h2>
                <?= place_h((string) ($place['name'] ?? 'This Place')) ?> is currently closed
            </h2>

            <p>
                Closed due to <?= place_h($reasonLabel) ?>.
                <?php if ($reopeningLabel !== ''): ?>
                    Expected to reopen <?= place_h($reopeningLabel) ?>.
                <?php elseif ($status === 'permanent'): ?>
                    No reopening is currently expected.
                <?php endif; ?>
            </p>
        </div>
    </section>

    <?php
    return;
}

/* No verified closure: fall back to the existing recent-access warning. */
$placeAccessAlert =
    llama_place_access_alert(
        $db,
        $placeId
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
    <i class="place-access-alert-icon" aria-hidden="true">
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
