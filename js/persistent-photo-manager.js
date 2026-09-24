(() => {
    'use strict';

    const ROOT_SELECTOR = '.community-profile-image-manager';
    const GRID_SELECTOR = '.community-profile-image-grid';
    const CARD_SELECTOR = '.community-profile-image-card';
    const ENDPOINT = '/profile-photo-manager.php';
    const STYLE_HREF = '/css/persistent-photo-manager.css';
    const DRAG_THRESHOLD = 6;

    function ensureStyles() {
        if (document.querySelector('link[data-persistent-photo-manager-style]')) {
            return;
        }

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = STYLE_HREF;
        link.dataset.persistentPhotoManagerStyle = '1';
        document.head.appendChild(link);
    }

    function initManager(root) {
        const grid = root.querySelector(GRID_SELECTOR);
        if (!grid) {
            return;
        }

        ensureStyles();

        const csrfInput = root.querySelector('input[name="csrf_token"]');
        const csrf = csrfInput ? csrfInput.value : '';
        const primaryPreview = root.querySelector('#profileImagePrimaryPreview');
        const countBadge = root.querySelector('.account-profile-status');
        const primaryBlock = root.querySelector('.community-profile-primary');
        const primaryHeading = primaryBlock ? primaryBlock.querySelector('h3') : null;
        const primaryCopy = primaryBlock ? primaryBlock.querySelector('p') : null;
        const emptyImageUrl = '/images/llamalogo.png';

        let requestChain = Promise.resolve();
        let dragState = null;

        const status = document.createElement('div');
        status.className = 'persistent-photo-manager__status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        status.setAttribute('aria-atomic', 'true');
        grid.insertAdjacentElement('afterend', status);

        if (primaryHeading) {
            primaryHeading.textContent = 'Featured profile photo';
        }
        if (primaryCopy) {
            primaryCopy.textContent = 'The first photo in your gallery is featured. Drag photos to rearrange them.';
        }

        function cards() {
            return Array.from(grid.querySelectorAll(CARD_SELECTOR));
        }

        function imageId(card) {
            const input = card.querySelector('input[name="image_id"]');
            const value = input ? Number.parseInt(input.value, 10) : 0;
            return Number.isFinite(value) && value > 0 ? value : 0;
        }

        function imageLabel(card) {
            const filename = card.querySelector('.community-profile-image-card__filename');
            const text = filename ? filename.textContent.trim() : '';
            return text || 'profile photo';
        }

        function orderedIds() {
            return cards().map(imageId).filter((id) => id > 0);
        }

        function announce(message, isError = false) {
            status.textContent = message;
            status.classList.toggle('is-error', isError);
        }

        function request(payload) {
            const run = async () => {
                const response = await fetch(ENDPOINT, {
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
                    const message = data && data.message
                        ? data.message
                        : 'Llama Scout could not save that photo change.';
                    throw new Error(message);
                }

                return data;
            };

            requestChain = requestChain.then(run, run);
            return requestChain;
        }

        function getActionForm(card, actionName) {
            return Array.from(card.querySelectorAll('form')).find((form) => {
                const action = form.querySelector('input[name="action"]');
                return action && action.value === actionName;
            }) || null;
        }

        function getOrCreateFeaturedBadge(card) {
            const titleRow = card.querySelector('.community-profile-image-card__title-row');
            if (!titleRow) {
                return null;
            }

            let badge = titleRow.querySelector('.community-profile-primary-badge');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'community-profile-primary-badge';
                titleRow.appendChild(badge);
            }
            return badge;
        }

        function refreshUi() {
            const allCards = cards();
            const first = allCards[0] || null;

            allCards.forEach((card, index) => {
                const badge = card.querySelector('.community-profile-primary-badge');
                const controls = card.querySelector('.persistent-photo-manager__controls');
                const earlier = controls ? controls.querySelector('[data-photo-move="earlier"]') : null;
                const later = controls ? controls.querySelector('[data-photo-move="later"]') : null;

                card.classList.toggle('is-primary', index === 0);
                card.dataset.photoPosition = String(index + 1);

                if (index === 0) {
                    const featuredBadge = getOrCreateFeaturedBadge(card);
                    if (featuredBadge) {
                        featuredBadge.textContent = 'Featured';
                    }
                } else if (badge) {
                    badge.remove();
                }

                if (earlier) {
                    earlier.disabled = index === 0;
                }
                if (later) {
                    later.disabled = index === allCards.length - 1;
                }
            });

            if (primaryPreview) {
                const firstImage = first ? first.querySelector('img') : null;
                primaryPreview.src = firstImage && firstImage.src ? firstImage.src : emptyImageUrl;
                primaryPreview.alt = first ? 'Featured profile photo' : 'Default profile picture';
            }

            if (countBadge) {
                countBadge.textContent = `${allCards.length} / 10`;
            }
        }

        function saveOrder(message = 'Photo order saved.') {
            const ids = orderedIds();
            announce('Saving photo order...');

            return request({
                action: 'reorder',
                image_ids: ids,
            }).then((data) => {
                refreshUi();
                announce(message || data.message || 'Photo order saved.');
                return data;
            }).catch((error) => {
                announce(error.message, true);
                throw error;
            });
        }

        function moveCard(card, direction) {
            const allCards = cards();
            const index = allCards.indexOf(card);
            if (index < 0) {
                return;
            }

            const nextIndex = index + direction;
            if (nextIndex < 0 || nextIndex >= allCards.length) {
                return;
            }

            if (direction < 0) {
                grid.insertBefore(card, allCards[nextIndex]);
            } else {
                grid.insertBefore(allCards[nextIndex], card);
            }

            refreshUi();
            saveOrder().catch(() => {});
            card.focus({ preventScroll: true });
        }

        function addControls(card) {
            if (card.querySelector('.persistent-photo-manager__controls')) {
                return;
            }

            const meta = card.querySelector('.community-profile-image-card__meta');
            if (!meta) {
                return;
            }

            card.tabIndex = -1;
            card.dataset.photoId = String(imageId(card));

            const controls = document.createElement('div');
            controls.className = 'persistent-photo-manager__controls';

            const drag = document.createElement('button');
            drag.type = 'button';
            drag.className = 'pill-button persistent-photo-manager__drag';
            drag.textContent = 'Drag';
            drag.setAttribute('aria-label', `Drag ${imageLabel(card)} to rearrange`);
            drag.title = 'Drag to rearrange';

            const earlier = document.createElement('button');
            earlier.type = 'button';
            earlier.className = 'pill-button persistent-photo-manager__move';
            earlier.dataset.photoMove = 'earlier';
            earlier.textContent = 'Move earlier';
            earlier.addEventListener('click', () => moveCard(card, -1));

            const later = document.createElement('button');
            later.type = 'button';
            later.className = 'pill-button persistent-photo-manager__move';
            later.dataset.photoMove = 'later';
            later.textContent = 'Move later';
            later.addEventListener('click', () => moveCard(card, 1));

            controls.append(drag, earlier, later);
            meta.insertBefore(controls, meta.querySelector('.community-profile-image-card__actions'));

            const makePrimaryForm = getActionForm(card, 'make_primary');
            if (makePrimaryForm) {
                makePrimaryForm.hidden = true;
            }

            const deleteForm = getActionForm(card, 'delete');
            if (deleteForm) {
                const deleteButton = deleteForm.querySelector('button[type="submit"], button:not([type])');
                if (deleteButton) {
                    deleteButton.addEventListener('click', (event) => {
                        event.preventDefault();

                        if (!window.confirm('Remove this profile photo?')) {
                            return;
                        }

                        deleteButton.disabled = true;
                        announce('Removing profile photo...');

                        request({
                            action: 'delete',
                            image_id: imageId(card),
                        }).then(() => {
                            card.remove();
                            refreshUi();
                            announce('Profile photo removed.');
                        }).catch((error) => {
                            deleteButton.disabled = false;
                            announce(error.message, true);
                        });
                    });
                }
            }

            drag.addEventListener('pointerdown', (event) => {
                if (event.button !== undefined && event.button !== 0) {
                    return;
                }

                dragState = {
                    card,
                    handle: drag,
                    pointerId: event.pointerId,
                    startX: event.clientX,
                    startY: event.clientY,
                    started: false,
                    originalOrder: orderedIds().join(','),
                };

                if (drag.setPointerCapture) {
                    try {
                        drag.setPointerCapture(event.pointerId);
                    } catch (error) {
                        // Pointer capture is helpful, but not required.
                    }
                }
            });
        }

        function pointerMove(event) {
            if (!dragState || event.pointerId !== dragState.pointerId) {
                return;
            }

            const dx = event.clientX - dragState.startX;
            const dy = event.clientY - dragState.startY;

            if (!dragState.started && Math.hypot(dx, dy) < DRAG_THRESHOLD) {
                return;
            }

            if (!dragState.started) {
                dragState.started = true;
                dragState.card.classList.add('is-photo-manager-dragging');
                root.classList.add('is-photo-manager-sorting');
            }

            event.preventDefault();

            const elements = document.elementsFromPoint(event.clientX, event.clientY);
            const target = elements
                .map((element) => element.closest ? element.closest(CARD_SELECTOR) : null)
                .find((candidate) => candidate && candidate !== dragState.card && grid.contains(candidate));

            if (!target) {
                return;
            }

            const rect = target.getBoundingClientRect();
            const centerX = rect.left + (rect.width / 2);
            const centerY = rect.top + (rect.height / 2);
            const verticalDistance = Math.abs(event.clientY - centerY);
            const sameVisualRow = verticalDistance < rect.height * 0.35;
            const insertBefore = sameVisualRow
                ? event.clientX < centerX
                : event.clientY < centerY;

            if (insertBefore) {
                grid.insertBefore(dragState.card, target);
            } else {
                grid.insertBefore(dragState.card, target.nextSibling);
            }

            refreshUi();
        }

        function finishDrag(event) {
            if (!dragState || (event.pointerId !== undefined && event.pointerId !== dragState.pointerId)) {
                return;
            }

            const state = dragState;
            dragState = null;

            state.card.classList.remove('is-photo-manager-dragging');
            root.classList.remove('is-photo-manager-sorting');

            if (state.handle.releasePointerCapture && event.pointerId !== undefined) {
                try {
                    state.handle.releasePointerCapture(event.pointerId);
                } catch (error) {
                    // The pointer may already have been released by the browser.
                }
            }

            if (!state.started) {
                return;
            }

            refreshUi();
            const newOrder = orderedIds().join(',');
            if (newOrder !== state.originalOrder) {
                saveOrder().catch(() => {});
            }
        }

        document.addEventListener('pointermove', pointerMove, { passive: false });
        document.addEventListener('pointerup', finishDrag);
        document.addEventListener('pointercancel', finishDrag);

        const existingPrimary = grid.querySelector(`${CARD_SELECTOR}.is-primary`);
        const firstCard = cards()[0] || null;
        const needsInitialSync = Boolean(existingPrimary && firstCard && existingPrimary !== firstCard);

        if (needsInitialSync) {
            grid.insertBefore(existingPrimary, firstCard);
        }

        cards().forEach(addControls);
        refreshUi();

        if (needsInitialSync) {
            saveOrder('Featured photo synced with your gallery.').catch(() => {});
        }
    }

    function boot() {
        document.querySelectorAll(ROOT_SELECTOR).forEach(initManager);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
