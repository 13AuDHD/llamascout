# Automatic Nearest Services: regional OSM import

This is an independent ODbL reference layer, not a source of authored Scout answers or Resource/Facility records. The live lookup compares the closest mapped service within 100 straight-line miles from the actual Place coordinate against eligible standalone Scout Resources. Locate me and coordinate Search calculate distances and fill blank canonical Place Nearest Services selects; the named match and precise straight-line estimate appear below each automatically populated field. The Scout can review or edit the rounded distance before submitting. The current native fields store whole-mile choices (minimum 1 mile, or Over 20 miles), not the source name. No Site Nearest preview or report panel is needed. The first six categories retain Scout data even without OSM. Once regional data is imported, the additional town, alcohol sales and hospital categories can also return results. Missing data is not evidence that a service does not exist.

## Admin import

Use **Admin > Map Data > Resources** and the local server worker described in [OSM-RESOURCE-IMPORT.md](OSM-RESOURCE-IMPORT.md). The old GitHub artifact workflow is retired. Existing snapshots and the nearest-service schema remain unchanged. The CLI worker reads the application's private database configuration and imports directly into MariaDB.

Verify imported state counts with:

```sql
SELECT region, MAX(snapshot_date) AS snapshot_date, COUNT(*) AS service_rows
FROM osm_nearest_services GROUP BY region ORDER BY region;
```

Test Add a Place at known coordinates. Existing Nearest Services fields should automatically populate after Locate me or Search. Refreshes replace only the completed regional source snapshot. Older snapshots are rejected; adjacent extracts may share OSM identities. The manual local extraction/SQL tools remain available for recovery, but must not be run concurrently with the scheduled worker.

## Data scope and limitations

* OSM nodes and representative points inside mapped areas; settlement centers are named place nodes.
* Fuel, EV charging, explicit propane tags (not generic LPG), grocery/supermarket, explicitly tagged alcohol retail, potable water, toilets, named settlements, hospitals.
* No access to private, customer-only or explicitly closed/disused mapped objects when those tags are present. Missing access tags or opening hours do not guarantee public access. Propane tags do not confirm cylinder filling/exchange; hospital tags do not confirm a 24-hour emergency department.
* No real-time hours, route distance, or availability guarantees. Unnamed facilities have generic category labels; no business names are invented.
* No automatic submissions or Resource contributions. Existing manual Place-level answers are preserved. Autofilled distances are reviewed and submitted through the normal Place workflow. Previously approved answers are not retroactively rewritten.
* No external queries are made for a visitor's GPS; only the local reference database is queried. Existing Complete Place access checks remain in place.

## License

Contains data © OpenStreetMap contributors, available under the Open Database License (ODbL) 1.0. See https://www.openstreetmap.org/copyright and https://opendatacommons.org/licenses/odbl/1-0/. The derived OSM reference layer is independently downloadable through `/api/osm-nearest-data.php` (regional index and NDJSON downloads). Scout-submitted Resources, photos, Places and observations are not included in those exports. Verify applicable ODbL requirements before any future permanent merging of datasets. Source extracts: https://download.geofabrik.de/north-america/us/.
