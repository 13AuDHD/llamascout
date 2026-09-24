(() => {
    'use strict';

    const initDeleteConfirmation = () => {
        const buttons =
            Array.from(
                document.querySelectorAll(
                    '[data-delete-submission]'
                )
            );

        buttons.forEach(
            (button) => {
                let armed = false;
                let resetTimer = null;

                const originalHtml =
                    button.innerHTML;

                const disarm = () => {
                    armed = false;

                    button.classList.remove(
                        'is-armed'
                    );

                    button.removeAttribute(
                        'aria-describedby'
                    );

                    button.innerHTML =
                        originalHtml;

                    if (resetTimer) {
                        window.clearTimeout(
                            resetTimer
                        );

                        resetTimer = null;
                    }
                };

                button.addEventListener(
                    'click',
                    (event) => {
                        if (armed) {
                            /*
                             * Second click is intentional confirmation.
                             * Allow the normal form submission to proceed.
                             */
                            return;
                        }

                        event.preventDefault();

                        armed = true;

                        button.classList.add(
                            'is-armed'
                        );

                        button.innerHTML =
                            '<i class="llama-icon-mask" style="--llama-icon-mask:url(\'https://llamascout.com/assets/icons/alert-triangle.svg\')" aria-hidden="true"></i>'
                            + '<span>Click Again to Delete</span>';

                        resetTimer =
                            window.setTimeout(
                                disarm,
                                6000
                            );
                    }
                );

                button.addEventListener(
                    'blur',
                    () => {
                        /*
                         * Do not disarm immediately on touch devices.
                         * The timeout remains the safety reset.
                         */
                    }
                );
            }
        );
    };


    const initNearbyPlaceMap = () => {
        const report =
            document.querySelector(
                '.scout-report'
            );

        if (!report) {
            return;
        }

        const sections =
            Array.from(
                report.querySelectorAll(
                    ':scope > .scout-report-section'
                )
            );

        const locationSection =
            sections.find(
                (section) => {
                    const heading =
                        section.querySelector('h3');

                    return (
                        heading
                        && heading.textContent
                            .trim()
                            .toLowerCase()
                            === 'location'
                    );
                }
            );

        if (!locationSection) {
            return;
        }

        const existing =
            document.getElementById(
                'admin-nearby-place-map-section'
            );

        if (existing) {
            return;
        }

        const query =
            new URLSearchParams(
                window.location.search
            );

        let submissionId =
            String(
                query.get('id')
                || ''
            ).trim();

        if (submissionId === '') {
            const input =
                document.querySelector(
                    '.admin-submission-decision-form input[name="id"]'
                );

            submissionId =
                String(
                    input?.value
                    || ''
                ).trim();
        }

        if (
            submissionId === ''
            || !/^\d+$/.test(
                submissionId
            )
        ) {
            return;
        }

        const section =
            document.createElement(
                'section'
            );

        section.id =
            'admin-nearby-place-map-section';

        section.className =
            'scout-report-section admin-moderation-nearby-map-section';


        const heading =
            document.createElement(
                'h3'
            );

        heading.textContent =
            'Nearby Place Check';


        const summary =
            document.createElement(
                'p'
            );

        summary.className =
            'scout-report-summary';

        summary.textContent =
            'Compare the submitted coordinates with published Places nearby. Use Satellite for a closer visual inspection when two pins may represent the same campsite.';


        const frameShell =
            document.createElement(
                'div'
            );

        frameShell.className =
            'admin-moderation-nearby-map-shell';


        const frame =
            document.createElement(
                'iframe'
            );

        frame.className =
            'admin-moderation-nearby-map-frame';

        frame.src =
            '/nearby-place-map.php?id='
            + encodeURIComponent(
                submissionId
            );

        frame.title =
            'Nearby Place duplicate inspection map';

        frame.loading =
            'eager';

        frame.setAttribute(
            'referrerpolicy',
            'same-origin'
        );


        frameShell.appendChild(
            frame
        );

        section.append(
            heading,
            summary,
            frameShell
        );


        locationSection.before(
            section
        );
    };


    const initPhotoSorter = () => {
        const grid =
            document.querySelector(
                '.admin-moderation-photo-grid'
            );

        const decisionForm =
            document.querySelector(
                '.admin-submission-decision-form'
            );

        if (!grid || !decisionForm) {
            return;
        }

        const rawImages =
            Array.from(
                grid.children
            ).filter(
                (element) =>
                    element instanceof HTMLImageElement
            );

        if (!rawImages.length) {
            return;
        }

        const submissionIdInput =
            decisionForm.querySelector(
                'input[name="id"]'
            );

        const csrfInput =
            decisionForm.querySelector(
                'input[name="csrf_token"]'
            );

        if (
            !submissionIdInput
            || !csrfInput
        ) {
            return;
        }

        const submissionId =
            String(
                submissionIdInput.value
                || ''
            ).trim();

        const csrfToken =
            String(
                csrfInput.value
                || ''
            ).trim();

        if (
            submissionId === ''
            || csrfToken === ''
        ) {
            return;
        }

        const guide =
            document.createElement('div');

        guide.className =
            'admin-moderation-photo-sorter-guide';

        const help =
            document.createElement('p');

        help.className =
            'admin-moderation-photo-sorter-help';

        help.textContent =
            rawImages.length > 1
                ? 'Drag photos to reorder them. The first photo becomes the featured image when this Place is published.'
                : 'The first photo becomes the featured image when this Place is published.';

        const status =
            document.createElement('p');

        status.className =
            'admin-moderation-photo-sorter-status';

        status.setAttribute(
            'role',
            'status'
        );

        status.setAttribute(
            'aria-live',
            'polite'
        );

        status.textContent =
            rawImages.length > 1
                ? 'Photo order saves automatically.'
                : 'Featured photo selected.';

        guide.append(
            help,
            status
        );

        grid.parentNode.insertBefore(
            guide,
            grid
        );

        rawImages.forEach(
            (image, index) => {
                const item =
                    document.createElement(
                        'figure'
                    );

                item.className =
                    'admin-moderation-photo-item';

                item.dataset.photoIndex =
                    String(index);

                const media =
                    document.createElement('div');

                media.className =
                    'admin-moderation-photo-media';

                grid.insertBefore(
                    item,
                    image
                );

                media.appendChild(image);
                item.appendChild(media);

                const featured =
                    document.createElement('span');

                featured.className =
                    'admin-moderation-photo-featured';

                featured.textContent =
                    'Featured';

                item.appendChild(featured);

                const position =
                    document.createElement('span');

                position.className =
                    'admin-moderation-photo-position';

                position.setAttribute(
                    'aria-hidden',
                    'true'
                );

                item.appendChild(position);

                if (rawImages.length > 1) {
                    const handle =
                        document.createElement('button');

                    handle.type = 'button';

                    handle.className =
                        'admin-moderation-photo-sort-handle';

                    handle.setAttribute(
                        'data-photo-sort-handle',
                        ''
                    );

                    handle.setAttribute(
                        'title',
                        'Drag to reorder photo'
                    );

                    const handleMark =
                        document.createElement('span');

                    handleMark.setAttribute(
                        'aria-hidden',
                        'true'
                    );

                    handleMark.textContent =
                        '⋮⋮';

                    handle.appendChild(
                        handleMark
                    );

                    item.appendChild(
                        handle
                    );
                }
            }
        );

        const getItems = () =>
            Array.from(
                grid.querySelectorAll(
                    '.admin-moderation-photo-item'
                )
            );

        const actionButtons =
            Array.from(
                decisionForm.querySelectorAll(
                    'button[type="submit"]'
                )
            );

        const setStatus = (
            message,
            state = ''
        ) => {
            status.textContent =
                message;

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

        const refreshPresentation = () => {
            const items =
                getItems();

            const total =
                items.length;

            items.forEach(
                (item, index) => {
                    item.classList.toggle(
                        'is-featured',
                        index === 0
                    );

                    const featured =
                        item.querySelector(
                            '.admin-moderation-photo-featured'
                        );

                    if (featured) {
                        featured.hidden =
                            index !== 0;
                    }

                    const position =
                        item.querySelector(
                            '.admin-moderation-photo-position'
                        );

                    if (position) {
                        position.textContent =
                            String(
                                index + 1
                            );
                    }

                    const handle =
                        item.querySelector(
                            '[data-photo-sort-handle]'
                        );

                    if (!handle) {
                        return;
                    }

                    let label =
                        'Move photo '
                        + (index + 1)
                        + ' of '
                        + total
                        + '. Drag to reorder';

                    if (index === 0) {
                        label +=
                            '. This is the featured photo';
                    }

                    handle.setAttribute(
                        'aria-label',
                        label + '.'
                    );
                }
            );
        };

        const setDecisionDisabled = (
            disabled
        ) => {
            actionButtons.forEach(
                (button) => {
                    button.disabled =
                        disabled;
                }
            );
        };

        const setSortingDisabled = (
            disabled
        ) => {
            grid.classList.toggle(
                'is-saving',
                disabled
            );

            getItems().forEach(
                (item) => {
                    const handle =
                        item.querySelector(
                            '[data-photo-sort-handle]'
                        );

                    if (handle) {
                        handle.disabled =
                            disabled;
                    }
                }
            );
        };

        let lastSavedItems =
            getItems();

        let saveTimer =
            null;

        let saving =
            false;

        const currentOrder = () =>
            getItems().map(
                (item) =>
                    Number.parseInt(
                        item.dataset.photoIndex
                        || '',
                        10
                    )
            );

        const orderIsBaseline = (
            order
        ) =>
            order.every(
                (value, index) =>
                    value === index
            );

        const restoreLastSavedOrder =
            () => {
                lastSavedItems.forEach(
                    (item) => {
                        grid.appendChild(
                            item
                        );
                    }
                );

                refreshPresentation();
            };

        const saveOrder =
            async () => {
                if (saving) {
                    return;
                }

                if (saveTimer) {
                    window.clearTimeout(
                        saveTimer
                    );

                    saveTimer =
                        null;
                }

                const order =
                    currentOrder();

                if (
                    orderIsBaseline(
                        order
                    )
                ) {
                    refreshPresentation();

                    setDecisionDisabled(
                        false
                    );

                    setStatus(
                        'Photo order saved.',
                        'saved'
                    );

                    return;
                }

                if (
                    order.some(
                        (value) =>
                            !Number.isInteger(
                                value
                            )
                    )
                ) {
                    restoreLastSavedOrder();

                    setDecisionDisabled(
                        false
                    );

                    setStatus(
                        'Photo order could not be read. Reload this page before trying again.',
                        'error'
                    );

                    return;
                }

                saving =
                    true;

                setSortingDisabled(
                    true
                );

                setDecisionDisabled(
                    true
                );

                setStatus(
                    'Saving photo order…',
                    'saving'
                );

                const body =
                    new URLSearchParams();

                body.set(
                    'id',
                    submissionId
                );

                body.set(
                    'csrf_token',
                    csrfToken
                );

                body.set(
                    'order',
                    JSON.stringify(
                        order
                    )
                );

                try {
                    const response =
                        await fetch(
                            '/save-submission-photo-order.php',
                            {
                                method:
                                    'POST',

                                credentials:
                                    'same-origin',

                                headers: {
                                    'Content-Type':
                                        'application/x-www-form-urlencoded;charset=UTF-8',

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                body:
                                    body.toString(),
                            }
                        );

                    let payload =
                        null;

                    try {
                        payload =
                            await response.json();
                    } catch (error) {
                        payload =
                            null;
                    }

                    if (
                        !response.ok
                        || !payload
                        || payload.ok !== true
                    ) {
                        throw new Error(
                            payload
                            && typeof payload.message
                                === 'string'
                            && payload.message !== ''
                                ? payload.message
                                : 'The photo order could not be saved.'
                        );
                    }

                    const savedItems =
                        getItems();

                    savedItems.forEach(
                        (item, index) => {
                            item.dataset.photoIndex =
                                String(index);
                        }
                    );

                    lastSavedItems =
                        savedItems.slice();

                    refreshPresentation();

                    setStatus(
                        'Photo order saved.',
                        'saved'
                    );

                } catch (error) {
                    restoreLastSavedOrder();

                    setStatus(
                        error instanceof Error
                        && error.message !== ''
                            ? error.message
                            : 'The photo order could not be saved.',
                        'error'
                    );

                } finally {
                    saving =
                        false;

                    setSortingDisabled(
                        false
                    );

                    setDecisionDisabled(
                        false
                    );
                }
            };

        const scheduleSave = () => {
            if (saveTimer) {
                window.clearTimeout(
                    saveTimer
                );
            }

            setDecisionDisabled(
                true
            );

            setStatus(
                'Photo order changed. Saving…',
                'saving'
            );

            saveTimer =
                window.setTimeout(
                    saveOrder,
                    250
                );
        };

        const moveItem = (
            item,
            targetIndex
        ) => {
            const items =
                getItems();

            const currentIndex =
                items.indexOf(
                    item
                );

            if (
                currentIndex < 0
                || targetIndex < 0
                || targetIndex >=
                    items.length
                || targetIndex ===
                    currentIndex
            ) {
                return false;
            }

            const target =
                items[
                    targetIndex
                ];

            if (
                targetIndex
                > currentIndex
            ) {
                grid.insertBefore(
                    item,
                    target.nextSibling
                );
            } else {
                grid.insertBefore(
                    item,
                    target
                );
            }

            refreshPresentation();

            return true;
        };

        let draggedItem =
            null;

        let activePointerId =
            null;

        let pointerStartOrder =
            '';

        grid.addEventListener(
            'pointerdown',
            (event) => {
                const handle =
                    event.target.closest(
                        '[data-photo-sort-handle]'
                    );

                if (
                    !handle
                    || handle.disabled
                    || saving
                    || (
                        event.pointerType
                            === 'mouse'
                        && event.button !== 0
                    )
                ) {
                    return;
                }

                const item =
                    handle.closest(
                        '.admin-moderation-photo-item'
                    );

                if (!item) {
                    return;
                }

                event.preventDefault();

                draggedItem =
                    item;

                activePointerId =
                    event.pointerId;

                pointerStartOrder =
                    currentOrder()
                        .join(',');

                item.classList.add(
                    'is-dragging'
                );

                grid.classList.add(
                    'is-sorting'
                );

                try {
                    handle.setPointerCapture(
                        event.pointerId
                    );
                } catch (error) {
                    /*
                     * Pointer capture is optional.
                     */
                }
            }
        );

        grid.addEventListener(
            'pointermove',
            (event) => {
                if (
                    !draggedItem
                    || activePointerId
                        !== event.pointerId
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

                const targetItem =
                    hit
                        ? hit.closest(
                            '.admin-moderation-photo-item'
                        )
                        : null;

                if (
                    !targetItem
                    || targetItem
                        === draggedItem
                    || targetItem.parentNode
                        !== grid
                ) {
                    return;
                }

                const items =
                    getItems();

                const draggedIndex =
                    items.indexOf(
                        draggedItem
                    );

                const targetIndex =
                    items.indexOf(
                        targetItem
                    );

                if (
                    draggedIndex < 0
                    || targetIndex < 0
                ) {
                    return;
                }

                if (
                    targetIndex
                    > draggedIndex
                ) {
                    grid.insertBefore(
                        draggedItem,
                        targetItem.nextSibling
                    );
                } else {
                    grid.insertBefore(
                        draggedItem,
                        targetItem
                    );
                }

                refreshPresentation();
            }
        );

        const finishPointerSort = (
            event
        ) => {
            if (
                !draggedItem
                || activePointerId
                    !== event.pointerId
            ) {
                return;
            }

            const item =
                draggedItem;

            item.classList.remove(
                'is-dragging'
            );

            grid.classList.remove(
                'is-sorting'
            );

            draggedItem =
                null;

            activePointerId =
                null;

            const newOrder =
                currentOrder()
                    .join(',');

            if (
                newOrder
                !== pointerStartOrder
            ) {
                scheduleSave();
            }
        };

        grid.addEventListener(
            'pointerup',
            finishPointerSort
        );

        grid.addEventListener(
            'pointercancel',
            finishPointerSort
        );

        grid.addEventListener(
            'keydown',
            (event) => {
                const handle =
                    event.target.closest(
                        '[data-photo-sort-handle]'
                    );

                if (
                    !handle
                    || handle.disabled
                    || saving
                ) {
                    return;
                }

                const item =
                    handle.closest(
                        '.admin-moderation-photo-item'
                    );

                if (!item) {
                    return;
                }

                const items =
                    getItems();

                const currentIndex =
                    items.indexOf(
                        item
                    );

                let targetIndex =
                    currentIndex;

                if (
                    event.key ===
                        'ArrowLeft'
                    || event.key ===
                        'ArrowUp'
                ) {
                    targetIndex =
                        currentIndex - 1;

                } else if (
                    event.key ===
                        'ArrowRight'
                    || event.key ===
                        'ArrowDown'
                ) {
                    targetIndex =
                        currentIndex + 1;

                } else if (
                    event.key ===
                        'Home'
                ) {
                    targetIndex =
                        0;

                } else if (
                    event.key ===
                        'End'
                ) {
                    targetIndex =
                        items.length - 1;

                } else {
                    return;
                }

                event.preventDefault();

                if (
                    moveItem(
                        item,
                        targetIndex
                    )
                ) {
                    handle.focus();

                    scheduleSave();
                }
            }
        );

        refreshPresentation();
    };


    initDeleteConfirmation();
    initNearbyPlaceMap();
    initPhotoSorter();
})();
