<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/app/ridb-facility-details.php';

$facilityPublic = [];

try {
    $facilityPublic =
        llama_place_facility_public_data(
            db(),
            (int) (
                $place['id']
                ?? 0
            )
        );
} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'place.facility-info'
    );
}

$facilityAddress =
    trim(
        (string) (
            $facilityPublic['address']
            ?? ''
        )
    );

$facilityPhone =
    trim(
        (string) (
            $facilityPublic['phone']
            ?? ''
        )
    );

$facilityEmail =
    trim(
        (string) (
            $facilityPublic['email']
            ?? ''
        )
    );

$facilityLinks =
    (array) (
        $facilityPublic['links']
        ?? []
    );

$facilitySourceProvider =
    trim(
        (string) (
            $facilityPublic['source_provider']
            ?? ''
        )
    );

$facilitySourceUpdated =
    trim(
        (string) (
            $facilityPublic['source_updated_at']
            ?? ''
        )
    );

$hasFacilitySourceInfo =
    $facilityAddress !== ''
    || $facilityPhone !== ''
    || $facilityEmail !== ''
    || !empty($facilityLinks);

if (!$hasFacilitySourceInfo) {
    return;
}
?>

<link
    rel="stylesheet"
    href="/css/site/features/place-facility-info.css"
>

<section
    class="place-section place-facility-info"
    aria-labelledby="place-facility-info-heading"
>
    <div class="place-facility-info-heading">
        <div>
            <p class="eyebrow">
                Official source & contact
            </p>

            <h2 id="place-facility-info-heading">
                Facility information
            </h2>
        </div>

        <?php if ($facilitySourceProvider !== ''): ?>
            <span class="place-facility-source-pill">
                <?= place_h($facilitySourceProvider) ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if (
        $facilityAddress !== ''
        || $facilityPhone !== ''
        || $facilityEmail !== ''
    ): ?>
        <div class="place-facility-info-grid">
            <?php if ($facilityAddress !== ''): ?>
                <div class="place-facility-info-card">
                    <span>Official address</span>
                    <strong>
                        <?= place_h($facilityAddress) ?>
                    </strong>
                </div>
            <?php endif; ?>

            <?php if ($facilityPhone !== ''): ?>
                <div class="place-facility-info-card">
                    <span>Phone</span>
                    <strong>
                        <a
                            href="tel:<?= place_h(
                                preg_replace(
                                    '/[^0-9+]/',
                                    '',
                                    $facilityPhone
                                )
                            ) ?>"
                        >
                            <?= place_h($facilityPhone) ?>
                        </a>
                    </strong>
                </div>
            <?php endif; ?>

            <?php if ($facilityEmail !== ''): ?>
                <div class="place-facility-info-card">
                    <span>Email</span>
                    <strong>
                        <a
                            href="mailto:<?= place_h(
                                $facilityEmail
                            ) ?>"
                        >
                            <?= place_h($facilityEmail) ?>
                        </a>
                    </strong>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($facilityLinks): ?>
        <section class="place-facility-list-section">
            <h3>Official links</h3>

            <div class="place-facility-link-grid">
                <?php foreach ($facilityLinks as $link): ?>
                    <?php
                    $url =
                        trim(
                            (string) (
                                $link['url']
                                ?? ''
                            )
                        );

                    if (
                        $url === ''
                        || preg_match(
                            '#^https?://#i',
                            $url
                        ) !== 1
                    ) {
                        continue;
                    }

                    $title =
                        trim(
                            (string) (
                                $link['title']
                                ?? ''
                            )
                        );
                    ?>

                    <a
                        class="place-facility-link-card"
                        href="<?= place_h($url) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <span>
                            <?= place_h(
                                $title !== ''
                                    ? $title
                                    : 'Official link'
                            ) ?>
                        </span>

                        <?= llama_icon('external-link') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($facilitySourceUpdated !== ''): ?>
        <p class="place-facility-source-note">
            Official source updated:
            <?= place_h($facilitySourceUpdated) ?>
        </p>
    <?php endif; ?>
</section>
