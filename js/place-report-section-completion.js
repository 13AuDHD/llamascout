/* Live section indicators. Overall completion and submission requirements remain canonical. */
(() => {
  'use strict';
  const getValue = (form, key) => {
    const escaped = typeof CSS !== 'undefined' && CSS.escape
      ? CSS.escape(key) : key.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    const arrayControls = [...form.querySelectorAll(`[name="${escaped}[]"]`)];
    if (arrayControls.length) {
      if (arrayControls[0] instanceof HTMLSelectElement && arrayControls[0].multiple) {
        return [...arrayControls[0].selectedOptions].map(option => option.value);
      }
      return arrayControls.filter(el => el.checked).map(el => el.value);
    }
    const controls = [...form.querySelectorAll(`[name="${escaped}"]`)];
    if (!controls.length) return '';
    const radios = controls.filter(el => el.type === 'radio');
    if (radios.length) return radios.find(el => el.checked)?.value
      ?? controls.find(el => el.type === 'hidden')?.value ?? '';
    const checkboxes = controls.filter(el => el.type === 'checkbox');
    if (checkboxes.length) return checkboxes.some(el => el.checked) ? '1' : '';
    if (controls[0] instanceof HTMLSelectElement && controls[0].multiple) {
      return [...controls[0].selectedOptions].map(option => option.value);
    }
    return controls[0].value ?? '';
  };
  const answered = (value, field, cfg) => {
    if (value == null || value === cfg.unanswered_token) return false;
    if (value === cfg.unknown_token) return true;
    if (Array.isArray(value)) return value.some(v => String(v).trim() !== '');
    if (field.type === 'checkbox') return value === '1';
    const min = Number(field.min_characters || 0);
    if (min > 0) return String(value).trim().length >= min;
    return String(value).trim() !== '';
  };
  const matches = (form, rule, cfg, visited = new Set()) => {
    if (!rule || typeof rule !== 'object') return false;
    const op = String(rule.operator || rule.op || '').toLowerCase();
    if (op === 'all') return (rule.rules || []).every(r => matches(form, r, cfg, visited));
    if (op === 'any') return (rule.rules || []).some(r => matches(form, r, cfg, visited));
    const key = String(rule.field || '');
    const actual = getValue(form, key);
    const values = Array.isArray(actual) ? actual.map(String) : [String(actual ?? '')];
    const expect = Array.isArray(rule.value) ? rule.value.map(String) : [String(rule.value ?? '')];
    const excluded = ['', '0', 'false', String(cfg.unknown_token ?? '').toLowerCase(), String(cfg.unanswered_token ?? '').toLowerCase()];
    const truthy = values.some(v => !excluded.includes(v.toLowerCase()));
    if (op === 'truthy') return truthy;
    if (op === 'falsy') return !truthy;
    if (op === 'equals') return values.includes(expect[0]);
    if (op === 'not_equals') return !values.includes(expect[0]);
    if (op === 'in') return values.some(v => expect.includes(v));
    if (op === 'not_in') return values.every(v => !expect.includes(v));
    if (op === 'answered') {
      if (visited.has(key)) return false;
      const dependency = cfg.fields.find(f => f.key === key);
      if (!dependency) return values.some(v => v !== '');
      const next = new Set(visited); next.add(key);
      return applicable(form, dependency, cfg, next) && answered(actual, dependency, cfg);
    }
    return false;
  };
  const applicable = (form, field, cfg, visited = new Set()) =>
    !field.derived && (field.applicable_if || []).every(rule => matches(form, rule, cfg, visited));
  const init = (schema) => {
    const form = schema.closest('form');
    if (!form) return;
    let cfg;
    try { cfg = JSON.parse(schema.textContent); } catch (_) { return; }
    if (!Array.isArray(cfg.fields)) return;
    const sections = [...form.querySelectorAll('[data-report-main-section]')];
    if (!sections.length) return;
    const siteSelector = () => form.querySelector('[data-admin-campsite-selector]');
    const calculate = (fields) => {
      const items = new Map();
      fields.forEach(field => {
        if (field.counts_toward_completion === false || !applicable(form, field, cfg)) return;
        const id = field.completion_group ? `group:${field.completion_group}` : `field:${field.key}`;
        items.set(id, Boolean(items.get(id)) || answered(getValue(form, field.key), field, cfg));
      });
      if (!items.size) return null;
      return Math.round(100 * [...items.values()].filter(Boolean).length / items.size);
    };
    const render = () => {
      const selector = siteSelector();
      const requiresSite = Boolean(selector && !selector.disabled);
      const hasSite = !requiresSite || selector.value !== '';
      sections.forEach(section => {
        const sectionKey = section.dataset.reportMainSection;
        const waitingForSite = sectionKey === 'site' && !hasSite;
        const fields = cfg.fields.filter(f => f.layout_section === sectionKey);
        const mainPercent = calculate(fields);
        const mainLabel = section.querySelector(':scope > summary [data-section-completion-percent]');
        if (mainLabel) mainLabel.textContent = waitingForSite
          ? 'Select a site' : (mainPercent === null ? '' : `${mainPercent}%`);
        section.querySelectorAll('[data-report-subsection]').forEach(group => {
          const groupFields = fields.filter(f => f.layout_group === group.dataset.reportSubsection);
          const pct = calculate(groupFields);
          const label = group.querySelector(':scope > summary [data-section-completion-percent]');
          if (label) label.textContent = waitingForSite
            ? '' : (pct === null ? '' : `${pct}%`);
        });
      });
    };
    form.addEventListener('input', render);
    form.addEventListener('change', render);
    form.addEventListener('place-report:updated', render);
    form.addEventListener('llama:campsite-selected', render);
    form.addEventListener('llama:campsite-saved', render);
    render();
  };
  const start = () => document.querySelectorAll('script[data-place-report-schema-config]').forEach(init);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
})();
