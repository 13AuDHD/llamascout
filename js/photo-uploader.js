(() => {
    'use strict';

    const uploaders = document.querySelectorAll('[data-photo-uploader]');

    if (!uploaders.length) {
        return;
    }

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

        const context = String(root.dataset.photoContext || '').trim();
        const maxPhotos = Math.max(1, Number(root.dataset.photoMax || 5));
        const csrfToken = String(root.dataset.photoCsrf || '').trim();
        const endpoint = String(root.dataset.photoEndpoint || '/photo-upload.php').trim();
        const title = String(root.dataset.photoTitle || 'Photos').trim();
        const help = String(
            root.dataset.photoHelp ||
            'JPEG, PNG, WebP, HEIC, HEIF, or AVIF. Images are resized and location metadata is removed before storage.'
        ).trim();

        const tokenFieldName = String(root.dataset.photoTokenField || 'photo_stage_token');
        const photosFieldName = String(root.dataset.photoField || 'photos_json');

        let tokenField = form.querySelector(`input[name="${CSS.escape(tokenFieldName)}"]`);
        let photosField = form.querySelector(`input[name="${CSS.escape(photosFieldName)}"]`);

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
            const initial = JSON.parse(photosField.value || '[]');
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
                    <span class="llama-photo-count" data-photo-count>0 of ${maxPhotos}</span>
                </div>

                <label class="llama-photo-drop" data-photo-drop>
                    <input
                        type="file"
                        accept="image/*,.heic,.heif,.avif"
                        multiple
                        data-photo-files
                    >
                    <span class="llama-photo-drop-content">
                        <i class="fa-solid fa-images" aria-hidden="true"></i>
                        <strong>Choose photos</strong>
                        <small>Tap to choose files or drop photos here. Up to ${maxPhotos} photos, 15 MB each.</small>
                    </span>
                </label>

                <div class="llama-photo-status" data-photo-status aria-live="polite"></div>

                <div class="llama-photo-grid" data-photo-grid></div>

                <div class="llama-photo-empty" data-photo-empty>
                    No photos added yet.
                </div>
            </div>
        `;

        const fileInput = root.querySelector('[data-photo-files]');
        const dropZone = root.querySelector('[data-photo-drop]');
        const grid = root.querySelector('[data-photo-grid]');
        const empty = root.querySelector('[data-photo-empty]');
        const count = root.querySelector('[data-photo-count]');
        const status = root.querySelector('[data-photo-status]');

        const normalizePhoto = (photo) => ({
            path: String(photo?.path || ''),
            url: String(photo?.url || ''),
            filename: String(photo?.filename || ''),
            original_name: String(photo?.original_name || ''),
            mime_type: String(photo?.mime_type || 'image/jpeg'),
            width: Number(photo?.width || 0),
            height: Number(photo?.height || 0),
            size: Number(photo?.size || 0),
            alt: String(photo?.alt || '').trim(),
        });

        const syncHidden = () => {
            photos = photos.map(normalizePhoto).filter((photo) => photo.path);
            photosField.value = JSON.stringify(photos);
        };

        const setStatus = (message = '', isError = false) => {
            status.textContent = message;
            status.classList.toggle('is-error', Boolean(isError));
        };

        const request = async (action, extra = {}) => {
            const body = new FormData();
            body.append('action', action);
            body.append('context', context);
            body.append('csrf_token', csrfToken);

            if (tokenField.value) {
                body.append('token', tokenField.value);
            }

            Object.entries(extra).forEach(([key, value]) => {
                if (key === 'files') {
                    Array.from(value || []).forEach((file) => {
                        body.append('photos[]', file);
                    });
                    return;
                }

                body.append(key, String(value ?? ''));
            });

            const response = await fetch(endpoint, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                cache: 'no-store',
            });

            const raw = await response.text();
            let payload;

            try {
                payload = JSON.parse(raw);
            } catch (_) {
                throw new Error('The server returned an unexpected photo-upload response.');
            }

            if (!response.ok || payload?.success !== true) {
                throw new Error(payload?.message || 'The photo request failed.');
            }

            if (payload.token) {
                tokenField.value = String(payload.token);
            }

            if (Array.isArray(payload.photos)) {
                photos = payload.photos.map(normalizePhoto);
                syncHidden();
            }

            return payload;
        };

        const render = () => {
            syncHidden();
            count.textContent = `${photos.length} of ${maxPhotos}`;
            empty.hidden = photos.length > 0;
            grid.innerHTML = '';

            photos.forEach((photo, index) => {
                const card = document.createElement('article');
                card.className = 'llama-photo-card';

                const imageWrap = document.createElement('div');
                imageWrap.className = 'llama-photo-image';

                const image = document.createElement('img');
                image.src = photo.url || photo.path;
                image.alt = photo.alt || `Photo ${index + 1}`;
                image.loading = 'lazy';

                const number = document.createElement('span');
                number.className = 'llama-photo-number';
                number.textContent = String(index + 1);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'llama-photo-remove';
                remove.setAttribute('aria-label', `Remove photo ${index + 1}`);
                remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';

                remove.addEventListener('click', async () => {
                    if (busy || !photo.path) {
                        return;
                    }

                    busy = true;
                    root.classList.add('is-busy');
                    setStatus('Removing photo…');

                    try {
                        await request('delete', {path: photo.path});
                        render();
                        setStatus('Photo removed.');
                    } catch (error) {
                        setStatus(error.message || 'The photo could not be removed.', true);
                    } finally {
                        busy = false;
                        root.classList.remove('is-busy');
                    }
                });

                imageWrap.append(image, number, remove);

                const captionWrap = document.createElement('div');
                captionWrap.className = 'llama-photo-caption';

                const label = document.createElement('label');
                label.textContent = 'What does this photo show?';

                const caption = document.createElement('input');
                caption.type = 'text';
                caption.maxLength = 300;
                caption.placeholder = 'Optional photo description';
                caption.value = photo.alt || '';

                caption.addEventListener('input', () => {
                    photo.alt = caption.value;
                    image.alt = caption.value.trim() || `Photo ${index + 1}`;
                    syncHidden();
                });

                label.appendChild(caption);
                captionWrap.appendChild(label);
                card.append(imageWrap, captionWrap);
                grid.appendChild(card);
            });
        };

        const uploadFiles = async (fileList) => {
            const selected = Array.from(fileList || []);

            if (!selected.length || busy) {
                return;
            }

            if (photos.length + selected.length > maxPhotos) {
                setStatus(`You can include up to ${maxPhotos} photos here.`, true);
                fileInput.value = '';
                return;
            }

            busy = true;
            root.classList.add('is-busy');
            setStatus(selected.length === 1 ? 'Uploading photo…' : `Uploading ${selected.length} photos…`);

            try {
                await request('upload', {files: selected});
                render();
                setStatus(selected.length === 1 ? 'Photo ready.' : 'Photos ready.');
            } catch (error) {
                setStatus(error.message || 'The photos could not be uploaded.', true);
            } finally {
                busy = false;
                root.classList.remove('is-busy');
                fileInput.value = '';
            }
        };

        fileInput.addEventListener('change', () => uploadFiles(fileInput.files));

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropZone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropZone.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            dropZone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropZone.classList.remove('is-dragging');
            });
        });

        dropZone.addEventListener('drop', (event) => {
            uploadFiles(event.dataTransfer?.files || []);
        });

        form.addEventListener('submit', () => {
            submitting = true;
            syncHidden();
        });

        const abandon = () => {
            if (submitting || !tokenField.value || !photos.length) {
                return;
            }

            const body = new FormData();
            body.append('action', 'abandon');
            body.append('context', context);
            body.append('csrf_token', csrfToken);
            body.append('token', tokenField.value);

            if (navigator.sendBeacon) {
                navigator.sendBeacon(endpoint, body);
            }
        };

        window.addEventListener('pagehide', abandon);

        const loadExistingStage = async () => {
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
