<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/app/ridb-facility-details.php';

$facilityPublic =
    [];

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
        'place.facility.public',
        [
            'place_id' =>
                (int) (
                    $place['id']
                    ?? 0
                ),
        ]
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

$facilityReservationUrl =
    trim(
        (string) (
            $facilityPublic['reservation_url']
            ?? ''
        )
    );

$facilityFeeDescription =
    trim(
        (string) (
            $facilityPublic['fee_description']
            ?? ''
        )
    );

$facilityActivities =
    (array) (
        $facilityPublic['activities']
        ?? []
    );

$facilityLinks =
    (array) (
        $facilityPublic['links']
        ?? []
    );

$facilitySource =
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

$hasFacilityOfficialInfo =
    $facilityAddress !== ''
    || $facilityPhone !== ''
    || $facilityEmail !== ''
    || (
        $facilityReservationUrl !== ''
        && preg_match(
            '#^https?://#i',
            $facilityReservationUrl
        ) === 1
    )
    || $facilityFeeDescription !== ''
    || !empty($facilityActivities)
    || !empty($facilityLinks);

if (!$hasFacilityOfficialInfo) {
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
    <header class="place-facility-info-heading">
        <div>
            <p class="eyebrow">
                Official campground information
            </p>

            <h2 id="place-facility-info-heading">
                Facility details
            </h2>
        </div>

        <?php if ($facilitySource !== ''): ?>
            <span class="place-facility-source">
                <?= place_h($facilitySource) ?>
            </span>
        <?php endif; ?>
    </header>

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
                                ?? ''
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

    <?php if ($facilityFeeDescription !== ''): ?>
        <section class="place-facility-copy">
            <h3>Official fee information</h3>

            <p>
                <?= nl2br(
                    place_h(
                        $facilityFeeDescription
                    )
                ) ?>
            </p>
        </section>
    <?php endif; ?>

    <?php if (
        $facilityReservationUrl !== ''
        && preg_match(
            '#^https?://#i',
            $facilityReservationUrl
        ) === 1
    ): ?>
        <div class="place-facility-actions">
            <a
                class="place-facility-action"
                href="<?= place_h(
                    $facilityReservationUrl
                ) ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                Reservations
                <?= llama_icon('external-link') ?>
            </a>
        </div>
    <?php endif; ?>

    <?php if ($facilityActivities): ?>
        <section class="place-facility-list-section">
            <h3>Activities</h3>

            <div class="place-facility-pills">
                <?php foreach (
                    $facilityActivities
                    as $activity
                ): ?>
                    <span>
                        <?= place_h($activity) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($facilityLinks): ?>
        <section class="place-facility-list-section">
            <h3>Official links</h3>

            <div class="place-facility-links">
                <?php foreach (
                    $facilityLinks
                    as $link
                ): ?>
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
        <p class="place-facility-updated">
            Official source updated:
            <?= place_h($facilitySourceUpdated) ?>
        </p>
    <?php endif; ?>
</section>
