(function () {
    "use strict";

    const grid =
        document.querySelector(
            ".admin-system-health-panel .admin-health-grid"
        );

    const summary =
        document.querySelector(
            ".admin-system-health-panel .admin-health-summary"
        );

    if (!grid || !summary) {
        return;
    }

    if (
        grid.querySelector(
            '[data-health-key="printful_webhook"]'
        )
    ) {
        return;
    }

    const escapeHtml = (value) =>
        String(value ?? "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");

    const statusText = {
        good: "Healthy",
        attention: "Needs attention",
        down: "Problem",
    };

    const summaryLabel = {
        good: "Healthy",
        attention: "Need attention",
        down: "Problem",
    };

    function updateSummary(status) {
        const target =
            summary.querySelector(
                ".is-" + status
            );

        if (!target) {
            return;
        }

        const match =
            target.textContent.match(
                /\d[\d,]*/
            );

        const current =
            match
                ? Number.parseInt(
                    match[0].replaceAll(",", ""),
                    10
                )
                : 0;

        const next =
            Number.isFinite(current)
                ? current + 1
                : 1;

        target.innerHTML =
            '<i aria-hidden="true"></i> '
            + next.toLocaleString()
            + " "
            + summaryLabel[status];
    }

    fetch(
        "/system-health-printful.php",
        {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                "Accept": "application/json",
            },
        }
    )
        .then((response) => {
            if (!response.ok) {
                throw new Error(
                    "Printful health request failed."
                );
            }

            return response.json();
        })
        .then((payload) => {
            const card =
                payload
                && typeof payload === "object"
                ? payload.card
                : null;

            if (!card) {
                return;
            }

            const allowedStatuses =
                ["good", "attention", "down"];

            const status =
                allowedStatuses.includes(
                    String(card.status)
                )
                    ? String(card.status)
                    : "attention";

            const article =
                document.createElement(
                    "article"
                );

            article.className =
                "admin-health-card is-"
                + status;

            article.dataset.healthKey =
                "printful_webhook";

            article.innerHTML =
                '<span class="admin-health-light" aria-hidden="true"></span>'
                + '<div class="admin-health-card-icon">'
                + '<i class="fa-solid '
                + escapeHtml(
                    card.icon
                    || "fa-shield-halved"
                )
                + '" aria-hidden="true"></i>'
                + "</div>"
                + '<div class="admin-health-card-copy">'
                + "<span>"
                + escapeHtml(
                    card.label
                    || "Printful webhook"
                )
                + "</span>"
                + "<strong>"
                + escapeHtml(
                    card.value
                    || "Unknown"
                )
                + "</strong>"
                + "<small>"
                + escapeHtml(
                    card.detail
                    || "No status detail is available."
                )
                + "</small>"
                + "</div>"
                + '<span class="admin-health-status-text">'
                + statusText[status]
                + "</span>";

            grid.appendChild(
                article
            );

            updateSummary(
                status
            );
        })
        .catch(() => {
            /*
             * Do not break System Health if this optional
             * integration check itself cannot be rendered.
             */
        });
})();
