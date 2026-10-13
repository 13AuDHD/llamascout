<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/app/ridb-facility-details.php';
require_once dirname(__DIR__, 2)
    . '/app/ridb-useful-fields.php';

$facilityPublic =
    [];
$facilityUseful =
    [];

try {
    $placeId =
        (int) (
            $place['id']
            ?? 0
        );

    $facilityPublic =
        llama_place_facility_public_data(
            db(),
            $placeId
        );

    $facilityUseful =
        llama_ridb_useful_public_data(
            db(),
            $placeId
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

$facilityAddress3 =
    trim(
        (string) (
            $facilityUseful[
                'official_address_3'
            ]
            ?? ''
        )
    );

$facilityAddressType =
    trim(
        (string) (
            $facilityUseful[
                'official_address_type'
            ]
            ?? ''
        )
    );

$facilityAccessibility =
    trim(
        (string) (
            $facilityUseful[
                'accessibility_text'
            ]
            ?? ''
        )
    );

$facilityAdaAccess =
    trim(
        (string) (
            $facilityUseful[
                'ada_access'
            ]
            ?? ''
        )
    );

$facilityMapUrl =
    trim(
        (string) (
            $facilityUseful[
                'facility_map_url'
            ]
            ?? ''
        )
    );

$facilityStayLimit =
    trim(
        (string) (
            $facilityUseful[
                'stay_limit'
            ]
            ?? ''
        )
    );

$facilityActivityDetails =
    (array) (
        $facilityUseful[
            'activity_details'
        ]
        ?? []
    );

$facilityLinkDescriptions =
    (array) (
        $facilityUseful[
            'link_descriptions'
        ]
        ?? []
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
    || $facilityAddress3 !== ''
    || $facilityAccessibility !== ''
    || $facilityAdaAccess !== ''
    || $facilityStayLimit !== ''
    || (
        $facilityMapUrl !== ''
        && preg_match(
            '#^https?://#i',
            $facilityMapUrl
        ) === 1
    )
    || !empty($facilityActivities)
    || !empty($facilityActivityDetails)
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
                        <?php if ($facilityAddress3 !== ''): ?>
                            <br><?= place_h($facilityAddress3) ?>
                        <?php endif; ?>
                        <?php if ($facilityAddressType !== ''): ?>
                            <small>
                                <?= place_h($facilityAddressType) ?> address
                            </small>
                        <?php endif; ?>
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

    <?php if (
        $facilityAccessibility !== ''
        || $facilityAdaAccess !== ''
        || $facilityStayLimit !== ''
    ): ?>
        <div class="place-facility-info-grid">
            <?php if ($facilityAdaAccess !== ''): ?>
                <div class="place-facility-info-card">
                    <span>Official accessibility</span>
                    <strong>
                        <?= place_h($facilityAdaAccess) ?>
                    </strong>
                </div>
            <?php endif; ?>

            <?php if ($facilityStayLimit !== ''): ?>
                <div class="place-facility-info-card">
                    <span>Official stay limit</span>
                    <strong>
                        <?= place_h($facilityStayLimit) ?>
                    </strong>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($facilityAccessibility !== ''): ?>
            <section class="place-facility-copy">
                <h3>Official accessibility information</h3>

                <p>
                    <?= nl2br(
                        place_h(
                            $facilityAccessibility
                        )
                    ) ?>
                </p>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (
        $facilityMapUrl !== ''
        && preg_match(
            '#^https?://#i',
            $facilityMapUrl
        ) === 1
    ): ?>
        <div class="place-facility-actions">
            <a
                class="place-facility-action"
                href="<?= place_h(
                    $facilityMapUrl
                ) ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                Official facility map
                <?= llama_icon('external-link') ?>
            </a>
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

    <?php if ($facilityActivityDetails): ?>
        <section class="place-facility-list-section">
            <h3>Official activity details</h3>

            <?php foreach (
                $facilityActivityDetails
                as $activityDetail
            ): ?>
                <?php
                $activityName =
                    trim(
                        (string) (
                            $activityDetail['name']
                            ?? ''
                        )
                    );

                $activityDescription =
                    trim(
                        (string) (
                            $activityDetail[
                                'description'
                            ]
                            ?? ''
                        )
                    );

                $activityFee =
                    trim(
                        (string) (
                            $activityDetail[
                                'fee_description'
                            ]
                            ?? ''
                        )
                    );
                ?>

                <?php if (
                    $activityDescription !== ''
                    || $activityFee !== ''
                ): ?>
                    <div class="place-facility-copy">
                        <?php if ($activityName !== ''): ?>
                            <h4>
                                <?= place_h($activityName) ?>
                            </h4>
                        <?php endif; ?>

                        <?php if ($activityDescription !== ''): ?>
                            <p>
                                <?= nl2br(
                                    place_h(
                                        $activityDescription
                                    )
                                ) ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($activityFee !== ''): ?>
                            <p>
                                <strong>Fee:</strong>
                                <?= place_h($activityFee) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
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

                    $description =
                        trim(
                            (string) (
                                $facilityLinkDescriptions[
                                    $url
                                ]
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

                            <?php if ($description !== ''): ?>
                                <small>
                                    <?= place_h($description) ?>
                                </small>
                            <?php endif; ?>
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
