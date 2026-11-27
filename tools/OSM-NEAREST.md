# Automatic Nearest Services: regional OSM import

This is an independent ODbL reference layer, not a source of authored Scout answers or Resource/Facility records. The live lookup compares the closest mapped service within 100 straight-line miles from the actual Place coordinate against eligible standalone Scout Resources. Locate me and coordinate Search calculate distances and fill blank canonical Place Nearest Services selects; the named match and precise straight-line estimate appear below each automatically populated field. The Scout can review or edit the rounded distance before submitting. The current native fields store whole-mile choices (minimum 1 mile, or Over 20 miles), not the source name. No Site Nearest preview or report panel is needed. The first six categories retain Scout data even without OSM. Once regional data is imported, the additional town, alcohol sales and hospital categories can also return results. Missing data is not evidence that a service does not exist.

## cPanel / phpMyAdmin

1. Back up the database, run `tools/migrations/20261127_osm_nearest_services.sql`, merge/deploy the PR.
2. In GitHub Actions, choose **Extract OSM Nearest Services**, Run workflow, select main, enter `colorado`. This downloads the current Geofabrik state extract, keeps its referenced geometries, and packages a regional snapshot without accessing production.
3. Download and unzip the GitHub artifact. In phpMyAdmin select the Llama Scout database and import the enclosed `colorado.sql.gz` as SQL. Do not split the file or continue after errors; the temporary staging table, lock and transaction require one database session. The importer requires temporary-table privileges. If the shared host upload or execution limit is exceeded, do not remove the guard; use the CLI alternative or arrange a host-side import.
4. Verify the regional count and date using:
   ```sql
   SELECT region, MAX(snapshot_date) AS snapshot_date, COUNT(*) AS service_rows
   FROM osm_nearest_services GROUP BY region ORDER BY region;
   ```
5. Test Add a Place with known coordinates and confirm its existing Nearest Services fields are automatically populated after Locate me or Search, with OpenStreetMap or Scout attribution and straight-line distances. Then repeat for neighboring states and eventually the remaining states. Geofabrik region names must match its US download listing; this workflow is intentionally per-state, not the whole-country PBF.

Refresh a region by repeating the extraction and import. Older snapshots are refused, and missing records from the new snapshot are removed from that region. Adjacent extracts may contain the same OSM object; the lookup chooses one result per service category and never inserts those records into Scout tables.

## CLI alternative (where PHP CLI and tools are installed)

```sh
python -m pip install osmium shapely
# Download and filter a Geofabrik regional PBF, keeping referenced nodes/ways.
# Use its actual replication snapshot date (not the date of an older cached file).
python tools/extract-osm-nearest.py filtered.osm.pbf colorado.ndjson --region colorado --snapshot YYYY-MM-DD
php tools/import-osm-nearest.php colorado.ndjson
```

The deploy workflow deliberately excludes tools. Use a checked-out copy with the same private database configuration path or manually deploy the CLI script into the correct directory on a trusted host; never expose an import endpoint or credentials. The PHP importer rejects incomplete snapshots, validates rows, and atomically replaces one region. SQL alternative stages before replacing rows. If import reports an error, inspect the region counts before retrying.

## Data scope and limitations

* OSM nodes and representative points inside mapped areas; settlement centers are named place nodes.
* Fuel, EV charging, explicit propane tags (not generic LPG), grocery/supermarket, explicitly tagged alcohol retail, potable water, toilets, named settlements, hospitals.
* No access to private, customer-only or explicitly closed/disused mapped objects when those tags are present. Missing access tags or opening hours do not guarantee public access. Propane tags do not confirm cylinder filling/exchange; hospital tags do not confirm a 24-hour emergency department.
* No real-time hours, route distance, or availability guarantees. Unnamed facilities have generic category labels; no business names are invented.
* No automatic submissions or Resource contributions. Existing manual Place-level answers are preserved. Autofilled distances are reviewed and submitted through the normal Place workflow. Previously approved answers are not retroactively rewritten.
* No external queries are made for a visitor's GPS; only the local reference database is queried. Existing Complete Place access checks remain in place.

## License

Contains data © OpenStreetMap contributors, available under the Open Database License (ODbL) 1.0. See https://www.openstreetmap.org/copyright and https://opendatacommons.org/licenses/odbl/1-0/. The derived OSM reference layer is independently downloadable through `/api/osm-nearest-data.php` (regional index and NDJSON downloads). Scout-submitted Resources, photos, Places and observations are not included in those exports. Verify applicable ODbL requirements before any future permanent merging of datasets. Source extracts: https://download.geofabrik.de/north-america/us/.
