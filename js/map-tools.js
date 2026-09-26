(() => {
    'use strict';

    const control = document.getElementById('map-layer-control');

    if (!control) {
        return;
    }

    const mapCard = control.closest('.map-card');
    const triggers = [...control.querySelectorAll('[data-map-tool]')];
    const panels = [...control.querySelectorAll('[data-map-tool-panel]')];
    const mapValue = document.getElementById('map-tool-map-value');
    const landCount = document.getElementById('map-tool-land-count');
    const landSlot = document.getElementById('map-tools-land-slot');

    let openTool = null;
    let landControl = null;
    let landObserver = null;

    function closePanels() {
        openTool = null;

        triggers.forEach((trigger) => {
            trigger.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        });

        panels.forEach((panel) => {
            panel.hidden = true;
        });
    }

    function openPanel(name) {
        const trigger =
            control.querySelector(`[data-map-tool="${name}"]`);

        const panel =
            control.querySelector(`[data-map-tool-panel="${name}"]`);

        if (!trigger || !panel) {
            return;
        }

        const willOpen = openTool !== name;

        closePanels();

        if (!willOpen) {
            return;
        }

        openTool = name;
        trigger.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        panel.hidden = false;
    }

    function mapLabel(value) {
        const labels = {
            auto: 'Auto',
            street: 'Street',
            terrain: 'Terrain',
            topo: 'Topo',
            dark: 'Dark',
            satellite: 'Satellite'
        };

        return labels[value] || 'Map';
    }

    function syncMapValue() {
        const active =
            control.querySelector('[data-map-layer].is-active') ||
            control.querySelector('[data-map-layer][aria-pressed="true"]');

        if (mapValue) {
            mapValue.textContent =
                mapLabel(active?.dataset.mapLayer || 'auto');
        }
    }

    function syncLandCount() {
        if (!landControl || !landCount) {
            return;
        }

        const enabled =
            [...landControl.querySelectorAll('[data-land-layer]')]
                .filter((button) =>
                    button.getAttribute('aria-pressed') === 'true' ||
                    button.classList.contains('is-active')
                )
                .length;

        landCount.textContent = String(enabled);
        landCount.hidden = enabled === 0;

        const trigger = control.querySelector('[data-map-tool="land"]');
        trigger?.classList.toggle('has-active', enabled > 0);
    }

    function embedLandControl(node) {
        if (!landSlot || !node || node === landControl) {
            return;
        }

        landControl = node;
        landControl.classList.add('map-land-control-embedded');
        landSlot.appendChild(landControl);

        landObserver?.disconnect();
        landObserver = new MutationObserver(syncLandCount);

        landObserver.observe(landControl, {
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'aria-pressed']
        });

        syncLandCount();
    }

    function findLandControl() {
        if (!mapCard) {
            return;
        }

        const existing = mapCard.querySelector('.map-land-control');

        if (existing) {
            embedLandControl(existing);
        }
    }

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            openPanel(trigger.dataset.mapTool || '');
        });
    });

    control
        .querySelectorAll('[data-map-layer]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                if (mapValue) {
                    mapValue.textContent =
                        mapLabel(button.dataset.mapLayer || 'auto');
                }

                window.setTimeout(() => {
                    syncMapValue();
                    closePanels();
                }, 0);
            });
        });

    document.addEventListener('click', (event) => {
        if (
            openTool &&
            event.target instanceof Node &&
            !control.contains(event.target)
        ) {
            closePanels();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !openTool) {
            return;
        }

        const trigger =
            control.querySelector(`[data-map-tool="${openTool}"]`);

        closePanels();
        trigger?.focus();
    });

    if (mapCard) {
        const mapCardObserver = new MutationObserver(() => {
            findLandControl();
        });

        mapCardObserver.observe(mapCard, {
            childList: true,
            subtree: true
        });
    }

    const mapButtonObserver = new MutationObserver(syncMapValue);

    control
        .querySelectorAll('[data-map-layer]')
        .forEach((button) => {
            mapButtonObserver.observe(button, {
                attributes: true,
                attributeFilter: ['class', 'aria-pressed']
            });
        });


    document.addEventListener(
        'click',
        (event) => {
            const target = event.target;
    
            if (!(target instanceof Element)) {
                return;
            }
    
            const closeButton =
                target.closest(
                    '[data-map-overlay-popup-close]'
                );
    
            if (!closeButton) {
                return;
            }
    
            event.preventDefault();
            event.stopPropagation();
    
            window.LlamaScoutMap
                ?.map
                ?.closePopup();
        }
    );

    
    syncMapValue();
    findLandControl();
    closePanels();
})();
