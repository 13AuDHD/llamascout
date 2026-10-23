(() => {
    'use strict';

    const panel =
        document.querySelector(
            '[data-admin-place-map-panel]'
        );

    const mapElement =
        panel?.querySelector(
            '[data-admin-place-map]'
        );

    const dataNode =
        panel?.querySelector(
            '[data-admin-place-map-data]'
        );

    if (
        !panel
        || !mapElement
        || !dataNode
        || typeof L === 'undefined'
    ) {
        return;
    }

    let config;

    try {
        config =
            JSON.parse(
                dataNode.textContent
                || '{}'
            );
    } catch (_) {
        return;
    }

    const place =
        config.place
        && typeof config.place === 'object'
            ? config.place
            : {};

    const features =
        Array.isArray(config.features)
            ? config.features
            : [];

    const selection =
        panel.querySelector(
            '[data-admin-place-map-selection]'
        );

    const editLink =
        panel.querySelector(
            '[data-admin-place-map-edit]'
        );

    const map =
        L.map(
            mapElement,
            {
                zoomControl:
                    false,

                maxZoom:
                    20
            }
        );

    L.control.zoom({
        position:
            'bottomright'
    }).addTo(
        map
    );

    const tileSources = {
        street: {
            url:
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',

            options: {
                maxNativeZoom:
                    19,

                maxZoom:
                    20,

                attribution:
                    '&copy; OpenStreetMap contributors'
            }
        },

        terrain: {
            url:
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',

            options: {
                maxNativeZoom:
                    19,

                maxZoom:
                    20,

                attribution:
                    'Tiles &copy; Esri and contributors'
            }
        },

        satellite: {
            url:
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',

            options: {
                maxNativeZoom:
                    19,

                maxZoom:
                    20,

                attribution:
                    'Tiles &copy; Esri and imagery contributors'
            }
        }
    };

    let activeTileLayer = null;
    let mapStyle =
        'satellite';

    const applyMapStyle =
        (value) => {
            mapStyle =
                Object.hasOwn(
                    tileSources,
                    value
                )
                    ? value
                    : 'satellite';

            if (activeTileLayer) {
                map.removeLayer(
                    activeTileLayer
                );
            }

            const source =
                tileSources[
                    mapStyle
                ];

            activeTileLayer =
                L.tileLayer(
                    source.url,
                    source.options
                );

            activeTileLayer.addTo(
                map
            );

            activeTileLayer.bringToBack();

            panel
                .querySelectorAll(
                    '[data-admin-map-style]'
                )
                .forEach(
                    (button) => {
                        button
                            .classList
                            .toggle(
                                'is-active',
                                button.dataset.adminMapStyle
                                    === mapStyle
                            );
                    }
                );
        };

    panel
        .querySelectorAll(
            '[data-admin-map-style]'
        )
        .forEach(
            (button) => {
                button.addEventListener(
                    'click',
                    () => {
                        applyMapStyle(
                            button.dataset.adminMapStyle
                            || 'satellite'
                        );
                    }
                );
            }
        );

    map.createPane(
        'llama-place-boundary'
    );

    map.getPane(
        'llama-place-boundary'
    ).style.zIndex =
        '410';

    map.createPane(
        'llama-area'
    );

    map.getPane(
        'llama-area'
    ).style.zIndex =
        '420';

    map.createPane(
        'llama-site'
    );

    map.getPane(
        'llama-site'
    ).style.zIndex =
        '430';

    const styleFor =
        (
            type,
            active = false
        ) => {
            const palette = {
                place_boundary:
                    '#8b5cf6',

                camping_area:
                    '#1f8f4e',

                parking_area:
                    '#2563eb',

                camping_site:
                    '#d97706'
            };

            const color =
                palette[type]
                || '#64748b';

            return {
                color,
                fillColor:
                    color,

                weight:
                    active
                        ? 4
                        : 2,

                opacity:
                    active
                        ? 1
                        : .82,

                fillOpacity:
                    active
                        ? .28
                        : .13
            };
        };

    const paneFor =
        (type) => {
            if (type === 'camping_site') {
                return 'llama-site';
            }

            if (
                type === 'camping_area'
                || type === 'parking_area'
            ) {
                return 'llama-area';
            }

            return 'llama-place-boundary';
        };

    // Edit the canonical Site record coordinates, not a second map identity.
    const siteLocationForm =
        panel.querySelector('[data-admin-site-location-form]');
    const siteLocationSelect =
        siteLocationForm?.querySelector('[data-site-location-target]');
    const siteLatitude =
        siteLocationForm?.querySelector('[data-site-location-latitude]');
    const siteLongitude =
        siteLocationForm?.querySelector('[data-site-location-longitude]');
    const siteLocationStatus =
        siteLocationForm?.querySelector('[data-site-location-status]');
    const newSiteFields =
        siteLocationForm?.querySelector('[data-site-location-new]');
    const newSiteName =
        siteLocationForm?.querySelector('[data-site-location-name]');
    const siteLocationPick =
        siteLocationForm?.querySelector('[data-site-location-pick]');
    const siteLocationGps =
        siteLocationForm?.querySelector('[data-site-location-gps]');
    let pickingSitePoint = false;
    let sitePointPreview = null;

    const siteLocationMessage = (message) => {
        if (siteLocationStatus) {
            siteLocationStatus.textContent = message;
        }
    };

    const setSitePoint = (latitude, longitude, pan = false) => {
        if (!siteLatitude || !siteLongitude) {
            return;
        }
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)
            || latitude < -90 || latitude > 90
            || longitude < -180 || longitude > 180
            || (latitude === 0 && longitude === 0)) {
            return;
        }
        siteLatitude.value = latitude.toFixed(7);
        siteLongitude.value = longitude.toFixed(7);
        if (sitePointPreview) {
            map.removeLayer(sitePointPreview);
        }
        sitePointPreview = L.circleMarker([latitude, longitude], {
            pane: 'llama-site',
            radius: 9,
            color: '#d97706',
            weight: 3,
            fillColor: '#d97706',
            fillOpacity: .55,
            interactive: false,
        }).addTo(map);
        if (pan) {
            map.setView([latitude, longitude], Math.max(17, map.getZoom()));
        }
    };

    const updateSiteLocationSelection = () => {
        pickingSitePoint = false;
        if (siteLocationPick) {
            siteLocationPick.classList.remove('is-active');
        }
        if (sitePointPreview) {
            map.removeLayer(sitePointPreview);
            sitePointPreview = null;
        }
        if (!siteLatitude || !siteLongitude) {
            return;
        }
        siteLatitude.value = '';
        siteLongitude.value = '';
        const creatingSite = siteLocationSelect?.value === 'new';
        if (newSiteFields) {
            newSiteFields.hidden = !creatingSite;
        }
        if (newSiteName) {
            newSiteName.disabled = !creatingSite;
            newSiteName.required = creatingSite;
        }
        if (creatingSite) {
            siteLocationMessage('Name the new Site, then set its exact location. No Site boundary is required.');
            return;
        }
        const selected = siteLocationSelect?.selectedOptions?.[0];
        if (!selected?.value) {
            siteLocationMessage('Choose a Site before assigning its GPS point.');
            return;
        }
        const latText = selected.dataset.latitude || '';
        const lngText = selected.dataset.longitude || '';
        if (latText !== '' && lngText !== '') {
            setSitePoint(Number(latText), Number(lngText), true);
            siteLocationMessage('Existing Site location loaded. You can adjust and save it.');
        } else {
            siteLocationMessage('This Site has no GPS point yet. Place a pin, use GPS, or enter coordinates.');
        }
    };

    const pickSiteLocation = (event) => {
        if (!pickingSitePoint || !siteLocationSelect?.value) {
            return false;
        }
        pickingSitePoint = false;
        siteLocationPick?.classList.remove('is-active');
        const point = event?.latlng;
        if (!point) {
            return false;
        }
        setSitePoint(point.lat, point.lng);
        siteLocationMessage('Site pin selected. Choose Save Site GPS point to keep it.');
        return true;
    };

    siteLocationSelect?.addEventListener('change', updateSiteLocationSelection);
    const previewTypedSiteLocation = () => {
        if (!siteLocationSelect?.value) {
            return;
        }
        const lat = Number(siteLatitude?.value);
        const lng = Number(siteLongitude?.value);
        if (siteLatitude?.value && siteLongitude?.value
            && Number.isFinite(lat) && Number.isFinite(lng)
            && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
            && (lat !== 0 || lng !== 0)) {
            setSitePoint(lat, lng);
            siteLocationMessage('Site GPS position previewed. Save to publish the coordinates.');
        }
    };
    siteLatitude?.addEventListener('change', previewTypedSiteLocation);
    siteLongitude?.addEventListener('change', previewTypedSiteLocation);
    siteLocationPick?.addEventListener('click', () => {
        if (!siteLocationSelect?.value) {
            siteLocationMessage('Choose a Site before placing a pin.');
            siteLocationSelect?.focus();
            return;
        }
        pickingSitePoint = !pickingSitePoint;
        siteLocationPick.classList.toggle('is-active', pickingSitePoint);
        siteLocationMessage(pickingSitePoint
            ? 'Tap the Site location on the map, including inside an Area boundary.'
            : 'Pin placement cancelled.');
    });
    siteLocationGps?.addEventListener('click', () => {
        if (!siteLocationSelect?.value) {
            siteLocationMessage('Choose a Site before using device GPS.');
            return;
        }
        if (!navigator.geolocation) {
            siteLocationMessage('Device GPS is unavailable in this browser.');
            return;
        }
        siteLocationMessage('Reading your current position...');
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setSitePoint(position.coords.latitude, position.coords.longitude, true);
                siteLocationMessage('Device location captured. Check the accuracy before saving.');
            },
            () => siteLocationMessage('Location unavailable or permission denied. Enter coordinates or place a pin.'),
            {enableHighAccuracy: true, timeout: 15000, maximumAge: 0}
        );
    });
    siteLocationForm?.addEventListener('submit', (event) => {
        const lat = Number(siteLatitude?.value);
        const lng = Number(siteLongitude?.value);
        if (!siteLocationSelect?.value
            || (siteLocationSelect.value === 'new' && !newSiteName?.value.trim())
            || !siteLatitude?.value || !siteLongitude?.value
            || !Number.isFinite(lat) || !Number.isFinite(lng)
            || lat < -90 || lat > 90 || lng < -180 || lng > 180
            || (lat === 0 && lng === 0)) {
            event.preventDefault();
            siteLocationMessage('Choose a Site and enter valid nonzero coordinates first.');
        }
    });
    window.addEventListener('llama:place-context-changed', (event) => {
        if (event?.detail?.level !== 'site' || !siteLocationSelect) {
            return;
        }
        const value = 'campsite_record:' + Number(event.detail.site_id || 0);
        if (Array.from(siteLocationSelect.options).some((option) => option.value === value)) {
            siteLocationSelect.value = value;
            updateSiteLocationSelection();
        }
    });

    const layers =
        new Map();

    const allBounds =
        L.latLngBounds([]);

    features.forEach(
        (feature) => {
            const featureId =
                Number(
                    feature?.id
                    || 0
                );

            const type =
                String(
                    feature?.feature_type
                    || ''
                );

            if (
                featureId < 1
                || !feature?.geometry
            ) {
                return;
            }

            const layer =
                L.geoJSON(
                    feature.geometry,
                    {
                        pane:
                            paneFor(
                                type
                            ),

                        style:
                            styleFor(
                                type,
                                false
                            ),

                        bubblingMouseEvents:
                            false
                    }
                );

            layer.addTo(
                map
            );

            layer.eachLayer(
                (child) => {
                    child.options.bubblingMouseEvents =
                        false;

                    child.on(
                        'click',
                        (event) => {
                            if (pickSiteLocation(event)) {
                                return;
                            }
                            window.dispatchEvent(
                                new CustomEvent(
                                    'llama:map-feature-selected',
                                    {
                                        detail: {
                                            map_feature_id:
                                                featureId
                                        }
                                    }
                                )
                            );
                        }
                    );
                }
            );

            const bounds =
                layer.getBounds();

            if (bounds.isValid()) {
                allBounds.extend(
                    bounds
                );
            }

            layers.set(
                featureId,
                {
                    feature,
                    layer
                }
            );
        }
    );

    let selectedFeatureId =
        0;

    const updateStyles =
        () => {
            layers.forEach(
                (
                    entry,
                    featureId
                ) => {
                    const type =
                        String(
                            entry.feature
                                ?.feature_type
                            || ''
                        );

                    entry.layer.setStyle(
                        styleFor(
                            type,
                            featureId
                                === selectedFeatureId
                        )
                    );
                }
            );
        };

    const fitSelection =
        (
            featureId,
            level
        ) => {
            const entry =
                layers.get(
                    featureId
                );

            if (!entry) {
                if (
                    level === 'place'
                    && allBounds.isValid()
                ) {
                    map.fitBounds(
                        allBounds,
                        {
                            padding:
                                [30, 30],

                            maxZoom:
                                19
                        }
                    );
                }

                return;
            }

            const bounds =
                entry.layer.getBounds();

            if (!bounds.isValid()) {
                return;
            }

            map.fitBounds(
                bounds,
                {
                    padding:
                        [34, 34],

                    maxZoom:
                        level === 'site'
                            ? 20
                            : 19
                }
            );
        };

    const updateSelectionLabel =
        (detail) => {
            if (!selection) {
                return;
            }

            const strong =
                selection.querySelector(
                    'strong'
                );

            const span =
                selection.querySelector(
                    'span'
                );

            if (strong) {
                strong.textContent =
                    String(
                        detail?.label
                        || place.name
                        || 'Place'
                    );
            }

            if (span) {
                const level =
                    String(
                        detail?.level
                        || 'place'
                    );

                span.textContent =
                    level === 'site'
                        ? 'Site'
                        : (
                            level === 'area'
                                ? 'Area'
                                : 'Place'
                        );
            }
        };

    const updateEditLink =
        (featureId) => {
            if (!editLink) {
                return;
            }

            const slug =
                String(
                    place.slug
                    || ''
                );

            const url =
                new URL(
                    'https://llamascout.com/place-map-editor.php'
                );

            url.searchParams.set(
                'slug',
                slug
            );

            if (featureId > 0) {
                url.searchParams.set(
                    'feature',
                    String(
                        featureId
                    )
                );
            }

            editLink.href =
                url.toString();
        };

    map.on('click', (event) => {
        pickSiteLocation(event);
    });

    window.addEventListener(
        'llama:place-context-changed',
        (event) => {
            const detail =
                event?.detail;

            if (
                !detail
                || typeof detail !== 'object'
            ) {
                return;
            }

            selectedFeatureId =
                Math.max(
                    0,
                    Number(
                        detail.map_feature_id
                        || 0
                    )
                );

            updateStyles();

            updateSelectionLabel(
                detail
            );

            updateEditLink(
                selectedFeatureId
            );

            fitSelection(
                selectedFeatureId,
                String(
                    detail.level
                    || 'place'
                )
            );
        }
    );

    applyMapStyle(
        mapStyle
    );

    if (allBounds.isValid()) {
        map.fitBounds(
            allBounds,
            {
                padding:
                    [30, 30],

                maxZoom:
                    19
            }
        );
    } else {
        const latitude =
            Number(
                place.latitude
            );

        const longitude =
            Number(
                place.longitude
            );

        if (
            Number.isFinite(
                latitude
            )
            && Number.isFinite(
                longitude
            )
        ) {
            map.setView(
                [
                    latitude,
                    longitude
                ],
                18
            );
        } else {
            map.setView(
                [
                    0,
                    0
                ],
                2
            );
        }
    }

    updateSiteLocationSelection();

    window.requestAnimationFrame(
        () => {
            map.invalidateSize(
                false
            );
        }
    );
})();
