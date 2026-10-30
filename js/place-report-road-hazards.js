(() => {
  'use strict';

  const HAZARDS = [
    'rocks', 'washboards', 'potholes', 'mud_risk',
    'steep_grades', 'drop_off_exposure', 'water_crossings',
    'downed_tree_risk', 'approach_deep_ruts'
  ];
  const SPECIAL = new Set(['none', 'unknown']);
  const query = (root, selector) => root.querySelector(selector);
  const quote = (name) => CSS.escape(name);

  function initialize(form) {
    const group = query(form, '[data-report-subsection="road_conditions"]');
    if (!group) return;
    const hazards = [...group.querySelectorAll('input[name="approach_road_hazards[]"]')];
    if (!hazards.length) return;
    const byValue = new Map(hazards.map(el => [el.value, el]));

    function fieldControls(key) {
      return [...group.querySelectorAll(`[name="${quote(key)}"]`)];
    }

    function fieldCard(key) {
      const node = fieldControls(key).find(el => el.type !== 'hidden') || fieldControls(key)[0];
      return node?.closest('.contribution-field, .contribution-check') || null;
    }

    function answered(key) {
      const controls = fieldControls(key);
      const checked = controls.find(el => el.type === 'radio' && el.checked);
      if (checked) {
        return Number(checked.value) > 1 || (!Number.isFinite(Number(checked.value)) && checked.value !== '');
      }
      const select = controls.find(el => el.tagName === 'SELECT');
      if (select) return select.value !== '' && select.value !== '0';
      return controls.some(el => el.type === 'checkbox' && el.checked);
    }

    function hydrateLegacy() {
      const anyChecked = hazards.some(el => el.checked);
      if (anyChecked) return;
      HAZARDS.forEach(key => {
        if (answered(key) && byValue.has(key)) byValue.get(key).checked = true;
      });
    }

    function refresh() {
      const selected = new Set(hazards.filter(el => el.checked).map(el => el.value));
      const noneOrUnknown = [...SPECIAL].some(v => selected.has(v));
      HAZARDS.forEach(key => {
        const card = fieldCard(key);
        if (!card) return;
        card.hidden = noneOrUnknown || !selected.has(key);
      });
    }

    function announce() {
      form.dispatchEvent(new Event('change', { bubbles: true }));
    }

    hazards.forEach(input => {
      input.addEventListener('change', () => {
        if (input.checked && SPECIAL.has(input.value)) {
          hazards.forEach(el => {
            if (el !== input) el.checked = false;
          });
        } else if (input.checked) {
          SPECIAL.forEach(v => {
            if (byValue.has(v)) byValue.get(v).checked = false;
          });
        }
        refresh();
        announce();
      });
    });

    hydrateLegacy();
    refresh();

    form.addEventListener('change', event => {
      if (hazards.includes(event.target)) return;
      queueMicrotask(refresh);
    });
  }

  function boot() {
    document.querySelectorAll('form').forEach(initialize);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else boot();
})();


/* Reservations & Pricing: load the repeatable Rate Card enhancement only when needed. */
(() => {
  'use strict';
  const boot = () => {
    if (!document.querySelector('textarea[name="place_rate_cards_json"]')) return;
    if (!document.querySelector('link[data-place-rate-cards-style]')) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = '/css/site/features/place-report-rate-cards.css';
      link.dataset.placeRateCardsStyle = '1';
      document.head.appendChild(link);
    }
    if (!document.querySelector('script[data-place-rate-cards-script]')) {
      const script = document.createElement('script');
      script.src = '/js/place-report-rate-cards.js';
      script.defer = true;
      script.dataset.placeRateCardsScript = '1';
      document.head.appendChild(script);
    }
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else boot();
})();


/*
 * Shared amenities, operating hours and closures are submitted by the
 * canonical Admin Place Report transaction (admin_place_save_shared_report).
 * Do not reload their retired supplemental writers after that save: those
 * endpoints treated Unknown and Unanswered alike and could overwrite the
 * published answer state outside the transaction.
 */
