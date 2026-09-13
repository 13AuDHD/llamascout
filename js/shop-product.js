(() => {
    'use strict';

    const root =
        document.querySelector(
            '[data-product-page]'
        );

    const dataNode =
        document.getElementById(
            'product-variant-data'
        );

    if (!root || !dataNode) {
        return;
    }

    let data;

    try {
        data =
            JSON.parse(
                dataNode.textContent
                || '{}'
            );
    } catch (error) {
        console.error(
            'Llama Scout product data could not be read.',
            error
        );

        return;
    }

    const options =
        Array.isArray(data.options)
            ? data.options
            : [];

    const variants =
        Array.isArray(data.variants)
            ? data.variants
            : [];

    const images =
        Array.isArray(data.images)
            ? data.images
            : [];

    const optionOrder =
        options
            .map(
                (option) =>
                    String(
                        option.name
                        || ''
                    )
            )
            .filter(Boolean);

    const selected = {};

    let currentVariant = null;
    let currentImageIndex = 0;

    const variantInput =
        root.querySelector(
            '[data-selected-variant]'
        );

    const quantitySelect =
        root.querySelector(
            '[data-product-quantity]'
        );

    const addButton =
        root.querySelector(
            '[data-add-to-cart]'
        );

    const addButtonLabel =
        root.querySelector(
            '[data-add-to-cart-label]'
        );

    const priceNode =
        root.querySelector(
            '[data-product-price]'
        );

    const compareNode =
        root.querySelector(
            '[data-compare-price]'
        );

    const saleBadge =
        root.querySelector(
            '[data-sale-badge]'
        );

    const stockNode =
        root.querySelector(
            '[data-stock-status]'
        );

    const mainImage =
        root.querySelector(
            '[data-main-product-image]'
        );

    const thumbnailTrack =
        root.querySelector(
            '[data-thumbnail-track]'
        );

    const thumbnails =
        Array.from(
            root.querySelectorAll(
                '[data-thumbnail-index]'
            )
        );

    const optionPills =
        Array.from(
            root.querySelectorAll(
                '.product-option-pill'
            )
        );


    /* =====================================================
       VARIANTS
       ===================================================== */

    const money = (
        cents,
        currency = 'usd'
    ) => {
        const amount =
            Number(cents || 0)
            / 100;

        try {
            return new Intl.NumberFormat(
                'en-US',
                {
                    style:
                        'currency',
                    currency:
                        String(
                            currency
                            || 'usd'
                        ).toUpperCase(),
                }
            ).format(
                amount
            );
        } catch (_) {
            return '$'
                + amount.toFixed(2);
        }
    };


    const variantById = (
        id
    ) => (
        variants.find(
            (variant) =>
                Number(
                    variant.id
                )
                ===
                Number(id)
        )
        || null
    );


    const exactVariant = (
        selection = selected
    ) => {
        if (!optionOrder.length) {
            return variants[0]
                || null;
        }

        return variants.find(
            (variant) => {
                const pairs =
                    variant.options
                    || {};

                return optionOrder.every(
                    (name) =>
                        String(
                            pairs[name]
                            || ''
                        )
                        ===
                        String(
                            selection[name]
                            || ''
                        )
                );
            }
        ) || null;
    };


    const variantCanSell = (
        variant
    ) => {
        if (
            !variant
            || !variant.active
        ) {
            return false;
        }

        return Boolean(
            variant.state
            && variant.state.purchasable
            && Number(
                variant.priceCents
                || 0
            ) > 0
        );
    };


    const variantsForOptionValue = (
        name,
        value
    ) => (
        variants.filter(
            (variant) =>
                String(
                    (
                        variant.options
                        || {}
                    )[name]
                    || ''
                )
                ===
                String(value)
        )
    );


    const selectionScore = (
        variant,
        ignoredOption = ''
    ) => {
        const pairs =
            variant.options
            || {};

        let score = 0;

        optionOrder.forEach(
            (name) => {
                if (
                    name ===
                    ignoredOption
                ) {
                    return;
                }

                const wanted =
                    String(
                        selected[name]
                        || ''
                    );

                if (
                    wanted !== ''
                    &&
                    String(
                        pairs[name]
                        || ''
                    )
                    === wanted
                ) {
                    score += 1;
                }
            }
        );

        return score;
    };


    /*
     * When a customer chooses an option value, prefer the exact
     * combination they already have. If that combination does not
     * exist, choose the closest real variant instead of leaving the
     * storefront in a fake impossible combination.
     */
    const bestVariantForOption = (
        name,
        value
    ) => {
        const wanted = {
            ...selected,
            [name]:
                String(value),
        };

        const exact =
            exactVariant(
                wanted
            );

        if (exact) {
            return exact;
        }

        const candidates =
            variantsForOptionValue(
                name,
                value
            );

        if (!candidates.length) {
            return null;
        }

        return candidates
            .map(
                (variant) => ({
                    variant,
                    score:
                        selectionScore(
                            variant,
                            name
                        ),
                    sellable:
                        variantCanSell(
                            variant
                        )
                            ? 1
                            : 0,
                    active:
                        variant.active
                            ? 1
                            : 0,
                })
            )
            .sort(
                (a, b) =>
                    b.score
                    - a.score
                    ||
                    b.sellable
                    - a.sellable
                    ||
                    b.active
                    - a.active
            )[0]
            ?.variant
            || null;
    };


    /*
     * Crossed-out pills now mean the option value really has no
     * purchasable variant. A product photo is never allowed to make
     * another color/size appear sold out.
     */
    const optionValueCanSell = (
        name,
        value
    ) => (
        variantsForOptionValue(
            name,
            value
        ).some(
            variantCanSell
        )
    );


    /* =====================================================
       PRODUCT PHOTOS
       ===================================================== */

    const criteriaForImage = (
        image
    ) => {
        if (
            !image
            || !image.criteria
            || typeof image.criteria
                !== 'object'
        ) {
            return {};
        }

        const criteria = {};

        Object.entries(
            image.criteria
        ).forEach(
            ([name, values]) => {
                const list =
                    Array.isArray(values)
                        ? values
                        : [values];

                const clean =
                    list
                        .map(
                            (value) =>
                                String(
                                    value
                                    || ''
                                ).trim()
                        )
                        .filter(Boolean);

                if (clean.length) {
                    criteria[
                        String(name)
                    ] = clean;
                }
            }
        );

        return criteria;
    };


    const imageMatchesVariant = (
        image,
        variant
    ) => {
        if (
            !image
            || !variant
        ) {
            return false;
        }

        const criteria =
            criteriaForImage(
                image
            );

        if (
            !Object.keys(criteria)
                .length
        ) {
            return false;
        }

        if (
            criteria.__variant__
        ) {
            return criteria
                .__variant__
                .some(
                    (value) =>
                        Number(value)
                        ===
                        Number(
                            variant.id
                        )
                );
        }

        const pairs =
            variant.options
            || {};

        return Object.entries(
            criteria
        ).every(
            ([name, values]) => {
                if (
                    name ===
                    '__variant__'
                ) {
                    return true;
                }

                return values.includes(
                    String(
                        pairs[name]
                        || ''
                    )
                );
            }
        );
    };


    const imageSpecificity = (
        image
    ) => {
        const criteria =
            criteriaForImage(
                image
            );

        if (
            criteria.__variant__
        ) {
            return 10000;
        }

        let groups = 0;
        let breadth = 0;

        Object.values(
            criteria
        ).forEach(
            (values) => {
                groups += 1;
                breadth +=
                    values.length;
            }
        );

        return (
            groups * 100
        ) - breadth;
    };


    const bestImageIndexForVariant = (
        variant
    ) => {
        if (
            !variant
            || !images.length
        ) {
            return -1;
        }

        const currentImage =
            images[
                currentImageIndex
            ];

        /*
         * Keep the photo the customer is already viewing if it
         * actually belongs to the newly selected variant.
         */
        if (
            currentImage
            &&
            imageMatchesVariant(
                currentImage,
                variant
            )
        ) {
            return currentImageIndex;
        }

        const matching =
            images
                .map(
                    (image, index) => ({
                        image,
                        index,
                        score:
                            imageSpecificity(
                                image
                            ),
                    })
                )
                .filter(
                    (entry) =>
                        imageMatchesVariant(
                            entry.image,
                            variant
                        )
                )
                .sort(
                    (a, b) =>
                        b.score
                        - a.score
                        ||
                        a.index
                        - b.index
                );

        if (matching.length) {
            return matching[0]
                .index;
        }

        const primary =
            images.findIndex(
                (image) =>
                    image.primary
            );

        if (primary >= 0) {
            return primary;
        }

        const general =
            images.findIndex(
                (image) =>
                    Object.keys(
                        criteriaForImage(
                            image
                        )
                    ).length === 0
            );

        return general >= 0
            ? general
            : 0;
    };


    const variantMatchesImageCriteria = (
        variant,
        criteria
    ) => {
        if (!variant) {
            return false;
        }

        if (
            !criteria
            || !Object.keys(
                criteria
            ).length
        ) {
            return false;
        }

        if (
            criteria.__variant__
        ) {
            return criteria
                .__variant__
                .some(
                    (value) =>
                        Number(value)
                        ===
                        Number(
                            variant.id
                        )
                );
        }

        const pairs =
            variant.options
            || {};

        return Object.entries(
            criteria
        ).every(
            ([name, values]) => {
                if (
                    name ===
                    '__variant__'
                ) {
                    return true;
                }

                return values.includes(
                    String(
                        pairs[name]
                        || ''
                    )
                );
            }
        );
    };


    /*
     * Photos are allowed to select the variant they depict, but they
     * do not become an availability filter. That keeps "this is the
     * blue photo" separate from "only blue is available".
     */
    const bestVariantForImage = (
        image
    ) => {
        const criteria =
            criteriaForImage(
                image
            );

        if (
            !Object.keys(criteria)
                .length
        ) {
            return null;
        }

        if (
            criteria.__variant__
        ) {
            return variantById(
                criteria
                    .__variant__[0]
            );
        }

        const matches =
            variants.filter(
                (variant) =>
                    variantMatchesImageCriteria(
                        variant,
                        criteria
                    )
            );

        if (!matches.length) {
            return null;
        }

        const currentExact =
            exactVariant(
                selected
            );

        if (
            currentExact
            &&
            matches.includes(
                currentExact
            )
        ) {
            return currentExact;
        }

        return matches
            .map(
                (variant) => ({
                    variant,
                    score:
                        selectionScore(
                            variant
                        ),
                    sellable:
                        variantCanSell(
                            variant
                        )
                            ? 1
                            : 0,
                    active:
                        variant.active
                            ? 1
                            : 0,
                })
            )
            .sort(
                (a, b) =>
                    b.score
                    - a.score
                    ||
                    b.sellable
                    - a.sellable
                    ||
                    b.active
                    - a.active
            )[0]
            ?.variant
            || null;
    };


    /* =====================================================
       RENDERING
       ===================================================== */

    const renderQuantity = (
        maxQuantity
    ) => {
        if (!quantitySelect) {
            return;
        }

        const previous =
            Math.max(
                1,
                Number(
                    quantitySelect.value
                    || 1
                )
            );

        quantitySelect.innerHTML =
            '';

        if (maxQuantity < 1) {
            const option =
                document.createElement(
                    'option'
                );

            option.value =
                '1';

            option.textContent =
                '1';

            quantitySelect
                .appendChild(
                    option
                );

            quantitySelect.disabled =
                true;

            return;
        }

        quantitySelect.disabled =
            false;

        for (
            let quantity = 1;
            quantity <= maxQuantity;
            quantity += 1
        ) {
            const option =
                document.createElement(
                    'option'
                );

            option.value =
                String(quantity);

            option.textContent =
                String(quantity);

            quantitySelect
                .appendChild(
                    option
                );
        }

        quantitySelect.value =
            String(
                Math.min(
                    previous,
                    maxQuantity
                )
            );
    };


    const renderPills = () => {
        optionPills.forEach(
            (pill) => {
                const name =
                    String(
                        pill.dataset
                            .optionName
                        || ''
                    );

                const value =
                    String(
                        pill.dataset
                            .optionValue
                        || ''
                    );

                const isSelected =
                    String(
                        selected[name]
                        || ''
                    )
                    === value;

                const sellable =
                    optionValueCanSell(
                        name,
                        value
                    );

                pill.classList.toggle(
                    'is-selected',
                    isSelected
                );

                pill.classList.toggle(
                    'is-unavailable',
                    !sellable
                );

                pill.setAttribute(
                    'aria-pressed',
                    isSelected
                        ? 'true'
                        : 'false'
                );

                pill.setAttribute(
                    'aria-disabled',
                    sellable
                        ? 'false'
                        : 'true'
                );
            }
        );
    };


    const renderVariant = () => {
        const variant =
            currentVariant;

        const exact =
            Boolean(variant);

        const priceCents =
            exact
                ? Number(
                    variant.priceCents
                    || 0
                )
                : 0;

        const compareCents =
            exact
            &&
            variant
                .compareAtPriceCents
                != null
                ? Number(
                    variant
                        .compareAtPriceCents
                )
                : 0;

        const onSale =
            priceCents > 0
            &&
            compareCents
                > priceCents;

        const state =
            exact
            &&
            variant.state
                ? variant.state
                : {
                    key:
                        'unavailable',
                    label:
                        'Unavailable',
                    purchasable:
                        false,
                };

        const purchasable =
            Boolean(
                exact
                &&
                state.purchasable
                &&
                priceCents > 0
            );

        const maxQuantity =
            purchasable
                ? Math.max(
                    1,
                    Number(
                        variant
                            .maxQuantity
                        || 1
                    )
                )
                : 0;

        if (variantInput) {
            variantInput.value =
                exact
                    ? String(
                        variant.id
                    )
                    : '0';
        }

        if (priceNode) {
            priceNode.textContent =
                priceCents > 0
                    ? money(
                        priceCents,
                        variant.currency
                    )
                    : 'Unavailable';
        }

        if (compareNode) {
            compareNode.hidden =
                !onSale;

            compareNode.textContent =
                onSale
                    ? money(
                        compareCents,
                        variant.currency
                    )
                    : '';
        }

        if (saleBadge) {
            saleBadge.hidden =
                !onSale;
        }

        if (stockNode) {
            stockNode.className =
                'product-stock-status is-'
                +
                String(
                    state.key
                    || 'unavailable'
                );

            stockNode.textContent =
                String(
                    state.label
                    || 'Unavailable'
                );
        }

        renderQuantity(
            maxQuantity
        );

        if (
            addButton
            &&
            addButtonLabel
        ) {
            addButton.disabled =
                !purchasable;

            switch (
                String(
                    state.key
                    || 'unavailable'
                )
            ) {
                case 'preorder':
                    addButtonLabel
                        .textContent =
                            'Preorder';
                    break;

                case 'backorder':
                    addButtonLabel
                        .textContent =
                            'Backorder';
                    break;

                case 'out_of_stock':
                    addButtonLabel
                        .textContent =
                            'Out of stock';
                    break;

                case 'unavailable':
                    addButtonLabel
                        .textContent =
                            'Unavailable';
                    break;

                default:
                    addButtonLabel
                        .textContent =
                            'Add to cart';
                    break;
            }
        }
    };


    const render = () => {
        renderPills();
        renderVariant();
    };


    /* =====================================================
       SELECTION
       ===================================================== */

    const selectBestImageForVariant = (
        variant
    ) => {
        const index =
            bestImageIndexForVariant(
                variant
            );

        if (index >= 0) {
            showImage(
                index,
                {
                    syncVariant:
                        false,
                    scrollThumbnail:
                        true,
                }
            );
        }
    };


    const applyVariantSelection = (
        variant,
        {
            updateImage = true,
        } = {}
    ) => {
        if (!variant) {
            currentVariant =
                null;

            render();

            return;
        }

        currentVariant =
            variant;

        optionOrder.forEach(
            (name) => {
                if (
                    variant.options
                    &&
                    variant.options[name]
                        != null
                ) {
                    selected[name] =
                        String(
                            variant
                                .options[
                                    name
                                ]
                        );
                }
            }
        );

        render();

        if (updateImage) {
            selectBestImageForVariant(
                variant
            );
        }
    };


    const setSelectionValue = (
        name,
        value
    ) => {
        const variant =
            bestVariantForOption(
                name,
                value
            );

        if (variant) {
            applyVariantSelection(
                variant
            );

            return;
        }

        selected[name] =
            String(value);

        currentVariant =
            exactVariant(
                selected
            );

        render();
    };


    function showImage(
        index,
        {
            syncVariant = true,
            scrollThumbnail = true,
        } = {}
    ) {
        if (
            !images.length
            || !mainImage
        ) {
            return;
        }

        const normalized =
            (
                (
                    Number(index)
                    % images.length
                )
                + images.length
            )
            % images.length;

        const image =
            images[
                normalized
            ];

        currentImageIndex =
            normalized;

        mainImage.src =
            String(
                image.src
                || ''
            );

        mainImage.alt =
            String(
                image.alt
                || ''
            );

        thumbnails.forEach(
            (thumbnail) => {
                const active =
                    Number(
                        thumbnail
                            .dataset
                            .thumbnailIndex
                    )
                    === normalized;

                thumbnail
                    .classList.toggle(
                        'is-active',
                        active
                    );

                thumbnail
                    .setAttribute(
                        'aria-current',
                        active
                            ? 'true'
                            : 'false'
                    );
            }
        );

        if (scrollThumbnail) {
            const activeThumbnail =
                thumbnails.find(
                    (thumbnail) =>
                        Number(
                            thumbnail
                                .dataset
                                .thumbnailIndex
                        )
                        === normalized
                );

            activeThumbnail
                ?.scrollIntoView({
                    behavior:
                        'smooth',
                    block:
                        'nearest',
                    inline:
                        'center',
                });
        }

        if (!syncVariant) {
            return;
        }

        const variant =
            bestVariantForImage(
                image
            );

        if (variant) {
            applyVariantSelection(
                variant,
                {
                    updateImage:
                        false,
                }
            );
        }
    }


    /* =====================================================
       EVENTS
       ===================================================== */

    optionPills.forEach(
        (pill) => {
            pill.addEventListener(
                'click',
                () => {
                    setSelectionValue(
                        String(
                            pill.dataset
                                .optionName
                            || ''
                        ),
                        String(
                            pill.dataset
                                .optionValue
                            || ''
                        )
                    );
                }
            );
        }
    );


    thumbnails.forEach(
        (thumbnail) => {
            thumbnail.addEventListener(
                'click',
                () => {
                    showImage(
                        Number(
                            thumbnail.dataset
                                .thumbnailIndex
                            || 0
                        )
                    );
                }
            );
        }
    );


    root.querySelector(
        '[data-gallery-previous]'
    )?.addEventListener(
        'click',
        () => {
            showImage(
                currentImageIndex
                - 1
            );
        }
    );


    root.querySelector(
        '[data-gallery-next]'
    )?.addEventListener(
        'click',
        () => {
            showImage(
                currentImageIndex
                + 1
            );
        }
    );


    root.querySelector(
        '[data-thumbnails-previous]'
    )?.addEventListener(
        'click',
        () => {
            if (!thumbnailTrack) {
                showImage(
                    currentImageIndex
                    - 1
                );

                return;
            }

            showImage(
                Math.max(
                    0,
                    currentImageIndex
                    - 1
                )
            );
        }
    );


    root.querySelector(
        '[data-thumbnails-next]'
    )?.addEventListener(
        'click',
        () => {
            if (!thumbnailTrack) {
                showImage(
                    currentImageIndex
                    + 1
                );

                return;
            }

            showImage(
                Math.min(
                    images.length - 1,
                    currentImageIndex
                    + 1
                )
            );
        }
    );


    /* =====================================================
       INITIAL STATE
       ===================================================== */

    const initial =
        variantById(
            data.initialVariantId
        );

    if (initial) {
        applyVariantSelection(
            initial
        );

        return;
    }

    optionOrder.forEach(
        (name) => {
            const option =
                options.find(
                    (entry) =>
                        String(
                            entry.name
                            || ''
                        )
                        === name
                );

            const first =
                Array.isArray(
                    option?.values
                )
                    ? option.values[0]
                    : '';

            if (first) {
                selected[name] =
                    String(first);
            }
        }
    );

    currentVariant =
        exactVariant(
            selected
        );

    render();

    if (currentVariant) {
        selectBestImageForVariant(
            currentVariant
        );
    }
})();
