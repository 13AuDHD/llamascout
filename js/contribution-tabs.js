document.addEventListener('DOMContentLoaded', () => {
    for (const shell of document.querySelectorAll('[data-contribution-tabs]')) {
        const picker = shell.querySelector('[data-contribution-picker]');
        const report = shell.querySelector('[data-contribution-report]');
        if (!picker || !report) continue;
        const buttons = [...picker.querySelectorAll('[data-contribution-mode]')];
        const groups = report.querySelectorAll('[data-report-main-section], [data-contribution-place-only]');
        const placePhotoPanel = report.querySelector('[data-contribution-place-photo-panel]');
        const firstSitePhotoPanel = report.querySelector('[data-contribution-site-photo-panel]');
        const areaPanel = shell.querySelector('[data-contribution-area-panel]');
        const emptyAdminAreaPanel = shell.querySelector('[data-contribution-area-empty]');
        const areaTools = shell.querySelector('[data-contribution-area-tools]');
        const adminAreaSection = report.querySelector('[data-admin-area-picker]');
        const adminSitePicker = report.querySelector('[data-admin-campsite-selector]');
        const adminAreaPicker = report.querySelector('[data-admin-area-selector]');
        const isNewPlace = shell.hasAttribute('data-contribution-split') && !adminSitePicker;
        let activeMode = shell.dataset.contributionInitial || 'place';
        const hasStagedPhotos = () => ['photos_json', 'site_photos_json']
            .some(name => {
                const input = report.querySelector(`[name="${name}"]`);
                if (!input) return false;
                try {
                    const photos = JSON.parse(input.value || '[]');
                    return Array.isArray(photos) && photos.length > 0;
                } catch (_) {
                    return input.value.trim() !== '' && input.value.trim() !== '[]';
                }
            });
        const select = mode => {
            if (!buttons.some(button => button.dataset.contributionMode === mode && !button.disabled)) return;
            if (mode === 'area' && isNewPlace && activeMode !== 'area'
                && hasStagedPhotos()) {
                window.alert('Save or remove staged Place and Site photos before switching to Areas.');
                return;
            }
            for (const button of buttons) {
                button.setAttribute('aria-pressed', String(button.dataset.contributionMode === mode));
            }
            // A new Place cannot own Areas until it has a saved Place ID.
            const standaloneArea = mode === 'area' && (isNewPlace || !!emptyAdminAreaPanel);
            report.hidden = standaloneArea;
            if (areaPanel) areaPanel.hidden = !(mode === 'area' && isNewPlace);
            if (emptyAdminAreaPanel) emptyAdminAreaPanel.hidden = mode !== 'area';
            if (areaTools) areaTools.hidden = mode !== 'area';
            if (adminAreaSection) adminAreaSection.hidden = mode !== 'area';
            const reportHeading = report.querySelector('[data-admin-report-mode-heading]');
            if (reportHeading) reportHeading.textContent =
                mode === 'area' ? 'Edit Area Report'
                : mode === 'site' ? 'Edit Site Report' : 'Edit Place Report';
            if (shell.hasAttribute('data-contribution-split')) {
                for (const group of groups) {
                    const site = group.dataset.reportMainSection === 'site';
                    group.toggleAttribute('data-contribution-inactive',
                        mode === 'area' && Boolean(adminAreaPicker)
                            || (site ? mode !== 'site' : mode === 'site'));
                    if (site && mode === 'site') group.open = true;
                }
            }
            if (placePhotoPanel && !adminSitePicker) placePhotoPanel.hidden = mode !== 'place';
            if (firstSitePhotoPanel) firstSitePhotoPanel.hidden = mode !== 'site';
            activeMode = mode;
            shell.dispatchEvent(new CustomEvent('llama:contribution-mode', { detail: { mode } }));
        };
        for (const button of buttons) button.addEventListener('click', () => select(button.dataset.contributionMode));
        if (shell.hasAttribute('data-contribution-split')) {
            report.addEventListener('invalid', event => {
                const group = event.target.closest('[data-report-main-section]');
                if (!group?.hasAttribute('data-contribution-inactive')) return;
                select(group.dataset.reportMainSection === 'site' ? 'site' : 'place');
                for (let section = event.target.closest('details');
                     section; section = section.parentElement?.closest('details')) section.open = true;
            }, true);
        }
        adminSitePicker?.addEventListener('change', event => {
            if (event.target.value) select('site');
        });
        adminAreaPicker?.addEventListener('change', event => {
            if (event.target.value) select('area');
        });
        picker.hidden = false;
        select(shell.dataset.contributionInitial || 'place');
    }
});
