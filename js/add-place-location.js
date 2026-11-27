"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const locateButton = document.querySelector("[data-locate-place]");
  const searchButton = document.querySelector("[data-search-coordinates]");
  const coordinateInput = document.querySelector("[data-coordinate-input]");
  const status = document.querySelector("[data-location-status]");
  const form = coordinateInput ? coordinateInput.closest("form") : null;
  const locationHint = status?.textContent.trim() || "";
  const lookupInProgress = "The llamas are checking the map and land records. This can take up to a minute.";
  const lookupSucceeded = "The llamas filled in what they found. Give the details a quick look before saving.";

  if (!locateButton && !searchButton && !coordinateInput) return;

  const setStatus = (message, type) => {
    if (!status) return;

    status.textContent = type === "is-error" ? `The llamas hit a snag. ${message}` : message;
    status.classList.remove("is-info", "is-warning", "is-success", "is-error");
    status.classList.add(type);
    status.classList.toggle("is-visible", Boolean(message));
  };

  const reportLookupSuccess = (result, accuracyHint = "") => {
    const elevation = result?.location?.elevation_feet;
    const elevationHint = elevation === null || elevation === undefined || elevation === ""
      ? " Elevation is unavailable; you can enter it manually."
      : "";
    const nearest = result?.nearest_services;
    const nearestWarning = nearest?.state === "unavailable" || (nearest?.state === "ready" && nearest.matched === 0);
    const nearestHint = nearest?.state === "unavailable"
      ? " Nearest Services lookup is unavailable."
      : nearest?.state === "ready" && nearest.matched === 0
        ? " No mapped services found near these coordinates. Regional data may not be imported yet."
        : nearest?.state === "ready" && nearest.filled > 0
          ? " Nearest Services: " + nearest.filled + " distances populated."
          : "";
    setStatus(lookupSucceeded + elevationHint + nearestHint + accuracyHint,
      elevationHint || nearestWarning || accuracyHint ? "is-warning" : "is-success");
  };

  const clearStatus = () => {
    setStatus(locationHint, "is-info");
  };

  const setField = (name, value) => {
    const field = document.querySelector(
      `[data-location-field="${name}"]`
    );

    if (!field) return;

    if (value === null || value === undefined) {
      field.value = "";
    } else {
      field.value = String(value);
    }

    field.dispatchEvent(
      new Event("input", {
        bubbles: true
      })
    );

    field.dispatchEvent(
      new Event("change", {
        bubbles: true
      })
    );
  };

  const locationField = name =>
    document.querySelector(
      `[data-location-field="${name}"]`
    );

  const syncVisibleCoordinatesFromStoredFields = () => {
    if (!coordinateInput) return false;

    const latitudeField =
      locationField("latitude");

    const longitudeField =
      locationField("longitude");

    if (!latitudeField || !longitudeField) {
      return false;
    }

    const latitudeRaw =
      String(latitudeField.value || "").trim();

    const longitudeRaw =
      String(longitudeField.value || "").trim();

    if (
      latitudeRaw === ""
      || longitudeRaw === ""
    ) {
      return false;
    }

    const latitude =
      Number(latitudeRaw);

    const longitude =
      Number(longitudeRaw);

    if (
      !Number.isFinite(latitude)
      || latitude < -90
      || latitude > 90
      || !Number.isFinite(longitude)
      || longitude < -180
      || longitude > 180
    ) {
      return false;
    }

    const formatted =
      `${latitude.toFixed(7)}, ${longitude.toFixed(7)}`;

    if (
      coordinateInput.value.trim()
      !== formatted
    ) {
      coordinateInput.value =
        formatted;
    }

    return true;
  };

  const normalizeMinus = value =>
    String(value || "").replace(/\u2212/g, "-");

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
        "Enter coordinates as latitude, longitude in decimal degrees, for example 37.2722978, -107.8824169."
      );
    }

    if (
      match[2].length < 5 ||
      match[4].length < 5
    ) {
      throw new Error(
        "Use at least 5 decimal places for both latitude and longitude so the Place location is accurate enough."
      );
    }

    const latitude = Number(match[1]);
    const longitude = Number(match[3]);

    if (
      !Number.isFinite(latitude) ||
      latitude < -90 ||
      latitude > 90
    ) {
      throw new Error(
        "Latitude must be between -90 and 90."
      );
    }

    if (
      !Number.isFinite(longitude) ||
      longitude < -180 ||
      longitude > 180
    ) {
      throw new Error(
        "Longitude must be between -180 and 180."
      );
    }

    return {
      empty: false,
      latitude,
      longitude,
      formatted:
        `${latitude.toFixed(7)}, ${longitude.toFixed(7)}`
    };
  };

  const syncCoordinates = parsed => {
    if (!coordinateInput) return;

    if (parsed.empty) {
      coordinateInput.value = "";

      setField(
        "latitude",
        ""
      );

      setField(
        "longitude",
        ""
      );

      return;
    }

    const latitude =
      parsed.latitude.toFixed(7);

    const longitude =
      parsed.longitude.toFixed(7);

    coordinateInput.value =
      `${latitude}, ${longitude}`;

    setField(
      "latitude",
      latitude
    );

    setField(
      "longitude",
      longitude
    );
  };

  // These are existing Place-owned questionnaire inputs, not extra Site
  // observations. Keep the native selects and their card-view adapters.
  const nearestFields = Array.from(form?.querySelectorAll(
    '[data-report-subsection="nearest_services"] select[name^="nearest_"]'
  ) || []);
  const nearestAuto = new Map();
  const nearestHints = new Map();
  let nearestPoint = "";
  let applyingNearest = false;
  const nearestFieldValue = (miles) => {
    if (miles > 20) return "Over 20 miles";
    const rounded = Math.max(1, Math.round(miles));
    return rounded + (rounded === 1 ? " mile" : " miles");
  };
  const setNearest = (field, value) => {
    applyingNearest = true;
    try {
      field.value = value;
      field.dispatchEvent(new Event("input", {bubbles:true}));
      field.dispatchEvent(new Event("change", {bubbles:true}));
    } finally { applyingNearest = false; }
  };
  const clearNearestHint = (field) => {
    nearestHints.get(field)?.remove();
    nearestHints.delete(field);
  };
  const resetNearest = () => {
    for (const field of nearestFields) {
      if (nearestAuto.has(field) && field.value === nearestAuto.get(field)) setNearest(field, "");
      nearestAuto.delete(field);
      clearNearestHint(field);
    }
    nearestPoint = "";
  };
  for (const field of nearestFields) {
    field.addEventListener("change", () => {
      if (!applyingNearest) {
        nearestAuto.delete(field);
        clearNearestHint(field);
      }
    });
  }
  const applyNearest = (payload, latitude, longitude) => {
    if (!nearestFields.length) return 0;
    const point = Number(latitude).toFixed(7) + ", " + Number(longitude).toFixed(7);
    if (coordinateInput?.value.trim() !== point) return 0;
    if (nearestPoint && nearestPoint !== point) resetNearest();
    nearestPoint = point;
    if (payload?.state !== "ready") return 0;
    let filled = 0;
    for (const field of nearestFields) {
      const result = (payload.services || []).find(item => item.key === field.name);
      const match = result?.match;
      const miles = Number(match?.distance_miles);
      const previous = nearestAuto.get(field);
      // Admin can refresh the calculated distances through the same
      // Location action, while explicit Unknown / manual values remain
      // protected in the contributor questionnaire.
      const isAdmin = form?.id === "place-report";
      if (!isAdmin && field.value !== "" && field.value !== previous) continue;
      if (!match || match.distance_miles == null || !Number.isFinite(miles) || miles < 0) {
        if (previous !== undefined) setNearest(field, "");
        nearestAuto.delete(field);
        clearNearestHint(field);
        continue;
      }
      const value = nearestFieldValue(miles);
      if (![...field.options].some(option => option.value === value && !option.disabled)) continue;
      setNearest(field, value);
      nearestAuto.set(field, value);
      clearNearestHint(field);
      const hint = document.createElement("small");
      hint.dataset.nearestAutofillHint = "";
      const distance = miles < 0.1 ? "under 0.1 mi" : miles.toFixed(1) + " mi";
      hint.textContent = match.name + " · " + distance + " straight-line"
        + (match.status === "seasonal" ? " · Seasonal" : "");
      if (match.source === "OpenStreetMap") {
        const source = document.createElement("a");
        source.href = "https://www.openstreetmap.org/copyright";
        source.textContent = "© OpenStreetMap contributors (ODbL)";
        hint.append(" · ", source);
      } else {
        hint.append(" · Llama Scout");
      }
      const wrapper = field.closest("[data-report-field]");
      wrapper?.append(hint);
      nearestHints.set(field, hint);
      filled++;
    }
    return filled;
  };
  coordinateInput?.addEventListener("input", () => {
    if (nearestPoint && coordinateInput.value.trim() !== nearestPoint) resetNearest();
  });

  const lookupLocation = async (
    latitude,
    longitude
  ) => {
    const point = Number(latitude).toFixed(7) + ", " + Number(longitude).toFixed(7);
    if (nearestPoint && nearestPoint !== point) resetNearest();
    const url =
      `/api/location-lookup.php?lat=${encodeURIComponent(latitude)}` +
      `&lng=${encodeURIComponent(longitude)}` + (nearestFields.length ? "&nearest=1" : "");

    const response = await fetch(
      url,
      {
        credentials: "same-origin",
        headers: {
          Accept: "application/json"
        }
      }
    );

    const raw =
      await response.text();

    let result;

    try {
      result =
        JSON.parse(raw);
    } catch {
      throw new Error(
        "The location service returned an unexpected response."
      );
    }

    if (
      !response.ok ||
      !result.success
    ) {
      throw new Error(
        result.message ||
        "The location details could not be resolved."
      );
    }

    const location =
      result.location || {};

    setField(
      "elevation_feet",
      location.elevation_feet
    );

    setField(
      "road",
      location.road
    );

    setField(
      "city",
      location.city
    );

    setField(
      "county",
      location.county
    );

    setField(
      "state",
      location.state
    );

    /*
     * Clear values from the previous lookup first.
     */
    setField(
      "land_type",
      ""
    );

    setField(
      "land_manager",
      ""
    );

    setField(
      "region",
      ""
    );

    /*
     * =====================================================
     * PAD-US LAND TYPE + MANAGER
     * =====================================================
     */

    const padUs =
      result.pad_us || {};

    const land =
      padUs.best_match || null;

    if (
      land &&
      land.mapped
    ) {
      if (
        land.property_type
      ) {
        setField(
          "land_type",
          land.property_type
        );
      }

      const managerRaw =
        String(
          land.manager || ""
        ).toLowerCase();

      let manager = "";

      if (
        managerRaw.includes(
          "forest service"
        )
      ) {
        manager =
          "U.S. Forest Service";

      } else if (
        managerRaw.includes(
          "bureau of land management"
        ) ||
        managerRaw.includes(
          "blm"
        )
      ) {
        manager =
          "Bureau of Land Management";

      } else if (
        managerRaw.includes(
          "national park service"
        )
      ) {
        manager =
          "National Park Service";

      } else if (
        managerRaw.includes(
          "fish and wildlife"
        )
      ) {
        manager =
          "U.S. Fish and Wildlife Service";

      } else if (
        managerRaw.includes(
          "army corps"
        )
      ) {
        manager =
          "U.S. Army Corps of Engineers";

      } else if (
        managerRaw.includes(
          "bureau of reclamation"
        )
      ) {
        manager =
          "Bureau of Reclamation";

      } else if (
        managerRaw.includes(
          "tennessee valley authority"
        )
      ) {
        manager =
          "Tennessee Valley Authority";

      } else if (
        managerRaw.includes(
          "county"
        )
      ) {
        manager =
          "County / regional government";

      } else if (
        managerRaw.includes(
          "city"
        ) ||
        managerRaw.includes(
          "municipal"
        )
      ) {
        manager =
          "City / municipal government";

      } else if (
        managerRaw.includes(
          "state"
        )
      ) {
        manager =
          "State government";

      } else if (
        managerRaw.includes(
          "tribal"
        )
      ) {
        manager =
          "Tribal government";
      }

      /*
       * Changing Land Type may rebuild Manager choices.
       * Set Manager just afterward.
       */
      if (manager) {
        window.setTimeout(
          () => {
            setField(
              "land_manager",
              manager
            );
          },
          0
        );
      }
    }

    /*
     * =====================================================
     * LOCAL MANAGEMENT AREA
     *
     * Backend decides whether this is:
     * USFS Ranger District
     * BLM Field Office
     * or nothing
     * =====================================================
     */

    const localManagementArea =
      result.local_management_area || null;

    if (
      localManagementArea &&
      localManagementArea.name
    ) {
      setField(
        "region",
        localManagementArea.name
      );

    } else {
      /*
       * Backward-compatible fallbacks.
       */
      const usfs =
        result.usfs || {};

      const ranger =
        usfs.ranger_district || null;

      const blm =
        result.blm || {};

      const fieldOffice =
        blm.field_office || null;

      if (
        ranger &&
        ranger.district
      ) {
        setField(
          "region",
          ranger.district
        );

      } else if (
        fieldOffice &&
        fieldOffice.field_office
      ) {
        setField(
          "region",
          fieldOffice.field_office
        );
      }
    }

    const filled = applyNearest(result.nearest_services, latitude, longitude);
    if (result.nearest_services?.state === "ready") {
      result.nearest_services.filled = filled;
      result.nearest_services.matched = (result.nearest_services.services || []).filter(item => item.match).length;
    }
    return result;
  };

  const resolveCoordinates =
    async parsed => {
      syncCoordinates(
        parsed
      );

      setStatus(
        lookupInProgress,
        "is-warning"
      );

      const result = await lookupLocation(
        parsed.latitude,
        parsed.longitude
      );

      reportLookupSuccess(result);
    };

  const setLookupLoading =
    loading => {
      [
        locateButton,
        searchButton
      ].forEach(button => {
        if (!button) return;

        button.disabled =
          loading;

        button.classList.toggle(
          "is-loading",
          loading
        );
      });
    };

  /*
   * The recovery/draft systems persist the canonical hidden latitude and
   * longitude fields. The combined visible coordinate input is only a UI
   * convenience. If the browser restores the hidden fields but not that
   * display field, rebuild it immediately so the form cannot appear to have
   * "lost" a location while completion still sees valid coordinates.
   */
  syncVisibleCoordinatesFromStoredFields();

  window.setTimeout(
    syncVisibleCoordinatesFromStoredFields,
    0
  );

  window.addEventListener(
    "pageshow",
    () => {
      syncVisibleCoordinatesFromStoredFields();
    }
  );

  [
    locationField("latitude"),
    locationField("longitude")
  ]
    .filter(Boolean)
    .forEach(field => {
      field.addEventListener(
        "change",
        () => {
          syncVisibleCoordinatesFromStoredFields();
        }
      );
    });

  if (
    searchButton &&
    coordinateInput
  ) {
    searchButton.addEventListener(
      "click",
      async () => {
        try {
          const parsed =
            parseCoordinates(
              coordinateInput.value
            );

          if (
            parsed.empty
          ) {
            throw new Error(
              "Enter coordinates before searching."
            );
          }

          setLookupLoading(
            true
          );

          await resolveCoordinates(
            parsed
          );

        } catch (error) {
          console.error(
            error
          );

          setStatus(
            error instanceof Error
              ? error.message
              : "The coordinates could not be searched.",
            "is-error"
          );

        } finally {
          setLookupLoading(
            false
          );
        }
      }
    );
  }

  if (coordinateInput) {
    coordinateInput.addEventListener(
      "blur",
      () => {
        try {
          const parsed =
            parseCoordinates(
              coordinateInput.value
            );

          syncCoordinates(
            parsed
          );

          if (
            parsed.empty
          ) {
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
      }
    );

    coordinateInput.addEventListener(
      "input",
      () => {
        if (
          !coordinateInput.value.trim()
        ) {
          setField(
            "latitude",
            ""
          );

          setField(
            "longitude",
            ""
          );
        }
      }
    );
  }

  if (
    form &&
    coordinateInput
  ) {
    form.addEventListener(
      "submit",
      event => {
        const submitter =
          event.submitter;

        if (
          submitter &&
          submitter.name ===
            "save_for_later"
        ) {
          return;
        }

        try {
          const parsed =
            parseCoordinates(
              coordinateInput.value
            );

          syncCoordinates(
            parsed
          );

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
      }
    );
  }

  if (locateButton) {
    // Keep the same native geolocation path in full and guided forms.
    // A GPS-only attempt can time out indoors or on Android; let the
    // browser fall back to its normal location provider before giving up.
    const getPosition = options => new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(resolve, reject, options);
    });

    locateButton.addEventListener("click", async () => {
      if (!navigator.geolocation) {
        setStatus(
          "This browser does not provide device location.",
          "is-error"
        );
        return;
      }

      setLookupLoading(true);
      setStatus(
        "The llamas are waiting for your device’s GPS.",
        "is-warning"
      );

      try {
        let position;
        try {
          position = await getPosition({
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 30000
          });
        } catch (error) {
          // Permission denial must not prompt again or be bypassed.
          if (error?.code !== 2 && error?.code !== 3) throw error;
          setStatus(
            "GPS is taking longer than expected. Trying your device’s standard location service.",
            "is-warning"
          );
          position = await getPosition({
            enableHighAccuracy: false,
            timeout: 20000,
            maximumAge: 30000
          });
        }

        const latitude = Number(position.coords.latitude);
        const longitude = Number(position.coords.longitude);
        if (!Number.isFinite(latitude) || latitude < -90 || latitude > 90
          || !Number.isFinite(longitude) || longitude < -180 || longitude > 180) {
          throw new Error("Your device returned invalid coordinates. Enter coordinates manually.");
        }

        const parsed = {empty: false, latitude, longitude};
        syncCoordinates(parsed);

        // Standard location can be coarse. Save what the browser provided,
        // but do not imply that decimal precision guarantees GPS accuracy.
        const accuracy = Number(position.coords.accuracy);
        const accuracyHint = Number.isFinite(accuracy) && accuracy > 100
          ? ` Device reports accuracy of about ${Math.round(accuracy)} m; verify the coordinates before saving.`
          : "";

        setStatus(`Location found.${accuracyHint} ${lookupInProgress}`, "is-warning");
        try {
          const result = await lookupLocation(latitude, longitude);
          reportLookupSuccess(result, accuracyHint);
        } catch (error) {
          console.error(error);
          setStatus(
            "The llamas added your coordinates. Fill in any details they couldn’t find." + accuracyHint,
            "is-warning"
          );
        }
      } catch (error) {
        const message = error?.code === 1
          ? "Location permission was denied. Allow location access for this site in your browser and device settings, or enter coordinates manually."
          : error?.code === 2
            ? "Your device could not determine its location. Check that device location is turned on, or enter coordinates manually."
            : error?.code === 3
              ? "The location request timed out. Try again with a clearer view of the sky, or enter coordinates manually."
              : error instanceof Error
                ? error.message
                : "Device location could not be obtained. Enter coordinates manually.";
        setStatus(message, "is-error");
      } finally {
        setLookupLoading(false);
      }
    });
  }
});
