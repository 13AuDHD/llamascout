(() => {
    'use strict';

    const root = document.documentElement;

    const button = document.getElementById('accessibility-button');
    const panel = document.getElementById('accessibility-panel');
    const closeButton = document.getElementById('accessibility-close');

    const themeSelect = document.getElementById('theme-select');
    const fontSizeSelect = document.getElementById('font-size-select');
    const reducedMotion = document.getElementById('reduced-motion');
    const resetButton = document.getElementById('accessibility-reset');

    if (
        !button ||
        !panel ||
        !themeSelect ||
        !fontSizeSelect ||
        !reducedMotion
    ) {
        return;
    }

    function getPreference(key, fallback) {
        try {
            return localStorage.getItem(key) || fallback;
        } catch (error) {
            return fallback;
        }
    }

    function setPreference(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (error) {
            // localStorage is optional.
        }
    }

    function removePreference(key) {
        try {
            localStorage.removeItem(key);
        } catch (error) {
            // localStorage is optional.
        }
    }

    function applyTheme(value) {
        if (value === 'light' || value === 'dark') {
            root.dataset.theme = value;
        } else {
            delete root.dataset.theme;
        }
    }

    function applyFontSize(value) {
        if (value === 'large' || value === 'larger') {
            root.dataset.fontSize = value;
        } else {
            delete root.dataset.fontSize;
        }
    }

    function applyReducedMotion(enabled) {
        if (enabled) {
            root.dataset.reducedMotion = 'true';
        } else {
            delete root.dataset.reducedMotion;
        }
    }

    function openPanel() {
        panel.hidden = false;
        button.setAttribute('aria-expanded', 'true');

        window.requestAnimationFrame(() => {
            themeSelect.focus();
        });
    }

    function closePanel() {
        panel.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        button.focus();
    }

    const storedTheme = getPreference('llama-theme', 'system');
    const storedFontSize = getPreference('llama-font-size', 'normal');
    const storedReducedMotion =
        getPreference('llama-reduced-motion', 'false') === 'true';

    themeSelect.value = storedTheme;
    fontSizeSelect.value = storedFontSize;
    reducedMotion.checked = storedReducedMotion;

    applyTheme(storedTheme);
    applyFontSize(storedFontSize);
    applyReducedMotion(storedReducedMotion);

    button.addEventListener('click', () => {
        if (panel.hidden) {
            openPanel();
        } else {
            closePanel();
        }
    });

    closeButton?.addEventListener('click', closePanel);

    themeSelect.addEventListener('change', () => {
        const value = themeSelect.value;

        setPreference('llama-theme', value);
        applyTheme(value);
    });

    fontSizeSelect.addEventListener('change', () => {
        const value = fontSizeSelect.value;

        setPreference('llama-font-size', value);
        applyFontSize(value);
    });

    reducedMotion.addEventListener('change', () => {
        const enabled = reducedMotion.checked;

        setPreference(
            'llama-reduced-motion',
            enabled ? 'true' : 'false'
        );

        applyReducedMotion(enabled);
    });

    resetButton?.addEventListener('click', () => {
        removePreference('llama-theme');
        removePreference('llama-font-size');
        removePreference('llama-reduced-motion');

        themeSelect.value = 'system';
        fontSizeSelect.value = 'normal';
        reducedMotion.checked = false;

        delete root.dataset.theme;
        delete root.dataset.fontSize;
        delete root.dataset.reducedMotion;
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            closePanel();
        }
    });
})();
