"use strict";

// The first Site's coordinates are independent of the Place's location fields.
document.addEventListener("DOMContentLoaded", () => {
    const panel = document.querySelector("[data-site-location-section]");
    if (!panel) return;
    const input = panel.querySelector("[data-site-coordinate-input]");
    const locate = panel.querySelector("[data-site-locate]");
    const search = panel.querySelector("[data-site-coordinate-search]");
    const status = panel.querySelector("[data-site-location-status]");
    const originalHint = status.textContent.trim();
    const form = input.closest("form");

    const show = (message, kind = "info") => {
        status.textContent = message;
        status.classList.remove("is-info", "is-success", "is-warning", "is-error");
        status.classList.add("is-" + kind);
    };
    const parse = raw => {
        const values = raw.trim().split(",").map(v => v.trim());
        if (values.length !== 2 || values.some(v => !/^[+-]?(?:\d+\.?\d*|\.\d+)$/.test(v))) {
            throw new Error("Enter Site coordinates as latitude, longitude.");
        }
        const [lat, lng] = values.map(Number);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)
            || Math.abs(lat) > 90 || Math.abs(lng) > 180
            || (lat === 0 && lng === 0)) {
            throw new Error("Enter a valid Site latitude and longitude.");
        }
        return {lat, lng, text: lat.toFixed(7) + ", " + lng.toFixed(7)};
    };
    const sync = text => {
        input.value = text;
        input.setCustomValidity("");
        input.dispatchEvent(new Event("input", {bubbles: true}));
        input.dispatchEvent(new Event("change", {bubbles: true}));
    };
    const validate = () => {
        if (!input.value.trim()) return null; // Site coordinates are optional.
        try {
            const result = parse(input.value);
            input.setCustomValidity("");
            return result;
        } catch (error) {
            input.setCustomValidity(error.message);
            show(error.message, "error");
            return false;
        }
    };
    input.addEventListener("input", () => {
        input.setCustomValidity("");
        show(originalHint);
    });
    form?.addEventListener("submit", event => {
        const result = validate();
        if (result !== false) return;
        event.preventDefault();
        panel.open = true;
        input.reportValidity();
        input.focus();
    });

    search?.addEventListener("click", async () => {
        const result = validate();
        if (result === false) {
            panel.open = true;
            input.reportValidity();
            return;
        }
        if (!result) {
            show("Enter the Site coordinates before searching.", "warning");
            return;
        }
        sync(result.text);
        search.disabled = true;
        show("Checking the Site's location…");
        try {
            const response = await fetch(
                "/api/location-lookup.php?lat=" + encodeURIComponent(result.lat)
                + "&lng=" + encodeURIComponent(result.lng),
                {headers: {Accept: "application/json"}, credentials: "same-origin"}
            );
            const details = await response.json();
            if (input.value !== result.text) {
                show("Site coordinates changed. Tap Search again.", "info");
                return;
            }
            if (!response.ok || details?.success === false) throw new Error("Lookup unavailable.");
            const location = details.location || details.address || {};
            const region = [location.city, location.state_name || location.state]
                .filter(v => typeof v === "string" && v.trim()).join(", ");
            show(region
                ? "Site coordinates confirmed near " + region + ". The Parent Place location is unchanged."
                : "Site coordinates recorded. The Parent Place location is unchanged.", "success");
        } catch (_) {
            show("Coordinates are valid, but the location lookup is unavailable. You can still submit this Site.", "warning");
        } finally {
            search.disabled = false;
        }
    });

    locate?.addEventListener("click", () => {
        if (!navigator.geolocation) {
            show("Device location is not available. Enter the Site coordinates manually.", "warning");
            return;
        }
        locate.disabled = true;
        show("Finding your Site location…");
        navigator.geolocation.getCurrentPosition(
            ({coords}) => {
                sync(parse(coords.latitude.toFixed(7) + ", " + coords.longitude.toFixed(7)).text);
                show("Site GPS recorded. The Parent Place coordinates were not changed.", "success");
                locate.disabled = false;
            },
            () => {
                show("Site location was unavailable. You can enter the coordinates manually.", "error");
                locate.disabled = false;
            },
            {enableHighAccuracy: true, timeout: 15000, maximumAge: 0}
        );
    });
});
