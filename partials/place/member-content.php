<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/app/place-report.php';

/*
 * Issue #41: this partial is no longer a fork between a "member page" and a
 * separate fake locked page. Every authorized viewer receives the canonical
 * Scout Report structure. Complete Access controls answers and the richer
 * overnight/Site experience, not which Scout Report exists.
 */
$viewerHasCompleteAccess =
    (bool) (
        $hasCompleteAccess
        ?? $hasMemberAccess
        ?? false
    );
?>

<?php if (!empty($hasContributorPlaceAccess) && empty($hasGlobalMemberAccess)): ?>
    <section class="place-section place-contributor-access-note">
        <i aria-hidden="true"><?= llama_icon('key') ?></i>
        <div>
            <strong>Complete Access to this Place</strong>
            <p>
                You originally contributed this Place, so its complete report,
                restricted location access, gallery, and historical reports remain
                available to your account even without a paid membership.
            </p>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/overnight-options.php'; ?>

<?php require __DIR__ . '/scout-report/index.php'; ?>
