(() => {
    'use strict';

    // A single viewer serves the server-rendered Place photo strip and the
    // on-demand Site strip. No gallery nodes are moved after page load.
    const dialog = document.getElementById('place-gallery-lightbox');
    if (!dialog) return;

    const image = document.getElementById('place-gallery-large-image');
    const caption = document.getElementById('place-gallery-caption');
    const counter = document.getElementById('place-gallery-counter');
    const previous = document.getElementById('place-gallery-previous');
    const next = document.getElementById('place-gallery-next');
    const close = document.getElementById('place-gallery-close');
    const thumbRail = document.getElementById('place-gallery-lightbox-thumbs');

    let photos = [];
    let currentIndex = 0;
    let returnFocus = null;
    let touchStartX = null;

    const normalizeIndex = (index) => {
        if (!photos.length) return 0;
        return (index + photos.length) % photos.length;
    };

    const render = (requestedIndex) => {
        if (!photos.length) return;
        currentIndex = normalizeIndex(requestedIndex);
        const photo = photos[currentIndex];
        image.src = photo.src;
        image.alt = photo.alt;
        if (caption) {
            caption.textContent = [photo.caption || photo.alt, photo.credit ? `Credit: ${photo.credit}` : '']
                .filter(Boolean).join(' · ');
        }
        if (counter) counter.textContent = `${currentIndex + 1} of ${photos.length}`;
        thumbRail?.querySelectorAll('[data-gallery-jump]').forEach((button, index) => {
            button.classList.toggle('is-active', index === currentIndex);
            button.setAttribute('aria-current', index === currentIndex ? 'true' : 'false');
        });
        thumbRail?.querySelector(`[data-gallery-jump="${currentIndex}"]`)?.scrollIntoView({
            behavior: 'auto', block: 'nearest', inline: 'nearest',
        });
    };

    const open = (trigger) => {
        const gallery = trigger.closest('[data-place-gallery], .place-campsite-gallery');
        if (!gallery) return;
        const buttons = Array.from(gallery.querySelectorAll(
            '[data-gallery-open], .place-campsite-gallery-item a'
        ));
        photos = buttons.map((item) => {
            const thumb = item.querySelector('img');
            const figure = item.closest('.place-campsite-gallery-item');
            return {
                src: thumb?.getAttribute('src') || '',
                alt: thumb?.alt || '',
                caption: item.dataset.photoCaption
                    || figure?.querySelector('figcaption span')?.textContent?.trim() || '',
                credit: item.dataset.photoCredit
                    || figure?.querySelector('figcaption small')?.textContent?.trim()
                        ?.replace(/^Credit:\s*/i, '') || '',
            };
        });
        if (!photos.length) return;
        returnFocus = trigger;
        thumbRail.replaceChildren();
        photos.forEach((photo, index) => {
            const jump = document.createElement('button');
            jump.type = 'button';
            jump.dataset.galleryJump = String(index);
            jump.setAttribute('aria-label', `View photo ${index + 1}`);
            const thumb = document.createElement('img');
            thumb.src = photo.src;
            thumb.alt = '';
            thumb.loading = 'lazy';
            jump.appendChild(thumb);
            thumbRail.appendChild(jump);
        });
        const single = photos.length <= 1;
        previous.hidden = single;
        next.hidden = single;
        thumbRail.hidden = single;
        const index = buttons.indexOf(trigger);
        render(Math.max(0, index));
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
        document.body.classList.add('place-gallery-is-open');
        close?.focus();
    };

    const closeDialog = () => {
        if (typeof dialog.close === 'function') dialog.close();
        else dialog.removeAttribute('open');
        document.body.classList.remove('place-gallery-is-open');
        image.removeAttribute('src');
        thumbRail?.replaceChildren();
        if (returnFocus?.isConnected) returnFocus.focus({ preventScroll: true });
        returnFocus = null;
    };

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const openButton = target.closest(
            '[data-place-gallery] [data-gallery-open], .place-campsite-gallery-item a'
        );
        if (openButton) {
            event.preventDefault();
            open(openButton);
        }
    });
    thumbRail?.addEventListener('click', (event) => {
        const jump = event.target.closest('[data-gallery-jump]');
        if (jump) render(Number(jump.dataset.galleryJump));
    });
    previous?.addEventListener('click', () => render(currentIndex - 1));
    next?.addEventListener('click', () => render(currentIndex + 1));
    close?.addEventListener('click', closeDialog);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) closeDialog();
    });
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeDialog();
    });
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            render(currentIndex - 1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            render(currentIndex + 1);
        }
    });
    dialog.addEventListener('touchstart', (event) => {
        touchStartX = event.touches?.[0]?.clientX ?? null;
    }, { passive: true });
    dialog.addEventListener('touchend', (event) => {
        if (touchStartX === null) return;
        const end = event.changedTouches?.[0]?.clientX ?? touchStartX;
        touchStartX = null;
        if (Math.abs(end) >= 50) render(end > 0 ? currentIndex - 1 : currentIndex + 1);
    }, { passive: true });
})();
