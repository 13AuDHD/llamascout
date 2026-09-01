"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const input = document.querySelector("[data-place-name]");
  const button = document.querySelector("[data-refresh-place-name]");

  if (!input || !button) return;

  let requestNumber = 0;

  const getSuggestion = async (force = false) => {
    if (!force && input.value.trim() !== "") return;

    const thisRequest = ++requestNumber;

    button.disabled = true;
    button.classList.add("is-loading");

    try {
      const response = await fetch("/api/place-name-suggestion.php", {
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
        throw new Error("The name service returned an unexpected response.");
      }

      if (
        !response.ok ||
        !result.success ||
        typeof result.name !== "string" ||
        result.name.trim() === ""
      ) {
        throw new Error(
          result.message ||
          "A suggested name could not be generated."
        );
      }

      if (thisRequest !== requestNumber) return;

      input.value = result.name.trim();
      input.dispatchEvent(
        new Event("change", {
          bubbles: true
        })
      );
    } catch (error) {
      console.error("Place name suggestion:", error);

      if (!input.value.trim()) {
        input.placeholder = "Enter a simple location-neutral name";
      }
    } finally {
      if (thisRequest === requestNumber) {
        button.disabled = false;
        button.classList.remove("is-loading");
      }
    }
  };

  button.addEventListener("click", () => {
    getSuggestion(true);
  });

  getSuggestion(false);
});
