document.addEventListener('DOMContentLoaded', () => {
    for (const shell of document.querySelectorAll('[data-contribution-tabs]')) {
        const picker = shell.querySelector('[data-contribution-picker]');
        const report = shell.querySelector('[data-contribution-report]');
        const resource = shell.querySelector('[data-contribution-resource]');
        if (!picker || !report || !resource) continue;

        const buttons = [...picker.querySelectorAll('[data-contribution-mode]')];
        const groups = report.querySelectorAll('[data-report-main-section], [data-contribution-place-only]');
        const select = (mode) => {
            if (!buttons.some(button => button.dataset.contributionMode === mode && !button.disabled)) return;
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

            if (mode !== 'resource') {
                shell.dispatchEvent(new CustomEvent('llama:contribution-mode', { detail: mode }));
            }
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
