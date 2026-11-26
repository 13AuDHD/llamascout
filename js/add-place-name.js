"use strict";

// Site-only suggestions; never auto-fill or overwrite an actual Place name.
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("add-site-main-form");
  const input = form?.querySelector("[data-site-name]");
  const button = form?.querySelector("[data-refresh-site-name]");
  const parent = form?.querySelector('[name="site_place_id"]');
  const status = form?.querySelector("[data-site-name-status]");
  if (!input || !button || !parent || !status) return;
  let request = 0;
  input.addEventListener("input", () => { ++request; button.disabled = false; button.classList.remove("is-loading"); status.textContent = ""; });
  button.addEventListener("click", async () => {
    const placeId = parent.value;
    if (!placeId) { status.textContent = "Select the Parent Place first."; return; }
    const original = input.value;
    const seq = ++request;
    button.disabled = true;
    button.classList.add("is-loading");
    status.textContent = "Looking for an available Site name…";
    try {
      const url = new URL("/api/place-name-suggestion.php", window.location.origin);
      url.searchParams.set("site_place_id", placeId);
      if (original.trim()) url.searchParams.set("exclude", original.trim());
      const response = await fetch(url, {credentials:"same-origin", cache:"no-store", headers:{Accept:"application/json"}});
      const result = await response.json();
      if (seq !== request) return;
      if (parent.value !== placeId || input.value !== original) { status.textContent = ""; return; }
      if (!response.ok || !result.success || typeof result.name !== "string" || !result.name.trim()) {
        throw new Error(result.message || "A Site name could not be suggested.");
      }
      input.value = result.name.trim();
      input.dispatchEvent(new Event("change", {bubbles:true}));
      status.textContent = "";
    } catch (error) {
      if (seq === request) status.textContent = error.message || "Site name suggestions are unavailable.";
    } finally {
      if (seq === request) { button.disabled = false; button.classList.remove("is-loading"); }
    }
  });
});
