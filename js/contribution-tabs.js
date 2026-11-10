document.addEventListener('DOMContentLoaded', () => {
    for (const shell of document.querySelectorAll('[data-contribution-tabs]')) {
        const picker = shell.querySelector('[data-contribution-picker]');
        const report = shell.querySelector('[data-contribution-report]');
        const resource = shell.querySelector('[data-contribution-resource]');
        if (!picker || !report || !resource) continue;

        const buttons = [...picker.querySelectorAll('[data-contribution-mode]')];
        const groups = report.querySelectorAll('[data-report-main-section], [data-contribution-place-only]');
        const placePhotoPanel = report.querySelector('[data-contribution-place-photo-panel]');
        const firstSitePhotoPanel = report.querySelector('[data-contribution-site-photo-panel]');
        const hasAdminSitePicker = Boolean(report.querySelector('[data-admin-campsite-selector]'));
        let currentMode = null;

        const hasStagedPhotos = (form) => {
            if (!form) return false;
            return [...form.querySelectorAll(
                'input[name="photos_json"], input[name="site_photos_json"], input[name="resource_photos_json"]'
            )].some((field) => {
                try {
                    const photos = JSON.parse(field.value || '[]');
                    return Array.isArray(photos) && photos.length > 0;
                } catch (_) {
                    return false;
                }
            });
        };

        const select = (mode) => {
            if (!buttons.some(button => button.dataset.contributionMode === mode && !button.disabled)) return;

            // Place and first Site now have separate staging fields, so
            // switching between their tabs is safe. Switching between the
            // Resource form and the Place/Site form stays guarded.
            if (currentMode !== null && currentMode !== mode
                && (currentMode === 'resource' || mode === 'resource')) {
                const activeForm = currentMode === 'resource'
                    ? resource.querySelector('form[data-resource-editor]')
                    : report;
                if (hasStagedPhotos(activeForm)) {
                    window.alert('Save or remove the selected photos before switching tabs.');
                    return;
                }
            }

            for (const button of buttons) {
                button.setAttribute('aria-pressed', String(button.dataset.contributionMode === mode));
            }
            report.hidden = mode === 'resource';
            resource.hidden = mode !== 'resource';

            if (shell.hasAttribute('data-contribution-split')) {
                for (const group of groups) {
                    const site = group.dataset.reportMainSection === 'site';
                    group.toggleAttribute('data-contribution-inactive', site ? mode !== 'site' : mode === 'site');
                    if (site && mode === 'site') group.open = true;
                }
            }

            // Place and first Site uploaders stage to different targets;
            // only one is visible at a time on the shared Add Place form.
            if (placePhotoPanel && !hasAdminSitePicker) {
                placePhotoPanel.hidden = mode === 'site';
            }
            if (firstSitePhotoPanel) {
                firstSitePhotoPanel.hidden = mode !== 'site';
            }

            currentMode = mode;
            shell.dispatchEvent(new CustomEvent('llama:contribution-mode', { detail: { mode } }));
        };
        for (const button of buttons) {
            button.addEventListener('click', () => select(button.dataset.contributionMode));
        }

        if (shell.hasAttribute('data-contribution-split')) {
            report.addEventListener('invalid', (event) => {
                const group = event.target.closest('[data-report-main-section]');
                if (!group?.hasAttribute('data-contribution-inactive')) return;
                select(group.dataset.reportMainSection === 'site' ? 'site' : 'place');
                for (let section = event.target.closest('details'); section; section = section.parentElement?.closest('details')) {
                    section.open = true;
                }
            }, true);
        }

        report.querySelector('[data-admin-campsite-selector]')?.addEventListener('change', (event) => {
            if (event.target.value) select('site');
        });
        report.querySelector('[data-admin-area-selector]')?.addEventListener('change', () => select('place'));
        picker.hidden = false;
        select(shell.dataset.contributionInitial || 'place');
    }
});
