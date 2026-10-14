(() => {
    'use strict';

    const namespace =
        window.LlamaPlaceMapEditor
        = window.LlamaPlaceMapEditor
        || {};

    namespace.createGps =
        ({
            editor,
            map,
            leaflet,
            setStatus,
            syncSelectedPointPanel,
            addGpsPoint
        }) => {
            if (
                !editor
                || !map
                || !leaflet
            ) {
                throw new Error(
                    'Mapped-area GPS requires the editor, map, and Leaflet.'
                );
            }

            const gpsState =
                editor.querySelector(
                    '[data-gps-state]'
                );

            const gpsReading =
                editor.querySelector(
                    '[data-gps-reading]'
                );

            const gpsLatitude =
                editor.querySelector(
                    '[data-gps-latitude]'
                );

            const gpsLongitude =
                editor.querySelector(
                    '[data-gps-longitude]'
                );

            const gpsAccuracy =
                editor.querySelector(
                    '[data-gps-accuracy]'
                );

            const startGpsButton =
                editor.querySelector(
                    '[data-start-gps]'
                );

            const stopGpsButton =
                editor.querySelector(
                    '[data-stop-gps]'
                );

            const addGpsPointButton =
                editor.querySelector(
                    '[data-add-gps-point]'
                );

            const centerGpsButton =
                editor.querySelector(
                    '[data-center-gps]'
                );

            let gpsWatchId = null;
            let gpsPosition = null;
            let gpsMarker = null;
            let gpsAccuracyCircle = null;

            const setGpsState =
                (
                    label,
                    active = false
                ) => {
                    if (!gpsState) {
                        return;
                    }

                    gpsState.textContent =
                        label;

                    gpsState.classList.toggle(
                        'is-active',
                        active
                    );
                };

            const clearGpsMapLayers =
                () => {
                    if (gpsMarker) {
                        map.removeLayer(
                            gpsMarker
                        );

                        gpsMarker =
                            null;
                    }

                    if (gpsAccuracyCircle) {
                        map.removeLayer(
                            gpsAccuracyCircle
                        );

                        gpsAccuracyCircle =
                            null;
                    }
                };

            const renderGpsPosition =
                (position) => {
                    const lat =
                        Number(
                            position.coords
                                .latitude
                        );

                    const lng =
                        Number(
                            position.coords
                                .longitude
                        );

                    const accuracy =
                        Number(
                            position.coords
                                .accuracy
                        );

                    if (
                        !Number.isFinite(
                            lat
                        )
                        || !Number.isFinite(
                            lng
                        )
                    ) {
                        return;
                    }

                    gpsPosition = {
                        lat,
                        lng,

                        accuracy:
                            Number.isFinite(
                                accuracy
                            )
                                ? accuracy
                                : null,

                        capturedAt:
                            new Date(
                                position.timestamp
                                || Date.now()
                            ).toISOString()
                    };

                    if (
                        gpsReading
                    ) {
                        gpsReading.hidden =
                            false;
                    }

                    if (
                        gpsLatitude
                    ) {
                        gpsLatitude.textContent =
                            lat.toFixed(
                                7
                            );
                    }

                    if (
                        gpsLongitude
                    ) {
                        gpsLongitude.textContent =
                            lng.toFixed(
                                7
                            );
                    }

                    if (
                        gpsAccuracy
                    ) {
                        gpsAccuracy.textContent =
                            gpsPosition.accuracy
                            === null
                                ? 'Unknown'
                                : `±${Math.round(gpsPosition.accuracy)} m`;
                    }

                    if (
                        addGpsPointButton
                    ) {
                        addGpsPointButton.disabled =
                            false;
                    }

                    if (
                        centerGpsButton
                    ) {
                        centerGpsButton.disabled =
                            false;
                    }

                    syncSelectedPointPanel?.();

                    clearGpsMapLayers();

                    gpsAccuracyCircle =
                        leaflet.circle(
                            [
                                lat,
                                lng
                            ],
                            {
                                radius:
                                    Math.max(
                                        1,
                                        gpsPosition
                                            .accuracy
                                        || 1
                                    ),

                                color:
                                    '#2563eb',

                                weight:
                                    1,

                                opacity:
                                    .7,

                                fillColor:
                                    '#2563eb',

                                fillOpacity:
                                    .08,

                                interactive:
                                    false
                            }
                        ).addTo(
                            map
                        );

                    gpsMarker =
                        leaflet.marker(
                            [
                                lat,
                                lng
                            ],
                            {
                                interactive:
                                    false,

                                icon:
                                    leaflet.divIcon({
                                        className:
                                            '',

                                        html:
                                            '<span class="mapped-area-editor-gps-marker" aria-hidden="true"></span>',

                                        iconSize:
                                            [18, 18],

                                        iconAnchor:
                                            [9, 9]
                                    })
                            }
                        ).addTo(
                            map
                        );

                    setGpsState(
                        gpsPosition.accuracy
                        === null
                            ? 'GPS active'
                            : `±${Math.round(gpsPosition.accuracy)} m`,
                        true
                    );
                };

            const gpsErrorMessage =
                (error) => {
                    switch (
                        Number(
                            error?.code
                        )
                    ) {
                        case 1:
                            return 'Location permission was denied.';

                        case 2:
                            return 'Your device could not determine a GPS position.';

                        case 3:
                            return 'The GPS request timed out.';

                        default:
                            return 'Device GPS is unavailable.';
                    }
                };

            const stopGps =
                () => {
                    if (
                        gpsWatchId
                            !== null
                        && navigator
                            .geolocation
                    ) {
                        navigator
                            .geolocation
                            .clearWatch(
                                gpsWatchId
                            );
                    }

                    gpsWatchId =
                        null;

                    if (
                        startGpsButton
                    ) {
                        startGpsButton.disabled =
                            false;
                    }

                    if (
                        stopGpsButton
                    ) {
                        stopGpsButton.disabled =
                            true;
                    }

                    setGpsState(
                        gpsPosition
                            ? 'Paused'
                            : 'Off',
                        false
                    );
                };

            const startGps =
                () => {
                    if (
                        !(
                            'geolocation'
                            in navigator
                        )
                    ) {
                        setStatus?.(
                            'This browser does not provide device geolocation.',
                            true
                        );

                        return;
                    }

                    if (
                        gpsWatchId
                        !== null
                    ) {
                        return;
                    }

                    if (
                        startGpsButton
                    ) {
                        startGpsButton.disabled =
                            true;
                    }

                    if (
                        stopGpsButton
                    ) {
                        stopGpsButton.disabled =
                            false;
                    }

                    setGpsState(
                        'Locating...',
                        true
                    );

                    setStatus?.(
                        'Waiting for a high-accuracy GPS position...'
                    );

                    gpsWatchId =
                        navigator
                            .geolocation
                            .watchPosition(
                                (position) => {
                                    renderGpsPosition(
                                        position
                                    );

                                    setStatus?.(
                                        ''
                                    );
                                },

                                (error) => {
                                    setStatus?.(
                                        gpsErrorMessage(
                                            error
                                        ),
                                        true
                                    );

                                    stopGps();
                                },

                                {
                                    enableHighAccuracy:
                                        true,

                                    maximumAge:
                                        1000,

                                    timeout:
                                        15000
                                }
                            );
                };

            const addCurrentGpsPoint =
                () => {
                    if (
                        !gpsPosition
                    ) {
                        return;
                    }

                    addGpsPoint?.(
                        gpsPosition
                    );

                    setStatus?.(
                        `GPS point added${gpsPosition.accuracy === null ? '.' : ` at ±${Math.round(gpsPosition.accuracy)}m accuracy.`}`
                    );
                };

            const centerOnGps =
                () => {
                    if (
                        !gpsPosition
                    ) {
                        return;
                    }

                    map.setView(
                        [
                            gpsPosition.lat,
                            gpsPosition.lng
                        ],
                        Math.max(
                            map.getZoom(),
                            19
                        )
                    );
                };

            startGpsButton
                ?.addEventListener(
                    'click',
                    startGps
                );

            stopGpsButton
                ?.addEventListener(
                    'click',
                    stopGps
                );

            addGpsPointButton
                ?.addEventListener(
                    'click',
                    addCurrentGpsPoint
                );

            centerGpsButton
                ?.addEventListener(
                    'click',
                    centerOnGps
                );

            window.addEventListener(
                'pagehide',
                stopGps
            );

            return {
                startGps,
                stopGps,
                centerOnGps,
                clearGpsMapLayers,
                renderGpsPosition,

                getGpsPosition:
                    () =>
                        gpsPosition,

                getWatchId:
                    () =>
                        gpsWatchId
            };
        };
})();
