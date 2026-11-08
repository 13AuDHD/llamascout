(() => {
    for (const form of document.querySelectorAll('[data-resource-editor]')) {
        const mode = form.querySelector('[data-resource-hours-mode]');
        const panels = form.querySelectorAll('[data-resource-hours-panel]');
        const sync = () => {
            for (const panel of panels) {
                const active = panel.dataset.resourceHoursPanel === mode.value;
                panel.hidden = !active;
                for (const field of panel.querySelectorAll('select, input')) {
                    field.disabled = !active;
                    field.required = active;
                }
            }
        };
        mode?.addEventListener('change', sync);
        if (mode) sync();

        const locate = form.querySelector('[data-resource-locate]');
        const status = form.querySelector('[data-resource-location-status]');
        if (!locate || !status || !navigator.geolocation) continue;

        locate.hidden = false;
        locate.addEventListener('click', () => {
            locate.disabled = true;
            status.textContent = 'Finding your location…';
            navigator.geolocation.getCurrentPosition(({ coords }) => {
                form.elements.coordinates.value = `${coords.latitude.toFixed(7)}, ${coords.longitude.toFixed(7)}`;
                status.textContent = `Estimated GPS accuracy ±${Math.round(coords.accuracy)} m`;
                locate.disabled = false;
            }, () => {
                status.textContent = 'Location unavailable. Try again or enter coordinates.';
                locate.disabled = false;
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        });
    }
})();
