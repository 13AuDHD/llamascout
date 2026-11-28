# Map Data > Resources: local state import and refresh

The Admin page tracks all 50 states plus DC and retains all previously imported snapshots. The default shared-host mode processes the queue directly from the open Admin page. No cron, GitHub token, worker key, remote import endpoint, or Actions extraction is required for browser imports. GitHub remains source control, CI and existing deployment only.

## Current shared hosting: browser import

1. Run the existing Resource import migration if not already applied, then run `tools/migrations/20261127_osm_browser_import.sql` in the primary database. This only adds resume metadata to the existing jobs table.
2. Open Admin > Map Data > Resources. Select a state and click Import / refresh selected. The page starts processing automatically, including any previously queued states. To process existing queued jobs, click Resume queued imports.
3. Keep the page open. Each request reads a bounded HTTP byte range from the Geofabrik state PBF, decodes point features in PHP, stages at most 500 service records in a single prepared multi-row INSERT, and saves the exact byte and within-block row cursor. The browser scans up to 8 MiB per request, skips blocks without possible service tags, and waits 1.25 seconds before the next request. The server also rejects concurrent requests and writes within the same database timestamp second. No command-line tools, cron, full state-file storage, or external credentials are used.
4. Pause or close the page and resume later. The source file's version is verified on every range. If Geofabrik replaces the source between visits, restart the affected state; its old published records remain intact. Errors or incomplete scans cannot replace the old data. PHP needs cURL, zlib, mbstring and 64-bit integer support. The host must allow outbound HTTPS range requests. Real-state performance and host behavior must be verified on Colorado first.
5. The shared-host reader imports OSM nodes only (all supported camper-priority tags), not way/relation geometries. Existing mapped areas are kept, not refreshed, during a point-only import. A full future server import refreshes both points and areas. These are reference candidates, not verified public Scout Resources.

## Optional full server extraction after migration

The optional local PHP CLI job reads the same private/config.php and db() connection as the rest of Llama Scout. It runs independently of web requests. Existing queue/staging tables from 20261127_osm_resource_import.sql are reused. The browser cursor migration remains compatible with the local worker. If those tables were never created, run that existing migration first.

On the new Debian/Ubuntu DigitalOcean host, install the extraction dependencies once:

```sh
sudo apt-get update
sudo apt-get install -y osmium-tool python3-venv curl coreutils
```

As the site user, create a private Python virtual environment and install the two extraction libraries with its pip: `python3 -m venv /path/to/private/osm-venv`, then `/path/to/private/osm-venv/bin/pip install osmium shapely`. Set the optional `osm_resource_import.python` setting in private/config.php to `/path/to/private/osm-venv/bin/python`. Alternatively use distribution-installed Python libraries with the default python3. PHP CLI must support PDO MySQL, mbstring and proc_open. Run the worker as the same site user that owns the private directory/config, not root. Adjust the absolute site path and PHP executable to the actual deployment.

```sh
php /path/to/public_html/jobs/osm-resource-import.php --check
```

When that succeeds, schedule the same job without --check, for example once every five minutes:

```cron
*/5 * * * * /usr/bin/php /path/to/public_html/jobs/osm-resource-import.php >> /path/to/private/osm-resource-worker.log 2>&1
```

This is a server setup step, not an API credential. Each run processes at most one state; overlapping cron invocations exit without doing work. No need to leave the Admin page open. Job stages appear in Admin. The scheduled worker can process unstarted jobs and its own interrupted jobs; it does not take over a partially scanned browser job. Resume that job in the browser or restart the state before switching to the full extractor. Do not launch the full CLI PBF extraction on the current shared host without verifying CPU, storage quota, process permissions and hosting policy; use the bounded browser path instead.

Migrate the existing database, the private config, and site files; stop any old worker before starting the new one. If a state was interrupted during migration and its private snapshot was not copied, restart the selected state to supersede the old batch. Once cron is configured, start with Colorado alone and monitor the website/database before queuing all missing states. Existing Colorado/Oregon data stays readable during refresh. Old GitHub workflow credentials, if ever created, may be revoked; they are no longer referenced by the application.

