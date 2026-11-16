"use strict";

/**
 * Administration owns the Site selector. Site GPS is stored on the selected
 * place_campsites record, never on the parent Place and never in Scout answers.
 * Mapped Sites must be moved with Edit Map to keep the linked pin in sync.
 */
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("place-report");
    const panel = form?.querySelector("[data-site-location-section]");
    const selection = form?.querySelector("[data-admin-campsite-selector]");
    const catalogueNode = form?.querySelector("[data-admin-place-campsites]");
    if (!form || !panel || !selection || !catalogueNode) return;

    let catalogue;
    try {
        catalogue = JSON.parse(catalogueNode.textContent || "{}");
    } catch (_) {
        return;
    }
    const sites = Array.isArray(catalogue.sites) ? catalogue.sites : [];
    const values = new Map(sites.map(site => [String(site.id || ""), {
        latitude: site.latitude,
        longitude: site.longitude,
        map_feature_id: site.map_feature_id
    }]));
    const input = panel.querySelector("[data-site-coordinate-input]");
    const locate = panel.querySelector("[data-site-locate]");
    const search = panel.querySelector("[data-site-coordinate-search]");
    const touched = panel.querySelector("[data-admin-site-coordinates-changed]");
    const note = panel.querySelector("[data-admin-site-location-note]");
    const status = panel.querySelector("[data-site-location-status]");
    const editMapLink = document.querySelector("[data-admin-place-map-edit]");
    if (!input || !locate || !search || !touched || !note || !status) return;

    const selected = () => values.get(String(selection.value || "")) || null;
    const showNote = (message, withMapLink = false) => {
        note.textContent = message;
        if (withMapLink && editMapLink?.href) {
            const link = document.createElement("a");
            link.href = editMapLink.href;
            link.textContent = " Edit Map";
            link.target = "_blank";
            link.rel = "noopener";
            note.append(link);
        }
    };
    const coordsText = entry => {
        if (!entry || entry.latitude === null || entry.longitude === null
            || entry.latitude === "" || entry.longitude === "") return "";
        const latitude = Number(entry.latitude);
        const longitude = Number(entry.longitude);
        return Number.isFinite(latitude) && Number.isFinite(longitude)
            ? latitude.toFixed(7) + ", " + longitude.toFixed(7) : "";
    };
    const clearValidity = () => input.setCustomValidity("");
    const sync = () => {
        const site = selected();
        const mapped = site && Number(site.map_feature_id || 0) > 0;
        const canEdit = Boolean(site) && !mapped;
        input.disabled = !canEdit;
        locate.disabled = !canEdit;
        search.disabled = !canEdit;
        input.value = coordsText(site);
        touched.value = "0";
        clearValidity();
        status.classList.remove("is-error", "is-warning", "is-success");
        status.classList.add("is-info");
        status.textContent = !site
            ? "Choose an individual Site to view its location. Bulk Site coordinates cannot be edited."
            : mapped
                ? "This Site is tied to a map pin. Edit its position on the map to keep the two synchronized."
                : "Use Locate me or enter coordinates for this Site. The Parent Place location will not change.";
        showNote(!site
            ? "Choose a Site above."
            : mapped
                ? "Mapped Site coordinates are managed in Edit Map."
                : "Coordinates save to this Site only. Leave blank if its position is unknown.",
            Boolean(mapped));
    };
    const parsedValue = () => {
        const raw = input.value.trim();
        if (!raw) return "";
        const parts = raw.split(",").map(part => part.trim());
        if (parts.length !== 2 || parts.some(part =>
            !/^[+-]?(?:\d+\.?\d*|\.\d+)$/.test(part))) return null;
        const [latitude, longitude] = parts.map(Number);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)
            || Math.abs(latitude) > 90 || Math.abs(longitude) > 180
            || (latitude === 0 && longitude === 0)) return null;
        return latitude.toFixed(7) + ", " + longitude.toFixed(7);
    };

    form.addEventListener("llama:campsite-selected", sync);
    input.addEventListener("input", () => {
        if (!selected() || input.disabled) return;
        touched.value = "1";
        form.dispatchEvent(new CustomEvent("llama:admin-site-location-dirty"));
    });
    form.addEventListener("llama:admin-place-report-before-save", event => {
        if (touched.value !== "1") return;
        if (!selected() || input.disabled) {
            event.preventDefault();
            showNote("Select an unmapped Site before changing its location.");
            return;
        }
        const normalized = parsedValue();
        if (normalized === null) {
            event.preventDefault();
            panel.open = true;
            input.setCustomValidity("Enter Site coordinates as latitude, longitude.");
            input.reportValidity();
            input.focus();
            return;
        }
        clearValidity();
    });
    form.addEventListener("llama:campsite-saved", () => {
        if (touched.value !== "1" || !selected()) return;
        const text = parsedValue();
        if (text === null) return;
        const parts = text ? text.split(",").map(Number) : [];
        values.set(String(selection.value), {
            ...selected(),
            latitude: parts[0] ?? null,
            longitude: parts[1] ?? null
        });
        touched.value = "0";
        showNote("Site coordinates saved. The Parent Place location was not changed.");
    });
    sync();
});