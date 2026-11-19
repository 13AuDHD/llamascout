(() => {
    "use strict";

    const picker = document.querySelector("[data-site-parent-picker]");
    if (!picker) return; // Revisions keep their original parent Place.

    const input = picker.querySelector("[data-site-parent-input]");
    const selectedId = picker.querySelector("[data-site-parent-id]");
    const list = picker.querySelector("[data-site-parent-results]");
    const form = picker.closest("form");
    let options = [];
    let active = -1;
    let requestNumber = 0;
    let pending = null;
    let debounce = null;

    const close = () => {
        list.hidden = true;
        input.setAttribute("aria-expanded", "false");
        input.removeAttribute("aria-activedescendant");
        active = -1;
    };

    const highlight = (index) => {
        const buttons = [...list.querySelectorAll("[role=option]")];
        active = index < 0 ? -1 : Math.min(index, buttons.length - 1);
        buttons.forEach((button, i) => {
            button.classList.toggle("is-active", i === active);
            button.setAttribute("aria-selected", String(i === active));
        });
        if (active >= 0) {
            input.setAttribute("aria-activedescendant", buttons[active].id);
            buttons[active].scrollIntoView({block: "nearest"});
        } else {
            input.removeAttribute("aria-activedescendant");
        }
    };

    const select = (place) => {
        selectedId.value = String(place.id);
        input.value = String(place.name);
        input.setCustomValidity("");
        close();
        input.focus();
    };

    const render = (places) => {
        list.replaceChildren();
        options = places.filter(place =>
            Number.isSafeInteger(Number(place.id)) && Number(place.id) > 0
            && typeof place.name === "string"
        );
        options.forEach((place, index) => {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "site-parent-place-choice";
            button.id = "site-parent-option-" + index;
            button.setAttribute("role", "option");
            button.setAttribute("aria-selected", "false");
            button.textContent = place.name + (place.state ? " (" + place.state + ")" : "");
            button.addEventListener("click", () => select(place));
            list.appendChild(button);
        });
        list.hidden = options.length === 0;
        input.setAttribute("aria-expanded", String(options.length > 0));
        highlight(-1);
    };

    const lookup = async () => {
        const seq = ++requestNumber;
        pending?.abort();
        pending = new AbortController();
        const query = input.value.trim();
        try {
            const url = new URL("/add-site.php", window.location.origin);
            url.searchParams.set("parent_place_suggestions", "1");
            url.searchParams.set("q", query);
            const response = await fetch(url, {
                credentials: "same-origin",
                cache: "no-store",
                signal: pending.signal
            });
            if (!response.ok) throw new Error("Parent Place search unavailable");
            const data = await response.json();
            if (seq !== requestNumber || document.activeElement !== input) return;
            render(Array.isArray(data.places) ? data.places : []);
        } catch (error) {
            if (seq === requestNumber && error.name !== "AbortError") close();
        }
    };

    const schedule = () => {
        clearTimeout(debounce);
        debounce = setTimeout(lookup, 170);
    };

    input.addEventListener("focus", lookup);
    input.addEventListener("input", () => {
        selectedId.value = "";
        input.setCustomValidity("");
        close();
        schedule();
    });

    input.addEventListener("keydown", event => {
        if (event.key === "Escape") {
            close();
            return;
        }
        if (event.key === "ArrowDown" || event.key === "ArrowUp") {
            if (list.hidden) {
                lookup();
                event.preventDefault();
                return;
            }
            event.preventDefault();
            const step = event.key === "ArrowDown" ? 1 : -1;
            highlight((active + step + options.length) % options.length);
        }
        if (event.key === "Enter" && !list.hidden && options.length) {
            event.preventDefault();
            select(options[active >= 0 ? active : 0]);
        }
    });

    document.addEventListener("pointerdown", event => {
        if (!picker.contains(event.target)) close();
    });
    document.addEventListener("focusin", event => {
        if (!picker.contains(event.target)) close();
    });

    form?.addEventListener("submit", event => {
        if (!selectedId.value || !input.value.trim()) {
            event.preventDefault();
            input.setCustomValidity("Select an existing parent Place.");
            input.reportValidity();
            input.focus();
        }
    });
})();
