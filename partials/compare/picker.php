<section
    class="compare-picker"
    aria-labelledby="compare-picker-heading"
>
    <div class="compare-picker-heading">
        <div>
            <p class="compare-eyebrow">
                Choose Places
            </p>

            <h2 id="compare-picker-heading">
                Build your comparison
            </h2>
        </div>

        <span data-compare-heading-count>
            <?= count($compareSlugs) ?>
            of
            <?= LLAMA_COMPARE_MAX_PLACES ?>
            selected
        </span>
    </div>

    <form
        class="compare-picker-form"
        method="get"
        action="/compare.php"
        data-compare-picker
        data-max-places="<?= LLAMA_COMPARE_MAX_PLACES ?>"
        data-min-places="<?= LLAMA_COMPARE_MIN_PLACES ?>"
    >
        <div
            class="compare-selected"
            data-compare-selected
            <?= $compareSlugs ? '' : 'hidden' ?>
        >
            <div class="compare-selected-heading">
                <strong>Selected Places</strong>
                <span>
                    Tap × to remove one.
                </span>
            </div>

            <div
                class="compare-selected-list"
                data-compare-selected-list
            >
                <?php foreach ($placeOptions as $option): ?>
                    <?php
                    $optionSlug =
                        (string) $option['slug'];

                    if (
                        !in_array(
                            $optionSlug,
                            $compareSlugs,
                            true
                        )
                    ) {
                        continue;
                    }

                    $optionLocation =
                        implode(
                            ', ',
                            array_filter(
                                [
                                    (string) (
                                        $option['city']
                                        ?? ''
                                    ),
                                    (string) (
                                        $option['state']
                                        ?? ''
                                    ),
                                ]
                            )
                        );
                    ?>

                    <button
                        type="button"
                        class="compare-selected-chip"
                        data-remove-compare-place="<?= llama_compare_h($optionSlug) ?>"
                        aria-label="Remove <?= llama_compare_h((string) $option['name']) ?> from comparison"
                    >
                        <span>
                            <strong>
                                <?= llama_compare_h(
                                    (string) $option['name']
                                ) ?>
                            </strong>

                            <?php if ($optionLocation !== ''): ?>
                                <small>
                                    <?= llama_compare_h($optionLocation) ?>
                                </small>
                            <?php endif; ?>
                        </span>

                        <i
                            class="fa-solid fa-xmark"
                            aria-hidden="true"
                        ></i>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="compare-add-place">
            <label for="compare-place-search">
                Add another Place
            </label>

            <div class="compare-picker-search">
                <i
                    class="fa-solid fa-magnifying-glass"
                    aria-hidden="true"
                ></i>

                <input
                    id="compare-place-search"
                    type="search"
                    placeholder="Start typing a Place, town, or type..."
                    autocomplete="off"
                    data-compare-search
                >
            </div>

            <p class="compare-add-help">
                Search, then tap a Place to add it. You can compare up to
                <?= LLAMA_COMPARE_MAX_PLACES ?> Places at once.
            </p>
        </div>

        <div
            class="compare-place-options"
            data-compare-options
        >
            <?php foreach ($placeOptions as $option): ?>
                <?php
                $optionSlug =
                    (string) $option['slug'];

                $isSelected =
                    in_array(
                        $optionSlug,
                        $compareSlugs,
                        true
                    );

                $optionLocation =
                    implode(
                        ', ',
                        array_filter(
                            [
                                (string) (
                                    $option['city']
                                    ?? ''
                                ),
                                (string) (
                                    $option['state']
                                    ?? ''
                                ),
                            ]
                        )
                    );

                $optionMeta =
                    trim(
                        implode(
                            ' · ',
                            array_filter(
                                [
                                    llama_compare_label(
                                        $option['type']
                                        ?? ''
                                    ),
                                    $optionLocation,
                                    is_numeric(
                                        $option['elevation_feet']
                                        ?? null
                                    )
                                        ? number_format(
                                            (float) $option['elevation_feet']
                                        ) . ' ft'
                                        : '',
                                ]
                            )
                        )
                    );

                $searchText =
                    strtolower(
                        implode(
                            ' ',
                            [
                                (string) (
                                    $option['name']
                                    ?? ''
                                ),
                                (string) (
                                    $option['type']
                                    ?? ''
                                ),
                                $optionLocation,
                            ]
                        )
                    );
                ?>

                <label
                    class="compare-place-option <?= $isSelected ? 'is-selected' : '' ?>"
                    data-compare-option
                    data-place-slug="<?= llama_compare_h($optionSlug) ?>"
                    data-place-name="<?= llama_compare_h((string) $option['name']) ?>"
                    data-place-meta="<?= llama_compare_h($optionMeta) ?>"
                    data-search-text="<?= llama_compare_h($searchText) ?>"
                >
                    <input
                        type="checkbox"
                        name="places[]"
                        value="<?= llama_compare_h($optionSlug) ?>"
                        <?= $isSelected ? 'checked' : '' ?>
                    >

                    <span class="compare-place-option-check">
                        <i
                            class="fa-solid fa-plus"
                            aria-hidden="true"
                        ></i>
                    </span>

                    <span class="compare-place-option-copy">
                        <strong>
                            <?= llama_compare_h(
                                (string) $option['name']
                            ) ?>
                        </strong>

                        <?php if ($optionMeta !== ''): ?>
                            <small>
                                <?= llama_compare_h($optionMeta) ?>
                            </small>
                        <?php endif; ?>
                    </span>
                </label>
            <?php endforeach; ?>

            <div
                class="compare-search-empty"
                data-compare-search-empty
                hidden
            >
                No matching Places.
            </div>
        </div>

        <div class="compare-picker-footer">
            <span data-compare-count>
                <?= count($compareSlugs) ?>
                selected
            </span>

            <div>
                <?php if ($compareSlugs): ?>
                    <a
                        class="compare-button is-quiet"
                        href="/compare.php"
                    >
                        Clear
                    </a>
                <?php endif; ?>

                <button
                    class="compare-button"
                    type="submit"
                    data-compare-submit
                    <?= count($compareSlugs) < LLAMA_COMPARE_MIN_PLACES ? 'disabled' : '' ?>
                >
                    <i
                        class="fa-solid fa-code-compare"
                        aria-hidden="true"
                    ></i>

                    Compare Selected
                </button>
            </div>
        </div>
    </form>
</section>
