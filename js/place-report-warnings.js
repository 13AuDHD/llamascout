(() => {
    'use strict';

    const section =
        document.querySelector(
            '[data-report-warnings]'
        );

    if (!section) {
        return;
    }

    const summary =
        section.querySelector(
            '[data-report-warning-summary]'
        );

    const grid =
        section.querySelector(
            '[data-report-warning-grid]'
        );

    if (!summary || !grid) {
        return;
    }

    const baseline = {
        hidden:
            section.hidden,
        summary:
            summary.textContent,
        html:
            grid.innerHTML,
    };

    const warningList =
        (warnings) => {
            if (
                !warnings
                || typeof warnings !== 'object'
            ) {
                return [];
            }

            return Array.isArray(warnings)
                ? warnings
                : Object.values(warnings);
        };

    const safeIcon =
        (value) => {
            const icon =
                String(
                    value
                    || ''
                ).trim();

            return /^[a-z0-9-]+$/.test(icon)
                ? icon
                : 'alert-triangle';
        };

    const render =
        (
            warnings,
            target = 'Place'
        ) => {
            const items =
                warningList(warnings)
                    .filter(
                        (warning) =>
                            warning
                            && typeof warning
                                === 'object'
                    );

            grid.replaceChildren();

            if (!items.length) {
                section.hidden =
                    true;

                return;
            }

            for (const warning of items) {
                const item =
                    document.createElement(
                        'div'
                    );

                item.className =
                    'scout-report-item scout-report-value-item scout-report-warning-item';

                const content =
                    document.createElement(
                        'div'
                    );

                content.className =
                    'scout-report-value-content';

                const label =
                    document.createElement(
                        'span'
                    );

                label.textContent =
                    String(
                        warning.label
                        || 'Warning'
                    );

                const status =
                    document.createElement(
                        'strong'
                    );

                status.textContent =
                    'Warning';

                content.append(
                    label,
                    status
                );

                // A CSS mask uses the icon's silhouette, rather than the
                // source SVG's fixed paint color. The same icon is visible
                // against both light and dark backgrounds.
                const icon =
                    document.createElement('span');

                const iconName = safeIcon(warning.icon);

                icon.className =
                    'scout-report-value-icon scout-report-warning-icon';

                icon.style.setProperty(
                    '--scout-warning-icon-image',
                    `url("/assets/icons/${iconName}.svg")`
                );

                icon.setAttribute('aria-hidden', 'true');

                // Preserve the old missing-icon fallback. A failed asset
                // probe replaces only the mask, not the text or warning.
                const probe = document.createElement('img');
                probe.addEventListener('error', () => {
                    icon.style.setProperty(
                        '--scout-warning-icon-image',
                        'url("/assets/icons/alert-triangle.svg")'
                    );
                }, {once: true});
                probe.src = `/assets/icons/${iconName}.svg`;

                item.append(
                    content,
                    icon
                );

                grid.appendChild(
                    item
                );
            }

            const cleanTarget =
                String(
                    target
                    || 'Place'
                ).trim()
                || 'Place';

            summary.textContent =
                `Important conditions reported for this ${cleanTarget}.`;

            section.hidden =
                false;
        };

    const restore = () => {
        summary.textContent =
            baseline.summary;

        grid.innerHTML =
            baseline.html;

        section.hidden =
            baseline.hidden;
    };

    const applyState =
        (state) => {
            if (
                !state
                || typeof state !== 'object'
            ) {
                return;
            }

            if (state.restore) {
                restore();
                return;
            }

            render(
                state.warnings,
                state.target
            );
        };

    window.LlamaScoutReportWarnings = {
        render,
        restore,
    };

    window.addEventListener(
        'llama:report-warnings-change',
        (event) =>
            applyState(
                event.detail
            )
    );

    applyState(
        window.LlamaScoutReportWarningsState
    );
})();
