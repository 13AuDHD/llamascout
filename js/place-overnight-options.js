(() => {
    'use strict';
    const section = document.querySelector('[data-stay-options]');
    const mount = document.querySelector('[data-place-campsites-mount]');
    if (!section || !mount) return;
    const slot = section.querySelector('[data-stay-browser-slot]');
    const parkingList = section.querySelector('[data-stay-parking-list]');
    const detail = section.querySelector('[data-stay-selection]');
    const parkingButtons = [...parkingList.querySelectorAll('[data-stay-parking-id]')];

    // The existing campsite browser remains responsible for searching,
    // filtering, URL selection, and updating Scout Report site details.
    slot.appendChild(mount);

    let watching = false;
    const refresh = () => {
        const browser = mount.querySelector('[data-place-campsites]');
        if (!browser) {
            parkingList.hidden = parkingButtons.length === 0;
            return;
        }
        browser.classList.add('is-stay-browser');
        const list = browser.querySelector('[data-campsite-list]');
        if (!list) return;

        // Share one scrolling row with the campsite selector. The native
        // campsite browser keeps ownership of site selection and filtering.
        const filter = browser.querySelector('[data-campsite-filter].is-active');
        const query = String(browser.querySelector('[data-campsite-search]')?.value || '').trim().toLowerCase();
        const showParking = (!filter || filter.dataset.campsiteFilter === 'all');
        for (const button of parkingButtons) {
            if (button.parentElement !== list) list.appendChild(button);
            button.hidden = !showParking || (query !== '' && !button.textContent.toLowerCase().includes(query));
        }
        parkingList.hidden = true;
        if (!watching) {
            watching = true;
            new MutationObserver(refresh).observe(list, {childList: true});
            browser.addEventListener('input', () => requestAnimationFrame(refresh));
            browser.addEventListener('click', () => requestAnimationFrame(refresh));
        }
    };
    new MutationObserver(refresh).observe(mount, {childList: true});
    refresh();

    parkingButtons.forEach(button => {
        button.addEventListener('click', () => {
            parkingButtons.forEach(other => {
                const selected = other === button;
                other.classList.toggle('is-selected', selected);
                other.setAttribute('aria-current', selected ? 'true' : 'false');
            });
            detail.replaceChildren();
            const heading = document.createElement('strong');
            heading.textContent = button.dataset.stayParkingName || 'Parking area';
            const paragraph = document.createElement('p');
            paragraph.textContent = `${button.dataset.stayParkingCost || 'Cost unknown'} · ${button.dataset.stayParkingStatus || 'Overnight status unknown'}`;
            const link = document.createElement('a');
            link.href = '#place-map-heading';
            link.textContent = 'View area on map';
            detail.append(heading, paragraph, link);
            detail.hidden = false;
        });
    });
    mount.addEventListener('click', (event) => {
        if (event.target.closest('[data-campsite-id]')) {
            parkingButtons.forEach(button => {
                button.classList.remove('is-selected');
                button.setAttribute('aria-current', 'false');
            });
            detail.hidden = true;
        }
    });
    refresh();
})();
