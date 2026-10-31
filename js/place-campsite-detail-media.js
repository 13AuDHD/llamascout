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

    const equipmentMaximumLength = (
        site,
        rows
    ) => {
        const direct =
            Number(
                site?.max_vehicle_length_ft
            );

        if (
            Number.isFinite(direct)
            && direct > 0
        ) {
            return direct;
        }

        let maximum = 0;

        rows.forEach((row) => {
            const qualifier =
                clean(
                    row?.qualifier
                );

            const match =
                qualifier.match(
                    /(\d+(?:\.\d+)?)\s*ft/i
                );

            if (!match) {
                return;
            }

            const value =
                Number(
                    match[1]
                );

            if (
                Number.isFinite(value)
                && value > maximum
            ) {
                maximum = value;
            }
        });

        return maximum > 0
            ? maximum
            : null;
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

        const names =
            Array.from(
                new Set(
                    rows
                        .map(
                            (row) =>
                                smartCase(
                                    row.feature_value
                                )
                        )
                        .filter(Boolean)
                )
            );

        if (!names.length) {
            return '';
        }

        const pills =
            names
                .map(
                    (name) =>
                        `
                            <span class="place-campsite-equipment-pill">
                                ${escapeHtml(name)}
                            </span>
                        `
                )
                .join('');

        return `
            <section class="place-campsite-rich-section">
                <div class="place-campsite-rich-heading">
                    <h4>Permitted equipment</h4>

                </div>

                <div class="place-campsite-equipment-pills">
                    ${pills}
                </div>
            </section>
        `;
    };

    const galleryMarkup = (
        images,
        canOpenPhotos = false
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

                    const imageMarkup =
                        canOpenPhotos
                            ? `
                                <a
                                    href="${escapeHtml(sourceUrl)}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    data-photo-caption="${escapeHtml(caption)}"
                                    data-photo-credit="${escapeHtml(credit)}"
                                >
                                    <img
                                        src="${escapeHtml(src)}"
                                        alt="${escapeHtml(alt)}"
                                        loading="lazy"
                                    >
                                </a>
                            `
                            : `
                                <div class="place-campsite-gallery-preview">
                                    <img
                                        src="${escapeHtml(src)}"
                                        alt="${escapeHtml(alt)}"
                                        loading="lazy"
                                    >
                                </div>
                            `;

                    return `
                        <figure class="place-campsite-gallery-item${canOpenPhotos ? '' : ' is-preview'}">
                            ${imageMarkup}

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

                <div class="place-campsite-gallery${canOpenPhotos ? '' : ' is-preview'}">
                    ${items}
                </div>

                ${
                    canOpenPhotos
                        ? ''
                        : '<p class="place-campsite-photo-access-note">Sign in with a free account to enlarge and browse these Site photos.</p>'
                }
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

    const polishCoreDetail = (
        detail
    ) => {
        detail
            .querySelectorAll(
                '.place-campsite-card'
            )
            .forEach((card) => {
                const label =
                    clean(
                        card
                            .querySelector('span')
                            ?.textContent
                    );

                const valueNode =
                    card.querySelector(
                        'strong'
                    );

                const value =
                    clean(
                        valueNode?.textContent
                    );

                if (
                    label.toLowerCase()
                    === 'total sites'
                ) {
                    card.remove();
                    return;
                }

                if (
                    valueNode
                    && /^no\s+ft$/i.test(value)
                ) {
                    valueNode.textContent =
                        'No';
                }
            });

        detail
            .querySelectorAll(
                '.place-campsite-card-section'
            )
            .forEach((section) => {
                if (
                    !section.querySelector(
                        '.place-campsite-card'
                    )
                ) {
                    section.remove();
                }
            });
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

        polishCoreDetail(
            detail
        );

        const gallery =
            galleryMarkup(
                payload.images,
                payload.photo_access?.can_open === true
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

            /*
             * Canonical campsite facts belong in the Scout Report below.
             * Keep this upper area only for site photos, permitted equipment,
             * and genuinely source-specific RIDB metadata.
             */
            grid.remove();
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

        const loading = document.createElement('p');
            loading.className = 'place-campsite-photo-loading';
            loading.textContent = 'Loading Site photos…';
            loading.setAttribute('role', 'status');
        
        detail.appendChild(loading);

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
            
        } finally {
            loading.remove();
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

        if (!campsiteId) {
            return;
        }
        
        if (
            campsiteId === activeId
            && detail.querySelector(
                '[data-campsite-rich-detail], .place-campsite-photo-loading'
            )
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
