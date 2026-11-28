/* Browser-local map preferences. No location requests or network storage. */
(() => {
    'use strict';
    const viewKey = 'llama-map-view-v1';
    const filtersKey = 'llama-map-resource-filters-v1';
    const groups = Object.freeze({
        water: ['potable_water', 'water_fill'],
        dump_station: ['dump_station'],
        shower: ['shower'],
        laundry: ['laundry'],
        propane: ['propane'],
        toilet: ['toilet'],
        trash: ['trash'],
        fuel: ['fuel'],
        groceries: ['groceries'],
        ev_charging: ['ev_charging'],
        firewood: ['firewood'],
        other: ['other', 'recycling', 'air', 'ice']
    });
    function read(storage, key) {
        try { return JSON.parse(storage.getItem(key)); } catch (_) { return null; }
    }
    function write(storage, key, value) {
        try { storage.setItem(key, JSON.stringify(value)); } catch (_) { /* storage unavailable */ }
    }
    function validView(value) {
        return !!value && Number.isFinite(value.lat) && Math.abs(value.lat) <= 85.051129
            && Number.isFinite(value.lng) && Math.abs(value.lng) <= 180
            && Number.isFinite(value.zoom) && value.zoom >= 0 && value.zoom <= 20;
    }
    function cleanFilters(value) {
        return Array.isArray(value)
            ? [...new Set(value.filter(key => typeof key === 'string' && Object.hasOwn(groups, key)))]
            : [];
    }
    function serviceTypes(filters) {
        return new Set(cleanFilters(filters).flatMap(key => groups[key]));
    }
    function matchingService(resource, filters) {
        const enabled = serviceTypes(filters);
        const services = Array.isArray(resource?.services)
            ? resource.services.map(service => service?.service_type).filter(Boolean) : [];
        // The marker's configured service wins if selected; otherwise use
        // one actually enabled service, never create a second marker.
        const primary = resource?.marker_service_type;
        if (enabled.has(primary) && services.includes(primary)) return primary;
        return services.find(type => enabled.has(type)) || null;
    }
    const api = Object.freeze({
        groups,
        readView(storage) {
            const view = read(storage, viewKey);
            return validView(view) ? view : null;
        },
        saveView(storage, value) {
            if (!value || !Number.isFinite(value.lng)) return;
            const normalized = {...value, lng: ((value.lng + 180) % 360 + 360) % 360 - 180};
            if (validView(normalized)) write(storage, viewKey, normalized);
        },
        readFilters(storage) { return cleanFilters(read(storage, filtersKey)); },
        saveFilters(storage, value) { write(storage, filtersKey, cleanFilters(value)); },
        matchingService,
        serviceTypes
    });
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else window.LlamaMapPreferences = api;
})();
