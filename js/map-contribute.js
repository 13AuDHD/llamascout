(() => {
    'use strict';
    const root = document.querySelector('#map-contribute');
    if (!root || !window.L) return;
    const form = root.querySelector('#map-contribute-form');
    const status = root.querySelector('#map-contribute-status');
    const count = root.querySelector('#map-contribute-count');
    const type = form.elements.feature_type;
    const featureChoice = form.elements.feature_id;
    const areaChoice = form.elements.area_id;
    const siteChoice = form.elements.site_id;
    let existing = [];
    try { existing = JSON.parse(document.querySelector('#map-contribute-features')?.textContent || '[]'); } catch (_) { existing = []; }

    const map = L.map('map-contribute-map', { zoomControl: true }).setView([
        Number(root.dataset.lat), Number(root.dataset.lng)
    ], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    const refLayer = L.featureGroup().addTo(map);
    for (const feature of existing) {
        if (!feature.geometry) continue;
        L.geoJSON(feature.geometry, {
            style: { color: feature.feature_type === 'camping_site' ? '#e88b17'
                : feature.feature_type === 'parking_area' ? '#3275d5' : '#279e59',
                weight: 2, fillOpacity: 0.05 }, interactive: false
        }).addTo(refLayer);
    }

    let points = [];
    let sources = [];
    const outline = L.polygon([], { color: '#e88b17', weight: 3, fillOpacity: 0.15 }).addTo(map);
    const markers = L.layerGroup().addTo(map);
    function draw() {
        outline.setLatLngs(points);
        markers.clearLayers();
        points.forEach((p, i) => L.circleMarker(p, {
            radius: 5, color: '#fff', weight: 1, fillColor: '#e88b17', fillOpacity: 1
        }).addTo(markers).bindTooltip(String(i + 1)));
        count.textContent = `${points.length} perimeter points`;
    }
    function addPoint(lat, lng, source, accuracy) {
        if (!Number.isFinite(lat) || !Number.isFinite(lng) || points.length >= 450) return;
        points.push([lat,lng]);
        sources.push({ source, ...(Number.isFinite(accuracy) ? { accuracy_m: Math.max(0, accuracy) } : {}) });
        draw();
    }
    map.on('click', e => addPoint(e.latlng.lat, e.latlng.lng, 'manual'));
    root.querySelector('#map-contribute-undo').addEventListener('click', () => { points.pop(); sources.pop(); draw(); });
    root.querySelector('#map-contribute-clear').addEventListener('click', () => { points = []; sources = []; draw(); });
    root.querySelector('#map-contribute-gps').addEventListener('click', () => {
        if (!navigator.geolocation) { status.textContent = 'GPS is not available on this device.'; return; }
        status.textContent = 'Getting a GPS point…';
        navigator.geolocation.getCurrentPosition(pos => {
            addPoint(pos.coords.latitude, pos.coords.longitude, 'gps', pos.coords.accuracy);
            map.panTo([pos.coords.latitude, pos.coords.longitude]);
            status.textContent = 'GPS point added. Check its position before continuing.';
        }, () => { status.textContent = 'GPS unavailable. You can tap the map to add points.'; },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    });
    function syncType() {
        const site = type.value === 'camping_site';
        root.querySelector('[data-parent-area]').hidden = !site;
        root.querySelector('[data-existing-site]').hidden = !site;
        featureChoice.querySelectorAll('option[data-type]').forEach(o => {
            o.hidden = o.dataset.type !== type.value;
        });
        if (featureChoice.selectedOptions[0]?.hidden) featureChoice.value = '0';
    }
    type.addEventListener('change', syncType);
    syncType();
    featureChoice.addEventListener('change', () => {
        const id = Number(featureChoice.value);
        const item = existing.find(f => Number(f.id) === id);
        if (!item) { points = []; sources = []; draw(); return; }
        form.elements.label.value = item.label || form.elements.label.value;
        type.value = item.feature_type;
        syncType();
        const ring = item.geometry?.coordinates?.[0] || [];
        points = ring.slice(0, -1).map(p => [Number(p[1]), Number(p[0])]);
        sources = points.map(() => ({ source: 'manual' }));
        draw();
        if (points.length) map.fitBounds(outline.getBounds(), { padding: [30,30] });
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (points.length < 3) { status.textContent = 'Add at least three perimeter points.'; return; }
        if (type.value === 'camping_site' && !siteChoice.value) {
            status.textContent = 'Choose the existing Site this polygon represents. A containing Area is optional.';
            return;
        }
        const submit = form.querySelector('[type=submit]');
        submit.disabled = true;
        status.textContent = 'Submitting for review…';
        const ring = points.map(p => [p[1],p[0]]);
        ring.push([...ring[0]]);
        const sourceTypes = new Set(sources.map(s => s.source));
        try {
            const response = await fetch('/api/place-map-proposals.php', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    slug: root.dataset.slug, csrf_token: root.dataset.csrf,
                    feature_type: type.value, feature_id: Number(featureChoice.value) || 0,
                    label: String(form.elements.label.value).trim(),
                    geometry: { type: 'Polygon', coordinates: [ring] },
                    source_type: sourceTypes.size > 1 ? 'mixed' : sources[0]?.source || 'manual',
                    metadata: { vertices: sources },
                    site_details: type.value === 'camping_site'
                        ? { camping_area_feature_id: Number(areaChoice.value), proposed_campsite_id: Number(siteChoice.value) }
                        : {},
                })
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Submission failed.');
            status.textContent = result.message;
            points = []; sources = []; draw();
        } catch (error) {
            status.textContent = error.message || 'Submission failed.';
        } finally { submit.disabled = false; }
    });
})();
