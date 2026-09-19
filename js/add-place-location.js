"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const locateButton = document.querySelector("[data-locate-place]");
  const searchButton = document.querySelector("[data-search-coordinates]");
  const coordinateInput = document.querySelector("[data-coordinate-input]");
  const status = document.querySelector("[data-location-status]");
  const form = coordinateInput ? coordinateInput.closest("form") : null;

  if (!locateButton && !searchButton && !coordinateInput) return;

  const setStatus = (message, type = "") => {
    if (!status) return;

    status.textContent = message;
    status.className = "add-place-location-status is-visible";

    if (type) status.classList.add(type);
  };

  const clearStatus = () => {
    if (!status) return;

    status.textContent = "";
    status.className = "add-place-location-status";
  };

  const setField = (name, value) => {
    const field = document.querySelector(`[data-location-field="${name}"]`);

    if (!field) return;

    if (value === null || value === undefined) {
      field.value = "";
    } else {
      field.value = String(value);
    }

    field.dispatchEvent(new Event("input", { bubbles: true }));
    field.dispatchEvent(new Event("change", { bubbles: true }));
  };

  const normalizeMinus = value => String(value || "").replace(/\u2212/g, "-");

  const parseCoordinates = rawValue => {
    const raw = normalizeMinus(rawValue).trim();

    if (!raw) {
      return {
        empty: true,
        latitude: null,
        longitude: null,
        formatted: ""
      };
    }

    const match = raw.match(
      /^([+-]?\d{1,2}\.(\d+))\s*(?:,\s*|\s+)([+-]?\d{1,3}\.(\d+))$/
    );

    if (!match) {
      throw new Error(
        "Enter coordinates as latitude, longitude in decimal degrees, for example 37.2522200, -107.2192000."
      );
    }

    if (match[2].length < 5 || match[4].length < 5) {
      throw new Error(
        "Use at least 5 decimal places for both latitude and longitude so the Place location is accurate enough."
      );
    }

    const latitude = Number(match[1]);
    const longitude = Number(match[3]);

    if (!Number.isFinite(latitude) || latitude < -90 || latitude > 90) {
      throw new Error("Latitude must be between -90 and 90.");
    }

    if (!Number.isFinite(longitude) || longitude < -180 || longitude > 180) {
      throw new Error("Longitude must be between -180 and 180.");
    }

    return {
      empty: false,
      latitude,
      longitude,
      formatted: `${latitude.toFixed(7)}, ${longitude.toFixed(7)}`
    };
  };

  const syncCoordinates = parsed => {
    if (!coordinateInput) return;

    if (parsed.empty) {
      coordinateInput.value = "";
      setField("latitude", "");
      setField("longitude", "");
      return;
    }

    const latitude = parsed.latitude.toFixed(7);
    const longitude = parsed.longitude.toFixed(7);

    coordinateInput.value = `${latitude}, ${longitude}`;
    setField("latitude", latitude);
    setField("longitude", longitude);
  };

  const lookupLocation = async (latitude, longitude) => {
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
      throw new Error("The location service returned an unexpected response.");
    }

    if (!response.ok || !result.success) {
      throw new Error(
        result.message || "The location details could not be resolved."
      );
    }

    const location = result.location || {};

    setField("elevation_feet", location.elevation_feet);
    setField("road", location.road);
    setField("city", location.city);
    setField("county", location.county);
    setField("state", location.state);
  };

  const resolveCoordinates = async parsed => {
    syncCoordinates(parsed);

    setStatus(
      "Coordinates accepted. Looking up elevation and nearby address information..."
    );

    await lookupLocation(parsed.latitude, parsed.longitude);

    setStatus(
      "Location filled in. Check the road, city, county, and state before submitting because map data can occasionally be imperfect.",
      "is-success"
    );
  };

  const setLookupLoading = loading => {
    [locateButton, searchButton].forEach(button => {
      if (!button) return;
      button.disabled = loading;
      button.classList.toggle("is-loading", loading);
    });
  };

  if (searchButton && coordinateInput) {
    searchButton.addEventListener("click", async () => {
      try {
        const parsed = parseCoordinates(coordinateInput.value);

        if (parsed.empty) {
          throw new Error("Enter coordinates before searching.");
        }

        setLookupLoading(true);
        await resolveCoordinates(parsed);
      } catch (error) {
        console.error(error);
        setStatus(
          error instanceof Error
            ? error.message
            : "The coordinates could not be searched.",
          "is-error"
        );
      } finally {
        setLookupLoading(false);
      }
    });
  }

  if (coordinateInput) {
    coordinateInput.addEventListener("blur", () => {
      try {
        const parsed = parseCoordinates(coordinateInput.value);
        syncCoordinates(parsed);

        if (parsed.empty) {
          clearStatus();
        }
      } catch (error) {
        setStatus(
          error instanceof Error
            ? error.message
            : "Check the coordinate format.",
          "is-error"
        );
      }
    });

    coordinateInput.addEventListener("input", () => {
      if (!coordinateInput.value.trim()) {
        setField("latitude", "");
        setField("longitude", "");
      }
    });
  }

  if (form && coordinateInput) {
    form.addEventListener("submit", event => {
      const submitter = event.submitter;

      if (submitter && submitter.name === "save_for_later") {
        return;
      }

      try {
        const parsed = parseCoordinates(coordinateInput.value);
        syncCoordinates(parsed);
      } catch (error) {
        event.preventDefault();
        coordinateInput.focus();
        setStatus(
          error instanceof Error
            ? error.message
            : "Check the coordinate format before submitting.",
          "is-error"
        );
      }
    });
  }

  if (locateButton) {
    locateButton.addEventListener("click", () => {
      if (!navigator.geolocation) {
        setStatus(
          "This browser does not provide device location.",
          "is-error"
        );
        return;
      }

      setLookupLoading(true);
      setStatus("Finding your current GPS position...");

      navigator.geolocation.getCurrentPosition(
        async position => {
          const latitude = Number(position.coords.latitude);
          const longitude = Number(position.coords.longitude);

          const parsed = {
            empty: false,
            latitude,
            longitude,
            formatted: `${latitude.toFixed(7)}, ${longitude.toFixed(7)}`
          };

          try {
            syncCoordinates(parsed);

            setStatus(
              "GPS found. Looking up elevation and nearby address information..."
            );

            await lookupLocation(latitude, longitude);

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
            setLookupLoading(false);
          }
        },
        error => {
          setLookupLoading(false);

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
  }
});
