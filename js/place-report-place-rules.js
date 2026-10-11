(() => {
  'use strict';

  const FIELD_NAMES = [
    'generator_prohibited',
    'generator_run_restrictions',
    'generator_max_run_hours',
    'generator_restriction_details',
    'place_charcoal_grills_allowed',
    'place_propane_fire_pits_allowed',
    'place_quiet_hours',
    'place_quiet_hours_begin',
    'place_quiet_hours_end',
    'place_alcohol_permitted',
    'fire_activities_conditional_details',
    'fire_activity_permit_url',
    'place_additional_rules'
  ];

  const clean = value => String(value ?? '').trim();

  const valueFor = (form, name) => {
    const controls = [...form.querySelectorAll(`[name="${CSS.escape(name)}"]`)];
    if (!controls.length) return '';

    const radios = controls.filter(control => control.type === 'radio');
    if (radios.length) {
      return radios.find(control => control.checked)?.value
        ?? controls.find(control => control.type === 'hidden')?.value
        ?? '';
    }

    const visible = controls.find(control => control.type !== 'hidden') || controls[0];
    return visible?.value ?? '';
  };

  const initialize = form => {
    if (form.dataset.placeRulesSaveReady === '1') return;
    if (!form.querySelector('[name="place_admin_action"][value="save-report"]')) return;

    const placeId = clean(form.querySelector('[name="place_id"]')?.value);
    const csrf = clean(form.querySelector('[name="csrf_token"]')?.value);
    if (!placeId || !csrf) return;

    form.dataset.placeRulesSaveReady = '1';

    window.addEventListener('llama:admin-place-report-saved', async event => {
      if (String(event.detail?.placeId || '') !== placeId) return;

      const body = new FormData();
      body.set('csrf_token', csrf);
      body.set('place_id', placeId);

      FIELD_NAMES.forEach(name => {
        body.set(name, valueFor(form, name));
      });

      try {
        const response = await fetch('/save-place-rules-supplemental.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
          body
        });

        if (!response.ok) {
          const payload = await response.json().catch(() => ({}));
          throw new Error(payload.error || 'Supplemental Place rules were not saved.');
        }
      } catch (error) {
        console.error(error);
      }
    });
  };

  const boot = () => document.querySelectorAll('form.place-report-form').forEach(initialize);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
