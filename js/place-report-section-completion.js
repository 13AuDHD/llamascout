/* Live section indicators plus canonical campsite selection. */
(() => {
  'use strict';

  const SITE_CAPABLE_TYPES = new Set([
    'dispersed-camping',
    'developed-campground',
    'camping-area',
    'rv-park-resort',
    'membership-host',
    'private-property',
    'fairgrounds',
  ]);

  const escapeSelector = (value) => (
    typeof CSS !== 'undefined'
    && typeof CSS.escape === 'function'
      ? CSS.escape(value)
      : value.replace(
          /[^a-zA-Z0-9_-]/g,
          '\\$&'
        )
  );

  const getValue = (form, key) => {
    const escaped = escapeSelector(key);
    const arrayControls = [
      ...form.querySelectorAll(
        `[name="${escaped}[]"]`
      ),
    ];

    if (arrayControls.length) {
      if (
        arrayControls[0] instanceof HTMLSelectElement
        && arrayControls[0].multiple
      ) {
        return [
          ...arrayControls[0].selectedOptions,
        ].map((option) => option.value);
      }

      return arrayControls
        .filter((element) => element.checked)
        .map((element) => element.value);
    }

    const controls = [
      ...form.querySelectorAll(
        `[name="${escaped}"]`
      ),
    ];

    if (!controls.length) {
      return '';
    }

    const radios =
      controls.filter(
        (element) => element.type === 'radio'
      );

    if (radios.length) {
      return (
        radios.find(
          (element) => element.checked
        )?.value
        ?? controls.find(
          (element) =>
            element.type === 'hidden'
        )?.value
        ?? ''
      );
    }

    const checkboxes =
      controls.filter(
        (element) =>
          element.type === 'checkbox'
      );

    if (checkboxes.length) {
      return checkboxes.some(
        (element) => element.checked
      )
        ? '1'
        : '';
    }

    if (
      controls[0] instanceof HTMLSelectElement
      && controls[0].multiple
    ) {
      return [
        ...controls[0].selectedOptions,
      ].map((option) => option.value);
    }

    return controls[0].value ?? '';
  };

  const answered = (
    value,
    field,
    cfg
  ) => {
    if (
      value == null
      || value === cfg.unanswered_token
    ) {
      return false;
    }

    if (value === cfg.unknown_token) {
      return true;
    }

    if (Array.isArray(value)) {
      return value.some(
        (item) =>
          String(item).trim() !== ''
      );
    }

    if (field.type === 'checkbox') {
      return value === '1';
    }

    const min =
      Number(
        field.min_characters || 0
      );

    if (min > 0) {
      return (
        String(value).trim().length
        >= min
      );
    }

    return String(value).trim() !== '';
  };

  const matches = (
    form,
    rule,
    cfg,
    visited = new Set()
  ) => {
    if (
      !rule
      || typeof rule !== 'object'
    ) {
      return false;
    }

    const op =
      String(
        rule.operator
        || rule.op
        || ''
      ).toLowerCase();

    if (op === 'all') {
      return (rule.rules || []).every(
        (item) =>
          matches(
            form,
            item,
            cfg,
            visited
          )
      );
    }

    if (op === 'any') {
      return (rule.rules || []).some(
        (item) =>
          matches(
            form,
            item,
            cfg,
            visited
          )
      );
    }

    const key =
      String(rule.field || '');

    const actual =
      getValue(
        form,
        key
      );

    const values =
      Array.isArray(actual)
        ? actual.map(String)
        : [
            String(
              actual ?? ''
            ),
          ];

    const expect =
      Array.isArray(rule.value)
        ? rule.value.map(String)
        : [
            String(
              rule.value ?? ''
            ),
          ];

    const excluded = [
      '',
      '0',
      'false',
      String(
        cfg.unknown_token ?? ''
      ).toLowerCase(),
      String(
        cfg.unanswered_token ?? ''
      ).toLowerCase(),
    ];

    const truthy =
      values.some(
        (value) =>
          !excluded.includes(
            value.toLowerCase()
          )
      );

    if (op === 'truthy') {
      return truthy;
    }

    if (op === 'falsy') {
      return !truthy;
    }

    if (op === 'equals') {
      return values.includes(
        expect[0]
      );
    }

    if (op === 'not_equals') {
      return !values.includes(
        expect[0]
      );
    }

    if (op === 'in') {
      return values.some(
        (value) =>
          expect.includes(value)
      );
    }

    if (op === 'not_in') {
      return values.every(
        (value) =>
          !expect.includes(value)
      );
    }

    if (op === 'answered') {
      if (visited.has(key)) {
        return false;
      }

      const dependency =
        cfg.fields.find(
          (field) =>
            field.key === key
        );

      if (!dependency) {
        return values.some(
          (value) =>
            value !== ''
        );
      }

      const next =
        new Set(visited);

      next.add(key);

      return (
        applicable(
          form,
          dependency,
          cfg,
          next
        )
        && answered(
          actual,
          dependency,
          cfg
        )
      );
    }

    return false;
  };

  const applicable = (
    form,
    field,
    cfg,
    visited = new Set()
  ) => (
    !field.derived
    && (
      field.applicable_if
      || []
    ).every(
      (rule) =>
        matches(
          form,
          rule,
          cfg,
          visited
        )
    )
  );

  const dispatchFieldUpdate = (
    control
  ) => {
    control.dispatchEvent(
      new Event(
        'input',
        {
          bubbles: true,
        }
      )
    );

    control.dispatchEvent(
      new Event(
        'change',
        {
          bubbles: true,
        }
      )
    );
  };

  const siteLookupContext = (
    form
  ) => {
    const placeId =
      String(
        form.querySelector(
          '[name="place_id"]'
        )?.value
        || form.dataset.placeId
        || ''
      ).trim();

    const params =
      new URLSearchParams(
        window.location.search
      );

    const slug =
      String(
        form.querySelector(
          '[name="place_slug"]'
        )?.value
        || form.dataset.placeSlug
        || params.get('slug')
        || ''
      ).trim();

    if (placeId !== '') {
      return {
        query:
          `place_id=${
            encodeURIComponent(placeId)
          }`,
      };
    }

    if (slug !== '') {
      return {
        query:
          `slug=${
            encodeURIComponent(slug)
          }`,
      };
    }

    return null;
  };

  const initSiteSelection = (
    form
  ) => {
    const group =
      form.querySelector(
        '[data-report-subsection="selection_type"]'
      );

    const siteNumber =
      form.querySelector(
        '[name="site_number"]'
      );

    if (
      !group
      || !siteNumber
      || group.querySelector(
        '[data-place-report-site-context]'
      )
    ) {
      return;
    }

    const body =
      group.querySelector(
        ':scope > .contribution-section-body'
      );

    if (!body) {
      return;
    }

    const context =
      siteLookupContext(form);

    const container =
      document.createElement(
        'div'
      );

    container.className =
      'contribution-field contribution-field-wide';

    container.setAttribute(
      'data-place-report-site-context',
      '1'
    );

    const fieldLabel =
      document.createElement(
        'span'
      );

    fieldLabel.className =
      'place-report-field-label';

    const labelText =
      document.createElement(
        'span'
      );

    labelText.textContent =
      'Which campsite are you reporting?';

    fieldLabel.appendChild(
      labelText
    );

    container.appendChild(
      fieldLabel
    );

    const status =
      document.createElement(
        'small'
      );

    status.setAttribute(
      'data-place-report-site-status',
      '1'
    );

    const hiddenId =
      document.createElement(
        'input'
      );

    hiddenId.type = 'hidden';
    hiddenId.name = 'campsite_id';
    hiddenId.value =
      String(
        form.querySelector(
          '[name="campsite_id"]'
        )?.value
        || ''
      );

    const oldHidden =
      form.querySelector(
        '[name="campsite_id"]'
      );

    if (oldHidden) {
      oldHidden.remove();
    }

    container.appendChild(
      hiddenId
    );

    const manualButton =
      document.createElement(
        'button'
      );

    manualButton.type = 'button';
    manualButton.className =
      'add-place-radio-clear';

    manualButton.textContent =
      'Add a new campsite';

    const lookupWrap =
      document.createElement(
        'div'
      );

    lookupWrap.className =
      'add-place-radio-control';

    const search =
      document.createElement(
        'input'
      );

    search.type = 'search';
    search.placeholder =
      'Search campsite number or name...';
    search.autocomplete = 'off';

    const select =
      document.createElement(
        'select'
      );

    select.setAttribute(
      'data-admin-campsite-selector',
      '1'
    );

    const emptyOption =
      document.createElement(
        'option'
      );

    emptyOption.value = '';
    emptyOption.textContent =
      'Select a campsite...';

    select.appendChild(
      emptyOption
    );

    lookupWrap.appendChild(
      search
    );

    lookupWrap.appendChild(
      select
    );

    lookupWrap.appendChild(
      manualButton
    );

    const allSites = [];

    const switchToManual = (
      clearIdentifier = true
    ) => {
      hiddenId.value = '';
      select.value = '';
      siteNumber.readOnly = false;

      if (clearIdentifier) {
        siteNumber.value = '';
        dispatchFieldUpdate(
          siteNumber
        );
      }

      status.textContent =
        'New campsite. Enter the posted site number or name below. If Llama Scout does not have an official campsite record yet, this identifier can be matched to official data later.';

      form.dispatchEvent(
        new CustomEvent(
          'llama:campsite-selected',
          {
            bubbles: true,
            detail: {
              campsiteId: null,
              mode: 'new',
            },
          }
        )
      );
    };

    const renderOptions = (
      filter = ''
    ) => {
      const query =
        String(filter)
          .trim()
          .toLowerCase();

      const current =
        select.value;

      select.replaceChildren(
        emptyOption.cloneNode(true)
      );

      allSites
        .filter(
          (site) => {
            if (query === '') {
              return true;
            }

            return [
              site.display_name,
              site.site_code,
              site.site_name,
            ].some(
              (value) =>
                String(value || '')
                  .toLowerCase()
                  .includes(query)
            );
          }
        )
        .forEach(
          (site) => {
            const option =
              document.createElement(
                'option'
              );

            option.value =
              String(site.id);

            const code =
              String(
                site.site_code
                || ''
              ).trim();

            const name =
              String(
                site.site_name
                || ''
              ).trim();

            option.textContent =
              code !== ''
              && name !== ''
              && code !== name
                ? `${code} Â· ${name}`
                : String(
                    site.display_name
                    || code
                    || name
                    || `Site ${site.id}`
                  );

            select.appendChild(
              option
            );
          }
        );

      if (
        current !== ''
        && [
          ...select.options,
        ].some(
          (option) =>
            option.value === current
        )
      ) {
        select.value =
          current;
      }
    };

    select.addEventListener(
      'change',
      () => {
        const campsiteId =
          Number(select.value || 0);

        if (campsiteId < 1) {
          return;
        }

        const site =
          allSites.find(
            (item) =>
              Number(item.id)
              === campsiteId
          );

        if (!site) {
          return;
        }

        const identifier =
          String(
            site.canonical_identifier
            || site.site_code
            || site.site_name
            || site.display_name
            || ''
          ).trim();

        hiddenId.value =
          String(campsiteId);

        siteNumber.value =
          identifier;

        siteNumber.readOnly =
          true;

        dispatchFieldUpdate(
          siteNumber
        );

        status.textContent =
          identifier !== ''
            ? `Using existing campsite ${identifier}. The identifier is locked to the existing Llama Scout record.`
            : 'Using the selected existing campsite record.';

        form.dispatchEvent(
          new CustomEvent(
            'llama:campsite-selected',
            {
              bubbles: true,
              detail: {
                campsiteId,
                mode: 'existing',
              },
            }
          )
        );
      }
    );

    search.addEventListener(
      'input',
      () => {
        renderOptions(
          search.value
        );
      }
    );

    manualButton.addEventListener(
      'click',
      () => {
        switchToManual(true);
        siteNumber.focus();
      }
    );

    if (!context) {
      lookupWrap.hidden = true;

      status.textContent =
        'This is a new Place, so this campsite can be added with the site number or name you observed. Llama Scout can match it to an official campsite record later if one becomes available.';
    } else {
      status.textContent =
        'Loading existing campsite records...';

      fetch(
        `/api/place-site-options.php?${context.query}`,
        {
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
          },
        }
      )
        .then(
          async (response) => {
            const payload =
              await response.json();

            if (
              !response.ok
              || !payload?.ok
            ) {
              throw new Error(
                payload?.error
                || 'Unable to load campsites.'
              );
            }

            allSites.push(
              ...(
                Array.isArray(
                  payload.sites
                )
                  ? payload.sites
                  : []
              )
            );

            renderOptions();

            if (!allSites.length) {
              lookupWrap.hidden = true;

              status.textContent =
                'Llama Scout does not have individual campsite records for this Place yet. Enter the site number or name you observed below.';
              return;
            }

            status.textContent =
              'Choose the matching existing campsite so its identifier stays consistent, or add a new campsite if it is not listed.';

            const currentIdentifier =
              String(
                siteNumber.value
                || ''
              ).trim()
              .toLowerCase();

            if (
              currentIdentifier !== ''
              && hiddenId.value === ''
            ) {
              const exact =
                allSites.find(
                  (site) =>
                    [
                      site.site_code,
                      site.site_name,
                      site.canonical_identifier,
                    ].some(
                      (value) =>
                        String(value || '')
                          .trim()
                          .toLowerCase()
                        === currentIdentifier
                    )
                );

              if (exact) {
                select.value =
                  String(exact.id);

                select.dispatchEvent(
                  new Event(
                    'change',
                    {
                      bubbles: true,
                    }
                  )
                );
              }
            }
          }
        )
        .catch(
          () => {
            lookupWrap.hidden = true;
            siteNumber.readOnly = false;

            status.textContent =
              'Existing campsite records could not be loaded. You can still enter the site number or name you observed.';
          }
        );
    }

    container.appendChild(
      lookupWrap
    );

    container.appendChild(
      status
    );

    const grid =
      body.querySelector(
        ':scope > .contribution-grid'
      );

    if (grid) {
      body.insertBefore(
        container,
        grid
      );
    } else {
      body.prepend(
        container
      );
    }

    const updateVisibility =
      () => {
        const type =
          String(
            getValue(
              form,
              'type'
            )
            || ''
          );

        container.hidden =
          type !== ''
          && !SITE_CAPABLE_TYPES.has(
            type
          );
      };

    form.addEventListener(
      'change',
      updateVisibility
    );

    form.addEventListener(
      'input',
      updateVisibility
    );

    updateVisibility();
  };

  const init = (schema) => {
    const form =
      schema.closest('form');

    if (!form) {
      return;
    }

    let cfg;

    try {
      cfg =
        JSON.parse(
          schema.textContent
        );
    } catch (_) {
      return;
    }

    if (!Array.isArray(cfg.fields)) {
      return;
    }

    initSiteSelection(form);

    const sections = [
      ...form.querySelectorAll(
        '[data-report-main-section]'
      ),
    ];

    if (!sections.length) {
      return;
    }

    const siteSelector = () =>
      form.querySelector(
        '[data-admin-campsite-selector]'
      );

    const calculate = (
      fields
    ) => {
      const items =
        new Map();

      fields.forEach(
        (field) => {
          if (
            field.counts_toward_completion
              === false
            || !applicable(
              form,
              field,
              cfg
            )
          ) {
            return;
          }

          const id =
            field.completion_group
              ? `group:${
                  field.completion_group
                }`
              : `field:${field.key}`;

          items.set(
            id,
            Boolean(
              items.get(id)
            )
            || answered(
              getValue(
                form,
                field.key
              ),
              field,
              cfg
            )
          );
        }
      );

      if (!items.size) {
        return null;
      }

      return Math.round(
        100
        * [
            ...items.values(),
          ].filter(Boolean).length
        / items.size
      );
    };

    const render = () => {
      const selector =
        siteSelector();

      const requiresSite =
        Boolean(
          selector
          && !selector.disabled
          && !selector.hidden
        );

      const hasSite =
        !requiresSite
        || selector.value !== ''
        || String(
          form.querySelector(
            '[name="site_number"]'
          )?.value
          || ''
        ).trim() !== '';

      sections.forEach(
        (section) => {
          const sectionKey =
            section.dataset
              .reportMainSection;

          const waitingForSite =
            sectionKey === 'site'
            && !hasSite;

          const fields =
            cfg.fields.filter(
              (field) =>
                field.layout_section
                === sectionKey
            );

          const mainPercent =
            calculate(fields);

          const mainLabel =
            section.querySelector(
              ':scope > summary [data-section-completion-percent]'
            );

          if (mainLabel) {
            mainLabel.textContent =
              waitingForSite
                ? 'Select a site'
                : (
                    mainPercent === null
                      ? ''
                      : `${mainPercent}%`
                  );
          }

          section
            .querySelectorAll(
              '[data-report-subsection]'
            )
            .forEach(
              (group) => {
                const groupFields =
                  fields.filter(
                    (field) =>
                      field.layout_group
                      === group.dataset
                        .reportSubsection
                  );

                const pct =
                  calculate(
                    groupFields
                  );

                const label =
                  group.querySelector(
                    ':scope > summary [data-section-completion-percent]'
                  );

                if (label) {
                  label.textContent =
                    waitingForSite
                      ? ''
                      : (
                          pct === null
                            ? ''
                            : `${pct}%`
                        );
                }
              }
            );
        }
      );
    };

    form.addEventListener(
      'input',
      render
    );

    form.addEventListener(
      'change',
      render
    );

    form.addEventListener(
      'place-report:updated',
      render
    );

    form.addEventListener(
      'llama:campsite-selected',
      render
    );

    form.addEventListener(
      'llama:campsite-saved',
      render
    );

    render();
  };

  const start = () =>
    document
      .querySelectorAll(
        'script[data-place-report-schema-config]'
      )
      .forEach(init);

  if (
    document.readyState
    === 'loading'
  ) {
    document.addEventListener(
      'DOMContentLoaded',
      start,
      {
        once: true,
      }
    );
  } else {
    start();
  }
})();
