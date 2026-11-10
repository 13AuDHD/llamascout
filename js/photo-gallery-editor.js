/* One photo gallery editor for Place, Site, Area and Resource admins.
 * Reorder is a continuous pointer gesture with a floating preview and an
 * insertion placeholder. No CDN dependency or parallel photo storage. */
(() => {
    'use strict';

    const mount = (grid, options = {}) => {
        if (!grid) return null;
        const removeName = options.removeName || 'remove_existing_photo_ids[]';
        const onChange = typeof options.onChange === 'function'
            ? options.onChange : () => {};
        grid.classList.add('admin-photo-gallery');
        grid.dataset.adminPhotoSortable = '1';
        grid.setAttribute('aria-label', 'Photo gallery. Drag a photo handle to reorder.');

        const cards = () => Array.from(grid.querySelectorAll('[data-admin-photo-id]'));
        const items = () => cards().map(card => ({
            id: Number(card.dataset.adminPhotoId),
            alt: String(card.querySelector('[data-admin-photo-caption]')?.value || ''),
            removed: Boolean(card.querySelector('[data-admin-photo-remove]')?.checked),
        }));
        const refreshHero = () => {
            let first = true;
            cards().forEach(card => {
                const removed = Boolean(card.querySelector('[data-admin-photo-remove]')?.checked);
                card.classList.toggle('is-photo-removed', removed);
                const hero = card.querySelector('[data-admin-photo-hero]');
                if (hero) hero.hidden = removed || !first;
                if (!removed) first = false;
            });
        };
        const changed = () => {
            refreshHero();
            onChange();
        };
        const createCard = photo => {
            const id = Number(photo.id);
            const card = document.createElement('article');
            card.className = 'add-place-existing-photo admin-attached-photo';
            card.dataset.adminPhotoId = String(id);

            const preview = document.createElement('div');
            preview.className = 'admin-attached-photo-preview';
            const img = document.createElement('img');
            img.src = String(photo.url || photo.src || '');
            img.alt = String(photo.alt || '');
            img.loading = 'lazy';
            img.draggable = false;

            const hero = document.createElement('span');
            hero.className = 'admin-attached-photo-hero';
            hero.dataset.adminPhotoHero = '1';
            hero.textContent = '★ Hero';
            hero.hidden = true;

            const handle = document.createElement('button');
            handle.type = 'button';
            handle.className = 'admin-attached-photo-handle';
            handle.textContent = '⠿';
            handle.title = 'Hold and drag anywhere in the gallery to reorder';
            handle.setAttribute('aria-label', 'Move photo in gallery');
            handle.setAttribute('aria-keyshortcuts', 'ArrowLeft ArrowRight ArrowUp ArrowDown Home End');
            preview.append(img, hero, handle);

            const remove = document.createElement('label');
            remove.className = 'admin-attached-photo-remove';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = removeName;
            checkbox.value = String(id);
            checkbox.dataset.adminPhotoRemove = '1';
            remove.append(checkbox, document.createTextNode(' Remove this photo'));
            checkbox.addEventListener('change', changed);

            const caption = document.createElement('input');
            caption.className = 'admin-attached-photo-caption';
            caption.type = 'text';
            caption.maxLength = 300;
            caption.placeholder = 'Photo description';
            caption.dataset.adminPhotoCaption = '1';
            caption.setAttribute('aria-label', 'Photo description');
            caption.value = String(photo.alt || '');
            caption.addEventListener('input', () => {
                img.alt = caption.value;
                changed();
            });
            card.append(preview, remove, caption);
            return card;
        };
        const render = photos => {
            grid.replaceChildren();
            (Array.isArray(photos) ? photos : [])
                .filter(p => p && Number(p.id) > 0 && (p.url || p.src))
                .forEach(p => grid.append(createCard(p)));
            refreshHero();
        };
        const snapshot = originalPhotos => {
            const entries = items();
            const current = Array.isArray(originalPhotos) ? originalPhotos : [];
            const dirty = entries.length !== current.length
                || entries.some((item, index) =>
                    Number(current[index]?.id) !== item.id
                    || String(current[index]?.alt || '') !== item.alt
                    || item.removed
                );
            return {
                items: entries.filter(item => !item.removed).map(({id, alt}) => ({id, alt})),
                dirty,
            };
        };
        const move = (card, other, after) => {
            if (!card || !other || card === other || card.parentElement !== grid
                || other.parentElement !== grid) return;
            const previous = cards().indexOf(card);
            grid.insertBefore(card, after ? other.nextSibling : other);
            if (cards().indexOf(card) !== previous) changed();
        };

        let active = null;
        let scrollFrame = 0;
        const stopScroll = () => {
            if (scrollFrame && window.cancelAnimationFrame) {
                window.cancelAnimationFrame(scrollFrame);
            }
            scrollFrame = 0;
        };
        const autoScroll = () => {
            scrollFrame = 0;
            if (!active || !active.started || !window.requestAnimationFrame) return;
            const height = window.innerHeight || 700;
            const margin = 85;
            const y = active.y;
            const speed = y < margin ? -Math.min(19, (margin - y) / 3)
                : y > height - margin ? Math.min(19, (y - (height - margin)) / 3) : 0;
            if (speed !== 0 && typeof window.scrollBy === 'function') {
                window.scrollBy(0, speed);
                positionTarget(active.x, active.y);
            }
            scrollFrame = window.requestAnimationFrame(autoScroll);
        };
        const positionGhost = () => {
            if (!active?.ghost) return;
            active.ghost.style.left = (active.x - active.offsetX) + 'px';
            active.ghost.style.top = (active.y - active.offsetY) + 'px';
        };
        const positionTarget = (x, y) => {
            if (!active?.started) return;
            const hit = document.elementFromPoint?.(x, y);
            let other = hit?.closest?.('[data-admin-photo-id]');
            if (other && other !== active.card && other.parentElement === grid) {
                const rect = other.getBoundingClientRect();
                const after = (y > rect.top + rect.height / 2)
                    || (Math.abs(y - (rect.top + rect.height / 2)) < rect.height / 3
                        && x > rect.left + rect.width / 2);
                move(active.card, other, after);
                return;
            }
            // A gap in the CSS grid is still a valid drop point. Find the
            // closest tile, not just the immediately adjacent tile.
            if (hit === grid || hit?.closest?.('.admin-photo-gallery') === grid) {
                const neighbors = cards().filter(card => card !== active.card);
                if (!neighbors.length) return;
                let nearest = neighbors[0];
                let distance = Infinity;
                neighbors.forEach(card => {
                    const r = card.getBoundingClientRect();
                    const dx = x - (r.left + r.width / 2);
                    const dy = y - (r.top + r.height / 2);
                    const d = dx * dx + dy * dy;
                    if (d < distance) { distance = d; nearest = card; }
                });
                const r = nearest.getBoundingClientRect();
                move(active.card, nearest,
                    y > r.top + r.height / 2
                    || (Math.abs(y - (r.top + r.height / 2)) < r.height / 3
                        && x > r.left + r.width / 2));
            }
        };
        const beginDrag = () => {
            if (!active || active.started) return;
            active.started = true;
            const rect = active.card.getBoundingClientRect();
            const ghost = active.card.cloneNode(true);
            ghost.classList.add('admin-photo-drag-ghost');
            ghost.classList.remove('is-photo-removed');
            ghost.setAttribute('aria-hidden', 'true');
            ghost.querySelectorAll('[name], button').forEach(el => {
                el.removeAttribute('name');
                el.tabIndex = -1;
            });
            ghost.style.width = rect.width + 'px';
            ghost.style.height = rect.height + 'px';
            document.body.appendChild(ghost);
            active.ghost = ghost;
            active.card.classList.add('is-photo-drop-target');
            positionGhost();
            if (window.requestAnimationFrame) {
                scrollFrame = window.requestAnimationFrame(autoScroll);
            }
        };
        const pointerMove = event => {
            if (!active || event.pointerId !== active.id) return;
            active.x = event.clientX;
            active.y = event.clientY;
            if (!active.started
                && Math.hypot(active.x - active.startX, active.y - active.startY) < 8) return;
            if (!active.started) beginDrag();
            positionGhost();
            positionTarget(active.x, active.y);
            event.preventDefault();
        };
        const endDrag = event => {
            if (!active || event.pointerId !== active.id) return;
            const old = active;
            active = null;
            stopScroll();
            window.removeEventListener('pointermove', pointerMove);
            window.removeEventListener('pointerup', endDrag);
            window.removeEventListener('pointercancel', endDrag);
            old.ghost?.remove();
            old.card.classList.remove('is-photo-drop-target');
            if (event.type === 'pointercancel') {
                // A canceled touch should not silently reorder the gallery.
                old.initialOrder.forEach(card => grid.appendChild(card));
            }
            changed();
        };
        grid.addEventListener('pointerdown', event => {
            const handle = event.target?.closest?.('.admin-attached-photo-handle');
            if (!handle || (event.pointerType === 'mouse' && event.button !== 0)
                || active) return;
            const card = handle.closest('[data-admin-photo-id]');
            if (!card) return;
            const rect = card.getBoundingClientRect();
            active = {
                id: event.pointerId, card, initialOrder: cards(), started: false,
                x: event.clientX, y: event.clientY,
                startX: event.clientX, startY: event.clientY,
                offsetX: event.clientX - rect.left,
                offsetY: event.clientY - rect.top,
                ghost: null,
            };
            window.addEventListener('pointermove', pointerMove, {passive: false});
            window.addEventListener('pointerup', endDrag);
            window.addEventListener('pointercancel', endDrag);
            event.preventDefault();
        });
        grid.addEventListener('keydown', event => {
            const handle = event.target?.closest?.('.admin-attached-photo-handle');
            if (!handle) return;
            const card = handle.closest('[data-admin-photo-id]');
            const current = cards();
            const position = current.indexOf(card);
            if (position < 0) return;
            const up = event.key === 'ArrowLeft' || event.key === 'ArrowUp';
            const down = event.key === 'ArrowRight' || event.key === 'ArrowDown';
            if (!up && !down && event.key !== 'Home' && event.key !== 'End') return;
            event.preventDefault();
            if (event.key === 'Home') grid.prepend(card);
            else if (event.key === 'End') grid.append(card);
            else {
                const next = current[position + (up ? -1 : 1)];
                if (next) grid.insertBefore(card, up ? next : next.nextSibling);
            }
            changed();
            handle.focus();
        });

        return {render, cards, snapshot, refreshHero, items};
    };

    window.LlamaPhotoGallery = {mount};
})();
