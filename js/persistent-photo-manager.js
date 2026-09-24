(() => {
    'use strict';

    const ROOT_SELECTOR = '[data-persistent-photo-manager]';
    const CARD_SELECTOR = '[data-photo-manager-card]';
    const DRAG_THRESHOLD = 6;
    const scriptElement = document.currentScript;
    const assetOrigin = scriptElement && scriptElement.src
        ? new URL(scriptElement.src, window.location.href).origin
        : window.location.origin;

    function ensureStyles() {
        if (document.querySelector('link[data-persistent-photo-manager-style]')) {
            return;
        }

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = `${assetOrigin}/css/persistent-photo-manager.css?v=20260923-3`;
        link.dataset.persistentPhotoManagerStyle = '1';
        document.head.appendChild(link);
    }

    function initManager(root) {
        const list = root.querySelector('[data-photo-manager-list]');
        if (!list || root.dataset.photoManagerReady === '1') {
            return;
        }

        root.dataset.photoManagerReady = '1';
        ensureStyles();

        const endpoint = root.dataset.photoManagerEndpoint || '/profile-image-action.php';
        const csrf = root.dataset.photoManagerCsrf || '';
        const avatarSelector = root.dataset.photoAvatarSelector || '.community-profile-avatar-large';
        const avatar = document.querySelector(avatarSelector);
        const defaultSrc = root.dataset.photoDefaultSrc || '';
        const countNode = document.querySelector('[data-profile-photo-count]');
        const status = root.querySelector('[data-photo-manager-status]');
        const help = root.querySelector('.persistent-photo-manager__help');
        const initialPrimaryId = Number.parseInt(root.dataset.photoPrimaryId || '0', 10);

        let requestChain = Promise.resolve();
        let dragState = null;
        let confirmedOrder = [];

        if (help) {
            help.textContent = 'Grab the handle in the top-right corner of a photo to rearrange it. Photo #1 is always Featured. Earlier and Later are there when dragging is not convenient.';
        }

        function cards() {
            return Array.from(list.querySelectorAll(CARD_SELECTOR));
        }

        function imageId(card) {
            const value = Number.parseInt(card.dataset.photoId || '0', 10);
            return Number.isFinite(value) && value > 0 ? value : 0;
        }

        function orderedIds() {
            return cards().map(imageId).filter((id) => id > 0);
        }

        function announce(message, isError = false) {
            if (!status) {
                return;
            }

            status.textContent = message;
            status.classList.toggle('is-error', isError);
        }

        function request(payload) {
            const run = async () => {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrf,
                    },
                    body: JSON.stringify(payload),
                });

                let data = null;

                try {
                    data = await response.json();
                } catch (error) {
                    data = null;
                }

                if (!response.ok || !data || data.ok !== true) {
                    throw new Error(
                        data && data.message
                            ? data.message
                            : 'Llama Scout could not save that photo change.'
                    );
                }

                return data;
            };

            requestChain = requestChain.then(run, run);
            return requestChain;
        }

        function restoreOrder(ids) {
            const byId = new Map(cards().map((card) => [imageId(card), card]));

            ids.forEach((id) => {
                const card = byId.get(id);
                if (card) {
                    list.appendChild(card);
                }
            });
        }

        function ensurePositionNumber(card) {
            const imageWrap = card.querySelector('.persistent-photo-manager__image-wrap');

            if (!imageWrap) {
                return null;
            }

            let number = imageWrap.querySelector('[data-photo-position-number]');

            if (!number) {
                number = document.createElement('span');
                number.className = 'persistent-photo-manager__position';
                number.setAttribute('data-photo-position-number', '');
                number.setAttribute('aria-hidden', 'true');
                imageWrap.appendChild(number);
            }

            return number;
        }

        function refreshUi() {
            const allCards = cards();
            const first = allCards[0] || null;
            const total = allCards.length;

            allCards.forEach((card, index) => {
                const badge = card.querySelector('[data-photo-featured-badge]');
                const earlier = card.querySelector('[data-photo-move-earlier]');
                const later = card.querySelector('[data-photo-move-later]');
                const handle = card.querySelector('[data-photo-drag-handle]');
                const number = ensurePositionNumber(card);

                card.classList.toggle('is-featured', index === 0);
                card.dataset.photoPosition = String(index + 1);

                if (badge) {
                    badge.hidden = index !== 0;
                }

                if (number) {
                    number.textContent = String(index + 1);
                }

                if (handle) {
                    let label = `Move photo ${index + 1} of ${total}. Drag to reorder.`;

                    if (index === 0) {
                        label += ' This is the Featured profile photo.';
                    }

                    handle.setAttribute('aria-label', label);
                    handle.setAttribute(
                        'title',
                        `Drag photo ${index + 1} to rearrange`
                    );
                }

                if (earlier) {
                    earlier.disabled = index === 0;
                }

                if (later) {
                    later.disabled = index === allCards.length - 1;
                }
            });

            if (avatar) {
                const firstImage = first
                    ? first.querySelector('[data-photo-preview]')
                    : null;

                avatar.src = firstImage && firstImage.src
                    ? firstImage.src
                    : defaultSrc;

                avatar.alt = first
                    ? 'Featured profile photo'
                    : 'Default profile picture';
            }

            if (countNode) {
                countNode.textContent = `${allCards.length}/5`;
            }

            root.classList.toggle('is-empty', allCards.length === 0);
        }

        function persistOrder(
            message = 'Photo order saved.',
            initialSync = false
        ) {
            const ids = orderedIds();

            announce('Saving photo order...');

            return request({
                action: 'reorder',
                image_ids: ids,
            }).then((data) => {
                confirmedOrder = ids.slice();

                refreshUi();

                announce(
                    message
                    || data.message
                    || 'Photo order saved.'
                );

                return data;
            }).catch((error) => {
                if (initialSync) {
                    announce(error.message, true);

                    window.setTimeout(
                        () => window.location.reload(),
                        900
                    );

                    throw error;
                }

                restoreOrder(confirmedOrder);
                refreshUi();
                announce(error.message, true);

                throw error;
            });
        }

        function moveCard(card, direction) {
            const allCards = cards();
            const index = allCards.indexOf(card);
            const nextIndex = index + direction;

            if (
                index < 0
                || nextIndex < 0
                || nextIndex >= allCards.length
            ) {
                return;
            }

            if (direction < 0) {
                list.insertBefore(
                    card,
                    allCards[nextIndex]
                );
            } else {
                list.insertBefore(
                    allCards[nextIndex],
                    card
                );
            }

            refreshUi();

            persistOrder().catch(() => {});
        }

        function removeCard(card, button) {
            if (!window.confirm('Remove this profile photo?')) {
                return;
            }

            button.disabled = true;

            announce('Removing profile photo...');

            request({
                action: 'delete',
                image_id: imageId(card),
            }).then(() => {
                card.remove();

                confirmedOrder = orderedIds();

                refreshUi();

                announce('Profile photo removed.');

                window.setTimeout(
                    () => window.location.reload(),
                    450
                );
            }).catch((error) => {
                button.disabled = false;

                announce(error.message, true);
            });
        }

        function prepareCardLayout(card) {
            const controls = card.querySelector(
                '.persistent-photo-manager__controls'
            );

            const imageWrap = card.querySelector(
                '.persistent-photo-manager__image-wrap'
            );

            const handle = card.querySelector(
                '[data-photo-drag-handle]'
            );

            const remove = card.querySelector(
                '[data-photo-remove]'
            );

            const earlier = card.querySelector(
                '[data-photo-move-earlier]'
            );

            ensurePositionNumber(card);

            if (
                handle
                && imageWrap
                && handle.parentNode !== imageWrap
            ) {
                handle.innerHTML =
                    '<span aria-hidden="true">•••</span>';

                imageWrap.appendChild(handle);
            }

            if (
                controls
                && remove
                && earlier
                && remove.nextElementSibling !== earlier
            ) {
                controls.insertBefore(remove, earlier);
            }
        }

        function bindCard(card) {
            prepareCardLayout(card);

            const earlier = card.querySelector(
                '[data-photo-move-earlier]'
            );

            const later = card.querySelector(
                '[data-photo-move-later]'
            );

            const remove = card.querySelector(
                '[data-photo-remove]'
            );

            const handle = card.querySelector(
                '[data-photo-drag-handle]'
            );

            if (earlier) {
                earlier.addEventListener(
                    'click',
                    () => moveCard(card, -1)
                );
            }

            if (later) {
                later.addEventListener(
                    'click',
                    () => moveCard(card, 1)
                );
            }

            if (remove) {
                remove.addEventListener(
                    'click',
                    () => removeCard(card, remove)
                );
            }

            if (!handle) {
                return;
            }

            handle.addEventListener(
                'pointerdown',
                (event) => {
                    if (
                        event.button !== undefined
                        && event.button !== 0
                    ) {
                        return;
                    }

                    dragState = {
                        card,
                        handle,
                        pointerId: event.pointerId,
                        startX: event.clientX,
                        startY: event.clientY,
                        started: false,
                        originalOrder:
                            orderedIds().join(','),
                    };

                    if (handle.setPointerCapture) {
                        try {
                            handle.setPointerCapture(
                                event.pointerId
                            );
                        } catch (error) {
                            // Pointer capture is helpful,
                            // but not required.
                        }
                    }
                }
            );

            handle.addEventListener(
                'keydown',
                (event) => {
                    let direction = 0;

                    if (
                        event.key === 'ArrowLeft'
                        || event.key === 'ArrowUp'
                    ) {
                        direction = -1;
                    } else if (
                        event.key === 'ArrowRight'
                        || event.key === 'ArrowDown'
                    ) {
                        direction = 1;
                    } else if (
                        event.key === 'Home'
                    ) {
                        const allCards = cards();
                        const currentIndex =
                            allCards.indexOf(card);

                        if (currentIndex > 0) {
                            event.preventDefault();

                            list.insertBefore(
                                card,
                                allCards[0]
                            );

                            refreshUi();

                            persistOrder()
                                .catch(() => {});
                        }

                        return;
                    } else if (
                        event.key === 'End'
                    ) {
                        const allCards = cards();
                        const currentIndex =
                            allCards.indexOf(card);

                        if (
                            currentIndex >= 0
                            && currentIndex
                                < allCards.length - 1
                        ) {
                            event.preventDefault();

                            list.appendChild(card);

                            refreshUi();

                            persistOrder()
                                .catch(() => {});
                        }

                        return;
                    } else {
                        return;
                    }

                    event.preventDefault();

                    moveCard(card, direction);

                    handle.focus();
                }
            );
        }

        function pointerMove(event) {
            if (
                !dragState
                || event.pointerId !== dragState.pointerId
            ) {
                return;
            }

            const dx =
                event.clientX - dragState.startX;

            const dy =
                event.clientY - dragState.startY;

            if (
                !dragState.started
                && Math.hypot(dx, dy) < DRAG_THRESHOLD
            ) {
                return;
            }

            if (!dragState.started) {
                dragState.started = true;

                dragState.card.classList.add(
                    'is-photo-manager-dragging'
                );

                root.classList.add(
                    'is-photo-manager-sorting'
                );
            }

            event.preventDefault();

            const target =
                document.elementsFromPoint(
                    event.clientX,
                    event.clientY
                )
                .map(
                    (element) =>
                        element.closest
                            ? element.closest(
                                CARD_SELECTOR
                            )
                            : null
                )
                .find(
                    (candidate) => (
                        candidate
                        && candidate
                            !== dragState.card
                        && list.contains(candidate)
                    )
                );

            if (!target) {
                return;
            }

            const rect =
                target.getBoundingClientRect();

            const centerX =
                rect.left + (rect.width / 2);

            const centerY =
                rect.top + (rect.height / 2);

            const sameRow =
                Math.abs(
                    event.clientY - centerY
                ) < rect.height * 0.35;

            const before = sameRow
                ? event.clientX < centerX
                : event.clientY < centerY;

            if (before) {
                list.insertBefore(
                    dragState.card,
                    target
                );
            } else {
                list.insertBefore(
                    dragState.card,
                    target.nextSibling
                );
            }

            refreshUi();
        }

        function finishDrag(event) {
            if (!dragState) {
                return;
            }

            if (
                event.pointerId !== undefined
                && event.pointerId
                    !== dragState.pointerId
            ) {
                return;
            }

            const state = dragState;

            dragState = null;

            state.card.classList.remove(
                'is-photo-manager-dragging'
            );

            root.classList.remove(
                'is-photo-manager-sorting'
            );

            if (
                state.handle.releasePointerCapture
                && event.pointerId !== undefined
            ) {
                try {
                    state.handle.releasePointerCapture(
                        event.pointerId
                    );
                } catch (error) {
                    // The browser may already have
                    // released the pointer.
                }
            }

            if (!state.started) {
                return;
            }

            refreshUi();

            if (
                orderedIds().join(',')
                !== state.originalOrder
            ) {
                persistOrder().catch(() => {});
            }
        }

        confirmedOrder = orderedIds();

        cards().forEach(bindCard);

        document.addEventListener(
            'pointermove',
            pointerMove,
            { passive: false }
        );

        document.addEventListener(
            'pointerup',
            finishDrag
        );

        document.addEventListener(
            'pointercancel',
            finishDrag
        );

        const primaryCard =
            initialPrimaryId > 0
                ? cards().find(
                    (card) =>
                        imageId(card)
                            === initialPrimaryId
                )
                : null;

        const firstCard =
            cards()[0] || null;

        const needsInitialSync =
            Boolean(
                primaryCard
                && firstCard
                && primaryCard !== firstCard
            );

        if (needsInitialSync) {
            list.insertBefore(
                primaryCard,
                firstCard
            );
        }

        refreshUi();

        if (needsInitialSync) {
            persistOrder(
                'Featured photo synced with your gallery.',
                true
            ).catch(() => {});
        }
    }

    function boot() {
        document
            .querySelectorAll(ROOT_SELECTOR)
            .forEach(initManager);
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            boot,
            { once: true }
        );
    } else {
        boot();
    }
})();
