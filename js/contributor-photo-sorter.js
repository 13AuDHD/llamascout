(() => {
    'use strict';

    const STYLE_ID =
        'llama-contributor-photo-sorter-css';

    const STYLE_URL =
        'https://llamascout.com/css/contributor-photo-sorter.css?v=20260922-2';

    const ENDPOINT =
        '/photo-order.php';

    const loadStyles = () => {
        if (document.getElementById(STYLE_ID)) {
            return;
        }

        const link =
            document.createElement('link');

        link.id = STYLE_ID;
        link.rel = 'stylesheet';
        link.href = STYLE_URL;

        document.head.appendChild(link);
    };

    const parsePhotos = (field) => {
        try {
            const value = JSON.parse(
                field.value || '[]'
            );

            return Array.isArray(value)
                ? value.filter(
                    (photo) =>
                        photo
                        && typeof photo === 'object'
                        && String(
                            photo.path || ''
                        ).trim() !== ''
                )
                : [];
        } catch (error) {
            return [];
        }
    };

    const initSorter = (root) => {
        const context = String(
            root.dataset.photoContext || ''
        ).trim();

        if (context !== 'add-place') {
            return;
        }

        const form = root.closest('form');

        if (!form) {
            return;
        }

        const grid = root.querySelector(
            '[data-photo-grid]'
        );

        const photosField = form.querySelector(
            'input[name="photos_json"]'
        );

        const tokenField = form.querySelector(
            'input[name="photo_stage_token"]'
        );

        if (
            !grid
            || !photosField
            || !tokenField
        ) {
            return;
        }

        const csrfToken = String(
            root.dataset.photoCsrf || ''
        ).trim();

        const existingPhotos =
            form.querySelectorAll(
                '.add-place-existing-photo'
            );

        const isRevision =
            existingPhotos.length > 0;

        loadStyles();

        const guide =
            document.createElement('div');

        guide.className =
            'contributor-photo-sort-guide';

        const help =
            document.createElement('p');

        help.className =
            'contributor-photo-sort-help';

        help.textContent = isRevision
            ? 'Drag newly added photos to set their order. Photos already attached stay ahead of newly added photos unless you remove them.'
            : 'Drag photos to set the published gallery order. Photo #1 becomes the featured image.';

        const status =
            document.createElement('p');

        status.className =
            'contributor-photo-sort-status';
        status.setAttribute('role', 'status');
        status.setAttribute(
            'aria-live',
            'polite'
        );
        status.textContent =
            'Photo order saves automatically.';

        guide.append(help, status);

        grid.parentNode.insertBefore(
            guide,
            grid
        );

        let decorating = false;
        let saving = false;
        let draggedCard = null;
        let pointerId = null;
        let startingOrder = '';
        let syncTimer = null;

        const setStatus = (
            message,
            state = ''
        ) => {
            status.textContent = message;
            status.classList.remove(
                'is-saving',
                'is-saved',
                'is-error'
            );

            if (state !== '') {
                status.classList.add(
                    'is-' + state
                );
            }
        };

        const getCards = () =>
            Array.from(
                grid.children
            ).filter(
                (element) =>
                    element.classList
                    && element.classList.contains(
                        'llama-photo-card'
                    )
            );

        const photoPath = (photo) =>
            String(
                photo?.path || ''
            ).trim();

        const currentOrderSignature = () =>
            getCards()
                .map(
                    (card) =>
                        String(
                            card.dataset.photoPath
                            || ''
                        )
                )
                .join('\n');

        const applyDomOrderToHidden = () => {
            const photos =
                parsePhotos(photosField);

            if (!photos.length) {
                return [];
            }

            const byPath = new Map();

            photos.forEach((photo) => {
                const path = photoPath(photo);

                if (path !== '') {
                    byPath.set(path, photo);
                }
            });

            const ordered = [];

            getCards().forEach((card) => {
                const path = String(
                    card.dataset.photoPath
                    || ''
                ).trim();

                if (byPath.has(path)) {
                    ordered.push(
                        byPath.get(path)
                    );
                    byPath.delete(path);
                }
            });

            if (
                ordered.length !== photos.length
                || byPath.size !== 0
            ) {
                return [];
            }

            photosField.value =
                JSON.stringify(ordered);

            return ordered;
        };

        const refreshPresentation = () => {
            const cards = getCards();
            const total = cards.length;

            cards.forEach((card, index) => {
                const featured =
                    card.querySelector(
                        '[data-contributor-featured]'
                    );

                card.classList.toggle(
                    'is-contributor-featured',
                    !isRevision && index === 0
                );

                if (featured) {
                    featured.hidden =
                        isRevision || index !== 0;
                    featured.style.display =
                        !isRevision && index === 0
                            ? 'inline-flex'
                            : 'none';
                }

                const number =
                    card.querySelector(
                        '.llama-photo-number'
                    );

                if (number) {
                    number.textContent =
                        String(index + 1);
                }

                const handle =
                    card.querySelector(
                        '[data-contributor-sort-handle]'
                    );

                if (handle) {
                    let label =
                        `Move photo ${index + 1} of ${total}. Drag to reorder.`;

                    if (!isRevision && index === 0) {
                        label +=
                            ' This is the featured photo.';
                    }

                    handle.setAttribute(
                        'aria-label',
                        label
                    );
                }
            });
        };

        const decorate = () => {
            if (decorating) {
                return;
            }

            decorating = true;

            try {
                const photos =
                    parsePhotos(photosField);

                const cards = getCards();

                cards.forEach((card, index) => {
                    const photo = photos[index];
                    const path = photoPath(photo);

                    if (path !== '') {
                        card.dataset.photoPath = path;
                    }

                    if (
                        !card.querySelector(
                            '[data-contributor-featured]'
                        )
                    ) {
                        const featured =
                            document.createElement(
                                'span'
                            );

                        featured.className =
                            'contributor-photo-featured';
                        featured.setAttribute(
                            'data-contributor-featured',
                            ''
                        );
                        featured.textContent =
                            'Featured';

                        const imageWrap =
                            card.querySelector(
                                '.llama-photo-image'
                            );

                        if (imageWrap) {
                            imageWrap.appendChild(
                                featured
                            );
                        }
                    }

                    if (
                        !card.querySelector(
                            '[data-contributor-sort-handle]'
                        )
                    ) {
                        const handle =
                            document.createElement(
                                'button'
                            );

                        handle.type = 'button';
                        handle.className =
                            'contributor-photo-sort-handle';
                        handle.setAttribute(
                            'data-contributor-sort-handle',
                            ''
                        );
                        handle.setAttribute(
                            'title',
                            'Drag to reorder photo'
                        );
                        handle.innerHTML =
                            '<span aria-hidden="true">•••</span>';

                        const imageWrap =
                            card.querySelector(
                                '.llama-photo-image'
                            );

                        if (imageWrap) {
                            imageWrap.appendChild(
                                handle
                            );
                        }
                    }
                });

                refreshPresentation();
            } finally {
                decorating = false;
            }
        };

        const setSortingDisabled = (
            disabled
        ) => {
            saving = Boolean(disabled);
            grid.classList.toggle(
                'is-contributor-saving',
                saving
            );

            grid.querySelectorAll(
                '[data-contributor-sort-handle]'
            ).forEach((handle) => {
                handle.disabled = saving;
            });
        };

        const saveOrder = async () => {
            if (saving) {
                return;
            }

            const ordered =
                applyDomOrderToHidden();

            if (!ordered.length) {
                refreshPresentation();
                return;
            }

            const token = String(
                tokenField.value || ''
            ).trim();

            if (
                token === ''
                || csrfToken === ''
            ) {
                setStatus(
                    'Photo order could not be saved. Reload the page and try again.',
                    'error'
                );
                return;
            }

            setSortingDisabled(true);
            setStatus(
                'Saving photo order…',
                'saving'
            );

            const body =
                new URLSearchParams();

            body.set('context', context);
            body.set('token', token);
            body.set('csrf_token', csrfToken);
            body.set(
                'photos_json',
                JSON.stringify(ordered)
            );

            try {
                const response = await fetch(
                    ENDPOINT,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Content-Type':
                                'application/x-www-form-urlencoded;charset=UTF-8',
                            'X-Requested-With':
                                'XMLHttpRequest',
                        },
                        body: body.toString(),
                    }
                );

                let payload = null;

                try {
                    payload =
                        await response.json();
                } catch (error) {
                    payload = null;
                }

                if (
                    !response.ok
                    || !payload
                    || payload.success !== true
                ) {
                    throw new Error(
                        payload
                        && typeof payload.message === 'string'
                        && payload.message !== ''
                            ? payload.message
                            : 'The photo order could not be saved.'
                    );
                }

                setStatus(
                    'Photo order saved.',
                    'saved'
                );
            } catch (error) {
                setStatus(
                    error instanceof Error
                    && error.message !== ''
                        ? error.message
                        : 'The photo order could not be saved.',
                    'error'
                );
            } finally {
                setSortingDisabled(false);
                refreshPresentation();
            }
        };

        const scheduleSave = () => {
            window.clearTimeout(syncTimer);

            syncTimer = window.setTimeout(
                saveOrder,
                250
            );
        };

        const moveCard = (
            card,
            targetIndex
        ) => {
            const cards = getCards();
            const currentIndex =
                cards.indexOf(card);

            if (
                currentIndex < 0
                || targetIndex < 0
                || targetIndex >= cards.length
                || targetIndex === currentIndex
            ) {
                return false;
            }

            const target = cards[targetIndex];

            if (targetIndex > currentIndex) {
                grid.insertBefore(
                    card,
                    target.nextSibling
                );
            } else {
                grid.insertBefore(
                    card,
                    target
                );
            }

            refreshPresentation();
            applyDomOrderToHidden();

            return true;
        };

        grid.addEventListener(
            'pointerdown',
            (event) => {
                const handle =
                    event.target.closest(
                        '[data-contributor-sort-handle]'
                    );

                if (
                    !handle
                    || handle.disabled
                    || saving
                    || (
                        event.pointerType === 'mouse'
                        && event.button !== 0
                    )
                ) {
                    return;
                }

                const card = handle.closest(
                    '.llama-photo-card'
                );

                if (!card) {
                    return;
                }

                event.preventDefault();

                draggedCard = card;
                pointerId = event.pointerId;
                startingOrder =
                    currentOrderSignature();

                card.classList.add(
                    'is-contributor-dragging'
                );
                grid.classList.add(
                    'is-contributor-sorting'
                );

                try {
                    handle.setPointerCapture(
                        event.pointerId
                    );
                } catch (error) {
                    /* Pointer capture is optional. */
                }
            }
        );

        grid.addEventListener(
            'pointermove',
            (event) => {
                if (
                    !draggedCard
                    || pointerId !== event.pointerId
                    || saving
                ) {
                    return;
                }

                event.preventDefault();

                const hit =
                    document.elementFromPoint(
                        event.clientX,
                        event.clientY
                    );

                const target = hit
                    ? hit.closest(
                        '.llama-photo-card'
                    )
                    : null;

                if (
                    !target
                    || target === draggedCard
                    || target.parentNode !== grid
                ) {
                    return;
                }

                const cards = getCards();
                const currentIndex =
                    cards.indexOf(draggedCard);
                const targetIndex =
                    cards.indexOf(target);

                if (
                    currentIndex < 0
                    || targetIndex < 0
                ) {
                    return;
                }

                if (targetIndex > currentIndex) {
                    grid.insertBefore(
                        draggedCard,
                        target.nextSibling
                    );
                } else {
                    grid.insertBefore(
                        draggedCard,
                        target
                    );
                }

                refreshPresentation();
                applyDomOrderToHidden();
            }
        );

        const finishPointer = (event) => {
            if (
                !draggedCard
                || pointerId !== event.pointerId
            ) {
                return;
            }

            draggedCard.classList.remove(
                'is-contributor-dragging'
            );
            grid.classList.remove(
                'is-contributor-sorting'
            );

            draggedCard = null;
            pointerId = null;

            if (
                currentOrderSignature()
                !== startingOrder
            ) {
                saveOrder();
            }
        };

        grid.addEventListener(
            'pointerup',
            finishPointer
        );
        grid.addEventListener(
            'pointercancel',
            finishPointer
        );

        grid.addEventListener(
            'keydown',
            (event) => {
                const handle =
                    event.target.closest(
                        '[data-contributor-sort-handle]'
                    );

                if (
                    !handle
                    || handle.disabled
                    || saving
                ) {
                    return;
                }

                const card = handle.closest(
                    '.llama-photo-card'
                );

                if (!card) {
                    return;
                }

                const cards = getCards();
                const currentIndex =
                    cards.indexOf(card);
                let targetIndex = currentIndex;

                if (
                    event.key === 'ArrowLeft'
                    || event.key === 'ArrowUp'
                ) {
                    targetIndex = currentIndex - 1;
                } else if (
                    event.key === 'ArrowRight'
                    || event.key === 'ArrowDown'
                ) {
                    targetIndex = currentIndex + 1;
                } else if (event.key === 'Home') {
                    targetIndex = 0;
                } else if (event.key === 'End') {
                    targetIndex =
                        cards.length - 1;
                } else {
                    return;
                }

                event.preventDefault();

                if (
                    moveCard(
                        card,
                        targetIndex
                    )
                ) {
                    handle.focus();
                    scheduleSave();
                }
            }
        );

        root.addEventListener(
            'input',
            (event) => {
                if (
                    event.target.matches(
                        '.llama-photo-caption input'
                    )
                ) {
                    window.setTimeout(
                        applyDomOrderToHidden,
                        0
                    );
                }
            }
        );

        const observer =
            new MutationObserver(() => {
                window.requestAnimationFrame(
                    decorate
                );
            });

        observer.observe(
            grid,
            {
                childList: true,
            }
        );

        decorate();
    };

    document.querySelectorAll(
        '[data-photo-uploader]'
    ).forEach(initSorter);
})();
