(() => {
    'use strict';
    const render = (panel, payload) => {
        const status = panel.querySelector('[data-nearest-status]');
        const results = panel.querySelector('[data-nearest-results]');
        if (!status || !results) return;
        results.replaceChildren();
        const state = payload?.state;
        if (state !== 'ready') {
            status.textContent = state === 'loading' ? 'Finding nearby mapped services…'
                : state === 'unavailable' ? 'Nearby services are temporarily unavailable.'
                : 'Enter Site coordinates to automatically find nearby mapped services.';
            return;
        }
        status.textContent = 'Straight-line distances, not driving distances. Closest known mapped services within '
            + payload.radius_miles + ' miles. Verify access, hours and availability.';
        const dl = document.createElement('dl');
        dl.className = 'nearest-services-list';
        for (const service of payload.services || []) {
            const term = document.createElement('dt');
            term.textContent = service.label;
            const value = document.createElement('dd');
            const match = service.match;
            if (!match) {
                value.textContent = service.supported ? 'No mapped match within ' + payload.radius_miles + ' mi'
                    : 'No data source yet';
            } else {
                const miles = Number(match.distance_miles);
                const distance = miles < 0.1 ? 'Under 0.1 mi' : miles.toFixed(1) + ' mi';
                value.textContent = match.name + ' · ' + distance
                    + (match.source === 'OpenStreetMap' ? ' · OpenStreetMap' : '')
                    + (match.facility ? ' · Parent Place facility' : '')
                    + (match.status === 'seasonal' ? ' · Seasonal' : match.status === 'unknown' ? ' · Availability unknown' : '');
            }
            dl.append(term, value);
        }
        results.append(dl);
        if (payload.osm_data_loaded) {
            const credit = document.createElement('small');
            const license = document.createElement('a');
            license.href = 'https://www.openstreetmap.org/copyright';
            license.textContent = '© OpenStreetMap contributors (ODbL)';
            const download = document.createElement('a');
            download.href = '/api/osm-nearest-data.php';
            download.textContent = 'Download reference data';
            credit.append(license, ' · ', download);
            results.append(credit);
        }
    };
    window.LlamaScoutNearestServices = {render};

    const init = () => {
        const panel = document.querySelector('[data-site-nearest-preview]');
        if (!panel) return;
        const form = panel.closest('form');
        const input = form?.querySelector('[data-site-coordinate-input]');
        const parent = form?.querySelector('[name="site_place_id"]');
        if (!input || !parent) return;
        let timer, controller, lastKey = '', sequence = 0;
        const schedule = () => {
            const raw = input.value.trim().split(',').map(value => value.trim());
            const valid = raw.length === 2 && raw.every(value => /^[+-]?(?:\d+\.?\d*|\.\d+)$/.test(value));
            const [lat, lng] = raw.map(Number);
            const coordinates = valid && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 && (lat !== 0 || lng !== 0);
            const key = coordinates ? JSON.stringify([lat, lng, parent.value]) : '';
            if (key === lastKey) return;
            lastKey = key;
            const token = ++sequence;
            clearTimeout(timer);
            controller?.abort();
            if (!coordinates) { render(panel, {state:'no_coordinates'}); return; }
            render(panel, {state:'loading'});
            timer = setTimeout(async () => {
                controller = new AbortController();
                const url = new URL('/add-site.php', window.location.origin);
                url.searchParams.set('nearest_services', '1');
                url.searchParams.set('lat', lat);
                url.searchParams.set('lng', lng);
                if (parent.value) url.searchParams.set('site_place_id', parent.value);
                try {
                    const response = await fetch(url, {credentials:'same-origin', cache:'no-store', signal:controller.signal});
                    if (!response.ok) throw new Error('Nearest services unavailable');
                    const payload = await response.json();
                    if (token === sequence) render(panel, payload);
                } catch (error) {
                    if (token === sequence && error.name !== 'AbortError') {
                        lastKey = '';
                        render(panel, {state:'unavailable'});
                    }
                }
            }, 350);
        };
        // Locate me and Search already dispatch input/change on the original
        // coordinate control. Parent context changes use the same form event.
        input.addEventListener('input', schedule);
        form.addEventListener('change', schedule);
        form.querySelector('[data-site-coordinate-search]')?.addEventListener('click', schedule);
        schedule();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once:true});
    else init();
})();
