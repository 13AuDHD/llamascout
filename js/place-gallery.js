(() => {
    'use strict';

    const gallery = document.querySelector('[data-place-gallery]');
    const dialog = document.getElementById('place-gallery-lightbox');

    if (!gallery || !dialog) return;

    const openButtons = Array.from(gallery.querySelectorAll('[data-place-gallery-open]'));
    const jumpButtons = Array.from(dialog.querySelectorAll('[data-place-gallery-jump]'));
    const image = document.getElementById('place-gallery-large-image');
    const caption = document.getElementById('place-gallery-caption');
    const counter = document.getElementById('place-gallery-counter');
    const previous = document.getElementById('place-gallery-previous');
    const next = document.getElementById('place-gallery-next');
    const close = document.getElementById('place-gallery-close');
    const thumbRail = document.getElementById('place-gallery-lightbox-thumbs');

    const photos = openButtons.map((button) => {
        const img = button.querySelector('img');
        return {
            src: img?.currentSrc || img?.src || '',
            alt: img?.alt || ''
        };
    });

    let currentIndex = 0;
    let returnFocus = null;
    let touchStartX = null;

    const normalizeIndex = (index) => {
        if (!photos.length) return 0;
        if (index < 0) return photos.length - 1;
        if (index >= photos.length) return 0;
        return index;
    };

    const render = (requestedIndex) => {
        currentIndex = normalizeIndex(requestedIndex);
        const photo = photos[currentIndex];
        if (!photo || !image) return;

        image.src = photo.src;
        image.alt = photo.alt;

        if (caption) caption.textContent = photo.alt;
        if (counter) counter.textContent = `${currentIndex + 1} of ${photos.length}`;

        jumpButtons.forEach((button, index) => {
            const active = index === currentIndex;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-current', active ? 'true' : 'false');
        });

        jumpButtons[currentIndex]?.scrollIntoView({
            behavior:
                document.documentElement.dataset.reducedMotion === 'true'
                    ? 'auto'
                    : 'smooth',
            block: 'nearest',
            inline: 'center'
        });
    };

    const openDialog = (index, trigger) => {
        returnFocus = trigger || null;
        render(index);

        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');

        document.body.classList.add('place-gallery-is-open');
        close?.focus();
    };

    const closeDialog = () => {
        if (typeof dialog.close === 'function') dialog.close();
        else dialog.removeAttribute('open');

        document.body.classList.remove('place-gallery-is-open');
        returnFocus?.focus();
        returnFocus = null;
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            openDialog(Number(button.dataset.placeGalleryOpen || 0), button);
        });
    });

    jumpButtons.forEach((button) => {
        button.addEventListener('click', () => {
            render(Number(button.dataset.placeGalleryJump || 0));
        });
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
        }
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            render(currentIndex + 1);
        }
    });

    dialog.addEventListener('touchstart', (event) => {
        touchStartX = event.touches?.[0]?.clientX ?? null;
    }, { passive: true });

    dialog.addEventListener('touchend', (event) => {
        if (touchStartX === null) return;
        const endX = event.changedTouches?.[0]?.clientX ?? touchStartX;
        const distance = endX - touchStartX;
        touchStartX = null;
        if (Math.abs(distance) < 50) return;
        render(distance > 0 ? currentIndex - 1 : currentIndex + 1);
    }, { passive: true });

    if (photos.length <= 1) {
        previous?.setAttribute('hidden', '');
        next?.setAttribute('hidden', '');
        thumbRail?.setAttribute('hidden', '');
    }
})();
