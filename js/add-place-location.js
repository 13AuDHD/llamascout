"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const button = document.querySelector("[data-locate-place]");
  const status = document.querySelector("[data-location-status]");

  if (!button) return;

  const setStatus = (message, type = "") => {
    if (!status) return;
    status.textContent = message;
    status.className = "add-place-location-status is-visible";
    if (type) status.classList.add(type);
  };

  const setField = (name, value) => {
    if (value === null || value === undefined || value === "") return;

    const field = document.querySelector(`[data-location-field="${name}"]`);

    if (!field) return;

    field.value = String(value);
    field.dispatchEvent(new Event("change", { bubbles: true }));
  };

  button.addEventListener("click", () => {
    if (!navigator.geolocation) {
      setStatus(
        "This browser does not provide device location.",
        "is-error"
      );
      return;
    }

    button.disabled = true;
    button.classList.add("is-loading");
    setStatus("Finding your current GPS position...");

    navigator.geolocation.getCurrentPosition(
      async position => {
        const latitude = Number(position.coords.latitude);
        const longitude = Number(position.coords.longitude);

        setField("latitude", latitude.toFixed(7));
        setField("longitude", longitude.toFixed(7));

        setStatus(
          "GPS found. Looking up elevation and nearby address information..."
        );

        try {
          const url =
            `/api/location-lookup.php?lat=${encodeURIComponent(latitude)}` +
            `&lng=${encodeURIComponent(longitude)}`;

          const response = await fetch(url, {
            credentials: "same-origin",
            headers: {
              Accept: "application/json"
            }
          });

          const raw = await response.text();

          let result;

          try {
            result = JSON.parse(raw);
          } catch {
            throw new Error(
              "The location service returned an unexpected response."
            );
          }

          if (!response.ok || !result.success) {
            throw new Error(
              result.message ||
              "The location details could not be resolved."
            );
          }

          const location = result.location || {};

          setField("elevation_feet", location.elevation_feet);
          setField("road", location.road);
          setField("city", location.city);
          setField("county", location.county);
          setField("state", location.state);

          setStatus(
            "Location filled in. Check the road, city, county, and state before submitting because map data can occasionally be imperfect.",
            "is-success"
          );
        } catch (error) {
          console.error(error);

          setStatus(
            "GPS coordinates were added, but the road, elevation, or locality lookup did not finish. You can fill those fields manually.",
            "is-warning"
          );
        } finally {
          button.disabled = false;
          button.classList.remove("is-loading");
        }
      },
      error => {
        button.disabled = false;
        button.classList.remove("is-loading");

        const message =
          error.code === 1
            ? "Location permission was denied. You can still enter coordinates manually."
            : error.code === 2
              ? "Your device could not determine its current location."
              : "The location request timed out. Try again or enter coordinates manually.";

        setStatus(message, "is-error");
      },
      {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 30000
      }
    );
  });
});
