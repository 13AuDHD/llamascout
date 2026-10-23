(() => {
'use strict';

const namespace =
window.LlamaPlaceReportForm =
window.LlamaPlaceReportForm || {};

const RECOVERY_MAX_AGE =
48 * 60 * 60 * 1000;

const AUTOSAVE_DELAY = 700;

const blockedNames = new Set([
'csrf_token',
'photo_stage_token',
'photos_json',
'draft_save_token',
'place_admin_action',
'submit_for_review',
'save_for_later',
]);

const randomId = () => {
if (
window.crypto
&& typeof window.crypto.randomUUID
=== 'function'
) {
return window.crypto.randomUUID();
}

return (
Date.now().toString(36)
+ '-'
+ Math.random()
.toString(36)
.slice(2)
);
};

const contributorInstanceId = () => {
const state =
window.history.state
&& typeof window.history.state
=== 'object'
? window.history.state
: {};

if (
typeof state.llamaPlaceRecoveryId
=== 'string'
&& state.llamaPlaceRecoveryId !== ''
) {
return state.llamaPlaceRecoveryId;
}

const id = randomId();

try {
window.history.replaceState(
{
...state,
llamaPlaceRecoveryId: id,
},
'',
window.location.href
);
} catch (_) {
return id;
}

return id;
};

const formRecoveryKey = (form) => {
const csrf =
String(
form.querySelector(
'[name="csrf_token"]'
)?.value
|| ''
);

const sessionPart =
csrf.slice(-16) || 'session';

const placeId =
String(
form.querySelector(
'[name="place_id"]'
)?.value
|| ''
).trim();

if (
form.id === 'place-report'
&& placeId !== ''
) {
return [
'llama',
'place-report-recovery',
'admin',
sessionPart,
placeId,
].join(':');
}

const draftId =
String(
form.querySelector(
'[name="draft_id"]'
)?.value
|| ''
).trim();

if (draftId !== '') {
return [
'llama',
'place-report-recovery',
'draft',
sessionPart,
draftId,
].join(':');
}

const submissionId =
String(
form.querySelector(
'[name="submission_id"]'
)?.value
|| ''
).trim();

if (submissionId !== '') {
return [
'llama',
'place-report-recovery',
'submission',
sessionPart,
submissionId,
].join(':');
}

return [
'llama',
'place-report-recovery',
'new',
sessionPart,
contributorInstanceId(),
].join(':');
};

const groupedControls = (form) => {
const groups = new Map();

[
...form.elements,
].forEach((control) => {
if (
!(control instanceof HTMLElement)
|| !('name' in control)
) {
return;
}

const name =
String(control.name || '');

if (
name === ''
|| blockedNames.has(name)
|| control instanceof HTMLButtonElement
|| (
control instanceof HTMLInputElement
&& (
control.type === 'submit'
|| control.type === 'button'
|| control.type === 'file'
|| control.type === 'reset'
)
)
) {
return;
}

if (!groups.has(name)) {
groups.set(name, []);
}

groups.get(name).push(control);
});

return groups;
};

const serializeForm = (form) => {
const state = {};

groupedControls(form).forEach(
(controls, name) => {
const first = controls[0];
const radios = controls.filter(control =>
control instanceof HTMLInputElement && control.type === 'radio'
);

// Yes/No groups include an unanswered hidden input before the radio buttons.
// Serialize the selected radio instead of that hidden fallback.
if (radios.length) {
state[name] = {
kind: 'radio',
value:
radios.find(
(control) =>
control.checked
)?.value
?? null,
};

return;
}

if (
first
instanceof HTMLInputElement
&& first.type === 'checkbox'
) {
state[name] = {
kind: 'checkbox',
values:
controls
.filter(
(control) =>
control.checked
)
.map(
(control) =>
control.value
),
};

return;
}

if (
first
instanceof HTMLSelectElement
&& first.multiple
) {
state[name] = {
kind: 'multiple',
values:
[
...first
.selectedOptions,
].map(
(option) =>
option.value
),
};

return;
}

state[name] = {
kind: 'value',
value:
'value' in first
? String(
first.value
?? ''
)
: '',
};
}
);

return state;
};

const stateJson = (form) =>
JSON.stringify(
serializeForm(form)
);

const restoreForm = (
form,
storedState
) => {
const groups =
groupedControls(form);

Object.entries(
storedState || {})
.forEach(
([name, saved]) => {
const controls =
groups.get(name);

if (
!controls
|| !controls.length
|| !saved
|| typeof saved !== 'object'
) {
return;
}

if (saved.kind === 'radio') {
controls.forEach(
(control) => {
control.checked =
saved.value !== null
&& control.value
=== saved.value;
}
);

return;
}

if (
saved.kind
=== 'checkbox'
) {
const values =
Array.isArray(
saved.values
)
? saved.values
: [];

controls.forEach(
(control) => {
control.checked =
values.includes(
control.value
);
}
);

return;
}

if (
saved.kind
=== 'multiple'
) {
const first =
controls[0];

if (
!(
first
instanceof HTMLSelectElement
)
) {
return;
}

const values =
Array.isArray(
saved.values
)
? saved.values
: [];

[
...first.options,
].forEach(
(option) => {
option.selected =
values.includes(
option.value
);
}
);

return;
}

const first =
controls[0];

if (
'value' in first
&& saved.kind
=== 'value'
) {
first.value =
String(
saved.value
?? ''
);
}
}
);
};

const photoRecoveryState = (form) => {
const token =
String(
form.querySelector(
'[name="photo_stage_token"]'
)?.value
|| ''
)
.trim()
.toLowerCase();

if (!/^[a-f0-9]{32}$/.test(token)) {
return null;
}

const raw =
String(
form.querySelector(
'[name="photos_json"]'
)?.value
|| '[]'
);

let photos = [];

try {
const parsed = JSON.parse(raw);

if (Array.isArray(parsed)) {
photos = parsed.filter(
(photo) =>
photo
&& typeof photo === 'object'
&& String(
photo.path
|| ''
).trim() !== ''
);
}
} catch (_) {
photos = [];
}

if (!photos.length) {
return null;
}

return {
token,
photos,
};
};

const restorePhotoRecoveryState = (
form,
storedState
) => {
if (
!storedState
|| typeof storedState !== 'object'
) {
return false;
}

const token =
String(
storedState.token
|| ''
)
.trim()
.toLowerCase();

const photos =
Array.isArray(storedState.photos)
? storedState.photos.filter(
(photo) =>
photo
&& typeof photo === 'object'
&& String(
photo.path
|| ''
).trim() !== ''
)
: [];

if (
!/^[a-f0-9]{32}$/.test(token)
|| !photos.length
) {
return false;
}

let tokenField =
form.querySelector(
'[name="photo_stage_token"]'
);

if (!tokenField) {
tokenField =
document.createElement(
'input'
);

tokenField.type = 'hidden';
tokenField.name =
'photo_stage_token';

form.appendChild(
tokenField
);
}

let photosField =
form.querySelector(
'[name="photos_json"]'
);

if (!photosField) {
photosField =
document.createElement(
'input'
);

photosField.type = 'hidden';
photosField.name =
'photos_json';

form.appendChild(
photosField
);
}

tokenField.value = token;
photosField.value =
JSON.stringify(photos);

return true;
};

const recoveryStatus = (form) => {
let status =
form.querySelector(
'[data-place-report-recovery-status]'
);

if (status) {
return status;
}

status =
document.createElement(
'span'
);

status.setAttribute(
'data-place-report-recovery-status',
'1'
);

status.setAttribute(
'role',
'status'
);

status.setAttribute(
'aria-live',
'polite'
);

status.classList.add(
'place-report-recovery-status'
);

const adminBar =
form.querySelector(
'.admin-place-report-savebar'
);

const contributionBar =
form.querySelector(
'.add-place-submit-bar'
);

if (adminBar) {
adminBar.classList.add(
'has-recovery-status'
);
adminBar.appendChild(status);
} else if (contributionBar) {
contributionBar.classList.add(
'has-recovery-status'
);
contributionBar.appendChild(
status
);
} else {
form.appendChild(status);
}

return status;
};

const setupRecovery = (form) => {
if (
!(form instanceof HTMLFormElement)
) {
return;
}

const key =
formRecoveryKey(form);

const status =
recoveryStatus(form);

const initialBaseline =
stateJson(form);

let baseline =
initialBaseline;

let dirty = false;
let restoring = false;
let savingTimer = 0;
let submitting = false;

const setStatus = (
message,
isError = false
) => {
status.textContent =
message;

status.classList.toggle(
'is-error',
isError
);
};

const removeRecovery = () => {
try {
localStorage.removeItem(
key
);
} catch (_) {
}
};

const saveRecovery = () => {
if (
restoring
|| submitting
) {
return;
}

const data =
serializeForm(form);

const current =
JSON.stringify(data);

const photoStage =
photoRecoveryState(form);

if (
current === baseline
&& photoStage === null
) {
dirty = false;
removeRecovery();
setStatus('');
return;
}

const payload = {
version: 3,
savedAt: Date.now(),
baseline,
data,
photoStage,
};

try {
localStorage.setItem(
key,
JSON.stringify(
payload
)
);

dirty = true;

setStatus(
'Unsaved changes backed up on this device.'
);
} catch (_) {
dirty = true;

setStatus(
'Unsaved changes could not be backed up in this browser.',
true
);
}
};

const queueRecovery = () => {
if (
restoring
|| submitting
) {
return;
}

window.clearTimeout(
savingTimer
);

savingTimer =
window.setTimeout(
saveRecovery,
AUTOSAVE_DELAY
);
};

try {
const raw =
localStorage.getItem(
key
);

if (raw) {
const payload =
JSON.parse(raw);

const age =
Date.now()
- Number(
payload?.savedAt
|| 0
);

if (
(
payload?.version === 1
|| payload?.version === 2
|| payload?.version === 3
)
&& age >= 0
&& age
<= RECOVERY_MAX_AGE
&& payload.baseline
=== initialBaseline
&& payload.data
&& typeof payload.data
=== 'object'
) {
const recovered =
JSON.stringify(
payload.data
);

const hasRecoveredPhotos =
payload?.version === 3
&& payload.photoStage
&& typeof payload.photoStage
=== 'object';

if (
recovered
!== initialBaseline
|| hasRecoveredPhotos
) {
restoring = true;

restoreForm(
form,
payload.data
);

const photosRecovered =
hasRecoveredPhotos
? restorePhotoRecoveryState(
form,
payload.photoStage
)
: false;

restoring = false;

if (
recovered
=== initialBaseline
&& !photosRecovered
) {
removeRecovery();
dirty = false;
setStatus('');
return;
}

dirty = true;

const recoveredAt =
new Date(
Number(
payload.savedAt
)
);

setStatus(
'Recovered unsaved changes from '
+ recoveredAt
.toLocaleTimeString(
[],
{
hour:
'numeric',
minute:
'2-digit',
}
)
+ '.'
);
} else {
removeRecovery();
}
} else {
removeRecovery();
}
}
} catch (_) {
removeRecovery();
}

form.addEventListener(
'input',
queueRecovery
);

form.addEventListener(
'change',
queueRecovery
);

form.addEventListener(
'llama:photo-staging-changed',
queueRecovery
);

const isAdminReport =
form.id === 'place-report'
&& form.querySelector(
'[name="place_admin_action"][value="save-report"]'
);

const saveRecoveryImmediately = () => {
const data =
serializeForm(form);

const current =
JSON.stringify(data);

const photoStage =
photoRecoveryState(form);

if (
current === baseline
&& photoStage === null
) {
return current;
}

try {
localStorage.setItem(
key,
JSON.stringify(
{
version: 3,
savedAt:
Date.now(),
baseline,
data,
photoStage,
}
)
);

dirty = true;
} catch (_) {
dirty = true;
}

return current;
};

if (isAdminReport) {
form.addEventListener(
'llama:admin-place-report-save-starting',
() => {
window.clearTimeout(
savingTimer
);

saveRecoveryImmediately();
}
);
} else {
form.addEventListener(
'submit',
() => {
submitting = true;

window.clearTimeout(
savingTimer
);

saveRecoveryImmediately();
}
);
}

form.addEventListener(
'llama:place-draft-save-starting',
() => {
window.clearTimeout(
savingTimer
);

saveRecoveryImmediately();
submitting = true;
}
);

form.addEventListener(
'llama:place-draft-save-failed',
() => {
submitting = false;
queueRecovery();
}
);

form.addEventListener(
'llama:place-draft-saved',
() => {
window.clearTimeout(
savingTimer
);

baseline =
stateJson(form);
dirty = false;
submitting = true;

removeRecovery();
setStatus('');
}
);

window.addEventListener(
'llama:admin-place-report-saved',
(event) => {
if (!isAdminReport) {
return;
}

const formPlaceId =
String(
form.querySelector(
'[name="place_id"]'
)?.value
|| ''
);

const savedPlaceId =
String(
event?.detail?.placeId
|| ''
);

if (
savedPlaceId !== ''
&& formPlaceId !== ''
&& savedPlaceId
!== formPlaceId
) {
return;
}

window.clearTimeout(
savingTimer
);

baseline =
stateJson(form);
dirty = false;
submitting = false;

removeRecovery();
}
);

window.addEventListener(
'beforeunload',
(event) => {
if (
!dirty
|| submitting
) {
return;
}

event.preventDefault();
event.returnValue = '';
}
);
};

namespace.setupRecovery =
setupRecovery;
})();
