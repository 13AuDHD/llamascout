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

    const tokenField =
        form.querySelector(
            '[name="photo_stage_token"]'
        );

    const csrfField =
        form.querySelector(
            '[name="csrf_token"]'
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
        () => {
            const key =
                currentTargetKey();

            /*
             * Never fall back to the Place gallery when an Area or Site is
             * selected. A missing scoped target means that selection has no
             * gallery, not that the previously viewed Place/Site photos should
             * remain visible.
             */
            if (
                key.startsWith('site:')
                || key.startsWith('area:')
            ) {
                return targets[key]
                    || null;
            }

            return targets[key]
                || targets['place:place:' + placeId]
                || null;
        };

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

    // The shared uploader initializes in the Admin footer, after this
    // controller. Resolve its controls at use time, not before they exist.
    const photoInput = () =>
        uploader.querySelector('[data-photo-files]');

    const photoDrop = () =>
        uploader.querySelector('[data-photo-drop]');

    // Stage and commit are separate operations. Saving photos must not
    // revalidate or rewrite hundreds of unrelated Scout Report answers.
    const photoSavePanel =
        document.createElement('div');
    photoSavePanel.className =
        'admin-place-photo-actions';

    const photoSaveButton =
        document.createElement('button');
    photoSaveButton.type = 'button';
    photoSaveButton.className = 'admin-button';
    photoSaveButton.textContent = 'Save selected photos';

    const photoSaveStatus =
        document.createElement('p');
    photoSaveStatus.className = 'admin-place-muted';
    photoSaveStatus.setAttribute('role', 'status');
    photoSaveStatus.setAttribute('aria-live', 'polite');

    photoSavePanel.append(photoSaveButton, photoSaveStatus);
    uploader.insertAdjacentElement('afterend', photoSavePanel);

    let savingPhotos = false;
    const reportSaveButton = form.querySelector('[data-place-report-admin-save]');

    const updatePhotoSaveButton = () => {
        const target = currentTarget();
        photoSaveButton.disabled =
            savingPhotos
            || uploader.getAttribute('aria-busy') === 'true'
            || reportSaveButton?.classList.contains('is-saving')
            || !target
            || target.supported === false
            || stagedPhotoCount() < 1;
        photoSaveButton.textContent =
            savingPhotos
                ? 'Saving photos...'
                : 'Save photos to ' + String(target?.label || 'selected location');
    };

    form.addEventListener(
        'llama:photo-staging-changed',
        updatePhotoSaveButton
    );

    photoSaveButton.addEventListener('click', async () => {
        if (savingPhotos) {
            return;
        }

        if (uploader.getAttribute('aria-busy') === 'true'
            || reportSaveButton?.classList.contains('is-saving')) {
            photoSaveStatus.textContent = 'Finish the current upload or report save first.';
            return;
        }

        // The uploader writes its current in-memory photos synchronously.
        form.dispatchEvent(new CustomEvent('llama:photo-uploader-sync'));
        const count = stagedPhotoCount();
        const targetKey = currentTargetKey();
        const target = currentTarget();

        if (!target || target.supported === false || count < 1) {
            photoSaveStatus.textContent =
                'Select a Place, Area, or saved Site and add a photo first.';
            return;
        }

        const payload = new FormData();
        payload.set('place_admin_action', 'save-report');
        payload.set('admin_photo_only', '1');
        payload.set('place_id', String(placeId));
        payload.set('csrf_token', String(csrfField?.value || ''));
        payload.set('photo_stage_token', String(tokenField?.value || ''));
        payload.set('photos_json', String(photosField?.value || '[]'));
        payload.set(
            'selected_campsite_id',
            String(siteSelector?.value || '')
        );
        payload.set(
            'selected_area_id',
            siteSelector?.value
                ? ''
                : String(areaSelector?.value || '')
        );

        savingPhotos = true;
        const reportSaveWasDisabled = Boolean(reportSaveButton?.disabled);
        if (reportSaveButton) {
            reportSaveButton.disabled = true;
        }
        updatePhotoSaveButton();
        photoSaveStatus.textContent = 'Attaching photos to ' + target.label + '...';

        try {
            const response = await fetch(form.action || window.location.href, {
                method: 'POST',
                body: payload,
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'X-Llama-Admin-Save': 'place-report'
                }
            });

            const raw = await response.text();
            let result;
            try {
                result = JSON.parse(raw);
            } catch (_) {
                throw new Error('The server returned an unexpected photo-save response.');
            }

            if (!response.ok || result?.success !== true) {
                throw new Error(
                    String(result?.message || `Photo save failed (HTTP ${response.status}).`)
                    + (result?.reference ? ` Error reference: ${result.reference}` : '')
                );
            }

            const saved = Number(result.photos_added || 0);
            if (saved !== count || !Array.isArray(result.gallery)) {
                throw new Error('The server response did not confirm every saved photo. Refresh and inspect the gallery before retrying.');
            }

            if (csrfField && result.csrf_token) {
                csrfField.value = String(result.csrf_token);
            }

            if (targets[targetKey]) {
                targets[targetKey].photos = result.gallery;
            }

            form.dispatchEvent(new CustomEvent('llama:photo-uploader-committed', {
                detail: {
                    context: 'admin-place',
                    count: saved,
                    total: Number(result.photo_total || 0)
                }
            }));

            render();
            photoSaveStatus.textContent =
                `${saved} photo${saved === 1 ? '' : 's'} saved to ${target.label}.`;
        } catch (error) {
            photoSaveStatus.textContent =
                'Photos not saved: ' + String(error?.message || 'Unknown error.');
        } finally {
            savingPhotos = false;
            if (reportSaveButton) {
                reportSaveButton.disabled = reportSaveWasDisabled;
            }
            updatePhotoSaveButton();
        }
    });

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

    const setUploaderSupported =
        (
            supported,
            target
        ) => {
            const input = photoInput();
            const drop = photoDrop();
            if (input) {
                input.disabled = !supported;
            }

            if (drop) {
                drop.setAttribute(
                    'aria-disabled',
                    supported
                        ? 'false'
                        : 'true'
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

            if (heading) {
                heading.textContent =
                    supported
                        ? (
                            'Photos for '
                            + String(
                                target?.label
                                || 'selected target'
                            )
                        )
                        : 'Photos unavailable for this target';
            }

            if (description) {
                if (supported) {
                    const max =
                        Math.max(
                            1,
                            Number(
                                target?.max
                                || (
                                    target?.scope === 'site'
                                        ? 10
                                        : 30
                                )
                            )
                        );

                    description.textContent =
                        'Add photos to '
                        + String(
                            target?.label
                            || 'the selected target'
                        )
                        + '. Up to '
                        + max
                        + ' saved photos are supported for this gallery.';
                } else {
                    description.textContent =
                        String(
                            target?.reason
                            || (
                                'Save this target before adding photos.'
                            )
                        );
                }
            }
        };

    const render =
        () => {
            const target =
                currentTarget();

            grid.replaceChildren();

            if (!target) {
                setUploaderSupported(
                    false,
                    null
                );

                if (help) {
                    help.textContent =
                        'No valid reporting target is selected.';
                }

                return;
            }

            const supported =
                target.supported !== false;

            setUploaderSupported(
                supported,
                target
            );
            updatePhotoSaveButton();

            if (help) {
                help.textContent =
                    supported
                        ? (
                            'Showing photos attached to '
                            + String(
                                target.label
                                || 'this reporting target'
                            )
                            + '.'
                        )
                        : String(
                            target.reason
                            || (
                                'Save this target before adding photos.'
                            )
                        );
            }

            const photos =
                Array.isArray(
                    target.photos
                )
                    ? target.photos
                    : [];

            if (photos.length < 1) {
                const empty =
                    document.createElement(
                        'p'
                    );

                empty.className =
                    'admin-place-muted';

                empty.textContent =
                    supported
                        ? 'No photos are attached to this reporting target yet.'
                        : 'No photo gallery is available for this selection yet.';

                grid.appendChild(
                    empty
                );

                return;
            }

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
                    'Save or remove these photos before switching to another Place, Area, or Site.'
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
            render();
        }
    );

    render();
    updatePhotoSaveButton();
})();
