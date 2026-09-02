(() => {
    'use strict';

    const root = document.querySelector('[data-product-page]');

    if (!root) {
        return;
    }

    const dataNode = document.querySelector('#shop-product-data');

    if (!dataNode) {
        return;
    }

    let data;

    try {
        data = JSON.parse(dataNode.textContent || '{}');
    } catch (error) {
        console.error('Unable to read product configuration.', error);
        return;
    }

    const variants = Array.isArray(data.variants)
        ? data.variants
        : [];

    const options = Array.isArray(data.options)
        ? data.options
        : [];

    const images = Array.isArray(data.images)
        ? data.images
        : [];

    const optionOrder = options
        .map((option) => String(option.name || ''))
        .filter(Boolean);

    const selected = {};

    let currentVariant = null;
    let currentImageIndex = 0;

    const optionPills = Array.from(
        root.querySelectorAll('[data-option-pill]')
    );

    const mainImage = root.querySelector('[data-main-product-image]');

    const thumbnails = Array.from(
        root.querySelectorAll('[data-product-thumbnail]')
    );

    const priceNode = root.querySelector('[data-product-price]');
    const compareNode = root.querySelector('[data-product-compare-price]');
    const saleBadge = root.querySelector('[data-product-sale]');
    const stockNode = root.querySelector('[data-product-stock]');
    const variantInput = root.querySelector('[data-product-variant-input]');
    const quantitySelect = root.querySelector('[data-product-quantity]');
    const addButton = root.querySelector('[data-product-add-button]');
    const addButtonLabel = root.querySelector('[data-product-add-label]');

    const money = (cents, currency = 'USD') => {
        const amount = Number(cents || 0) / 100;

        try {
            return new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: String(currency || 'USD').toUpperCase(),
            }).format(amount);
        } catch (error) {
            return '$' + amount.toFixed(2);
        }
    };

    const variantById = (id) => (
        variants.find((variant) => Number(variant.id) === Number(id))
        || null
    );

    const exactVariant = (selection) => {
        return variants.find((variant) => {
            const pairs = variant.options || {};

            return optionOrder.every((name) => (
                String(pairs[name] || '')
                === String(selection[name] || '')
            ));
        }) || null;
    };

    const variantCanSell = (variant) => {
        if (!variant || !variant.active) {
            return false;
        }

        if (Number(variant.priceCents || 0) <= 0) {
            return false;
        }

        const state = variant.state || {};

        return Boolean(state.purchasable);
    };

    const variantMatches = (
        variant,
        candidateSelection,
        ignoredOption = ''
    ) => {
        const pairs = variant.options || {};

        return optionOrder.every((name) => {
            if (name === ignoredOption) {
                return true;
            }

            const wanted = String(candidateSelection[name] || '');

            if (!wanted) {
                return true;
            }

            return String(pairs[name] || '') === wanted;
        });
    };

    const applyVariantSelection = (
        variant,
        { updateImage = true } = {}
    ) => {
        if (!variant) {
            return;
        }

        currentVariant = variant;

        optionOrder.forEach((name) => {
            if (
                variant.options
                && variant.options[name] != null
            ) {
                selected[name] =
                    String(variant.options[name]);
            }
        });

        render();

        if (updateImage) {
            selectBestImageForVariant(variant);
        }
    };

    const setSelectionValue = (name, value) => {
        selected[name] = value;

        currentVariant =
            exactVariant(selected);

        render();

        if (currentVariant) {
            selectBestImageForVariant(currentVariant);
        }
    };

    const renderPills = () => {
        optionPills.forEach((pill) => {
            const name =
                String(pill.dataset.optionName || '');

            const value =
                String(pill.dataset.optionValue || '');

            const isSelected =
                String(selected[name] || '') === value;

            const candidateSelection = {
                ...selected,
                [name]: value,
            };

            const candidate =
                exactVariant(candidateSelection);

            let hasSellablePath =
                Boolean(
                    candidate
                    && variantCanSell(candidate)
                );

            if (!candidate) {
                hasSellablePath =
                    variants.some((variant) => {
                        if (!variantCanSell(variant)) {
                            return false;
                        }

                        const pairs =
                            variant.options || {};

                        return (
                            String(pairs[name] || '') === value
                            && variantMatches(
                                variant,
                                candidateSelection,
                                name
                            )
                        );
                    });
            }

            pill.classList.toggle(
                'is-selected',
                isSelected
            );

            pill.classList.toggle(
                'is-unavailable',
                !hasSellablePath
            );

            pill.setAttribute(
                'aria-pressed',
                isSelected ? 'true' : 'false'
            );

            pill.setAttribute(
                'aria-disabled',
                hasSellablePath
                    ? 'false'
                    : 'true'
            );
        });
    };

    const renderQuantity = (maxQuantity) => {
        if (!quantitySelect) {
            return;
        }

        const previous =
            Math.max(
                1,
                Number(quantitySelect.value || 1)
            );

        quantitySelect.innerHTML = '';

        if (maxQuantity < 1) {
            const option =
                document.createElement('option');

            option.value = '1';
            option.textContent = '1';

            quantitySelect.appendChild(option);
            quantitySelect.disabled = true;

            return;
        }

        quantitySelect.disabled = false;

        for (
            let quantity = 1;
            quantity <= maxQuantity;
            quantity += 1
        ) {
            const option =
                document.createElement('option');

            option.value = String(quantity);
            option.textContent = String(quantity);

            quantitySelect.appendChild(option);
        }

        quantitySelect.value =
            String(
                Math.min(
                    previous,
                    maxQuantity
                )
            );
    };

    const renderVariant = () => {
        const variant =
            currentVariant;

        const exact =
            Boolean(variant);

        const priceCents =
            exact
                ? Number(variant.priceCents || 0)
                : 0;

        const compareCents =
            exact
            && variant.compareAtPriceCents != null
                ? Number(
                    variant.compareAtPriceCents
                )
                : 0;

        const onSale =
            priceCents > 0
            && compareCents > priceCents;

        const state =
            exact
            && variant.state
                ? variant.state
                : {
                    key: 'unavailable',
                    label: 'Unavailable',
                    purchasable: false,
                };

        const purchasable =
            Boolean(
                exact
                && state.purchasable
                && priceCents > 0
            );

        const maxQuantity =
            purchasable
                ? Math.max(
                    1,
                    Number(
                        variant.maxQuantity || 1
                    )
                )
                : 0;

        if (variantInput) {
            variantInput.value =
                exact
                    ? String(variant.id)
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
                + String(
                    state.key
                    || 'unavailable'
                );

            stockNode.textContent =
                String(
                    state.label
                    || 'Unavailable'
                );
        }

        renderQuantity(maxQuantity);

        if (
            addButton
            && addButtonLabel
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
                    addButtonLabel.textContent =
                        'Preorder';
                    break;

                case 'out_of_stock':
                    addButtonLabel.textContent =
                        'Out of stock';
                    break;

                case 'unavailable':
                    addButtonLabel.textContent =
                        'Unavailable';
                    break;

                default:
                    addButtonLabel.textContent =
                        'Add to cart';
                    break;
            }
        }
    };

    const render = () => {
        renderPills();
        renderVariant();
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

        const optionName =
            String(
                image.optionName || ''
            );

        const optionValue =
            String(
                image.optionValue || ''
            );

        if (
            !optionName
            || !optionValue
        ) {
            return false;
        }

        if (
            optionName
            === '__variant__'
        ) {
            return (
                Number(optionValue)
                === Number(variant.id)
            );
        }

        return (
            String(
                (variant.options || {})[
                    optionName
                ]
                || ''
            )
            === optionValue
        );
    };

    const selectBestImageForVariant = (
        variant
    ) => {
        if (
            !images.length
            || !variant
        ) {
            return;
        }

        let index =
            images.findIndex(
                (image) => (
                    String(
                        image.optionName || ''
                    )
                    === '__variant__'
                    && Number(
                        image.optionValue
                    )
                    === Number(
                        variant.id
                    )
                )
            );

        if (index < 0) {
            index =
                images.findIndex(
                    (image) =>
                        imageMatchesVariant(
                            image,
                            variant
                        )
                );
        }

        if (index < 0) {
            index =
                images.findIndex(
                    (image) =>
                        !image.optionName
                        && !image.optionValue
                );
        }

        if (index >= 0) {
            showImage(
                index,
                {
                    syncVariant: false,
                    scrollThumbnail: true,
                }
            );
        }
    };

    const bestVariantForImage = (
        image
    ) => {
        const optionName =
            String(
                image.optionName || ''
            );

        const optionValue =
            String(
                image.optionValue || ''
            );

        if (
            !optionName
            || !optionValue
        ) {
            return null;
        }

        if (
            optionName
            === '__variant__'
        ) {
            return variantById(
                Number(optionValue)
            );
        }

        const matches =
            variants.filter(
                (variant) =>
                    String(
                        (variant.options || {})[
                            optionName
                        ]
                        || ''
                    )
                    === optionValue
            );

        if (!matches.length) {
            return null;
        }

        const exactCurrent =
            matches.find((variant) => {
                const pairs =
                    variant.options || {};

                return optionOrder.every(
                    (name) => {
                        if (
                            name
                            === optionName
                        ) {
                            return true;
                        }

                        return (
                            !selected[name]
                            || String(
                                pairs[name]
                                || ''
                            )
                            === String(
                                selected[name]
                            )
                        );
                    }
                );
            });

        if (exactCurrent) {
            return exactCurrent;
        }

        return (
            matches.find(
                variantCanSell
            )
            || matches.find(
                (variant) =>
                    variant.active
            )
            || matches[0]
        );
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
            images[normalized];

        currentImageIndex =
            normalized;

        mainImage.src =
            String(
                image.src || ''
            );

        mainImage.alt =
            String(
                image.alt || ''
            );

        thumbnails.forEach(
            (thumbnail) => {
                const active =
                    Number(
                        thumbnail.dataset
                            .thumbnailIndex
                    )
                    === normalized;

                thumbnail.classList.toggle(
                    'is-active',
                    active
                );

                thumbnail.setAttribute(
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
                            thumbnail.dataset
                                .thumbnailIndex
                        )
                        === normalized
                );

            activeThumbnail
                ?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'center',
                });
        }

        if (syncVariant) {
            const variant =
                bestVariantForImage(
                    image
                );

            if (variant) {
                applyVariantSelection(
                    variant,
                    {
                        updateImage: false,
                    }
                );
            }
        }
    }

    optionPills.forEach((pill) => {
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
    });

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
                currentImageIndex - 1
            );
        }
    );

    root.querySelector(
        '[data-gallery-next]'
    )?.addEventListener(
        'click',
        () => {
            showImage(
                currentImageIndex + 1
            );
        }
    );

    root.querySelector(
        '[data-thumbnails-previous]'
    )?.addEventListener(
        'click',
        () => {
            showImage(
                currentImageIndex - 1
            );
        }
    );

    root.querySelector(
        '[data-thumbnails-next]'
    )?.addEventListener(
        'click',
        () => {
            showImage(
                currentImageIndex + 1
            );
        }
    );

    const initial =
        variantById(
            data.initialVariantId
        );

    if (initial) {
        applyVariantSelection(
            initial
        );
    } else {
        optionOrder.forEach(
            (name) => {
                const option =
                    options.find(
                        (entry) =>
                            String(
                                entry.name || ''
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
    }
})();
