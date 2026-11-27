(() => {
    "use strict";

    const picker = document.querySelector("[data-site-parent-picker]");
    if (!picker) return; // Revisions keep their original parent Place.

    const input = picker.querySelector("[data-site-parent-input]");
    const selectedId = picker.querySelector("[data-site-parent-id]");
    const list = picker.querySelector("[data-site-parent-results]");
    const form = picker.closest("form");
    const status = picker.querySelector("[data-site-parent-status]");
    const hint = "Search published Places or your own submitted Places awaiting review.";
    const show = message => { if (status) status.textContent = message; };
    let options = [];
    let active = -1;
    let requestNumber = 0;
    let pending = null;
    let debounce = null;
    let suppressFocusLookup = false;
    let contextRequest = 0;
    let contextPending = false;
    const setContext = context => {
        form.dataset.reportContext = JSON.stringify(context || {});
        form.dispatchEvent(new Event('change', {bubbles: true}));
    };
    const loadContext = async id => {
        const seq = ++contextRequest;
        contextPending = true;
        setContext({});
        try {
            const url = new URL('/add-site.php', window.location.origin);
            url.searchParams.set('parent_report_context', id);
            const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok) throw new Error('Parent Place context unavailable');
            const data = await response.json();
            if (seq !== contextRequest || selectedId.value !== String(id)) return;
            if (!data.context?.type) throw new Error('Parent Place context unavailable');
            setContext(data.context);
        } catch (_) {
            if (seq !== contextRequest) return;
            selectedId.value = '';
            input.setCustomValidity('Select the parent Place again. Its details could not be loaded.');
            show('The parent Place details could not be loaded. Select it again.');
        } finally {
            if (seq === contextRequest) contextPending = false;
        }
    };

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
            buttons[active].scrollIntoView?.({block: "nearest"});
        } else {
            input.removeAttribute("aria-activedescendant");
        }
    };

    const select = (place) => {
        clearTimeout(debounce);
        pending?.abort();
        ++requestNumber;
        selectedId.value = String(place.id);
        loadContext(place.id);
        input.value = String(place.name);
        input.setCustomValidity("");
        show(place.status === "pending" || place.status === "needs-changes"
            ? "Your Place is awaiting review. This Site can be submitted now, but cannot be published until its Parent Place is approved."
            : "Parent Place selected.");
        close();
        suppressFocusLookup = true;
        input.focus();
        suppressFocusLookup = false;
    };

    const render = (places) => {
        list.replaceChildren();
        options = places.filter(place =>
            /^(?:pending:)?[1-9][0-9]*$/.test(String(place.id))
            && Number.isSafeInteger(Number(String(place.id).replace(/^pending:/, "")))
            && typeof place.name === "string"
        );
        options.forEach((place, index) => {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "site-parent-place-choice";
            button.id = "site-parent-option-" + index;
            button.setAttribute("role", "option");
            button.setAttribute("aria-selected", "false");
            button.textContent = place.name + (place.state ? " (" + place.state + ")" : "")
                + (place.status === "pending" ? " · Pending review"
                    : place.status === "needs-changes" ? " · Needs changes" : "");
            button.addEventListener("click", () => select(place));
            list.appendChild(button);
        });
        list.hidden = options.length === 0;
        input.setAttribute("aria-expanded", String(options.length > 0));
        highlight(-1);
        show(options.length ? "Select the matching Parent Place." : "No matching Places found.");
    };

    const lookup = async () => {
        const seq = ++requestNumber;
        pending?.abort();
        pending = new AbortController();
        const query = input.value.trim();
        show("Searching Places…");
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
            if (seq === requestNumber && error.name !== "AbortError") {
                close();
                show("Parent Place search is unavailable. Try again.");
            }
        }
    };

    const schedule = () => {
        clearTimeout(debounce);
        debounce = setTimeout(lookup, 170);
    };

    input.addEventListener("focus", () => {
        if (!suppressFocusLookup) lookup();
    });
    input.addEventListener("input", () => {
        selectedId.value = "";
        ++requestNumber;
        pending?.abort();
        ++contextRequest;
        contextPending = false;
        setContext({});
        input.setCustomValidity("");
        show(hint);
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
            highlight(active < 0
                ? (step > 0 ? 0 : options.length - 1)
                : (active + step + options.length) % options.length);
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
        if (contextPending) {
            event.preventDefault();
            return;
        }
        if (!selectedId.value || !input.value.trim()) {
            event.preventDefault();
            input.setCustomValidity("Select a Parent Place from the search results.");
            input.reportValidity();
            input.focus();
        }
    });
})();
