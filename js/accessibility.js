(() => {
    'use strict';

    const root = document.documentElement;

    const button = document.getElementById('accessibility-button');
    const panel = document.getElementById('accessibility-panel');
    const closeButton = document.getElementById('accessibility-close');

    const themeSelect = document.getElementById('theme-select');
    const fontSizeSelect = document.getElementById('font-size-select');
    const reducedMotion = document.getElementById('reduced-motion');
    const focusIndicators = document.getElementById('focus-indicators');
    const resetButton = document.getElementById('accessibility-reset');
    
    if (
        !button ||
        !panel ||
        !themeSelect ||
        !fontSizeSelect ||
        !reducedMotion ||
        !focusIndicators
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

    function applyFocusIndicators(value) {
        root.dataset.focusIndicators =
            value === 'always'
                ? 'always'
                : 'auto';
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
    
    const storedFocusIndicators =
        getPreference(
            'llama-focus-indicators',
            'auto'
        );
    
    themeSelect.value = storedTheme;
    fontSizeSelect.value = storedFontSize;
    reducedMotion.checked = storedReducedMotion;
    focusIndicators.checked =
        storedFocusIndicators === 'always';
    
    applyTheme(storedTheme);
    applyFontSize(storedFontSize);
    applyReducedMotion(storedReducedMotion);
    applyFocusIndicators(storedFocusIndicators);
    
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

    focusIndicators.addEventListener('change', () => {
        const value =
            focusIndicators.checked
                ? 'always'
                : 'auto';
    
        setPreference(
            'llama-focus-indicators',
            value
        );
    
        applyFocusIndicators(value);
    });

    resetButton?.addEventListener('click', () => {
        removePreference('llama-theme');
        removePreference('llama-font-size');
        removePreference('llama-reduced-motion');
        removePreference('llama-focus-indicators');

        themeSelect.value = 'system';
        fontSizeSelect.value = 'normal';
        reducedMotion.checked = false;
        focusIndicators.checked = false;

        delete root.dataset.theme;
        delete root.dataset.fontSize;
        delete root.dataset.reducedMotion;
        
        root.dataset.focusIndicators = 'auto';
    });

    document.addEventListener(
        'pointerdown',
        () => {
            root.dataset.focusModality = 'pointer';
        },
        true
    );
    
    document.addEventListener(
        'keydown',
        (event) => {
            if (event.key === 'Tab') {
                root.dataset.focusModality = 'keyboard';
            }
        },
        true
    );
    
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            closePanel();
        }
    });
})();
