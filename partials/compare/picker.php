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

        <span>
            <?= count($compareSlugs) ?>
            of
            <?= LLAMA_COMPARE_MAX_PLACES ?>
            selected
        </span>

    </div>


    <div class="compare-picker-search">

        <i
            class="fa-solid fa-magnifying-glass"
            aria-hidden="true"
        ></i>

        <label
            class="visually-hidden"
            for="compare-place-search"
        >
            Search Places
        </label>

        <input
            id="compare-place-search"
            type="search"
            placeholder="Search Places, towns, types..."
            autocomplete="off"
            data-compare-search
        >

    </div>


    <form
        class="compare-picker-form"
        method="get"
        action="/compare.php"
        data-compare-picker
        data-max-places="<?= LLAMA_COMPARE_MAX_PLACES ?>"
    >

        <div class="compare-place-options">

            <?php foreach (
                $placeOptions
                as $option
            ): ?>
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
                            class="fa-solid fa-check"
                            aria-hidden="true"
                        ></i>
                    </span>

                    <span class="compare-place-option-copy">

                        <strong>
                            <?= llama_compare_h(
                                (string) $option['name']
                            ) ?>
                        </strong>

                        <small>
                            <?= llama_compare_h(
                                llama_compare_label(
                                    $option['type']
                                    ?? ''
                                )
                            ) ?>

                            <?php if (
                                $optionLocation !== ''
                            ): ?>
                                ·
                                <?= llama_compare_h(
                                    $optionLocation
                                ) ?>
                            <?php endif; ?>

                            <?php if (
                                is_numeric(
                                    $option['elevation_feet']
                                    ?? null
                                )
                            ): ?>
                                ·
                                <?= number_format(
                                    (float) $option[
                                        'elevation_feet'
                                    ]
                                ) ?>
                                ft
                            <?php endif; ?>
                        </small>

                    </span>

                </label>

            <?php endforeach; ?>

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
