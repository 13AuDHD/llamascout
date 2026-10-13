(() => {
    'use strict';

    const mount =
        document.querySelector(
            '[data-place-campsites-mount]'
        );

    if (!mount) {
        return;
    }

    const slug =
        String(
            mount.dataset.placeSlug
            || ''
        ).trim();

    if (!slug) {
        return;
    }

    const cache =
        new Map();

    let activeId = '';

    const clean = (value) =>
        String(
            value ?? ''
        ).trim();

    const escapeHtml = (value) => {
        const node =
            document.createElement(
                'div'
            );

        node.textContent =
            value ?? '';

        return node.innerHTML;
    };

    const smartCase = (value) => {
        const text =
            clean(value);

        if (!text) {
            return '';
        }

        const letters =
            text.replace(
                /[^A-Za-z]/g,
                ''
            );

        if (
            letters
            && letters
                === letters.toUpperCase()
        ) {
            return text
                .toLowerCase()
                .replace(
                    /\b[a-z]/g,
                    (letter) =>
                        letter.toUpperCase()
                )
                .replace(/\bRv\b/g, 'RV')
                .replace(/\bUsfs\b/g, 'USFS')
                .replace(/\bRidb\b/g, 'RIDB');
        }

        return text;
    };

    const yesNo = (value) => {
        if (
            value === true
            || value === 1
        ) {
            return 'Yes';
        }

        if (
            value === false
            || value === 0
        ) {
            return 'No';
        }

        const text =
            clean(value)
                .toLowerCase();

        if (
            [
                'yes',
                'y',
                'true',
                '1',
            ].includes(text)
        ) {
            return 'Yes';
        }

        if (
            [
                'no',
                'n',
                'false',
                '0',
            ].includes(text)
        ) {
            return 'No';
        }

        return smartCase(
            value
        );
    };

    const featureRows = (
        site,
        key
    ) => {
        const features =
            Array.isArray(
                site?.features
            )
                ? site.features
                : [];

        return features.filter(
            (feature) =>
                clean(
                    feature.feature_key
                ) === key
        );
    };

    const firstFeature = (
        site,
        key
    ) => {
        const row =
            featureRows(
                site,
                key
            )[0];

        return clean(
            row?.feature_value
        );
    };

    const infoCard = (
        label,
        value
    ) => {
        const text =
            clean(value);

        if (!text) {
            return '';
        }

        return `
            <div class="place-campsite-source-card">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(text)}</strong>
            </div>
        `;
    };

    const equipmentMarkup = (
        site
    ) => {
        const rows =
            featureRows(
                site,
                'permitted_equipment'
            );

        if (!rows.length) {
            return '';
        }

        const items =
            rows
                .map((row) => {
                    const name =
                        smartCase(
                            row.feature_value
                        );

                    const qualifier =
                        clean(
                            row.qualifier
                        );

                    if (!name) {
                        return '';
                    }

                    return `
                        <div class="place-campsite-equipment-card">
                            <strong>${escapeHtml(name)}</strong>
                            ${
                                qualifier
                                    ? `<span>${escapeHtml(qualifier)}</span>`
                                    : ''
                            }
                        </div>
                    `;
                })
                .filter(Boolean)
                .join('');

        if (!items) {
            return '';
        }

        return `
            <section class="place-campsite-rich-section">
                <h4>Permitted equipment</h4>

                <div class="place-campsite-equipment-grid">
                    ${items}
                </div>
            </section>
        `;
    };

    const galleryMarkup = (
        images
    ) => {
        if (
            !Array.isArray(images)
            || !images.length
        ) {
            return '';
        }

        const items =
            images
                .map((image) => {
                    const src =
                        clean(
                            image.src
                        );

                    if (!src) {
                        return '';
                    }

                    const alt =
                        clean(
                            image.alt_text
                        )
                        || 'Campsite photo';

                    const caption =
                        clean(
                            image.caption
                        );

                    const credit =
                        clean(
                            image.source_credit
                        );

                    const sourceUrl =
                        clean(
                            image.source_url
                        )
                        || src;

                    return `
                        <figure class="place-campsite-gallery-item">
                            <a
                                href="${escapeHtml(sourceUrl)}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <img
                                    src="${escapeHtml(src)}"
                                    alt="${escapeHtml(alt)}"
                                    loading="lazy"
                                >
                            </a>

                            ${
                                caption || credit
                                    ? `
                                        <figcaption>
                                            ${
                                                caption
                                                    ? `<span>${escapeHtml(caption)}</span>`
                                                    : ''
                                            }

                                            ${
                                                credit
                                                    ? `<small>Credit: ${escapeHtml(credit)}</small>`
                                                    : ''
                                            }
                                        </figcaption>
                                    `
                                    : ''
                            }
                        </figure>
                    `;
                })
                .filter(Boolean)
                .join('');

        if (!items) {
            return '';
        }

        return `
            <section class="place-campsite-rich-section">
                <div class="place-campsite-rich-heading">
                    <h4>Site photos</h4>

                    <span>
                        ${images.length.toLocaleString()}
                        ${
                            images.length === 1
                                ? 'photo'
                                : 'photos'
                        }
                    </span>
                </div>

                <div class="place-campsite-gallery">
                    ${items}
                </div>
            </section>
        `;
    };

    const sourceMarkup = (
        site
    ) => {
        const cards = [
            infoCard(
                'Loop',
                firstFeature(
                    site,
                    'ridb_loop'
                )
            ),
            infoCard(
                'Type of use',
                smartCase(
                    firstFeature(
                        site,
                        'ridb_type_of_use'
                    )
                )
            ),
            infoCard(
                'Reservable',
                yesNo(
                    firstFeature(
                        site,
                        'ridb_reservable'
                    )
                )
            ),
            infoCard(
                'RIDB updated',
                firstFeature(
                    site,
                    'ridb_last_updated'
                )
            )
        ]
            .filter(Boolean)
            .join('');

        if (!cards) {
            return '';
        }

        return `
            <section class="place-campsite-rich-section">
                <div class="place-campsite-rich-heading">
                    <h4>Official site information</h4>

                    <span>Recreation.gov / RIDB</span>
                </div>

                <div class="place-campsite-source-grid">
                    ${cards}
                </div>
            </section>
        `;
    };

    const render = (
        detail,
        payload
    ) => {
        detail
            .querySelectorAll(
                '[data-campsite-rich-detail]'
            )
            .forEach(
                (node) =>
                    node.remove()
            );

        const gallery =
            galleryMarkup(
                payload.images
            );

        const equipment =
            equipmentMarkup(
                payload.site
            );

        const source =
            sourceMarkup(
                payload.site
            );

        const markup =
            gallery
            + equipment
            + source;

        if (!markup) {
            return;
        }

        const wrapper =
            document.createElement(
                'div'
            );

        wrapper.dataset
            .campsiteRichDetail =
            '1';

        wrapper.className =
            'place-campsite-rich-detail';

        wrapper.innerHTML =
            markup;

        const grid =
            detail.querySelector(
                '.place-campsite-detail-grid'
            );

        if (grid) {
            detail.insertBefore(
                wrapper,
                grid
            );
        } else {
            detail.appendChild(
                wrapper
            );
        }
    };

    const load = async (
        campsiteId
    ) => {
        if (!campsiteId) {
            return;
        }

        const detail =
            mount.querySelector(
                `[data-campsite-detail-id="${CSS.escape(campsiteId)}"]`
            );

        if (!detail) {
            return;
        }

        if (
            cache.has(
                campsiteId
            )
        ) {
            render(
                detail,
                cache.get(
                    campsiteId
                )
            );

            return;
        }

        try {
            const response =
                await fetch(
                    `/api/place-campsite-detail.php?slug=${encodeURIComponent(slug)}&site=${encodeURIComponent(campsiteId)}`,
                    {
                        cache: 'no-store',
                        credentials: 'same-origin'
                    }
                );

            if (!response.ok) {
                return;
            }

            const payload =
                await response.json();

            if (!payload.ok) {
                return;
            }

            cache.set(
                campsiteId,
                payload
            );

            const currentDetail =
                mount.querySelector(
                    `[data-campsite-detail-id="${CSS.escape(campsiteId)}"]`
                );

            if (currentDetail) {
                render(
                    currentDetail,
                    payload
                );
            }

        } catch (error) {
            console.error(
                'Llama Scout campsite detail error:',
                error
            );
        }
    };

    const refresh = () => {
        const detail =
            mount.querySelector(
                '[data-campsite-detail-id]'
            );

        if (!detail) {
            return;
        }

        const campsiteId =
            clean(
                detail.dataset
                    .campsiteDetailId
            );

        if (
            !campsiteId
            || campsiteId
                === activeId
        ) {
            return;
        }

        activeId =
            campsiteId;

        load(
            campsiteId
        );
    };

    const observer =
        new MutationObserver(
            refresh
        );

    observer.observe(
        mount,
        {
            childList: true,
            subtree: true
        }
    );

    mount.addEventListener(
        'click',
        (event) => {
            const button =
                event.target.closest(
                    '[data-campsite-id]'
                );

            if (!button) {
                return;
            }

            window.setTimeout(
                refresh,
                0
            );
        }
    );

    refresh();
})();
