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

    // One atomic gallery payload for order, hero and alt/caption updates.
    // The actual removals continue to use the existing checked paths.
    const galleryField = document.createElement('input');
    galleryField.type = 'hidden';
    galleryField.name = 'admin_photo_gallery_json';
    galleryField.value = '';
    form.appendChild(galleryField);
    const photoOwnerField = document.createElement('input');
    photoOwnerField.type = 'hidden';
    photoOwnerField.name = 'admin_photo_owner';
    form.appendChild(photoOwnerField);

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

    const shell = form.closest('[data-contribution-tabs]');
    const currentMode = () => String(
        shell?.querySelector('[data-contribution-mode][aria-pressed="true"]')?.dataset.contributionMode
        || 'place'
    );

    const currentTargetKey =
        () => {
            const mode = currentMode();
            if (mode === 'resource') return null;
            const siteValue =
                String(
                    siteSelector?.value
                    || ''
                ).trim();

            if (mode === 'site') {
                return siteValue.startsWith('campsite_record:')
                    ? 'site:' + siteValue
                    : null;
            }

            // The Area picker retains its last selection across tabs.
            // Only the active Area tab may use that value as a photo owner.
            if (mode === 'area') {
                const areaValue = String(areaSelector?.value || '').trim();
                return areaValue !== '' ? 'area:' + areaValue : null;
            }

            return mode === 'place' ? 'place:place:' + placeId : null;
        };

    const currentTarget =
        () => {
            const key =
                currentTargetKey();

            if (!key) return null;

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

    grid.dataset.adminPhotoSortable = '1';
    grid.setAttribute('aria-label', 'Attached photos, drag a handle to reorder');
    let renderedTargetKey = null;

    // Place, Site and Resource now use the same mounted gallery controls
    // and continuous pointer reorder behavior. Only persistence differs.
    const gallery = window.LlamaPhotoGallery?.mount(grid, {
        removeName: 'remove_existing_photo_ids[]',
        onChange: () => syncGalleryEdits(),
    });
    if (!gallery) return;
    const photoEdits = () => {
        const targetKey = renderedTargetKey;
        const target = targetKey ? targets[targetKey] : null;
        if (!targetKey || !target || target.supported === false) return null;
        const snapshot = gallery.snapshot(target.photos);
        return {target: targetKey, items: snapshot.items, dirty: snapshot.dirty};
    };
    const syncGalleryEdits = () => {
        const edits = photoEdits();
        photoOwnerField.value = renderedTargetKey || '';
        galleryField.value = edits?.dirty
            ? JSON.stringify({target: edits.target, items: edits.items})
            : '';
    };
    const hasPendingGalleryEdits = () => Boolean(photoEdits()?.dirty);
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
        if (hasPendingGalleryEdits()) {
            photoSaveStatus.textContent =
                'Save Place Report first to apply the photo order, captions, or removals.';
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
        payload.set('admin_photo_owner', String(targetKey));
        payload.set('csrf_token', String(csrfField?.value || ''));
        payload.set('photo_stage_token', String(tokenField?.value || ''));
        payload.set('photos_json', String(photosField?.value || '[]'));
        const selectedSite = String(siteSelector?.value || '');
        const siteValue = selectedSite.startsWith('campsite_record:') ? selectedSite : '';
        // Keep the photo-only request's submitted target aligned with
        // the active tab, never with an inactive dropdown's stale value.
        payload.set(
            'selected_campsite_id',
            currentMode() === 'site' ? siteValue : ''
        );
        payload.set(
            'selected_area_id',
            currentMode() === 'area'
                ? String(areaSelector?.value || '')
                : ''
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

    const setUploaderSupported =
        (
            supported,
            target
        ) => {
            const savedCount = Array.isArray(target?.photos) ? target.photos.length : 0;
            const max = Math.max(1, Number(target?.max || 10));
            const canAdd = supported && savedCount < max;
            uploader.dataset.photoTargetSupported = canAdd ? 'true' : 'false';
            const input = photoInput();
            const drop = photoDrop();
            if (input) {
                input.disabled = !canAdd;
            }

            if (drop) {
                drop.setAttribute(
                    'aria-disabled',
                    canAdd
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
                    description.textContent = canAdd
                        ? savedCount + ' attached. Add up to '
                            + (max - savedCount) + ' more photo(s) to '
                            + String(target?.label || 'this gallery') + '.'
                        : savedCount + ' photos already attached. The limit is '
                            + max + '; remove photos and save before adding more.';
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
            renderedTargetKey = currentTargetKey();
            photoOwnerField.value = renderedTargetKey || '';
            galleryField.value = '';

            if (!target) {
                setUploaderSupported(
                    false,
                    null
                );

                if (help) {
                    help.textContent = currentMode() === 'site'
                        ? 'Choose a saved Site before adding photos. Nothing will be added to the Place gallery.'
                        : 'No valid reporting target is selected.';
                }

                return;
            }

            const supported =
                target.supported !== false;

            setUploaderSupported(
                supported,
                target
            );
            const title = existingShell.querySelector('strong');
            if (title) {
                title.textContent = 'Photos already attached ('
                    + (Array.isArray(target.photos) ? target.photos.length : 0) + ')';
            }
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
                            + '. Drag handles reorder them; the first photo becomes Hero.'
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

                gallery.render([]);
                grid.appendChild(empty);
                syncGalleryEdits();
                return;
            }

            gallery.render(photos);
            syncGalleryEdits();
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

            if (stagedPhotoCount() > 0 || (hasPendingGalleryEdits()
                && !window.confirm('Discard unsaved photo ordering, captions and removals before switching?'))) {
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

                if (stagedPhotoCount() > 0) {
                    window.alert(
                        'Save or remove staged photos before switching to another Place, Area, or Site.'
                    );
                }
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

    form.addEventListener('llama:photo-uploader-ready', (event) => {
        if (event.detail?.context === 'admin-place') render();
    });

    // Stop tab switches *before* the shared tab controller changes the
    // selected owner. Otherwise unsaved photo edits would be discarded.
    shell?.addEventListener('click', event => {
        const button = event.target?.closest?.('[data-contribution-mode]');
        if (!button || button.getAttribute('aria-pressed') === 'true') return;
        if (hasPendingGalleryEdits()
            && !window.confirm('Discard unsaved photo ordering, captions and removals before switching?')) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    shell?.addEventListener('llama:contribution-mode', () => {
        queueMicrotask(render);
    });

    // Include only actual edits in the normal atomic Place Report save.
    form.addEventListener('llama:admin-place-report-save-starting', syncGalleryEdits);
    form.addEventListener('llama:photo-gallery-saved', event => {
        const key = String(event.detail?.target || '');
        if (key && targets[key] && Array.isArray(event.detail?.gallery)) {
            targets[key].photos = event.detail.gallery;
        }
        galleryField.value = '';
        render();
    });

    window.addEventListener('beforeunload', event => {
        if (!hasPendingGalleryEdits()) return;
        event.preventDefault();
        event.returnValue = '';
    });

    window.addEventListener(
        'llama:admin-place-report-saved',
        () => {
            // The committed gallery is refreshed by photo-gallery-saved.
            // The generic success event must not undo pending photo edits.
        }
    );

    render();
    updatePhotoSaveButton();
})();
