(() => {
    'use strict';

    const form =
        document.querySelector(
            '#place-report'
        );

    const configNode =
        form?.querySelector(
            '[data-admin-place-photo-targets]'
        );

    if (!form || !configNode) {
        return;
    }

    let config = {
        place_id: 0,
        targets: {}
    };

    try {
        config = {
            ...config,
            ...JSON.parse(
                configNode.textContent
                || '{}'
            )
        };
    } catch (_) {
        return;
    }

    const targets =
        config.targets
        && typeof config.targets === 'object'
            ? config.targets
            : {};

    const placeId =
        Number(
            config.place_id
            || 0
        );

    const areaSelector =
        form.querySelector(
            '[data-admin-area-selector]'
        );

    const siteSelector =
        form.querySelector(
            '[data-admin-campsite-selector]'
        );

    const photosField =
        form.querySelector(
            '[name="photos_json"]'
        );

    const uploader =
        form.querySelector(
            '[data-photo-uploader]'
        );

    if (!uploader) {
        return;
    }

    const stagedPhotoCount =
        () => {
            if (!photosField) {
                return 0;
            }

            try {
                const decoded =
                    JSON.parse(
                        photosField.value
                        || '[]'
                    );

                return Array.isArray(decoded)
                    ? decoded.length
                    : 0;
            } catch (_) {
                return 0;
            }
        };

    const currentTargetKey =
        () => {
            const siteValue =
                String(
                    siteSelector?.value
                    || ''
                ).trim();

            if (siteValue !== '') {
                return 'site:' + siteValue;
            }

            const areaValue =
                String(
                    areaSelector?.value
                    || ''
                ).trim();

            if (areaValue !== '') {
                return 'area:' + areaValue;
            }

            return 'place:place:' + placeId;
        };

    const currentTarget =
        () =>
            targets[currentTargetKey()]
            || targets['place:place:' + placeId]
            || null;

    const photoBody =
        uploader.closest(
            '.contribution-section-body'
        );

    if (!photoBody) {
        return;
    }

    let existingShell =
        photoBody.querySelector(
            '.add-place-existing-photos'
        );

    if (!existingShell) {
        existingShell =
            document.createElement(
                'div'
            );

        existingShell.className =
            'add-place-existing-photos';

        existingShell.innerHTML = `
            <strong>Photos already attached</strong>
            <p data-admin-photo-target-help></p>
            <div class="add-place-existing-photo-grid"></div>
        `;

        photoBody.insertBefore(
            existingShell,
            uploader
        );
    }

    let help =
        existingShell.querySelector(
            '[data-admin-photo-target-help]'
        );

    if (!help) {
        help =
            existingShell.querySelector(
                'p'
            );
    }

    const grid =
        existingShell.querySelector(
            '.add-place-existing-photo-grid'
        );

    if (!grid) {
        return;
    }

    const makePhoto =
        (photo) => {
            const label =
                document.createElement(
                    'label'
                );

            label.className =
                'add-place-existing-photo';

            const image =
                document.createElement(
                    'img'
                );

            image.src =
                String(
                    photo.url
                    || photo.src
                    || ''
                );

            image.alt =
                String(
                    photo.alt
                    || ''
                );

            image.loading =
                'lazy';

            const row =
                document.createElement(
                    'span'
                );

            const checkbox =
                document.createElement(
                    'input'
                );

            checkbox.type =
                'checkbox';

            checkbox.name =
                'remove_existing_photos[]';

            checkbox.value =
                String(
                    photo.src
                    || ''
                );

            row.append(
                checkbox,
                document.createTextNode(
                    ' Remove this photo'
                )
            );

            label.append(
                image,
                row
            );

            return label;
        };

    const render =
        () => {
            const target =
                currentTarget();

            grid.replaceChildren();

            const photos =
                Array.isArray(
                    target?.photos
                )
                    ? target.photos
                    : [];

            existingShell.hidden =
                false;

            if (help) {
                help.textContent =
                    target
                        ? (
                            'Showing photos attached to '
                            + String(
                                target.label
                                || 'this reporting target'
                            )
                            + '.'
                        )
                        : 'No reporting target is selected.';
            }

            if (photos.length < 1) {
                const empty =
                    document.createElement(
                        'p'
                    );

                empty.className =
                    'admin-place-muted';

                empty.textContent =
                    'No photos are attached to this reporting target yet.';

                grid.appendChild(
                    empty
                );
            } else {
                photos.forEach(
                    (photo) => {
                        if (
                            !photo
                            || !String(
                                photo.src
                                || ''
                            ).trim()
                        ) {
                            return;
                        }

                        grid.appendChild(
                            makePhoto(
                                photo
                            )
                        );
                    }
                );
            }

            const heading =
                uploader.querySelector(
                    '.llama-photo-heading h3'
                );

            const description =
                uploader.querySelector(
                    '.llama-photo-heading p'
                );

            if (heading && target) {
                heading.textContent =
                    'Photos for '
                    + String(
                        target.label
                        || 'selected target'
                    );
            }

            if (description) {
                description.textContent =
                    'Add photos to the currently selected reporting target.';
            }
        };

    let previousArea =
        String(
            areaSelector?.value
            || ''
        );

    let previousSite =
        String(
            siteSelector?.value
            || ''
        );

    let restoring =
        false;

    form.addEventListener(
        'change',
        (event) => {
            const control =
                event.target;

            if (
                restoring
                || !(control instanceof HTMLSelectElement)
                || (
                    control !== areaSelector
                    && control !== siteSelector
                )
            ) {
                return;
            }

            if (stagedPhotoCount() > 0) {
                restoring =
                    true;

                if (control === areaSelector) {
                    control.value =
                        previousArea;
                } else {
                    control.value =
                        previousSite;
                }

                restoring =
                    false;

                event.stopImmediatePropagation();

                window.alert(
                    'Save or remove the staged photos before changing the reporting target. This keeps photos from being attached to the wrong Place, Area, or Site.'
                );

                return;
            }

            previousArea =
                String(
                    areaSelector?.value
                    || ''
                );

            previousSite =
                String(
                    siteSelector?.value
                    || ''
                );

            queueMicrotask(
                render
            );
        },
        true
    );

    window.addEventListener(
        'llama:admin-place-report-saved',
        () => {
            /*
             * The server has committed staged photos. The current page catalog
             * is intentionally not guessed forward; a reload will fetch the
             * authoritative saved gallery.
             */
            render();
        }
    );

    render();
})();
