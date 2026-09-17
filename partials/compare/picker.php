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
        data-place-search-endpoint="/api/compare-place-search.php"
    >
        <div
            data-compare-hidden-inputs
        >
            <?php foreach ($comparePlaces as $place): ?>
                <?php
                $slug =
                    (string) (
                        $place['slug']
                        ?? ''
                    );

                $location =
                    implode(
                        ', ',
                        array_filter(
                            [
                                (string) (
                                    $place['city']
                                    ?? ''
                                ),
                                (string) (
                                    $place['state']
                                    ?? ''
                                ),
                            ]
                        )
                    );

                $meta =
                    trim(
                        implode(
                            ' · ',
                            array_filter(
                                [
                                    llama_compare_label(
                                        $place['type']
                                        ?? ''
                                    ),
                                    $location,
                                    is_numeric(
                                        $place['elevation_feet']
                                        ?? null
                                    )
                                        ? number_format(
                                            (float) $place['elevation_feet']
                                        ) . ' ft'
                                        : '',
                                ]
                            )
                        )
                    );
                ?>

                <input
                    type="hidden"
                    name="places[]"
                    value="<?= llama_compare_h($slug) ?>"
                    data-compare-selected-input
                    data-place-name="<?= llama_compare_h(
                        (string) (
                            $place['name']
                            ?? 'Place'
                        )
                    ) ?>"
                    data-place-meta="<?= llama_compare_h($meta) ?>"
                >
            <?php endforeach; ?>
        </div>

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
            ></div>
        </div>

        <div class="compare-add-place">
            <label for="compare-place-search">
                Add another Place
            </label>

            <div class="compare-picker-search">
                <i aria-hidden="true"><?= llama_icon('search') ?></i>

                <input
                    id="compare-place-search"
                    type="search"
                    placeholder="Start typing a Place, town, or type..."
                    autocomplete="off"
                    autocapitalize="none"
                    spellcheck="false"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls="compare-place-search-results"
                    aria-expanded="false"
                    data-compare-search
                >
            </div>

            <p class="compare-add-help">
                Results narrow as you type. You can compare up to
                <?= LLAMA_COMPARE_MAX_PLACES ?> Places at once.
            </p>
        </div>

        <div
            id="compare-place-search-results"
            class="compare-place-options"
            role="listbox"
            data-compare-options
        ></div>

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
                    <i aria-hidden="true"><?= llama_icon('arrows-diff') ?></i>

                    Compare Selected
                </button>
            </div>
        </div>
    </form>
</section>
