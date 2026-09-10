<?php
$compareSections =
    llama_compare_sections();

$columnCount =
    count(
        $comparePlaces
    );
?>

<section
    class="compare-results"
    aria-labelledby="compare-results-heading"
>

    <div class="compare-results-heading">

        <div>
            <p class="compare-eyebrow">
                Side by Side
            </p>

            <h2 id="compare-results-heading">
                <?= number_format($columnCount) ?>
                Places
            </h2>
        </div>

        <p>
            Ratings are displayed as reported.
            A higher number is not automatically better for every metric.
        </p>

    </div>


    <div class="compare-table-scroll">

        <div
            class="compare-table"
            style="--compare-place-count: <?= (int) $columnCount ?>;"
        >

            <div class="compare-corner">
                <span>Place</span>
            </div>


            <?php foreach (
                $comparePlaces
                as $place
            ): ?>
                <?php
                $imageUrl =
                    llama_compare_image_url(
                        $place
                    );

                $image =
                    $place[
                        'compare_featured_image'
                    ]
                    ?? [];

                $imageAlt =
                    trim(
                        (string) (
                            is_array($image)
                                ? (
                                    $image['alt_text']
                                    ?? ''
                                )
                                : ''
                        )
                    );

                if ($imageAlt === '') {
                    $imageAlt =
                        (string) (
                            $place['name']
                            ?? 'Llama Scout Place'
                        );
                }
                ?>

                <article class="compare-place-header">

                    <a
                        class="compare-place-photo"
                        href="/place.php?slug=<?= rawurlencode(
                            (string) $place['slug']
                        ) ?>"
                    >

                        <?php if ($imageUrl !== ''): ?>

                            <img
                                src="<?= llama_compare_h(
                                    $imageUrl
                                ) ?>"
                                alt="<?= llama_compare_h(
                                    $imageAlt
                                ) ?>"
                            >

                        <?php else: ?>

                            <span>
                                <i
                                    class="fa-solid fa-mountain-sun"
                                    aria-hidden="true"
                                ></i>
                            </span>

                        <?php endif; ?>

                    </a>


                    <div class="compare-place-header-copy">

                        <small>
                            <?= llama_compare_h(
                                llama_compare_label(
                                    $place['type']
                                    ?? ''
                                )
                            ) ?>
                        </small>

                        <h3>
                            <?= llama_compare_h(
                                $place['name']
                                ?? ''
                            ) ?>
                        </h3>

                        <span>
                            <?= llama_compare_h(
                                llama_compare_location(
                                    $place
                                )
                            ) ?>
                        </span>

                        <a
                            href="/place.php?slug=<?= rawurlencode(
                                (string) $place['slug']
                            ) ?>"
                        >
                            Scout Report

                            <i
                                class="fa-solid fa-arrow-right"
                                aria-hidden="true"
                            ></i>
                        </a>

                    </div>

                </article>

            <?php endforeach; ?>


            <?php foreach (
                $compareSections
                as $section
            ): ?>

                <div class="compare-section-heading">

                    <i
                        class="fa-solid <?= llama_compare_h(
                            (string) $section['icon']
                        ) ?>"
                        aria-hidden="true"
                    ></i>

                    <?= llama_compare_h(
                        (string) $section['title']
                    ) ?>

                </div>


                <?php foreach (
                    (array) $section['rows']
                    as $row
                ): ?>

                    <?php if (
                        !llama_compare_row_has_data(
                            $comparePlaces,
                            $row
                        )
                    ): ?>
                        <?php continue; ?>
                    <?php endif; ?>

                    <div class="compare-row-label">
                        <?= llama_compare_h(
                            (string) $row['label']
                        ) ?>
                    </div>


                    <?php foreach (
                        $comparePlaces
                        as $place
                    ): ?>
                        <?php
                        $value =
                            llama_compare_format_row_value(
                                $place,
                                $row
                            );

                        $isMissing =
                            $value === 'Not reported';

                        $isYes =
                            $value === 'Yes';

                        $isNo =
                            $value === 'No';

                        $isRating =
                            preg_match(
                                '/^[1-5] \/ 5$/',
                                $value
                            ) === 1;
                        ?>

                        <div
                            class="compare-value
                                <?= $isMissing ? 'is-missing' : '' ?>
                                <?= $isYes ? 'is-yes' : '' ?>
                                <?= $isNo ? 'is-no' : '' ?>
                                <?= $isRating ? 'is-rating' : '' ?>"
                        >

                            <?php if ($isYes): ?>
                                <i
                                    class="fa-solid fa-circle-check"
                                    aria-hidden="true"
                                ></i>
                            <?php elseif ($isNo): ?>
                                <i
                                    class="fa-regular fa-circle-xmark"
                                    aria-hidden="true"
                                ></i>
                            <?php endif; ?>


                            <?php if ($isRating): ?>
                                <?php
                                $rating =
                                    (int) $value;
                                ?>

                                <span
                                    class="compare-rating-dots"
                                    aria-hidden="true"
                                >
                                    <?php for (
                                        $dot = 1;
                                        $dot <= 5;
                                        $dot++
                                    ): ?>
                                        <i
                                            class="<?= $dot <= $rating ? 'is-filled' : '' ?>"
                                        ></i>
                                    <?php endfor; ?>
                                </span>
                            <?php endif; ?>


                            <span>
                                <?= llama_compare_h(
                                    $value
                                ) ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                <?php endforeach; ?>

            <?php endforeach; ?>

        </div>

    </div>

</section>
