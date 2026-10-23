(() => {
'use strict';

const forms = [
...document.querySelectorAll(
'.place-report-form'
),
];

if (!forms.length) {
return;
}

const TAXONOMY_OTHER_VALUES =
new Set([
'Other',
'other',
]);

const TAXONOMY_BASE_MANAGERS = [
'U.S. Forest Service',
'Bureau of Land Management',
'National Park Service',
'U.S. Fish and Wildlife Service',
'U.S. Army Corps of Engineers',
'Bureau of Reclamation',
'Tennessee Valley Authority',
'State government',
'County / regional government',
'City / municipal government',
'Special district / public authority',
'Public utility / power authority',
'Tribal government',
'Land trust / conservation organization',
'Private landowner / business',
'Other',
];

const TAXONOMY_NATIONAL_MANAGERS = {
'National Forest': ['U.S. Forest Service'],
'National Grassland': ['U.S. Forest Service'],
'BLM Land': ['Bureau of Land Management'],
'National Conservation Area': ['Bureau of Land Management','National Park Service','Other'],
'National Park': ['National Park Service'],
'National Preserve / Reserve': ['National Park Service','Other'],
'National Monument': ['National Park Service','Bureau of Land Management','U.S. Forest Service','U.S. Fish and Wildlife Service','Other'],
'National Recreation Area': ['National Park Service','U.S. Forest Service','Bureau of Reclamation','U.S. Army Corps of Engineers','Other'],
'National Seashore / Lakeshore': ['National Park Service'],
'National River / Scenic Riverway': ['National Park Service','U.S. Forest Service','Bureau of Land Management','Other'],
'National Wildlife Refuge': ['U.S. Fish and Wildlife Service'],
'Federal Water Project / Recreation Land': ['U.S. Army Corps of Engineers','Bureau of Reclamation','Tennessee Valley Authority','Other'],
'County / Regional Park': ['County / regional government','Other'],
'City / Municipal Land': ['City / municipal government','Other'],
'Public Utility / Reservoir Land': ['Public utility / power authority','Bureau of Reclamation','U.S. Army Corps of Engineers','Other'],
'Public Parking / Civic Property': ['City / municipal government','County / regional government','State government','Other'],
'Rest Area / Transportation Facility': ['State government','County / regional government','Other'],
'Roadside / Highway Right-of-Way': ['State government','County / regional government','City / municipal government','Other'],
'Tribal Land': ['Tribal government','Other'],
'Land Trust / Conservation Preserve': ['Land trust / conservation organization','Other'],
'Membership / Hosted Property': ['Harvest Hosts','Boondockers Welcome','Other'],
'Travel Center / Truck Stop Property': ["Love's Travel Stops",'Pilot / Flying J','TA / Petro','Maverik',"Buc-ee's",'Other'],
'Retail / Commercial Property': ['Walmart','Home Depot',"Lowe's","Cabela's / Bass Pro Shops",'Camping World','Private landowner / business','Other'],
'Restaurant Property': ['Cracker Barrel','Private landowner / business','Other'],
'Medical / Healthcare Property': ['Private landowner / business','City / municipal government','County / regional government','State government','Other'],
'Religious / Community Property': ['Private landowner / business','Other'],
'Casino / Gaming Property': ['Private landowner / business','Tribal government','Other'],
'Private Land': ['Private landowner / business','Other'],
'Fairgrounds / Event Property': ['County / regional government','City / municipal government','State government','Private landowner / business','Other'],
};

const TAXONOMY_STATE_MANAGERS = {
Colorado: {
'State Park': ['Colorado Parks & Wildlife'],
'State Recreation Area': ['Colorado Parks & Wildlife','Other'],
'Wildlife Management / Game Lands': ['Colorado Parks & Wildlife'],
'State Trust Land': ['Colorado State Land Board'],
},
Florida: {
'Water Management District': ['South Florida Water Management District','Southwest Florida Water Management District','St. Johns River Water Management District','Suwannee River Water Management District','Northwest Florida Water Management District'],
'State Park': ['Florida State Parks'],
'Wildlife Management / Game Lands': ['Florida Fish and Wildlife Conservation Commission','Other'],
},
Arizona: {
'State Park': ['Arizona State Parks & Trails'],
'Wildlife Management / Game Lands': ['Arizona Game and Fish Department'],
'State Trust Land': ['Arizona State Land Department'],
},
Utah: {
'State Park': ['Utah Division of State Parks'],
'Wildlife Management / Game Lands': ['Utah Division of Wildlife Resources'],
'State Trust Land': ['Utah Trust Lands Administration'],
},
Nevada: {
'State Park': ['Nevada State Parks'],
'Wildlife Management / Game Lands': ['Nevada Department of Wildlife'],
},
'New Mexico': {
'State Park': ['New Mexico State Parks'],
'Wildlife Management / Game Lands': ['New Mexico Department of Game and Fish'],
'State Trust Land': ['New Mexico State Land Office'],
},
Wyoming: {
'State Park': ['Wyoming State Parks, Historic Sites & Trails'],
'Wildlife Management / Game Lands': ['Wyoming Game and Fish Department'],
},
Montana: {
'State Park': ['Montana Fish, Wildlife & Parks'],
'Wildlife Management / Game Lands': ['Montana Fish, Wildlife & Parks'],
},
Idaho: {
'State Park': ['Idaho Parks and Recreation'],
'Wildlife Management / Game Lands': ['Idaho Department of Fish and Game'],
'State Trust Land': ['Idaho Department of Lands'],
},
Oregon: {
'State Park': ['Oregon Parks and Recreation Department'],
'Wildlife Management / Game Lands': ['Oregon Department of Fish and Wildlife'],
},
Washington: {
'State Park': ['Washington State Parks'],
'Wildlife Management / Game Lands': ['Washington Department of Fish and Wildlife'],
'State Trust Land': ['Washington Department of Natural Resources'],
},
California: {
'State Park': ['California State Parks'],
'Wildlife Management / Game Lands': ['California Department of Fish and Wildlife'],
},
Texas: {
'State Park': ['Texas Parks and Wildlife Department'],
'Wildlife Management / Game Lands': ['Texas Parks and Wildlife Department'],
},
'South Dakota': {
'State Park': ['South Dakota Game, Fish and Parks'],
'Wildlife Management / Game Lands': ['South Dakota Game, Fish and Parks'],
},
};

const TAXONOMY_PLACE_TYPE_MANAGERS = {
'travel-center': ["Love's Travel Stops",'Pilot / Flying J','TA / Petro','Maverik',"Buc-ee's",'Other'],
'truck-stop': ["Love's Travel Stops",'Pilot / Flying J','TA / Petro','Maverik',"Buc-ee's",'Other'],
'retail-parking': ['Walmart','Home Depot',"Lowe's","Cabela's / Bass Pro Shops",'Camping World','Other'],
'restaurant-parking': ['Cracker Barrel','Other'],
'membership-host': ['Harvest Hosts','Boondockers Welcome','Other'],
};

const setupTaxonomy = (form) => {
const state =
form.querySelector(
'select[name="state"]'
);

const placeType =
form.querySelector(
'select[name="type"]'
);

const landType =
form.querySelector(
'select[name="land_type"]'
);

const manager =
form.querySelector(
'select[name="land_manager"]'
);

if (!landType || !manager) {
return;
}

const originalManager =
String(manager.value || '');

const makeOtherInput = (
select,
label
) => {
if (!select) {
return null;
}

const wrapper =
select.closest(
'.contribution-field'
)
|| select.parentElement;

if (!wrapper) {
return null;
}

const input =
document.createElement(
'input'
);

input.type = 'text';
input.autocomplete = 'off';
input.placeholder = label;
input.hidden = true;
input.dataset.taxonomyOtherInput =
select.name;

select.insertAdjacentElement(
'afterend',
input
);

const sync = () => {
const isOther =
TAXONOMY_OTHER_VALUES.has(
String(
select.value
|| ''
)
);

input.hidden = !isOther;
input.required = isOther;

if (!isOther) {
input.value = '';
}
};

select.addEventListener(
'change',
sync
);

sync();

return input;
};

const typeOther =
makeOtherInput(
placeType,
'Describe this Place type'
);

const landTypeOther =
makeOtherInput(
landType,
'Enter the property / land type'
);

const managerOther =
makeOtherInput(
manager,
'Enter the manager, operator, owner, business, or membership program'
);

const setOptions = (
select,
values,
current
) => {
const unknown =
[
...select.options,
].find(
(option) =>
option.textContent
?.includes(
'Unknown / could not determine'
)
);

select.innerHTML = '';

const blank =
new Option(
'Select...',
''
);

select.add(blank);

if (unknown) {
select.add(
new Option(
unknown.textContent,
unknown.value
)
);
}

const unique = [
...new Set(values),
];

unique.forEach((value) => {
select.add(
new Option(
value,
value
)
);
});

if (
current
&& ![
...select.options,
].some(
(option) =>
option.value
=== current
)
) {
const previous =
new Option(
current
+ ' (previous entry)',
current
);

select.add(previous);
}

select.value =
current || '';
};

const managerChoices = () => {
const selectedState =
String(
state?.value
|| ''
);

const selectedLandType =
String(
landType.value
|| ''
);

const selectedPlaceType =
String(
placeType?.value
|| ''
);

const values = [];

const add = (items) => {
(
Array.isArray(items)
? items
: []
).forEach((item) => {
if (!values.includes(item)) {
values.push(item);
}
});
};

add(
TAXONOMY_STATE_MANAGERS[
selectedState
]?.[
selectedLandType
]
);

add(
TAXONOMY_NATIONAL_MANAGERS[
selectedLandType
]
);

add(
TAXONOMY_PLACE_TYPE_MANAGERS[
selectedPlaceType
]
);

if (!values.length) {
add(TAXONOMY_BASE_MANAGERS);
} else if (
!values.includes('Other')
) {
values.push('Other');
}

return values;
};

const syncManager = (
preserve = true
) => {
const current =
preserve
? String(
manager.value
|| ''
)
: '';

setOptions(
manager,
managerChoices(),
current
);

manager.dispatchEvent(
new Event(
'change',
{
bubbles: false,
}
)
);
};

state?.addEventListener(
'change',
() => syncManager(false)
);

placeType?.addEventListener(
'change',
() => syncManager(false)
);

landType.addEventListener(
'change',
() => syncManager(false)
);

syncManager(true);

if (
originalManager
&& manager.value === ''
) {
manager.value =
originalManager;
}

form.addEventListener(
'submit',
() => {
const applyOther = (
select,
input
) => {
if (
!select
|| !input
|| !TAXONOMY_OTHER_VALUES.has(
String(
select.value
|| ''
)
)
) {
return;
}

const custom =
String(
input.value
|| ''
).trim();

if (!custom) {
return;
}

let stored = custom;

if (
select.name === 'type'
) {
stored =
'Other: ' + custom;
}

const option =
new Option(
stored,
stored,
true,
true
);

select.add(option);
select.value = stored;
};

applyOther(
placeType,
typeOther
);

applyOther(
landType,
landTypeOther
);

applyOther(
manager,
managerOther
);
},
{
capture: true,
}
);
};

/*
 * =========================================================
 * SHARED PLACE REPORT SCHEMA BEHAVIOR
 *
 * Contextual applicability controls whether a question is visible.
 * Completion eligibility is separate: optional notes may remain
 * visible without contributing to the completion denominator.
 * =========================================================
 */

const normalizedCharacterCount = (value) => {
const normalized =
String(value ?? '')
.trim()
.replace(/\s+/gu, ' ');

return Array.from(normalized).length;
};

const readPlaceReportConfig = (form) => {
const configNode =
form.querySelector(
'[data-place-report-schema-config]'
)
?? form.querySelector(
'[data-place-report-completion-config]'
);

if (!configNode) {
return null;
}

try {
const config =
JSON.parse(
configNode.textContent || '{}'
);

return Array.isArray(config.fields)
? config
: null;
} catch (_) {
return null;
}
};

const completionFieldValue = (
form,
fieldKey
) => {
const unknownControl = form.querySelector(`[name="${CSS.escape('_report_unknown[' + fieldKey + ']')}"]`);
if (unknownControl?.checked) return '__LLAMA_UNKNOWN__';
const arrayControls = [
...form.querySelectorAll(
`[name="${CSS.escape(fieldKey + '[]')}"]`
),
];

if (arrayControls.length) {
const first =
arrayControls[0];

if (
first instanceof HTMLSelectElement
&& first.multiple
) {
return [
...first.selectedOptions,
].map(
(option) => String(option.value)
);
}

const checkboxes =
arrayControls.filter(
(control) =>
control instanceof HTMLInputElement
&& control.type === 'checkbox'
);

if (checkboxes.length) {
return checkboxes
.filter(
(control) => control.checked
)
.map(
(control) => String(control.value)
);
}
}

const controls = [
...form.querySelectorAll(
`[name="${CSS.escape(fieldKey)}"]`
),
];

if (!controls.length) {
return '';
}

const radios =
controls.filter(
(control) =>
control instanceof HTMLInputElement
&& control.type === 'radio'
);

if (radios.length) {
return (
radios.find(
(control) => control.checked
)?.value
?? controls.find(
(control) =>
control instanceof HTMLInputElement
&& control.type === 'hidden'
)?.value
?? ''
);
}

const checkboxes =
controls.filter(
(control) =>
control instanceof HTMLInputElement
&& control.type === 'checkbox'
);

if (checkboxes.length) {
return checkboxes.some(
(control) => control.checked
)
? '1'
: '';
}

const first = controls[0];

if (
first instanceof HTMLSelectElement
&& first.multiple
) {
return [
...first.selectedOptions,
].map(
(option) => String(option.value)
);
}

return 'value' in first
? String(first.value ?? '')
: '';
};

const completionValueAnswered = (
value,
field,
config
) => {
if (
value === config.unanswered_token
|| value === null
|| value === undefined
) {
return false;
}

if (
value === config.unknown_token
) {
return true;
}

if (Array.isArray(value)) {
return value.some(
(item) =>
String(item ?? '').trim() !== ''
);
}

if (
String(field.type || '')
=== 'checkbox'
) {
return value === '1'
|| value === 1
|| value === true;
}

const minimum =
Math.max(
0,
Number(field.min_characters || 0)
);

if (minimum > 0) {
return normalizedCharacterCount(value)
>= minimum;
}

return String(value).trim() !== '';
};

const completionRuleMatches = (
form,
rule,
config
) => {
if (
!rule
|| typeof rule !== 'object'
) {
return false;
}

const operator =
String(
rule.operator || 'equals'
);

if (
operator === 'any'
|| operator === 'all'
) {
const nestedRules =
Array.isArray(rule.rules)
? rule.rules.filter(
(nested) =>
nested
&& typeof nested === 'object'
)
: [];

if (!nestedRules.length) {
return false;
}

return operator === 'any'
? nestedRules.some(
(nested) =>
completionRuleMatches(
form,
nested,
config
)
)
: nestedRules.every(
(nested) =>
completionRuleMatches(
form,
nested,
config
)
);
}

const fieldKey =
String(rule.field || '');

if (!fieldKey) {
return false;
}

if (fieldKey !== 'type') {
const dependency = Array.isArray(config.fields)
? config.fields.find((item) => String(item.key) === fieldKey)
: null;

if (dependency && !fieldContextuallyApplicable(form, dependency, config)) {
return false;
}
}

const actual =
completionFieldValue(
form,
fieldKey
);

const expected =
rule.value;

if (operator === 'answered') {
const dependency =
config.fields.find(
(field) =>
String(field.key) === fieldKey
);

return dependency
? (
fieldContextuallyApplicable(
form,
dependency,
config
)
&& completionValueAnswered(
actual,
dependency,
config
)
)
: String(actual).trim() !== '';
}

const falseValues = [
'',
'0',
'false',
String(
config.unanswered_token || ''
).toLowerCase(),
String(
config.unknown_token || ''
).toLowerCase(),
];

const actualValues =
Array.isArray(actual)
? actual.map(
(value) => String(value)
)
: [String(actual ?? '')];

const actualTruthy =
actualValues.length > 0
&& actualValues.some(
(value) =>
!falseValues.includes(
String(value).toLowerCase()
)
);

if (operator === 'truthy') {
return actualTruthy;
}

if (operator === 'falsy') {
return !actualTruthy;
}

const expectedValues =
Array.isArray(expected)
? expected.map(String)
: [String(expected ?? '')];

if (operator === 'equals') {
return actualValues.includes(
expectedValues[0]
);
}

if (operator === 'not_equals') {
return !actualValues.includes(
expectedValues[0]
);
}

if (operator === 'in') {
return actualValues.some(
(value) =>
expectedValues.includes(value)
);
}

if (operator === 'not_in') {
return actualValues.every(
(value) =>
!expectedValues.includes(value)
);
}

return false;
};

const fieldContextuallyApplicable = (
form,
field,
config
) => {
// Issue #5 reconciliation: keep dependency rules as metadata only.
// Derived fields are not editable questions.
return !field.derived;
};

const fieldCountsTowardCompletion = (
form,
field,
config
) =>
fieldContextuallyApplicable(
form,
field,
config
)
&& field.counts_toward_completion
!== false;

const syncApplicabilityVisibility = (
form,
config
) => {
// Reconciliation mode: show every question belonging to this form.
// Scope routing is handled by the form renderer, not dependencies.
config.fields.forEach((field) => {
const key = String(field.key || '');
if (!key || field.derived) return;

const controls = [
...form.querySelectorAll(
`[name="${CSS.escape(key)}"], [name="${CSS.escape(key + '[]')}"]`
)
];

controls.forEach((control) => {
const wrapper = control.closest(
'.contribution-field, .contribution-check'
);
if (!wrapper) return;

wrapper.hidden = false;
wrapper.classList.remove('is-applicability-hidden');
delete wrapper.dataset.placeReportApplicabilityHidden;
delete wrapper.dataset.placeReportOriginalHidden;
});
});

form.querySelectorAll('.contribution-section').forEach((section) => {
section.hidden = false;
section.classList.remove('is-applicability-hidden');
delete section.dataset.placeReportApplicabilityHidden;
});
};

const setupNarrativeCounters = (
form,
config
) => {
config.fields.forEach((field) => {
const minimum =
Math.max(
0,
Number(field.min_characters || 0)
);

if (minimum < 1) {
return;
}

const input =
form.querySelector(
`textarea[name="${CSS.escape(String(field.key))}"], input[name="${CSS.escape(String(field.key))}"]`
);

if (!input) {
return;
}

let counter =
input.parentElement
?.querySelector(
`[data-place-report-character-counter="${CSS.escape(String(field.key))}"]`
);

if (!counter) {
counter =
document.createElement(
'small'
);

counter.dataset.placeReportCharacterCounter =
String(field.key);

counter.className =
'place-report-character-counter';

input.insertAdjacentElement(
'afterend',
counter
);
}

const update = () => {
const count =
normalizedCharacterCount(
input.value
);

const met =
count >= minimum;

counter.textContent =
count.toLocaleString()
+ ' / '
+ minimum.toLocaleString()
+ ' characters';

counter.dataset.minimumMet =
met ? '1' : '0';
};

input.addEventListener(
'input',
update
);

update();
});
};

const setupFieldHelp = (form) => {
const toggles = [
...form.querySelectorAll(
'[data-place-report-help-toggle]'
),
];

if (!toggles.length) {
return;
}

const entries =
toggles
.map((toggle) => {
const panelId =
String(
toggle.getAttribute(
'data-place-report-help-toggle'
)
|| ''
);

const panel =
panelId !== ''
? form.querySelector(
'#' + CSS.escape(panelId)
)
: null;

return panel
? {
toggle,
panel,
}
: null;
})
.filter(Boolean);

const isOpen = (entry) =>
entry.panel.classList.contains(
'is-open'
);

const syncExpanded = (entry) => {
entry.toggle.setAttribute(
'aria-expanded',
isOpen(entry)
? 'true'
: 'false'
);
};

const closeEntry = (entry) => {
entry.panel.classList.remove(
'is-open'
);

syncExpanded(entry);
};

const closeOthers = (current) => {
entries.forEach((entry) => {
if (entry !== current) {
closeEntry(entry);
}
});
};

const openEntry = (entry) => {
closeOthers(entry);

const bounds =
entry.toggle.getBoundingClientRect();

const openRight =
bounds.left > window.innerWidth / 2;

entry.toggle
.closest(
'.place-report-field-help'
)
?.classList.toggle(
'is-align-right',
openRight
);

entry.panel.classList.add(
'is-open'
);

syncExpanded(entry);
};

const toggleEntry = (entry) => {
if (isOpen(entry)) {
closeEntry(entry);
} else {
openEntry(entry);
}
};

entries.forEach((entry) => {
entry.toggle.addEventListener(
'click',
(event) => {
event.preventDefault();
event.stopPropagation();

toggleEntry(entry);
}
);

entry.toggle.addEventListener(
'keydown',
(event) => {
if (
event.key !== 'Enter'
&& event.key !== ' '
) {
return;
}

event.preventDefault();
event.stopPropagation();

toggleEntry(entry);
}
);

syncExpanded(entry);
});

document.addEventListener(
'click',
(event) => {
if (
event.target instanceof Element
&& event.target.closest(
'.place-report-field-help'
)
) {
return;
}

entries.forEach(
closeEntry
);
}
);

document.addEventListener(
'keydown',
(event) => {
if (event.key !== 'Escape') {
return;
}

entries.forEach(
closeEntry
);
}
);
};

const setupMultiselectControls = (form) => {
form
.querySelectorAll(
'[data-place-report-multiselect]'
)
.forEach((control) => {
const search =
control.querySelector(
'[data-place-report-multiselect-search]'
);

const options = [
...control.querySelectorAll(
'[data-place-report-multiselect-option]'
),
];

const summary =
control.querySelector(
'[data-place-report-multiselect-summary]'
);

const defaultSummary =
String(
summary?.dataset.defaultSummary
|| 'Choose options'
);

const syncSummary = () => {
const selected =
options.filter(
(option) =>
option.querySelector(
'input[type="checkbox"]'
)?.checked
);

if (summary) {
summary.textContent =
selected.length > 0
? selected.length.toLocaleString()
+ ' selected'
: defaultSummary;
}
};

const filterOptions = () => {
const needle =
String(
search?.value || ''
)
.trim()
.toLowerCase();

options.forEach((option) => {
const label =
String(
option.dataset.searchText
|| option.textContent
|| ''
)
.toLowerCase();

option.hidden =
needle !== ''
&& !label.includes(needle);
});
};

search?.addEventListener(
'input',
filterOptions
);

control.addEventListener('change', event => {
const namedChoice = control.querySelector('input[name$="[]"]');
if (namedChoice) {
const key = namedChoice.name.slice(0, -2);
let touched = [...form.querySelectorAll('input[type="hidden"]')].find(el => el.name === `_report_touched[${key}]`);
if (!touched) { touched = document.createElement('input'); touched.type = 'hidden'; touched.name = `_report_touched[${key}]`; form.append(touched); }
touched.value = '1';
}
const unknownOption = control.querySelector('[data-report-multiselect-unknown]');
if (!unknownOption) return;
if (event.target === unknownOption && unknownOption.checked) {
for (const option of options) {
const checkbox = option.querySelector('input[type="checkbox"]');
if (checkbox) checkbox.checked = false;
}
} else if (event.target !== unknownOption && event.target.type === 'checkbox') {
unknownOption.checked = false;
}
});
control.addEventListener(
'change',
syncSummary
);

syncSummary();
});
};

const setupSharedPlaceReportBehavior = (
form
) => {
setupFieldHelp(
form
);
const config =
readPlaceReportConfig(form);

if (!config) {
return null;
}

setupNarrativeCounters(
form,
config
);

setupMultiselectControls(
form
);

const sync = () => {
syncApplicabilityVisibility(
form,
config
);
};

form.addEventListener(
'input',
sync
);

form.addEventListener(
'change',
sync
);

sync();

return config;
};

/*
 * =========================================================
 * LIVE PLACE REPORT COMPLETION
 * =========================================================
 */

const completionPhotoCount = (form) => {
let staged = 0;

try {
const parsed =
JSON.parse(
String(
form.querySelector(
'[name="photos_json"]'
)?.value
|| '[]'
)
);

if (Array.isArray(parsed)) {
staged =
parsed.filter(
(photo) =>
photo
&& typeof photo === 'object'
).length;
}
} catch (_) {
staged = 0;
}

let existing = 0;

form
.querySelectorAll(
'.add-place-existing-photo'
)
.forEach((photo) => {
const remove =
photo.querySelector(
'input[name="remove_existing_photos[]"]'
);

if (!remove?.checked) {
existing++;
}
});

return staged + existing;
};

const setupLiveCompletion = (
form,
sharedConfig = null
) => {
const box =
form.querySelector(
'[data-place-report-completion]'
);

if (!box) {
return;
}

const config =
sharedConfig
?? readPlaceReportConfig(form);

if (
!config
|| !Array.isArray(config.fields)
) {
return;
}

const percentNode =
box.querySelector(
'[data-place-report-completion-percent]'
);

const countNode =
box.querySelector(
'[data-place-report-completion-count]'
);

const minimumNode =
box.querySelector(
'[data-place-report-minimum-status]'
);

const panel =
form.querySelector(
'[data-place-report-missing-panel]'
);

const listNode =
form.querySelector(
'[data-place-report-missing-list]'
);

const toggle =
form.querySelector(
'[data-place-report-missing-toggle]'
);

let missingPanelOpened = false;

const hasMeaningfulProgress = () => {
if (completionPhotoCount(form) > 0) {
return true;
}

return config.fields.some((field) => {
const key =
String(field.key || '');

if (
!key
|| key === 'name'
|| !fieldContextuallyApplicable(
form,
field,
config
)
) {
return false;
}

const value =
completionFieldValue(
form,
key
);

if (Array.isArray(value)) {
return value.some(
(item) =>
String(item ?? '').trim() !== ''
);
}

const normalized =
String(value ?? '').trim();

return (
normalized !== ''
&& normalized
!== String(
config.unanswered_token || ''
)
);
});
};

const setMissingPanelOpen = (open) => {
missingPanelOpened =
Boolean(open);

if (panel) {
panel.hidden =
!missingPanelOpened;

panel.classList.toggle(
'is-force-hidden',
!missingPanelOpened
);
}

if (toggle) {
toggle.setAttribute(
'aria-expanded',
missingPanelOpened
? 'true'
: 'false'
);

toggle.textContent =
missingPanelOpened
? 'Hide missing'
: 'Show missing';
}
};

const setMissingToggleAvailable = (
available
) => {
if (!toggle) {
return;
}

toggle.hidden =
!available;

toggle.classList.toggle(
'is-force-hidden',
!available
);
};

const calculate = () => {
const items = new Map();

config.fields.forEach((field) => {
if (
!fieldCountsTowardCompletion(
form,
field,
config
)
) {
return;
}

const group =
String(
field.completion_group || ''
).trim();

const itemKey =
group
? 'group:' + group
: 'field:' + String(field.key);

if (!items.has(itemKey)) {
items.set(
itemKey,
{
key: itemKey,
label:
group
? group
.replace(/_/g, ' ')
.replace(
/\b\w/g,
(character) =>
character.toUpperCase()
)
: String(
field.label || field.key
),
answered: false,
fields: [],
details: [],
}
);
}

const item =
items.get(itemKey);

item.fields.push(
String(field.key)
);

const value =
completionFieldValue(
form,
String(field.key)
);

const answered =
completionValueAnswered(
value,
field,
config
);

if (answered) {
item.answered = true;
}

const minimum =
Math.max(
0,
Number(field.min_characters || 0)
);

if (
minimum > 0
&& !answered
) {
item.details.push(
normalizedCharacterCount(value)
.toLocaleString()
+ ' / '
+ minimum.toLocaleString()
+ ' characters'
);
}
});

items.set(
'evidence:photo',
{
key: 'evidence:photo',
label:
String(
config.photo_item?.label
|| '1 current photo'
),
answered:
completionPhotoCount(form) > 0,
fields: [],
details: [],
}
);

const rows = [
...items.values(),
];

const answered =
rows.filter(
(item) => item.answered
).length;

const total =
rows.length;

const percent =
total > 0
? Math.round(
100 * answered / total
)
: 0;

return {
answered,
total,
percent,
missing:
rows.filter(
(item) => !item.answered
),
};
};

const render = () => {
const summary =
calculate();

if (percentNode) {
percentNode.textContent =
summary.percent + '%';
}

const meaningfulProgress =
hasMeaningfulProgress();

if (countNode) {
countNode.textContent =
meaningfulProgress
? (
summary.answered.toLocaleString()
+ ' of '
+ summary.total.toLocaleString()
+ ' applicable completion items addressed.'
)
: 'Start filling out the report to track completion.';
}

if (minimumNode) {
const name =
String(
form.querySelector(
'[name="name"]'
)?.value
|| ''
).trim();

const type =
String(
form.querySelector(
'[name="type"]'
)?.value
|| ''
).trim();

const visitedAt =
String(
form.querySelector(
'[name="visited_at"]'
)?.value
|| ''
).trim();

const description =
String(
form.querySelector(
'[name="description"]'
)?.value
|| ''
).trim();

const latitude =
String(
form.querySelector(
'[name="latitude"]'
)?.value
|| ''
).trim();

const longitude =
String(
form.querySelector(
'[name="longitude"]'
)?.value
|| ''
).trim();

const hasPhoto =
completionPhotoCount(form) > 0;

const missing = [];

if (name === '') {
missing.push(
'name'
);
}

if (type === '') {
missing.push(
'place type'
);
}

if (visitedAt === '') {
missing.push(
'date visited'
);
}

if (description === '') {
missing.push(
'description'
);
}

if (
latitude === ''
|| longitude === ''
) {
missing.push(
'exact location'
);
}

if (!hasPhoto) {
missing.push(
'1 current photo'
);
}

if (!missing.length) {
minimumNode.textContent =
'Minimum requirements met.';
} else {
minimumNode.textContent =
'Minimum to submit: '
+ missing.join(', ')
+ '.';
}
}

const canShowMissing =
meaningfulProgress
&& summary.missing.length > 0;

setMissingToggleAvailable(
canShowMissing
);

if (!canShowMissing) {
setMissingPanelOpen(false);
} else {
setMissingPanelOpen(
missingPanelOpened
);
}

if (listNode) {
listNode.replaceChildren();

if (!summary.missing.length) {
const done =
document.createElement(
'span'
);

done.textContent =
'Nothing. Every applicable completion item is addressed.';

listNode.appendChild(done);
} else {
const list =
document.createElement(
'ul'
);

summary.missing.forEach(
(item) => {
const li =
document.createElement(
'li'
);

li.textContent =
item.label
+ (
item.details.length
? ' - '
+ item.details.join(', ')
: ''
);

list.appendChild(li);
}
);

listNode.appendChild(list);
}
}

box.dataset.completionPercent =
String(summary.percent);
box.dataset.completionAnswered =
String(summary.answered);
box.dataset.completionTotal =
String(summary.total);
};

toggle?.addEventListener(
'click',
() => {
if (!panel) {
return;
}

setMissingPanelOpen(
!missingPanelOpened
);
}
);

form.addEventListener(
'input',
render
);

form.addEventListener(
'change',
render
);

form.addEventListener(
'llama:photo-staging-changed',
render
);

render();
};

forms.forEach((form) => {
setupTaxonomy(form);
const placeReportConfig =
setupSharedPlaceReportBehavior(form);
setupLiveCompletion(
form,
placeReportConfig
);
form
.querySelectorAll(
'[data-place-report-clear]'
)
.forEach((button) => {
button.addEventListener(
'click',
() => {
const name =
button.getAttribute(
'data-place-report-clear'
);

if (!name) {
return;
}

form
.querySelectorAll(
`input[type="radio"][name="${CSS.escape(name)}"]`
)
.forEach(
(radio) => {
radio.checked =
false;
}
);

form.dispatchEvent(
new Event(
'change',
{
bubbles:
true,
}
)
);
}
);
});

const noAmenities =
form.querySelector(
'[data-place-report-no-amenities]'
);

const amenities = [
...form.querySelectorAll(
'[data-place-report-amenity]'
),
];

if (noAmenities) {
noAmenities.addEventListener(
'change',
() => {
if (
!noAmenities.checked
) {
return;
}

amenities.forEach(
(input) => {
input.checked =
false;
}
);
}
);

amenities.forEach(
(input) => {
input.addEventListener(
'change',
() => {
if (
input.checked
&& noAmenities
.checked
) {
noAmenities.checked =
false;
}
}
);
}
);
}

if (
!window.LlamaPlaceReportForm
|| typeof window.LlamaPlaceReportForm
.setupRecovery !== 'function'
) {
throw new Error(
'Place Report recovery module is not loaded.'
);
}

window.LlamaPlaceReportForm
.setupRecovery(form);
});
})();
