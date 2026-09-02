(() => {
    'use strict';

    const root = document.querySelector('[data-product-page]');
    const dataNode = document.getElementById('product-variant-data');

    if (!root || !dataNode) {
        return;
    }

    let data;

    try {
        data = JSON.parse(dataNode.textContent || '{}');
    } catch (error) {
        console.error('Llama Scout product data could not be read.', error);
        return;
    }

    const options = Array.isArray(data.options) ? data.options : [];
    const variants = Array.isArray(data.variants) ? data.variants : [];
    const images = Array.isArray(data.images) ? data.images : [];

    const optionOrder = options
        .map((option) => String(option.name || ''))
        .filter(Boolean);

    const selected = {};
    let currentVariant = null;
    let currentImageIndex = 0;
    let activeImageCriteria = null;

    const variantInput = root.querySelector('[data-selected-variant]');
    const quantitySelect = root.querySelector('[data-product-quantity]');
    const addButton = root.querySelector('[data-add-to-cart]');
    const addButtonLabel = root.querySelector('[data-add-to-cart-label]');
    const priceNode = root.querySelector('[data-product-price]');
    const compareNode = root.querySelector('[data-compare-price]');
    const saleBadge = root.querySelector('[data-sale-badge]');
    const stockNode = root.querySelector('[data-stock-status]');
    const mainImage = root.querySelector('[data-main-product-image]');
    const thumbnailTrack = root.querySelector('[data-thumbnail-track]');
    const thumbnails = Array.from(
        root.querySelectorAll('[data-thumbnail-index]')
    );
    const optionPills = Array.from(
        root.querySelectorAll('.product-option-pill')
    );

    const money = (cents, currency = 'usd') => {
        const amount = Number(cents || 0) / 100;

        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: String(currency || 'usd').toUpperCase(),
            }).format(amount);
        } catch (_) {
            return '$' + amount.toFixed(2);
        }
    };

    const variantById = (id) => (
        variants.find(
            (variant) => Number(variant.id) === Number(id)
        )
        || null
    );

    const exactVariant = (selection = selected) => {
        if (!optionOrder.length) {
            return variants[0] || null;
        }

        return variants.find((variant) => {
            const pairs = variant.options || {};

            return optionOrder.every((name) => (
                String(pairs[name] || '')
                === String(selection[name] || '')
            ));
        }) || null;
    };

    const variantMatchesSelection = (
        variant,
        wanted,
        ignoredOption = ''
    ) => {
        const pairs = variant.options || {};

        return optionOrder.every((name) => {
            if (name === ignoredOption) {
                return true;
            }

            if (!wanted[name]) {
                return true;
            }

            return (
                String(pairs[name] || '')
                === String(wanted[name])
            );
        });
    };

    const variantCanSell = (variant) => {
        if (!variant || !variant.active) {
            return false;
        }

        return Boolean(
            variant.state
            && variant.state.purchasable
            && Number(variant.priceCents || 0) > 0
        );
    };

    const criteriaForImage = (image) => {
        if (
            !image
            || !image.criteria
            || typeof image.criteria !== 'object'
        ) {
            return {};
        }

        const criteria = {};

        Object.entries(image.criteria).forEach(
            ([name, values]) => {
                const list = Array.isArray(values)
                    ? values
                    : [values];

                const clean = list
                    .map(
                        (value) =>
                            String(value || '').trim()
                    )
                    .filter(Boolean);

                if (clean.length) {
                    criteria[String(name)] = clean;
                }
            }
        );

        return criteria;
    };

    const imageMatchesVariant = (
        image,
        variant
    ) => {
        if (!image || !variant) {
            return false;
        }

        const criteria =
            criteriaForImage(image);

        if (criteria.__variant__) {
            return criteria.__variant__.some(
                (value) =>
                    Number(value)
                    === Number(variant.id)
            );
        }

        const pairs =
            variant.options || {};

        return Object.entries(criteria).every(
            ([name, values]) => {
                if (name === '__variant__') {
                    return true;
                }

                return values.includes(
                    String(pairs[name] || '')
                );
            }
        );
    };

    const imageSpecificity = (image) => {
        const criteria =
            criteriaForImage(image);

        if (criteria.__variant__) {
            return 10000;
        }

        let groups = 0;
        let breadth = 0;

        Object.values(criteria).forEach(
            (values) => {
                groups += 1;
                breadth += values.length;
            }
        );

        return (groups * 100) - breadth;
    };

    const bestImageIndexForVariant = (
        variant
    ) => {
        if (!variant || !images.length) {
            return -1;
        }

        const currentImage =
            images[currentImageIndex];

        if (
            currentImage
            && imageMatchesVariant(
                currentImage,
                variant
            )
        ) {
            return currentImageIndex;
        }

        const matching = images
            .map((image, index) => ({
                image,
                index,
                score:
                    imageSpecificity(image),
            }))
            .filter((entry) => (
                Object.keys(
                    criteriaForImage(entry.image)
                ).length > 0
                && imageMatchesVariant(
                    entry.image,
                    variant
                )
            ))
            .sort(
                (a, b) =>
                    b.score - a.score
                    || a.index - b.index
            );

        if (matching.length) {
            return matching[0].index;
        }

        const primary =
            images.findIndex(
                (image) => image.primary
            );

        if (primary >= 0) {
            return primary;
        }

        const general =
            images.findIndex(
                (image) =>
                    Object.keys(
                        criteriaForImage(image)
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
            || !Object.keys(criteria).length
        ) {
            return true;
        }

        if (criteria.__variant__) {
            return criteria.__variant__.some(
                (value) =>
                    Number(value)
                    === Number(variant.id)
            );
        }

        const pairs =
            variant.options || {};

        return Object.entries(criteria).every(
            ([name, values]) => {
                if (name === '__variant__') {
                    return true;
                }

                return values.includes(
                    String(pairs[name] || '')
                );
            }
        );
    };

    const applyImageCriteriaToSelection = (
        criteria
    ) => {
        if (
            !criteria
            || !Object.keys(criteria).length
        ) {
            return;
        }

        if (criteria.__variant__) {
            const variant =
                variantById(
                    criteria.__variant__[0]
                );

            if (variant) {
                optionOrder.forEach(
                    (name) => {
                        selected[name] =
                            String(
                                (
                                    variant.options
                                    || {}
                                )[name]
                                || ''
                            );
                    }
                );
            }

            return;
        }

        optionOrder.forEach((name) => {
            const allowed =
                criteria[name];

            if (
                !Array.isArray(allowed)
                || !allowed.length
            ) {
                return;
            }

            if (
                !allowed.includes(
                    String(
                        selected[name]
                        || ''
                    )
                )
            ) {
                selected[name] =
                    String(allowed[0]);
            }
        });
    };

    const bestVariantForImageCriteria = (
        criteria
    ) => {
        if (
            !criteria
            || !Object.keys(criteria).length
        ) {
            return currentVariant;
        }

        if (criteria.__variant__) {
            return variantById(
                criteria.__variant__[0]
            );
        }

        const direct =
            exactVariant(selected);

        if (
            direct
            && variantMatchesImageCriteria(
                direct,
                criteria
            )
        ) {
            return direct;
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

        const preservesMost =
            matches
                .map((variant) => {
                    const pairs =
                        variant.options || {};

                    let score = 0;

                    optionOrder.forEach(
                        (name) => {
                            if (
                                selected[name]
                                && String(
                                    pairs[name]
                                    || ''
                                )
                                === String(
                                    selected[name]
                                )
                            ) {
                                score += 1;
                            }
                        }
                    );

                    return {
                        variant,
                        score,
                    };
                })
                .sort((a, b) => (
                    b.score - a.score
                    || Number(
                        variantCanSell(
                            b.variant
                        )
                    )
                    - Number(
                        variantCanSell(
                            a.variant
                        )
                    )
                ));

        return (
            preservesMost[0]?.variant
            || matches[0]
        );
    };

    const renderQuantity = (
        maxQuantity
    ) => {
        if (!quantitySelect) {
            return;
        }

        const previous = Math.max(
            1,
            Number(
                quantitySelect.value
                || 1
            )
        );

        quantitySelect.innerHTML = '';

        if (maxQuantity < 1) {
            const option =
                document.createElement(
                    'option'
                );

            option.value = '1';
            option.textContent = '1';

            quantitySelect.appendChild(
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

            quantitySelect.appendChild(
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
        optionPills.forEach((pill) => {
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

            const candidateSelection = {
                ...selected,
                [name]: value,
            };

            const exact =
                exactVariant(
                    candidateSelection
                );

            let hasVariantPath =
                Boolean(exact);

            if (!hasVariantPath) {
                hasVariantPath =
                    variants.some(
                        (variant) => {
                            const pairs =
                                variant.options
                                || {};

                            return (
                                String(
                                    pairs[name]
                                    || ''
                                )
                                === value
                                && variantMatchesSelection(
                                    variant,
                                    candidateSelection,
                                    name
                                )
                            );
                        }
                    );
            }

            let allowedByPhoto =
                true;

            if (
                activeImageCriteria
                && Array.isArray(
                    activeImageCriteria[
                        name
                    ]
                )
                && activeImageCriteria[
                    name
                ].length
            ) {
                allowedByPhoto =
                    activeImageCriteria[
                        name
                    ].includes(value);
            }

            if (
                activeImageCriteria
                    ?.__variant__
            ) {
                const lockedVariant =
                    variantById(
                        activeImageCriteria
                            .__variant__[0]
                    );

                allowedByPhoto =
                    Boolean(
                        lockedVariant
                        && String(
                            (
                                lockedVariant
                                    .options
                                || {}
                            )[name]
                            || ''
                        )
                        === value
                    );
            }

            const sellablePath =
                variants.some(
                    (variant) => {
                        if (
                            !variantCanSell(
                                variant
                            )
                        ) {
                            return false;
                        }

                        const pairs =
                            variant.options
                            || {};

                        return (
                            String(
                                pairs[name]
                                || ''
                            )
                            === value
                            && variantMatchesSelection(
                                variant,
                                candidateSelection,
                                name
                            )
                        );
                    }
                );

            pill.classList.toggle(
                'is-selected',
                isSelected
            );

            pill.classList.toggle(
                'is-unavailable',
                !hasVariantPath
                || !sellablePath
                || !allowedByPhoto
            );

            pill.setAttribute(
                'aria-pressed',
                isSelected
                    ? 'true'
                    : 'false'
            );
        });
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
            && variant
                .compareAtPriceCents
                != null
                ? Number(
                    variant
                        .compareAtPriceCents
                )
                : 0;

        const onSale =
            priceCents > 0
            && compareCents
                > priceCents;

        const state =
            exact
            && variant.state
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
                && state.purchasable
                && priceCents > 0
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

        renderQuantity(
            maxQuantity
        );

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
                    addButtonLabel
                        .textContent =
                            'Preorder';
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

    const selectBestImageForVariant =
        (variant) => {
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
                        useImageCriteria:
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
            currentVariant = null;
            render();
            return;
        }

        currentVariant = variant;

        optionOrder.forEach(
            (name) => {
                if (
                    variant.options
                    && variant
                        .options[name]
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
        activeImageCriteria =
            null;

        selected[name] =
            value;

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
    };

    function showImage(
        index,
        {
            syncVariant = true,
            useImageCriteria = true,
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
                    .classList
                    .toggle(
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

        if (
            scrollThumbnail
        ) {
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

        const criteria =
            criteriaForImage(
                image
            );

        activeImageCriteria =
            useImageCriteria
                ? criteria
                : null;

        if (
            !Object.keys(
                criteria
            ).length
        ) {
            render();
            return;
        }

        applyImageCriteriaToSelection(
            criteria
        );

        const variant =
            bestVariantForImageCriteria(
                criteria
            );

        if (variant) {
            applyVariantSelection(
                variant,
                {
                    updateImage:
                        false,
                }
            );
        } else {
            currentVariant =
                exactVariant(
                    selected
                );

            render();
        }
    }

    optionPills.forEach(
        (pill) => {
            pill.addEventListener(
                'click',
                () => {
                    setSelectionValue(
                        String(
                            pill
                                .dataset
                                .optionName
                            || ''
                        ),
                        String(
                            pill
                                .dataset
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
            thumbnail
                .addEventListener(
                    'click',
                    () => {
                        showImage(
                            Number(
                                thumbnail
                                    .dataset
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
            if (!thumbnailTrack) {
                showImage(
                    currentImageIndex - 1
                );
                return;
            }

            const target =
                Math.max(
                    0,
                    currentImageIndex - 1
                );

            showImage(target);
        }
    );

    root.querySelector(
        '[data-thumbnails-next]'
    )?.addEventListener(
        'click',
        () => {
            if (!thumbnailTrack) {
                showImage(
                    currentImageIndex + 1
                );
                return;
            }

            const target =
                Math.min(
                    images.length - 1,
                    currentImageIndex + 1
                );

            showImage(target);
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
                                entry.name
                                || ''
                            )
                            === name
                    );

                const first =
                    Array.isArray(
                        option?.values
                    )
                        ? option
                            .values[0]
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
    }
})();