## Safety

- Geofabrik state PBF downloads are performed locally, one state at a time. Source files and temporary files remain under the private directory. The worker checks free space before downloading, caps download size (default 4 GiB), limits download rate to 5 MiB/s, uses low CPU scheduling priority and hard subprocess timeouts. Free filesystem space may not reflect a shared-host account quota; this is not a guarantee of safety on Porkbun.
- The same validated extractor provides the ten camper-priority categories plus existing nearest-service reference types. The same MariaDB staging tables, 100-row chunks, 2.5-second CLI pause, server-side global write lock and cooldown protect database writes. A complete state replaces its old snapshot atomically only after validation; old data remains on failure. Default state row limit remains 50,000, with a configurable ceiling of 200,000. Raw extraction itself can be resource-intensive, particularly for large states; monitor memory and disk. Keep enough disk and use host-level memory/CPU limits where appropriate.
- Optional private config keys in osm_resource_import: python (executable), max_download_bytes (100 MiB to 8 GiB), max_state_rows (1 to 200,000). No secrets are required beyond the existing database configuration. Do not increase limits until a smaller state succeeds.
- Local state jobs use fixed Geofabrik URLs, batch IDs, a private working directory, explicit SQL validation, and authenticated Admin/CSRF controls. There is no remote write API. The retired endpoint returns HTTP 410 even if an older FTP deploy left its file behind.
- The public ODbL export continues to contain only the separate source reference layer. Resource candidate review, ownership/deduplication against Scout Resources and public publication remain a separate phase.


## Resource Publication pilot

Run `tools/migrations/20261127_osm_resource_publication.sql` in the primary database. Open Admin > Map Data > Resources > Resource Publication, select a state and service, preview candidates, and publish selected eligible records. A batch is limited to 25 source identities and the pilot has a nationwide cap of 250 published identities until the public map stops loading the full Resource inventory. No external calls, credentials, cron or new downloads. Publication groups the first seven priority services on one OSM feature and creates a native Resource and service rows in the same transaction as its source ledger entry. Native Resource hours, status, fees and verification start unknown; no Scout observation is created. Duplicate source identities across state extracts are excluded; nearby existing Resources, Facilities or Places are held for review. Proximity is a conservative bounding-box check, not proof of same business or Place ownership; review disposition resolution and more detailed geometry matching are future work.

Existing Resource names and URLs must remain unique under current validation, so repeated imported names get a state and source-identity suffix. The original OSM name and selected source facts are retained in the separate publication ledger. Later source refreshes do not overwrite the native Resource or ledger. Source attribution stays attached when existing native OSM Resources are edited. Source baselines can be downloaded from the existing Data licenses page. The ODbL export is separate from Scout-authored observations, but the eventual treatment of edits to OSM-derived fields and database classification still needs review before nationwide publication. Verification is never inferred from an import or ordinary approved edit; explicit verification date is displayed as recorded verification, not a claim of personal Scout visitation.

Future phase: viewport-bounded public map queries, review resolution, additional categories and scale-up. No public publication happens merely by merging the PR or running the migration.


## Public Resource viewport loading

The public map now requests only enabled services within the visible map bounds. Zoom below 7 returns no Resources; zoom 7–9 returns geographic count clusters; zoom 10+ returns at most 250 matching Resources ordered by proximity to viewport center. The API requires viewport bounds, zoom and selected service keys, rather than exposing an unbounded nationwide listing. Searches run before the result limit. Multi-service Resources count once; inactive Resources and Place-owned Facilities remain excluded. Individual detail pages retain the existing full Resource data path. Browser requests debounce and cancel obsolete responses. This is a map-only performance change, not a publication increase. Verify live map behavior and query performance before lifting the pilot cap.
