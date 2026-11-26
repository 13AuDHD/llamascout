'use strict';

// Public terminology and color contract. Schema keys intentionally stay "area".
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

const theme = read('css/site.css');
const resourceMap = read('css/map.css');
const resourceDetail = read('css/resource-detail.css');
const mapJs = read('js/map.js');
const publicFeatures = read('api/place-map-public-areas.php');
const fullPlaceMap = read('js/place-map.js');
const areaSelector = read('partials/place/overnight-options.php');
const facilityForm = read('partials/place-report/add-area-form.php');
const facilityJs = read('js/place-explorer.js');
const facilityCardCss = read('css/site/features/place-explorer.css');
const report = read('partials/place/scout-report/index.php');
const tabs = read('partials/place-report/contribution-tabs.php');
const addPlace = read('add-place.php');
const resources = read('resource-contribution.php');
const resourceForm = read('partials/resources/contribution.php');
const resourceSubmissions = read('app/resource-submissions.php');

assert.match(theme, /--resource-accent:\s*#147f83\s*;/,
    'All Resource map pins and cards must inherit the teal token');
assert.match(resourceMap, /\.map-resource-marker\s*\{[^}]*background:\s*var\(--resource-accent\)/s);
assert.match(resourceMap, /\.map-resource-legend-symbol\s*\{[^}]*background:\s*var\(--resource-accent\)/s);
assert.match(resourceMap, /\.map-resource-card\s*\{[^}]*border:\s*3px solid var\(--resource-accent\)/s);
assert.match(resourceMap, /\.map-resource-marker\.is-service-dump_station/);
assert.match(resourceMap, /\.map-resource-marker\.is-service-propane/);
assert.match(resourceMap, /\.map-resource-marker\.is-service-water_fill/);
assert.match(resourceDetail, /border-inline-start:\s*4px solid var\(--resource-accent\)/);
assert.match(mapJs, /resourceMarkerIcon\(/,
    'Existing service-specific marker icons must remain in use');

assert.match(tabs, /'area'\s*=>\s*\[\s*'description' => 'Describe an on-site Facility/,
    'Facility intro must come from the shared contribution switcher');
assert.doesNotMatch(tabs, /<h2\b/, 'Shared intro boxes must not repeat the page heading');
assert.match(facilityForm, /'facility_area' => 'Facility'/);
assert.doesNotMatch(facilityForm, /'camping_area'\s*=>/);
assert.doesNotMatch(facilityForm, /'parking_area'\s*=>/);
assert.match(facilityForm, /name="area_feature_type"/,
    'Public Facility form reuses the existing record type key');
assert.match(facilityForm, /name="area_name"/);
assert.match(facilityForm, /name="area_coordinates"/);
assert.match(facilityForm, /name="submit_area"/);
assert.match(areaSelector, /if \(\$featureType !== 'facility_area'\)/,
    'Only actual Facilities should appear in the visitor selection list');
assert.match(publicFeatures, /'facility_area'/);
assert.doesNotMatch(publicFeatures, /'camping_area'\s*,/);
assert.doesNotMatch(publicFeatures, /'parking_area'\s*,/);
assert.match(fullPlaceMap, /feature\.feature_type !== 'camping_area'/);
assert.match(fullPlaceMap, /feature\.feature_type !== 'parking_area'/);
assert.match(facilityJs, /featureType !== 'facility_area'/);
assert.match(facilityCardCss, /\[data-stay-area-kind="facility"\]/);
assert.match(report, /Facility Scout Report/);
assert.doesNotMatch(areaSelector, /Sites and Areas|Select a Site or Area/);
assert.match(areaSelector, /Sites and Facilities/);

assert.match(tabs, /data-contribution-facility-link/,
    'Visitor Facility now opens an independent contribution form');
assert.doesNotMatch(addPlace, /require __DIR__ \. '\/partials\/place-report\/add-area-form\.php'/,
    'No bundled Facility form should remain inside Add Place');
assert.match(tabs, /data-contribution-resource-link/);
assert.match(tabs, /resource-contribution\.php/,
    'Resource tab continues to route to its dedicated submission form');
assert.match(addPlace, /partials\/place-report\/contribution-tabs\.php/);
assert.match(resources, /\$resourceLabel = \$contributionIsFacility \? 'Facility' : 'Resource'/,
    'One Resource editor should label the Facility mode independently');
assert.doesNotMatch(resources,
    /if \(!\$posted && !\$isUpdate && !\$revision\)\s*\{\s*header\(/s,
    'The new Resource route must never loop back to the Place form');
assert.match(resources, /llama_resource_submit\(/);
assert.match(resourceSubmissions, /INSERT INTO resource_submissions/,
    'New Resource uses the already-established moderation queue');
assert.match(resourceForm, /'Submit ' \. \$resourceFormLabel \. ' for Review'/);
assert.match(resources, /account\.llamascout\.com\/contributions\.php\?submitted=/,
    'New Resource should return to contributor submissions');

console.log('PASS: standalone Resource pins, legacy Area markers and independently submitted Parent-linked Facilities');
