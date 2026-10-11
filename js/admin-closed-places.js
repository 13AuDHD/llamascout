(() => {
    'use strict';

    const form =
        document.getElementById(
            'closure-form'
        );

    if (!form) {
        return;
    }

    const editor =
        document.getElementById(
            'closure-editor-title'
        );

    const escapeHtml = value =>
        String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

    const label = value => {
        const normalized =
            String(value || '')
                .replaceAll('_', ' ')
                .trim();

        if (!normalized) {
            return 'Unknown';
        }

        return normalized
            .replace(
                /\b\w/g,
                match =>
                    match.toUpperCase()
            );
    };

    const statusLabel = value => ({
        open: 'Open',
        temporary: 'Temporarily closed',
        seasonal: 'Seasonally closed',
        permanent: 'Permanently closed'
    })[String(value || '')] || 'Unknown';

    const populateEditor = button => {
        const mapping = {
            place_id: 'placeId',
            campsite_id: 'campsiteId',
            status: 'status',
            reason: 'reason',
            closure_began: 'began',
            expected_reopening: 'reopening',
            review_date: 'review',
            source_url: 'source',
            notes: 'notes'
        };

        for (
            const [name, attr]
            of Object.entries(mapping)
        ) {
            const control =
                form.elements.namedItem(name);

            if (!control) {
                continue;
            }

            const value =
                button.dataset[attr]
                || '';

            control.value =
                name === 'campsite_id'
                && value === '0'
                    ? ''
                    : value;
        }

        editor?.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

        form.elements
            .namedItem('status')
            ?.focus();
    };

    document.addEventListener(
        'click',
        event => {
            const button =
                event.target.closest(
                    '[data-closure-edit]'
                );

            if (!button) {
                return;
            }

            populateEditor(button);
        }
    );

    const fact = (
        title,
        value
    ) => `
        <div>
            <span>${escapeHtml(title)}</span>
            <strong>${escapeHtml(value || 'Not known')}</strong>
        </div>
    `;

    const renderPending = items => {
        const verifiedPanel =
            document.querySelector(
                '.closure-review-panel'
            );

        if (!verifiedPanel) {
            return;
        }

        const existing =
            document.querySelector(
                '[data-reported-closure-panel]'
            );

        if (existing) {
            existing.remove();
        }

        const section =
            document.createElement(
                'section'
            );

        section.className =
            'admin-panel closure-review-panel';

        section.dataset.reportedClosurePanel =
            '1';

        const count =
            items.length;

        const cards =
            items.map(item => {
                const reported =
                    String(
                        item.reported_status
                        || ''
                    );

                const verified =
                    String(
                        item.verified_status
                        || ''
                    );

                const isReopening =
                    reported === 'open'
                    && verified
                    && verified !== 'open';

                const stateText =
                    isReopening
                        ? 'Possible reopening'
                        : 'Closure report';

                const notes =
                    String(
                        item.reported_notes
                        || ''
                    ).trim();

                return `
                    <article class="closure-review-card is-upcoming">
                        <div class="closure-review-main">
                            <div class="closure-review-title-row">
                                <div>
                                    <div class="closure-review-pills">
                                        <span class="closure-status-pill">
                                            ${escapeHtml(stateText)}
                                        </span>
                                        <span class="closure-review-pill is-upcoming">
                                            Reported: ${escapeHtml(statusLabel(reported))}
                                        </span>
                                        ${
                                            verified
                                                ? `<span class="closure-review-pill">Verified: ${escapeHtml(statusLabel(verified))}</span>`
                                                : '<span class="closure-review-pill">Not yet verified</span>'
                                        }
                                    </div>

                                    <h3>
                                        ${escapeHtml(item.place_name || 'Place')}
                                    </h3>

                                    <p>
                                        ${escapeHtml(item.state || '')}
                                        ${
                                            reported === 'open'
                                                ? ' Â· Reported reopened'
                                                : ` Â· ${escapeHtml(label(item.reported_reason || 'unknown'))}`
                                        }
                                    </p>
                                </div>

                                <div class="closure-review-actions">
                                    <button
                                        type="button"
                                        class="admin-button"
                                        data-closure-edit
                                        data-place-id="${escapeHtml(item.place_id || '')}"
                                        data-campsite-id="0"
                                        data-status="${escapeHtml(reported)}"
                                        data-reason="${escapeHtml(reported === 'open' ? 'unknown' : (item.reported_reason || 'unknown'))}"
                                        data-began="${escapeHtml(reported === 'open' ? '' : (item.reported_began || ''))}"
                                        data-reopening="${escapeHtml(reported === 'open' ? '' : (item.reported_reopening || ''))}"
                                        data-review="${escapeHtml(reported === 'open' ? '' : (item.reported_review_date || ''))}"
                                        data-source="${escapeHtml(item.reported_source_url || '')}"
                                        data-notes="${escapeHtml(notes)}"
                                    >
                                        Load for verification
                                    </button>

                                    <a
                                        class="admin-button is-muted"
                                        href="/place.php?id=${encodeURIComponent(String(item.place_id || ''))}"
                                    >
                                        View Place
                                    </a>
                                </div>
                            </div>

                            <div class="closure-review-facts">
                                ${fact(
                                    reported === 'open'
                                        ? 'Current verified status'
                                        : 'Closure began',
                                    reported === 'open'
                                        ? statusLabel(verified)
                                        : item.reported_began
                                )}
                                ${fact(
                                    'Expected reopening',
                                    item.reported_reopening
                                )}
                                ${fact(
                                    'Suggested check-back',
                                    item.reported_review_date
                                )}
                            </div>
                        </div>
                    </article>
                `;
            }).join('');

        section.innerHTML = `
            <header class="admin-panel-header closure-review-header">
                <div>
                    <p>Place Report observations</p>
                    <h2>Reported statuses awaiting verification</h2>
                </div>

                <span class="closure-count">
                    ${count.toLocaleString()}
                    ${count === 1 ? 'report' : 'reports'}
                </span>
            </header>

            <div class="admin-panel-body closure-review-body">
                ${
                    count
                        ? `<div class="closure-review-list">${cards}</div>`
                        : `
                            <div class="admin-empty-state closure-empty">
                                <h3>No status reports awaiting verification</h3>
                                <p>
                                    Closure and reopening observations from the
                                    Place Report will appear here when they differ
                                    from the verified public operating status.
                                </p>
                            </div>
                        `
                }
            </div>
        `;

        verifiedPanel.parentNode.insertBefore(
            section,
            verifiedPanel
        );
    };

    const loadPendingReports =
        async () => {
            try {
                const response =
                    await fetch(
                        '/closure-reports.php',
                        {
                            credentials:
                                'same-origin',
                            headers: {
                                Accept:
                                    'application/json'
                            }
                        }
                    );

                const payload =
                    await response
                        .json()
                        .catch(() => ({}));

                if (
                    !response.ok
                    || payload.ok !== true
                ) {
                    return;
                }

                renderPending(
                    Array.isArray(payload.items)
                        ? payload.items
                        : []
                );
            } catch (_) {
                /*
                 * Verified closure management remains usable if the
                 * supplemental report feed is temporarily unavailable.
                 */
            }
        };

    loadPendingReports();
})();
