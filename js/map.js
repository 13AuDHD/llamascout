(() => {
    'use strict';

    const mapElement = document.getElementById('llama-map');

    if (!mapElement || typeof L === 'undefined') {
        return;
    }

    const mapCard = mapElement.closest('.map-card');
    const initialMemberAccess = mapCard?.dataset.mapMember === '1';

    const controls = {
        search: document.getElementById('map-search'),
        toggle: document.getElementById('map-filter-toggle'),
        panel: document.getElementById('map-filter-panel'),
        count: document.getElementById('map-filter-count'),
        clear: document.getElementById('map-clear'),
        fit: document.getElementById('map-fit-results'),
        status: document.getElementById('map-status'),
        precision: document.getElementById('map-location-precision'),
        results: document.getElementById('place-results'),
        resourceResults: document.getElementById('resource-results'),
        resourceSection: document.getElementById('resource-results-section'),
        empty: document.getElementById('map-empty'),
        layerControl: document.getElementById('map-layer-control'),

        state: document.getElementById('filter-state'),
        county: document.getElementById('filter-county'),
        city: document.getElementById('filter-city'),
        type: document.getElementById('filter-type'),
        landManager: document.getElementById('filter-land-manager'),
        landType: document.getElementById('filter-land-type'),
        elevationMax: document.getElementById('filter-elevation-max'),
        amenity: document.getElementById('filter-amenity')
    };

    // Closure filters are opt-in. Closed locations remain visible by default.
    const openOnlyFilter = document.createElement('label');
    openOnlyFilter.className = 'map-open-only-filter';
    openOnlyFilter.innerHTML = `
        <span>Show Open Places Only</span>
        <input type="checkbox" role="switch" aria-label="Show Open Places Only">
        <span class="map-open-only-track" aria-hidden="true"></span>
    `;
    const openOnlySwitch = openOnlyFilter.querySelector('input');
    controls.panel?.appendChild(openOnlyFilter);

    const isClosedPlace = (place) =>
        place?.is_closed === true || place?.is_closed === 1 ||
        place?.is_closed === '1';

    const amenityLabels = {
        toilets: 'Toilets',
        potable_water: 'Potable water',
        trash: 'Trash',
        fire_ring: 'Fire ring',
        picnic_table: 'Picnic table',
        bear_box: 'Bear box',
        showers: 'Showers',
        electricity: 'Electricity',
        dump_station: 'Dump station'
    };

    let places = [];
    let filteredPlaces = [];
    let visiblePlaces = [];
    let resources = [];
    let filteredResources = [];
    let visibleResources = [];
    let markers = [];
    let markerByPlace = new Map();
    let markerByResource = new Map();
    let memberMapAccess = initialMemberAccess;
    let selectedLayer = 'auto';
    let activeTileLayer = null;
    let searchRenderTimer = null;
    let userLocationMarker = null;

    const MAP_MAX_ZOOM = 20;

    const map = L.map(mapElement, {
        maxZoom: MAP_MAX_ZOOM,
        zoomControl: false
    }).setView([37.3, -107.4], 7);

    window.LlamaScoutMap = Object.freeze({
        map,
        mapElement
    });

    /*
     * Keep the map controls together in the lower-right corner.
     * The locate control is added after zoom so Leaflet stacks it above
     * the zoom buttons in a bottom-positioned control corner.
     */
    L.control.zoom({
        position: 'bottomright'
    }).addTo(map);

    const locateControl = L.control({
        position: 'bottomright'
    });

    locateControl.onAdd = () => {
        const container = L.DomUtil.create(
            'div',
            'map-locate-control'
        );

        const button = L.DomUtil.create(
            'button',
            'map-locate-button',
            container
        );

        button.type = 'button';
        button.title = 'Show my location';
        button.setAttribute('aria-label', 'Show my location');
        button.innerHTML =
            '<span class="map-locate-icon" aria-hidden="true"></span>';

        const status = L.DomUtil.create(
            'span',
            'visually-hidden',
            container
        );

        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');

        L.DomEvent.disableClickPropagation(container);
        L.DomEvent.disableScrollPropagation(container);

        L.DomEvent.on(button, 'click', () => {
            if (!navigator.geolocation) {
                status.textContent =
                    'Location is not available in this browser.';
                button.classList.add('is-error');
                return;
            }

            button.classList.remove('is-error');
            button.classList.add('is-locating');
            button.setAttribute('aria-busy', 'true');
            status.textContent = 'Finding your location.';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const latitude =
                        Number(position.coords.latitude);
                    const longitude =
                        Number(position.coords.longitude);

                    button.classList.remove('is-locating');
                    button.removeAttribute('aria-busy');

                    if (
                        !Number.isFinite(latitude) ||
                        !Number.isFinite(longitude)
                    ) {
                        button.classList.add('is-error');
                        status.textContent =
                            'Your location could not be determined.';
                        return;
                    }

                    const coordinates = [latitude, longitude];

                    if (userLocationMarker) {
                        userLocationMarker.setLatLng(coordinates);
                    } else {
                        userLocationMarker = L.marker(
                            coordinates,
                            {
                                icon: L.divIcon({
                                    className: 'map-user-location-shell',
                                    html: '<span class="map-user-location-dot"></span>',
                                    iconSize: [24, 24],
                                    iconAnchor: [12, 12]
                                }),
                                interactive: false,
                                keyboard: false
                            }
                        ).addTo(map);
                    }

                    const targetZoom = Math.min(
                        map.getMaxZoom(),
                        15
                    );

                    const reduceMotion =
                        document.documentElement.dataset.reducedMotion === 'true' ||
                        window.matchMedia?.(
                            '(prefers-reduced-motion: reduce)'
                        ).matches === true;

                    map.setView(
                        coordinates,
                        targetZoom,
                        {
                            animate: !reduceMotion
                        }
                    );

                    status.textContent = 'Your location is shown on the map.';
                },
                (error) => {
                    button.classList.remove('is-locating');
                    button.classList.add('is-error');
                    button.removeAttribute('aria-busy');

                    status.textContent =
                        error?.code === 1
                            ? 'Location permission was not granted.'
                            : 'Your location could not be determined.';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 60000
                }
            );
        });

        return container;
    };

    locateControl.addTo(map);


    const tileSources = {
        public: {
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            options: {
                maxNativeZoom: 19,
                maxZoom: MAP_MAX_ZOOM,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }
        },

        light: {
            url: '',
            options: {
                maxNativeZoom: 20,
                maxZoom: MAP_MAX_ZOOM,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://www.geoapify.com/">Geoapify</a>'
            }
        },

        street: {
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            options: {
                maxNativeZoom: 19,
                maxZoom: MAP_MAX_ZOOM,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }
        },

        terrain: {
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
            options: {
                maxNativeZoom: 19,
                maxZoom: MAP_MAX_ZOOM,
                attribution:
                    'Tiles &copy; Esri and contributors'
            }
        },

        topo: {
            url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
            options: {
                maxNativeZoom: 17,
                maxZoom: MAP_MAX_ZOOM,
                attribution:
                    'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, SRTM | Map style &copy; <a href="https://opentopomap.org">OpenTopoMap</a>'
            }
        },

        dark: {
            url: '',
            options: {
                maxNativeZoom: 20,
                maxZoom: MAP_MAX_ZOOM,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://www.geoapify.com/">Geoapify</a>'
            }
        },

        satellite: {
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            options: {
                maxNativeZoom: 19,
                maxZoom: MAP_MAX_ZOOM,
                attribution:
                    'Tiles &copy; Esri and imagery contributors'
            }
        }
    };


    const getStoredTheme = () => {
        try {
            return localStorage.getItem('llama-theme') || 'system';
        } catch (error) {
            return 'system';
        }
    };


    const systemPrefersDark = () =>
        window.matchMedia?.('(prefers-color-scheme: dark)').matches === true;


    const resolvedTheme = () => {
        const stored = getStoredTheme();

        if (stored === 'dark') {
            return 'dark';
        }

        if (stored === 'light') {
            return 'light';
        }

        const documentTheme = document.documentElement.dataset.theme;

        if (documentTheme === 'dark' || documentTheme === 'light') {
            return documentTheme;
        }

        return systemPrefersDark() ? 'dark' : 'light';
    };


    const layerKeyForSelection = (selection) => {
        if (!memberMapAccess) {
            return 'public';
        }

        if (selection === 'auto') {
            const automatic =
                resolvedTheme() === 'dark' ? 'dark' : 'light';

            return tileSources[automatic]?.url
                ? automatic
                : 'street';
        }

        if (
            (selection === 'light' || selection === 'dark') &&
            !tileSources[selection]?.url
        ) {
            return 'street';
        }

        return tileSources[selection] ? selection : 'street';
    };


    const setActiveLayerButton = () => {
        controls.layerControl
            ?.querySelectorAll('[data-map-layer]')
            .forEach((button) => {
                const active = button.dataset.mapLayer === selectedLayer;

                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
    };

    const applyTileLayer = () => {
        const key = layerKeyForSelection(selectedLayer);
        const source = tileSources[key];

        if (!source) {
            return;
        }

        if (activeTileLayer) {
            map.removeLayer(activeTileLayer);
        }

        activeTileLayer = L.tileLayer(source.url, source.options);
        activeTileLayer.addTo(map);
        setActiveLayerButton();
    };


    let contributorPlaceAccess = false;

    const syncMapAccess = (hasAccess, hasContributorAccess = false) => {
        memberMapAccess = hasAccess === true;
        contributorPlaceAccess = hasContributorAccess === true;

        map.setMaxZoom(MAP_MAX_ZOOM);

        if (controls.precision) {
            controls.precision.textContent = 'Stored Place locations';
        }

        if (controls.layerControl) {
            controls.layerControl.hidden = !memberMapAccess;
        }

        if (!memberMapAccess) {
            selectedLayer = 'auto';
        }

        applyTileLayer();
    };


    applyTileLayer();


    controls.layerControl
        ?.querySelectorAll('[data-map-layer]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                if (!memberMapAccess) {
                    return;
                }

                selectedLayer = button.dataset.mapLayer || 'auto';
                applyTileLayer();
            });
        });


    const themeObserver = new MutationObserver(() => {
        if (memberMapAccess && selectedLayer === 'auto') {
            applyTileLayer();
        }
    });

    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    const colorSchemeQuery =
        window.matchMedia?.('(prefers-color-scheme: dark)');

    const handleSystemThemeChange = () => {
        if (
            memberMapAccess &&
            selectedLayer === 'auto' &&
            getStoredTheme() === 'system'
        ) {
            applyTileLayer();
        }
    };

    if (colorSchemeQuery?.addEventListener) {
        colorSchemeQuery.addEventListener('change', handleSystemThemeChange);
    } else if (colorSchemeQuery?.addListener) {
        colorSchemeQuery.addListener(handleSystemThemeChange);
    }


    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    };


    const normalize = (value) =>
        String(value ?? '').trim().toLowerCase();


    const formatLabel = (value) =>
        String(value ?? '')
            .replaceAll('_', ' ')
            .replaceAll('-', ' ')
            .replace(/\b\w/g, (letter) => letter.toUpperCase());


    const imageUrl = (value) => {
        const src = String(value ?? '').trim();

        if (!src) {
            return '';
        }

        if (/^https?:\/\//i.test(src)) {
            return src;
        }

        return '/' + src.replace(/^\/+/, '');
    };


    const placeUrl = (place) =>
        '/place.php?slug=' +
        encodeURIComponent(String(place.slug || ''));


    const locationLabel = (place) =>
        [place.city, place.state].filter(Boolean).join(', ');


    const resourceLocationLabel = (resource) =>
        [
            resource.city,
            resource.state_region
        ].filter(Boolean).join(', ');


    const resourceCoordinates = (resource) => {
        const latitude = Number(resource?.latitude);
        const longitude = Number(resource?.longitude);

        if (
            !Number.isFinite(latitude)
            || !Number.isFinite(longitude)
            || latitude < -90
            || latitude > 90
            || longitude < -180
            || longitude > 180
            || (latitude === 0 && longitude === 0)
        ) {
            return null;
        }

        return [latitude, longitude];
    };


    const resourceTypeClass = (resource) => {
        const type =
            String(
                resource?.resource_type
                || 'other'
            )
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9_-]+/g, '-');

        return 'is-' + (type || 'other');
    };


    const resourceServices = (resource) =>
        Array.isArray(resource?.services)
            ? resource.services.filter(
                (service) =>
                    service
                    && typeof service === 'object'
                    && String(
                        service.service_type_label
                        || service.service_type
                        || ''
                    ).trim() !== ''
            )
            : [];


    const resourceMatches = (resource) => {
        const search =
            String(
                controls.search?.value
                || ''
            )
                .trim()
                .toLowerCase();

        if (search === '') {
            return true;
        }

        const searchable = [
            resource.name,
            resource.resource_type,
            resource.resource_type_label,
            resource.street_address,
            resource.city,
            resource.state_region,
            resource.postal_code,
            ...resourceServices(resource).flatMap(
                (service) => [
                    service.service_type,
                    service.service_type_label
                ]
            )
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return searchable.includes(search);
    };


    const isFeaturedPlace = (place) =>
        String(place?.status || '').toLowerCase() === 'featured' ||
        place?.is_featured === true ||
        place?.is_featured === 1 ||
        place?.is_featured === '1';


    const campingFeeStatus = (place) => {
        const status = String(place?.camping_fee_status ?? 'unknown')
            .trim()
            .toLowerCase();

        return status === 'paid' || status === 'mixed'
            ? status
            : 'unknown';
    };


    const activePartner = (place) => {
        const partner = place?.map_partner;
        return partner && Number(partner.id) > 0 ? partner : null;
    };

    const isAffiliatePlace = (place) =>
        activePartner(place)?.relationship === 'affiliate';

    // Partner colors are installed in a stylesheet, not in element style attributes.
    const partnerStyles = document.createElement('style');
    partnerStyles.id = 'llama-map-partner-colors';
    document.head.appendChild(partnerStyles);
    const styledPartners = new Set();
    const safeColor = (value) => /^#[0-9A-Fa-f]{6}$/.test(String(value || ''))
        ? String(value) : null;

    const registerPartnerStyles = (partner) => {
        if (!partner || styledPartners.has(Number(partner.id))) return;
        const id = Number(partner.id);
        if (!Number.isSafeInteger(id) || id < 1) return;
        styledPartners.add(id);
        const primary = safeColor(partner.primary_color) || '#FBD318';
        const accent = safeColor(partner.accent_color) || '#CC2027';
        const sheet = partnerStyles.sheet;
        if (!sheet) return;
        sheet.insertRule(`.map-place-card.map-partner-${id} { --map-partner-primary: ${primary}; --map-partner-accent: ${accent}; }`, sheet.cssRules.length);
    };

    const brandedMarkerUrl = (partner) => {
        if (!partner?.show_map_markers) return '';
        const icon = String(partner.marker_icon || '');
        return /^[a-z0-9][a-z0-9-]{0,79}$/.test(icon)
            ? `/assets/icons/${icon}.svg` : '';
    };

    const mapMarkerClass = (place) => {
        const feeStatus = campingFeeStatus(place);

        if (isClosedPlace(place)) {
            return ' is-closed';
        }

        if (isFeaturedPlace(place)) {
            return ' is-featured';
        }

        if (feeStatus === 'paid') {
            return ' is-paid';
        }

        if (feeStatus === 'mixed') {
            return ' is-mixed';
        }

        return '';
    };


    const markerIcon = (place) => {
        const partner = activePartner(place);
        const iconUrl = brandedMarkerUrl(partner);
        if (iconUrl && !isClosedPlace(place)) {
            return L.divIcon({
                className: 'map-place-marker-shell map-partner-marker-shell',
                html: `<img class="map-partner-marker-image" src="${escapeHtml(iconUrl)}" alt="" aria-hidden="true">`,
                iconSize: [36, 46],
                iconAnchor: [18, 44],
                popupAnchor: [0, -42]
            });
        }
        return L.divIcon({
            className: 'map-place-marker-shell',
            html: `
                <span
                    class="map-place-marker${
                        mapMarkerClass(place)
                    }"
                    aria-hidden="true"
                ></span>
            `,
iconSize: [36, 46],
iconAnchor: [18, 44],
popupAnchor: [0, -42]
        });
    };


    // Null/blank coordinates must not become 0,0 through Number().
    // The server returns stored coordinates only for visible Places.
    const placeCoordinates = (place) => {
        const pair = (latitude, longitude) => {
            if (latitude === null || latitude === undefined ||
                longitude === null || longitude === undefined ||
                String(latitude).trim() === '' ||
                String(longitude).trim() === '') {
                return null;
            }

            const lat = Number(latitude);
            const lng = Number(longitude);

            if (!Number.isFinite(lat) || !Number.isFinite(lng) ||
                lat < -90 || lat > 90 || lng < -180 || lng > 180 ||
                (lat === 0 && lng === 0)) {
                return null;
            }

            return [lat, lng];
        };

        return pair(place.latitude, place.longitude);
    };

    const activeFilterCount = () => {
        let count = 0;

        [
            controls.state,
            controls.county,
            controls.city,
            controls.type,
            controls.landManager,
            controls.landType,
            controls.elevationMax,
            controls.amenity
        ].forEach((element) => {
            if (element?.value) {
                count++;
            }
        });

        if (controls.search?.value.trim()) {
            count++;
        }

        return count;
    };


    const updateFilterCount = () => {
        const count = activeFilterCount();

        if (controls.count) {
            controls.count.textContent = String(count);
            controls.count.hidden = count === 0;
        }

        if (controls.clear) {
            controls.clear.hidden = count === 0;
        }
    };


    const uniqueSorted = (field) =>
        [...new Set(
            places
                .map((place) => String(place[field] || '').trim())
                .filter(Boolean)
        )].sort((a, b) => a.localeCompare(b));


    const populateSelect = (element, values) => {
        if (!element) {
            return;
        }

        const current = element.value;

        while (element.options.length > 1) {
            element.remove(1);
        }

        values.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = formatLabel(value);
            element.appendChild(option);
        });

        if ([...element.options].some((option) => option.value === current)) {
            element.value = current;
        }
    };


    const populateFilters = () => {
        populateSelect(controls.state, uniqueSorted('state'));
        populateSelect(controls.county, uniqueSorted('county'));
        populateSelect(controls.city, uniqueSorted('city'));
        populateSelect(controls.type, uniqueSorted('type'));
        populateSelect(controls.landManager, uniqueSorted('land_manager'));
        populateSelect(controls.landType, uniqueSorted('land_type'));
    };


    const matches = (place) => {
        if (openOnlySwitch?.checked && isClosedPlace(place)) {
            return false;
        }
        const search = normalize(controls.search?.value);
        const selectedAmenity = controls.amenity?.value || '';
        const maxElevation = Number(controls.elevationMax?.value || 0);

        const exactFilters = [
            ['state', controls.state?.value],
            ['county', controls.county?.value],
            ['city', controls.city?.value],
            ['type', controls.type?.value],
            ['land_manager', controls.landManager?.value],
            ['land_type', controls.landType?.value]
        ];

        for (const [field, selected] of exactFilters) {
            if (selected && String(place[field] || '') !== selected) {
                return false;
            }
        }

        if (
            maxElevation > 0 &&
            Number(place.elevation_feet || 0) > maxElevation
        ) {
            return false;
        }

        if (
            selectedAmenity &&
            Number(place.amenities?.[selectedAmenity] || 0) !== 1
        ) {
            return false;
        }

        if (!search) {
            return true;
        }

        const searchable = [
            place.name,
            place.type,
            place.city,
            place.county,
            place.state,
            place.region,
            place.land_manager,
            place.land_type
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return searchable.includes(search);
    };


    const clearMarkers = () => {
        markers.forEach((marker) => {
            map.removeLayer(marker);
        });

        markers = [];
        markerByPlace.clear();
        markerByResource.clear();
    };


    const fitVisiblePlaces = () => {
        const bounds = [
            ...filteredPlaces
                .map(placeCoordinates)
                .filter(Boolean),
            ...filteredResources
                .map(resourceCoordinates)
                .filter(Boolean)
        ];

        const fitMaxZoom = 16;

        if (bounds.length > 1) {
            map.fitBounds(bounds, {
                padding: [42, 42],
                maxZoom: fitMaxZoom
            });
        } else if (bounds.length === 1) {
            map.setView(
                bounds[0],
                16
            );
        }
    };


    const popupHtml = (place) => {
        const location = locationLabel(place);
        const image = imageUrl(place.featured_image);

        return `
            <article class="map-popup">
                ${
                    image
                        ? `<img src="${escapeHtml(image)}"
                             alt="${escapeHtml(place.featured_image_alt || place.name)}">`
                        : ''
                }

                <div>
                    <span class="map-popup-type">
                        ${escapeHtml(formatLabel(place.type))}
                    </span>

                    <strong>${escapeHtml(place.name)}</strong>

                    ${
                        location
                            ? `<span>${escapeHtml(location)}</span>`
                            : ''
                    }

                    <span class="map-popup-exact"><i class="llama-icon-mask" style="--llama-icon-mask:url('/assets/icons/current-location.svg')" aria-hidden="true"></i> Stored location</span>

                    <a href="${placeUrl(place)}">View Place</a>
                </div>
            </article>
        `;
    };


    const resourcePopupHtml = (resource) => {
        const location =
            resourceLocationLabel(resource);

        const services =
            resourceServices(resource)
                .slice(0, 5);

        return `
            <article class="map-popup map-resource-popup">
                <div>
                    <span class="map-popup-type">
                        Resource · ${escapeHtml(
                            resource.resource_type_label
                            || formatLabel(resource.resource_type)
                        )}
                    </span>

                    <strong>${escapeHtml(resource.name || 'Resource')}</strong>

                    ${
                        location
                            ? `<span>${escapeHtml(location)}</span>`
                            : ''
                    }

                    <div class="map-resource-popup-services">
                        ${services
                            .map(
                                (service) =>
                                    `<span>${escapeHtml(
                                        service.service_type_label
                                        || formatLabel(service.service_type)
                                    )}</span>`
                            )
                            .join('')}
                    </div>

                    ${
                        resource.hours_text
                            ? `<span>${escapeHtml(resource.hours_text)}</span>`
                            : ''
                    }

                    <span class="map-popup-exact">
                        <i class="llama-icon-mask" style="--llama-icon-mask:url('/assets/icons/current-location.svg')" aria-hidden="true"></i>
                        Resource location
                    </span>
                </div>
            </article>
        `;
    };


    const resourceMarkerIcon = (resource) =>
        L.divIcon({
            className:
                'map-resource-marker-shell',
            html:
                `<span class="map-resource-marker ${resourceTypeClass(resource)}" aria-hidden="true"></span>`,
            iconSize: [36, 36],
            iconAnchor: [18, 18],
            popupAnchor: [0, -20]
        });


    const renderResourceCard = (resource, marker) => {
        const card =
            document.createElement('button');

        const services =
            resourceServices(resource);

        const shownServices =
            services.slice(0, 4);

        const remaining =
            Math.max(
                0,
                services.length - shownServices.length
            );

        const location =
            resourceLocationLabel(resource);

        const status =
            String(
                resource.status
                || 'unknown'
            );

        card.type = 'button';
        card.className = 'map-resource-card';
        card.setAttribute(
            'aria-label',
            `Show ${String(resource.name || 'Resource')} on map`
        );

        card.innerHTML = `
            <span class="map-resource-card-icon ${resourceTypeClass(resource)}" aria-hidden="true">
                <i></i>
            </span>

            <span class="map-resource-card-body">
                <span class="map-resource-card-topline">
                    <span>Resource</span>
                    <strong>${escapeHtml(
                        resource.resource_type_label
                        || formatLabel(resource.resource_type)
                    )}</strong>
                </span>

                <span class="map-resource-card-name">
                    ${escapeHtml(resource.name || 'Resource')}
                </span>

                ${
                    location
                        ? `
                            <span class="map-resource-card-location">
                                <i class="llama-icon-mask" style="--llama-icon-mask:url('/assets/icons/map-pin.svg')" aria-hidden="true"></i>
                                ${escapeHtml(location)}
                            </span>
                        `
                        : ''
                }

                <span class="map-resource-services">
                    ${shownServices
                        .map(
                            (service) =>
                                `<span>${escapeHtml(
                                    service.service_type_label
                                    || formatLabel(service.service_type)
                                )}</span>`
                        )
                        .join('')}
                    ${
                        remaining > 0
                            ? `<span>+${remaining} more</span>`
                            : ''
                    }
                </span>

                <span class="map-resource-card-meta">
                    ${
                        status !== 'unknown'
                            ? `<span>${escapeHtml(formatLabel(status))}</span>`
                            : ''
                    }

                    ${
                        resource.hours_text
                            ? `<span>${escapeHtml(resource.hours_text)}</span>`
                            : ''
                    }
                </span>
            </span>
        `;

        const focusMarker = () => {
            if (!marker) {
                return;
            }

            marker.openPopup();

            map.panTo(
                marker.getLatLng(),
                {
                    animate: true
                }
            );
        };

        card.addEventListener(
            'click',
            focusMarker
        );

        card.addEventListener(
            'mouseenter',
            () => marker?.openPopup()
        );

        card.addEventListener(
            'focus',
            () => marker?.openPopup()
        );

        return card;
    };


    const resourcesInsideMap = () => {
        const bounds =
            map.getBounds();

        return filteredResources.filter(
            (resource) => {
                const coordinates =
                    resourceCoordinates(resource);

                if (!coordinates) {
                    return false;
                }

                return bounds.contains(
                    L.latLng(
                        coordinates[0],
                        coordinates[1]
                    )
                );
            }
        );
    };


    const renderAmenities = (place) => {
        const items = Object.entries(amenityLabels)
            .filter(([key]) => Number(place.amenities?.[key] || 0) === 1)
            .slice(0, 3);

        if (!items.length) {
            return '';
        }

        return `
            <div class="map-place-amenities">
                ${items
                    .map(([, label]) => `<span>${escapeHtml(label)}</span>`)
                    .join('')}
            </div>
        `;
    };


    const renderCard = (place, marker) => {
        const card = document.createElement('a');
        const image = imageUrl(place.featured_image);
        const location = locationLabel(place);
        const url = placeUrl(place);
        const featured = isFeaturedPlace(place);
        const partner = activePartner(place);
        const affiliate = isAffiliatePlace(place);
        const paid = campingFeeStatus(place) === 'paid' || campingFeeStatus(place) === 'mixed';
        if (partner?.use_branded_cards) registerPartnerStyles(partner);

        card.className =
            'map-place-card' +
            (isClosedPlace(place) ? ' is-closed' : '') +
            (featured ? ' is-featured' : '') +
            (paid ? ' is-paid-place' : '') +
            (partner?.use_branded_cards ? ` is-partner-branded map-partner-${Number(partner.id)}` : '');
        card.href = url;
        card.setAttribute(
            'aria-label',
            `View ${String(place.name || 'Place')}`
        );

        card.innerHTML = `
            <span class="map-place-image">
                ${
                    image
                        ? `
                            <img
                                src="${escapeHtml(image)}"
                                alt="${escapeHtml(place.featured_image_alt || place.name)}"
                                loading="lazy"
                            >
                        `
                        : `
                            <span class="map-place-image-placeholder">
                                <i class="llama-icon-mask" style="--llama-icon-mask:url('/assets/icons/mountain.svg')" aria-hidden="true"></i>
                            </span>
                        `
                }

                <span class="map-place-badges">
                    ${isClosedPlace(place)
                        ? '<span class="map-featured-badge map-closed-badge"><span class="map-closed-badge-icon" aria-hidden="true"></span> Closed</span>'
                        : ''}
                    ${
                        featured
                            ? `<span class="map-featured-badge">
                                    <span aria-hidden="true">★</span>
                                Featured
                            </span>`
                            : ''
                    }
                    ${
                        affiliate
                            ? '<span class="map-featured-badge map-affiliate-badge">Affiliate</span>'
                            : ''
                    }
                    ${
                        paid
                            ? '<span class="map-featured-badge map-paid-badge"><span class="map-paid-symbol" aria-hidden="true">$</span> Paid</span>'
                            : ''
                    }
                </span>
            </span>

            <div class="map-place-body">
                <div class="map-place-topline">
                    <span>${escapeHtml(formatLabel(place.type))}</span>
                </div>

                <h3>${escapeHtml(place.name)}</h3>

                ${
                    location
                        ? `
                            <p class="map-place-location">
                                <i class="llama-icon-mask" style="--llama-icon-mask:url('/assets/icons/map-pin.svg')" aria-hidden="true"></i>
                                ${escapeHtml(location)}
                            </p>
                        `
                        : ''
                }

                <div class="map-place-meta">
                    ${
                        place.elevation_feet
                            ? `<span>${Number(place.elevation_feet).toLocaleString()} ft</span>`
                            : ''
                    }

                    ${
                        place.land_manager
                            ? `<span>${escapeHtml(place.land_manager)}</span>`
                            : ''
                    }
                </div>
            </div>
        `;

        if (marker) {
            card.addEventListener('mouseenter', () => {
                marker.openPopup();
            });

            card.addEventListener('focus', () => {
                marker.openPopup();
            });
        }

        return card;
    };


    const placesInsideMap = () => {
        const bounds = map.getBounds();

        return filteredPlaces.filter((place) => {
            const coordinates = placeCoordinates(place);

            if (!coordinates) {
                return false;
            }

            return bounds.contains(
                L.latLng(coordinates[0], coordinates[1])
            );
        });
    };


    const renderViewportCards = () => {
        visiblePlaces =
            placesInsideMap();

        visibleResources =
            resourcesInsideMap();

        if (controls.results) {
            controls.results.innerHTML = '';

            visiblePlaces.forEach((place) => {
                controls.results.appendChild(
                    renderCard(
                        place,
                        markerByPlace.get(place) || null
                    )
                );
            });
        }

        if (controls.resourceResults) {
            controls.resourceResults.innerHTML = '';

            visibleResources.forEach(
                (resource) => {
                    controls.resourceResults.appendChild(
                        renderResourceCard(
                            resource,
                            markerByResource.get(resource)
                            || null
                        )
                    );
                }
            );
        }

        if (controls.resourceSection) {
            controls.resourceSection.hidden =
                visibleResources.length === 0;
        }

        if (controls.status) {
            controls.status.textContent =
                `${visiblePlaces.length} Place${visiblePlaces.length === 1 ? '' : 's'} · ${visibleResources.length} Resource${visibleResources.length === 1 ? '' : 's'} shown`;
        }

        if (controls.empty) {
            const heading =
                controls.empty.querySelector('h3');

            const paragraph =
                controls.empty.querySelector('p');

            const visibleCount =
                visiblePlaces.length
                + visibleResources.length;

            controls.empty.hidden =
                visibleCount !== 0;

            if (visibleCount === 0) {
                const filteredCount =
                    filteredPlaces.length
                    + filteredResources.length;

                if (filteredCount === 0) {
                    if (heading) {
                        heading.textContent =
                            'No Places or Resources match.';
                    }

                    if (paragraph) {
                        paragraph.textContent =
                            'Try changing the search or clearing Place filters.';
                    }
                } else {
                    if (heading) {
                        heading.textContent =
                            'No Places or Resources in this map area.';
                    }

                    if (paragraph) {
                        paragraph.textContent =
                            'Pan the map, zoom out, or choose Fit map to bring matching results back into view.';
                    }
                }
            }
        }
    };


    const render = (fitMap = false) => {
        filteredPlaces =
            places.filter(matches);

        filteredResources =
            resources.filter(resourceMatches);

        clearMarkers();

        filteredPlaces.forEach((place) => {
            const coordinates = placeCoordinates(place);

            if (!coordinates) {
                return;
            }

            const marker = L.marker(
                coordinates,
                {
                    icon: markerIcon(place)
                }
            );

            marker.bindPopup(popupHtml(place));
            marker.addTo(map);

            markers.push(marker);
            markerByPlace.set(place, marker);
        });

        filteredResources.forEach(
            (resource) => {
                const coordinates =
                    resourceCoordinates(resource);

                if (!coordinates) {
                    return;
                }

                const marker =
                    L.marker(
                        coordinates,
                        {
                            icon:
                                resourceMarkerIcon(
                                    resource
                                )
                        }
                    );

                marker.bindPopup(
                    resourcePopupHtml(
                        resource
                    )
                );

                marker.addTo(map);

                markers.push(marker);

                markerByResource.set(
                    resource,
                    marker
                );
            }
        );

        updateFilterCount();

        if (fitMap) {
            fitVisiblePlaces();
        }

        renderViewportCards();
    };


    const clearFilters = () => {
        if (controls.search) {
            controls.search.value = '';
        }

        [
            controls.state,
            controls.county,
            controls.city,
            controls.type,
            controls.landManager,
            controls.landType,
            controls.elevationMax,
            controls.amenity
        ].forEach((element) => {
            if (element) {
                element.value = '';
            }
        });

        if (openOnlySwitch) openOnlySwitch.checked = false;
        render(true);
    };


    const toggleFilters = () => {
        if (!controls.panel || !controls.toggle) {
            return;
        }

        const opening = controls.panel.hidden;

        controls.panel.hidden = !opening;
        controls.toggle.setAttribute(
            'aria-expanded',
            opening ? 'true' : 'false'
        );
    };


    async function loadPlaces() {
        if (controls.status) {
            controls.status.textContent = 'Loading Places...';
        }

        try {
            const [
                response,
                resourceResponse
            ] = await Promise.all([
                fetch(
                    '/api/places.php',
                    {
                        cache: 'no-store',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                ),
                fetch(
                    '/api/resources.php',
                    {
                        cache: 'no-store',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                ).catch(() => null)
            ]);

            if (!response.ok) {
                throw new Error('Unable to load Places.');
            }

            const data =
                await response.json();

            if (!data.ok || !Array.isArray(data.places)) {
                throw new Error('Unexpected Places response.');
            }

            let resourceData = {
                ok: false,
                resources: []
            };

            if (
                resourceResponse
                && resourceResponse.ok
            ) {
                try {
                    resourceData =
                        await resourceResponse.json();
                } catch (error) {
                    console.error(
                        'Llama Scout Resource map data:',
                        error
                    );
                }
            }

            syncMapAccess(
                data.member_map_access === true,
                data.contributor_place_access === true
            );

            if (
                memberMapAccess &&
                data.member_tiles?.geoapify_available === true
            ) {
                tileSources.light.url =
                    String(data.member_tiles.light || '');

                tileSources.dark.url =
                    String(data.member_tiles.dark || '');

                if (selectedLayer === 'auto') {
                    applyTileLayer();
                }
            }

            places = data.places;

            resources =
                resourceData.ok
                && Array.isArray(
                    resourceData.resources
                )
                    ? resourceData.resources
                    : [];

            populateFilters();
            render(true);

        } catch (error) {
            console.error('Llama Scout map:', error);

            if (controls.status) {
                controls.status.textContent =
                    'Places could not be loaded.';
            }

            if (controls.empty) {
                controls.empty.hidden = false;

                const heading = controls.empty.querySelector('h3');
                const paragraph = controls.empty.querySelector('p');

                if (heading) {
                    heading.textContent =
                        'The map could not load Places.';
                }

                if (paragraph) {
                    paragraph.textContent =
                        'Try reloading the page.';
                }
            }
        }
    }


    openOnlySwitch?.addEventListener('change', () => render(true));
    controls.toggle?.addEventListener('click', toggleFilters);
    controls.clear?.addEventListener('click', clearFilters);
    controls.fit?.addEventListener('click', fitVisiblePlaces);

    controls.search?.addEventListener('input', () => {
        window.clearTimeout(searchRenderTimer);

        searchRenderTimer = window.setTimeout(
            () => render(true),
            180
        );
    });

    [        controls.state,
        controls.county,
        controls.city,
        controls.type,
        controls.landManager,
        controls.landType,
        controls.elevationMax,
        controls.amenity
    ].forEach((element) => {
        element?.addEventListener('change', () => render(true));
    });

    map.on('moveend', () => {
        if (!places.length) {
            return;
        }

        renderViewportCards();
    });

    loadPlaces();
})();
