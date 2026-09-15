(() => {
    'use strict';

    const uploaders = document.querySelectorAll('[data-photo-uploader]');

    if (!uploaders.length) {
        return;
    }

    const MAX_PHOTO_BYTES = 15 * 1024 * 1024;
    const UPLOAD_TIMEOUT_MS = 180000;

    const PHOTO_ICON = `
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path d="M15 8h.01"></path>
            <path d="M3 6a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6"></path>
            <path d="m3 16 5-5c.928-.893 2.072-.893 3 0l5 5"></path>
            <path d="m14 14 1-1c.928-.893 2.072-.893 3 0l3 3"></path>
        </svg>
    `;

    const X_ICON = `
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path d="m18 6-12 12"></path>
            <path d="m6 6 12 12"></path>
        </svg>
    `;

    const htmlEscape = (value) => {
        const node = document.createElement('div');
        node.textContent = String(value ?? '');
        return node.innerHTML;
    };

    uploaders.forEach((root) => {
        const form = root.closest('form');

        if (!form) {
            return;
        }

        /*
         * The shared CSS has always targeted .llama-photo-uploader,
         * but older markup only supplied data-photo-uploader.
         * Normalize it here so busy/loading styles actually apply.
         */
        root.classList.add('llama-photo-uploader');

        const context = String(root.dataset.photoContext || '').trim();
        const maxPhotos = Math.max(1, Number(root.dataset.photoMax || 5));
        const csrfToken = String(root.dataset.photoCsrf || '').trim();
        const endpoint = String(
            root.dataset.photoEndpoint || '/photo-upload.php'
        ).trim();
        const title = String(root.dataset.photoTitle || 'Photos').trim();
        const help = String(
            root.dataset.photoHelp ||
            'JPEG, PNG, WebP, HEIC, HEIF, or AVIF. Images are resized and location metadata is removed before storage.'
        ).trim();

        const tokenFieldName = String(
            root.dataset.photoTokenField || 'photo_stage_token'
        );
        const photosFieldName = String(
            root.dataset.photoField || 'photos_json'
        );

        let tokenField = form.querySelector(
            `input[name="${CSS.escape(tokenFieldName)}"]`
        );
        let photosField = form.querySelector(
            `input[name="${CSS.escape(photosFieldName)}"]`
        );

        if (!tokenField) {
            tokenField = document.createElement('input');
            tokenField.type = 'hidden';
            tokenField.name = tokenFieldName;
            form.appendChild(tokenField);
        }

        if (!photosField) {
            photosField = document.createElement('input');
            photosField.type = 'hidden';
            photosField.name = photosFieldName;
            photosField.value = '[]';
            form.appendChild(photosField);
        }

        let photos = [];
        let busy = false;
        let submitting = false;

        try {
            const initial = JSON.parse(
                photosField.value || '[]'
            );

            if (Array.isArray(initial)) {
                photos = initial;
            }
        } catch (_) {
            photos = [];
        }

        root.innerHTML = `
            <div class="llama-photo-uploader-inner">
                <div class="llama-photo-heading">
                    <div>
                        <h3>${htmlEscape(title)}</h3>
                        <p>${htmlEscape(help)}</p>
                    </div>

                    <span
                        class="llama-photo-count"
                        data-photo-count
                    >
                        0 of ${maxPhotos}
                    </span>
                </div>

                <label
                    class="llama-photo-drop"
                    data-photo-drop
                >
                    <input
                        type="file"
                        accept="image/*,.heic,.heif,.avif"
                        multiple
                        data-photo-files
                    >

                    <span class="llama-photo-drop-content">
                        <span
                            class="llama-photo-drop-icon"
                            aria-hidden="true"
                        >
                            ${PHOTO_ICON}
                        </span>

                        <strong>Choose photos</strong>

                        <small>
                            Tap to choose files or drop photos here.
                            Up to ${maxPhotos} photos, 15 MB each.
                        </small>
                    </span>
                </label>

                <div
                    class="llama-photo-working"
                    data-photo-working
                    hidden
                >
                    <span
                        class="llama-photo-working-icon"
                        aria-hidden="true"
                    >
                        <span class="llama-photo-spinner"></span>
                    </span>

                    <div class="llama-photo-working-copy">
                        <strong data-photo-working-title>
                            Working on your photo...
                        </strong>

                        <span data-photo-working-detail>
                            Please keep this page open.
                        </span>
                    </div>
                </div>

                <div
                    class="llama-photo-progress"
                    data-photo-progress
                    hidden
                    aria-hidden="true"
                >
                    <span data-photo-progress-bar></span>
                </div>

                <div
                    class="llama-photo-status"
                    data-photo-status
                    aria-live="polite"
                ></div>

                <div
                    class="llama-photo-grid"
                    data-photo-grid
                ></div>

                <div
                    class="llama-photo-empty"
                    data-photo-empty
                >
                    No photos added yet.
                </div>
            </div>
        `;

        const fileInput = root.querySelector(
            '[data-photo-files]'
        );
        const dropZone = root.querySelector(
            '[data-photo-drop]'
        );
        const grid = root.querySelector(
            '[data-photo-grid]'
        );
        const empty = root.querySelector(
            '[data-photo-empty]'
        );
        const count = root.querySelector(
            '[data-photo-count]'
        );
        const status = root.querySelector(
            '[data-photo-status]'
        );
        const working = root.querySelector(
            '[data-photo-working]'
        );
        const workingTitle = root.querySelector(
            '[data-photo-working-title]'
        );
        const workingDetail = root.querySelector(
            '[data-photo-working-detail]'
        );
        const progress = root.querySelector(
            '[data-photo-progress]'
        );
        const progressBar = root.querySelector(
            '[data-photo-progress-bar]'
        );

        const submitControls = Array.from(
            form.querySelectorAll(
                'button[type="submit"], input[type="submit"]'
            )
        );

        /*
         * Only dedicated photo-only forms should require at least
         * one staged photo before their submit button is enabled.
         *
         * The shared uploader also lives inside larger forms such
         * as Admin Place Report, Add Place, Suggest an Update,
         * badge editing, and Report a Problem. Those forms must
         * remain savable even when no new photo is staged.
         */
        const requiresPhotoSelection =
            form.matches(
                '.community-profile-photo-upload-form, '
                + '.admin-commerce-photo-upload-form'
            );

        const normalizePhoto = (photo) => ({
            path: String(photo?.path || ''),
            url: String(photo?.url || ''),
            filename: String(photo?.filename || ''),
            original_name: String(
                photo?.original_name || ''
            ),
            mime_type: String(
                photo?.mime_type || 'image/jpeg'
            ),
            width: Number(photo?.width || 0),
            height: Number(photo?.height || 0),
            size: Number(photo?.size || 0),
            alt: String(photo?.alt || '').trim(),
        });

        const syncHidden = () => {
            photos = photos
                .map(normalizePhoto)
                .filter((photo) => photo.path);

            photosField.value = JSON.stringify(
                photos
            );
        };

        /*
         * Larger forms such as Admin Place Report save in the
         * background. Give them an explicit way to force the
         * current staged-photo state into the hidden form fields
         * immediately before FormData is created.
         */
        form.addEventListener(
            'llama:photo-uploader-sync',
            () => {
                syncHidden();
            }
        );

        const updateSubmitControls = () => {
            submitControls.forEach((control) => {
                control.disabled =
                    busy
                    || (
                        requiresPhotoSelection
                        && photos.length === 0
                    );
            });
        };

        const setBusy = (nextBusy) => {
            busy = Boolean(nextBusy);

            root.classList.toggle(
                'is-busy',
                busy
            );

            root.setAttribute(
                'aria-busy',
                busy ? 'true' : 'false'
            );

            fileInput.disabled = busy;

            updateSubmitControls();
        };

        const setStatus = (
            message = '',
            isError = false
        ) => {
            status.textContent = message;

            status.classList.toggle(
                'is-error',
                Boolean(isError)
            );
        };

        const setWorking = (
            isWorking,
            titleText = '',
            detailText = ''
        ) => {
            working.hidden = !isWorking;

            if (!isWorking) {
                return;
            }

            workingTitle.textContent =
                titleText ||
                'Working on your photo...';

            workingDetail.textContent =
                detailText ||
                'Please keep this page open.';
        };

        const setProgress = (
            percent = 0,
            visible = false
        ) => {
            const safePercent = Math.max(
                0,
                Math.min(
                    100,
                    Number(percent) || 0
                )
            );

            progress.hidden = !visible;

            progressBar.style.width =
                `${safePercent}%`;
        };

        const parseResponsePayload = (
            raw,
            httpStatus
        ) => {
            let payload;

            try {
                payload = JSON.parse(raw);
            } catch (_) {
                throw new Error(
                    'The server returned an unexpected photo-upload response.'
                );
            }

            if (
                httpStatus < 200 ||
                httpStatus >= 300 ||
                payload?.success !== true
            ) {
                const message =
                    payload?.message ||
                    'The photo request failed.';

                if (payload?.reference) {
                    throw new Error(
                        `${message} Error reference: ${payload.reference}`
                    );
                }

                throw new Error(message);
            }

            if (payload.token) {
                tokenField.value =
                    String(payload.token);
            }

            if (Array.isArray(payload.photos)) {
                photos = payload.photos.map(
                    normalizePhoto
                );

                syncHidden();
            }

            return payload;
        };

        const request = async (
            action,
            extra = {}
        ) => {
            const body = new FormData();

            body.append('action', action);
            body.append('context', context);
            body.append(
                'csrf_token',
                csrfToken
            );

            if (tokenField.value) {
                body.append(
                    'token',
                    tokenField.value
                );
            }

            Object.entries(extra).forEach(
                ([key, value]) => {
                    body.append(
                        key,
                        String(value ?? '')
                    );
                }
            );

            const controller =
                new AbortController();

            const timeoutId = window.setTimeout(
                () => controller.abort(),
                UPLOAD_TIMEOUT_MS
            );

            try {
                const response = await fetch(
                    endpoint,
                    {
                        method: 'POST',
                        body,
                        credentials: 'same-origin',
                        cache: 'no-store',
                        signal: controller.signal,
                    }
                );

                const raw =
                    await response.text();

                return parseResponsePayload(
                    raw,
                    response.status
                );
            } catch (error) {
                if (
                    error?.name ===
                    'AbortError'
                ) {
                    throw new Error(
                        'The photo request took too long. Check your connection and try again.'
                    );
                }

                throw error;
            } finally {
                window.clearTimeout(
                    timeoutId
                );
            }
        };

        const uploadRequest = (
            selectedFiles
        ) => new Promise(
            (resolve, reject) => {
                const body = new FormData();

                body.append(
                    'action',
                    'upload'
                );
                body.append(
                    'context',
                    context
                );
                body.append(
                    'csrf_token',
                    csrfToken
                );

                if (tokenField.value) {
                    body.append(
                        'token',
                        tokenField.value
                    );
                }

                selectedFiles.forEach(
                    (file) => {
                        body.append(
                            'photos[]',
                            file
                        );
                    }
                );

                const xhr =
                    new XMLHttpRequest();

                xhr.open(
                    'POST',
                    endpoint,
                    true
                );

                xhr.timeout =
                    UPLOAD_TIMEOUT_MS;

                xhr.withCredentials = true;

                xhr.upload.addEventListener(
                    'progress',
                    (event) => {
                        if (
                            !event.lengthComputable
                        ) {
                            setWorking(
                                true,
                                selectedFiles.length === 1
                                    ? 'Uploading photo...'
                                    : `Uploading ${selectedFiles.length} photos...`,
                                'Sending your photo to Llama Scout. Please keep this page open.'
                            );

                            return;
                        }

                        const percent =
                            Math.max(
                                1,
                                Math.min(
                                    99,
                                    Math.round(
                                        (
                                            event.loaded /
                                            event.total
                                        ) * 100
                                    )
                                )
                            );

                        setWorking(
                            true,
                            selectedFiles.length === 1
                                ? `Uploading photo... ${percent}%`
                                : `Uploading photos... ${percent}%`,
                            'Sending your photo to Llama Scout. Please keep this page open.'
                        );

                        setProgress(
                            percent,
                            true
                        );
                    }
                );

                xhr.upload.addEventListener(
                    'load',
                    () => {
                        setProgress(
                            100,
                            true
                        );

                        setWorking(
                            true,
                            selectedFiles.length === 1
                                ? 'Upload complete. Processing photo...'
                                : 'Upload complete. Processing photos...',
                            'Llama Scout is resizing the image and removing location metadata. This can take a moment.'
                        );
                    }
                );

                xhr.addEventListener(
                    'load',
                    () => {
                        try {
                            resolve(
                                parseResponsePayload(
                                    xhr.responseText,
                                    xhr.status
                                )
                            );
                        } catch (error) {
                            reject(error);
                        }
                    }
                );

                xhr.addEventListener(
                    'error',
                    () => {
                        reject(
                            new Error(
                                'The photo upload lost its connection. Check your connection and try again.'
                            )
                        );
                    }
                );

                xhr.addEventListener(
                    'timeout',
                    () => {
                        reject(
                            new Error(
                                'The photo upload took too long and was stopped. Try again with one photo at a time.'
                            )
                        );
                    }
                );

                xhr.addEventListener(
                    'abort',
                    () => {
                        reject(
                            new Error(
                                'The photo upload was canceled.'
                            )
                        );
                    }
                );

                xhr.send(body);
            }
        );

        const render = () => {
            syncHidden();

            count.textContent =
                `${photos.length} of ${maxPhotos}`;

            empty.hidden =
                photos.length > 0;

            grid.innerHTML = '';

            photos.forEach(
                (photo, index) => {
                    const card =
                        document.createElement(
                            'article'
                        );

                    card.className =
                        'llama-photo-card';

                    const imageWrap =
                        document.createElement(
                            'div'
                        );

                    imageWrap.className =
                        'llama-photo-image';

                    const image =
                        document.createElement(
                            'img'
                        );

                    image.src =
                        photo.url ||
                        photo.path;

                    image.alt =
                        photo.alt ||
                        `Photo ${index + 1}`;

                    image.loading = 'lazy';

                    const number =
                        document.createElement(
                            'span'
                        );

                    number.className =
                        'llama-photo-number';

                    number.textContent =
                        String(index + 1);

                    const remove =
                        document.createElement(
                            'button'
                        );

                    remove.type = 'button';
                    remove.className =
                        'llama-photo-remove';

                    remove.setAttribute(
                        'aria-label',
                        `Remove photo ${index + 1}`
                    );

                    remove.innerHTML =
                        X_ICON;

                    remove.addEventListener(
                        'click',
                        async () => {
                            if (
                                busy ||
                                !photo.path
                            ) {
                                return;
                            }

                            setBusy(true);
                            setWorking(
                                true,
                                'Removing photo...',
                                'Updating your temporary photo list.'
                            );
                            setStatus('');

                            try {
                                await request(
                                    'delete',
                                    {
                                        path:
                                            photo.path,
                                    }
                                );

                                render();

                                setStatus(
                                    'Photo removed.'
                                );
                            } catch (error) {
                                setStatus(
                                    error.message ||
                                    'The photo could not be removed.',
                                    true
                                );
                            } finally {
                                setProgress(
                                    0,
                                    false
                                );
                                setWorking(
                                    false
                                );
                                setBusy(false);
                            }
                        }
                    );

                    imageWrap.append(
                        image,
                        number,
                        remove
                    );

                    const captionWrap =
                        document.createElement(
                            'div'
                        );

                    captionWrap.className =
                        'llama-photo-caption';

                    const label =
                        document.createElement(
                            'label'
                        );

                    label.textContent =
                        'What does this photo show?';

                    const caption =
                        document.createElement(
                            'input'
                        );

                    caption.type = 'text';
                    caption.maxLength = 300;
                    caption.placeholder =
                        'Optional photo description';

                    caption.value =
                        photo.alt || '';

                    caption.addEventListener(
                        'input',
                        () => {
                            photo.alt =
                                caption.value;

                            image.alt =
                                caption.value.trim() ||
                                `Photo ${index + 1}`;

                            syncHidden();
                        }
                    );

                    label.appendChild(
                        caption
                    );

                    captionWrap.appendChild(
                        label
                    );

                    card.append(
                        imageWrap,
                        captionWrap
                    );

                    grid.appendChild(card);
                }
            );

            updateSubmitControls();
        };

        /*
         * A successful background save moves staged files into
         * permanent storage. Start a fresh staging batch afterward
         * so a second upload cannot reuse the already-committed
         * token or make the previous photo appear to vanish.
         */
        form.addEventListener(
            'llama:photo-uploader-committed',
            (event) => {
                const detail =
                    event?.detail
                    && typeof event.detail
                        === 'object'
                        ? event.detail
                        : {};

                if (
                    detail.context
                    && detail.context
                        !== context
                ) {
                    return;
                }

                const savedCount =
                    Math.max(
                        0,
                        Number(
                            detail.count
                            || 0
                        )
                    );

                tokenField.value = '';
                photos = [];
                syncHidden();
                submitting = false;

                render();

                if (savedCount > 0) {
                    setStatus(
                        `${savedCount} photo${savedCount === 1 ? '' : 's'} saved to this Place. You can add another batch now.`
                    );
                }
            }
        );

        const uploadFiles = async (
            fileList
        ) => {
            const selected = Array.from(
                fileList || []
            );

            if (
                !selected.length ||
                busy
            ) {
                return;
            }

            if (
                photos.length +
                selected.length >
                maxPhotos
            ) {
                setStatus(
                    `You can include up to ${maxPhotos} photos here.`,
                    true
                );

                fileInput.value = '';
                return;
            }

            const emptyFile =
                selected.find(
                    (file) =>
                        Number(file.size) < 1
                );

            if (emptyFile) {
                setStatus(
                    `"${emptyFile.name}" is empty and cannot be uploaded.`,
                    true
                );

                fileInput.value = '';
                return;
            }

            const tooLarge =
                selected.find(
                    (file) =>
                        Number(file.size) >
                        MAX_PHOTO_BYTES
                );

            if (tooLarge) {
                setStatus(
                    `"${tooLarge.name}" is larger than 15 MB. Choose a smaller photo.`,
                    true
                );

                fileInput.value = '';
                return;
            }

            setBusy(true);
            setStatus('');
            setProgress(0, true);

            setWorking(
                true,
                selected.length === 1
                    ? 'Preparing photo upload...'
                    : `Preparing ${selected.length} photo uploads...`,
                'Please keep this page open while Llama Scout works on your request.'
            );

            try {
                await uploadRequest(
                    selected
                );

                render();

                setStatus(
                    selected.length === 1
                        ? 'Photo is ready. Review it below, then choose Save selected photos.'
                        : 'Photos are ready. Review them below, then choose Save selected photos.'
                );
            } catch (error) {
                setStatus(
                    error.message ||
                    'The photos could not be uploaded.',
                    true
                );
            } finally {
                setProgress(
                    0,
                    false
                );
                setWorking(false);
                setBusy(false);
                fileInput.value = '';
            }
        };

        fileInput.addEventListener(
            'change',
            () => uploadFiles(
                fileInput.files
            )
        );

        [
            'dragenter',
            'dragover',
        ].forEach((eventName) => {
            dropZone.addEventListener(
                eventName,
                (event) => {
                    event.preventDefault();

                    if (busy) {
                        return;
                    }

                    dropZone.classList.add(
                        'is-dragging'
                    );
                }
            );
        });

        [
            'dragleave',
            'drop',
        ].forEach((eventName) => {
            dropZone.addEventListener(
                eventName,
                (event) => {
                    event.preventDefault();

                    dropZone.classList.remove(
                        'is-dragging'
                    );
                }
            );
        });

        dropZone.addEventListener(
            'drop',
            (event) => {
                if (busy) {
                    return;
                }

                uploadFiles(
                    event.dataTransfer?.files ||
                    []
                );
            }
        );

        form.addEventListener(
            'submit',
            (event) => {
                if (busy) {
                    event.preventDefault();

                    setStatus(
                        'Please wait for the current photo upload to finish before saving.',
                        true
                    );

                    return;
                }

                submitting = true;
                syncHidden();
            }
        );

        /*
         * Do not automatically abandon staged photos on pagehide.
         *
         * iPad/Safari can fire page lifecycle events while handing
         * control to native pickers or while tabs are backgrounded.
         * Automatic abandonment can therefore delete a perfectly
         * valid staging batch while the editor still appears open.
         *
         * Abandoned staging is already cleaned safely by the server
         * after its retention window, so keeping it here is the
         * safer data-preserving behavior.
         */

        const loadExistingStage =
            async () => {
                if (!tokenField.value) {
                    render();
                    return;
                }

                try {
                    await request('list');
                } catch (_) {
                    tokenField.value = '';
                    photos = [];
                    syncHidden();
                }

                render();
            };

        loadExistingStage();
    });
})();
