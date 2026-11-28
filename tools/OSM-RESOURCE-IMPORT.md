# Map Data > Resources: local state import and refresh

The Admin page tracks all 50 states plus DC and retains all previously imported snapshots. Buttons write directly into the primary MariaDB queue. No GitHub token, worker key, remote import endpoint, or Actions extraction is used. GitHub remains source control, CI and existing deployment only.

## Server requirements and migration

The local PHP CLI job reads the same private/config.php and db() connection as the rest of Llama Scout. It runs independently of web requests. Existing queue/staging tables from 20261127_osm_resource_import.sql are reused; no new migration is needed. If those tables were never created, run that existing migration first.

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

This is a server setup step, not an API credential. Each run processes at most one state; overlapping cron invocations exit without doing work. No need to leave the Admin page open. The local worker readiness status and job stages appear in Admin. If no worker is detected, the buttons still queue work, but it cannot start until the scheduler and local dependencies are installed. Do not launch raw PBF extraction on the current shared host without verifying CPU, storage quota, process permissions and hosting policy. Queueing a state does not itself perform an extraction.

Migrate the existing database, the private config, and site files; stop any old worker before starting the new one. If a state was interrupted during migration and its private snapshot was not copied, restart the selected state to supersede the old batch. Once cron is configured, start with Colorado alone and monitor the website/database before queuing all missing states. Existing Colorado/Oregon data stays readable during refresh. Old GitHub workflow credentials, if ever created, may be revoked; they are no longer referenced by the application.

## Safety

- Geofabrik state PBF downloads are performed locally, one state at a time. Source files and temporary files remain under the private directory. The worker checks free space before downloading, caps download size (default 4 GiB), limits download rate to 5 MiB/s, uses low CPU scheduling priority and hard subprocess timeouts. Free filesystem space may not reflect a shared-host account quota; this is not a guarantee of safety on Porkbun.
- The same validated extractor provides the ten camper-priority categories plus existing nearest-service reference types. The same MariaDB staging tables, 100-row chunks, 2.5-second CLI pause, server-side global write lock and cooldown protect database writes. A complete state replaces its old snapshot atomically only after validation; old data remains on failure. Default state row limit remains 50,000, with a configurable ceiling of 200,000. Raw extraction itself can be resource-intensive, particularly for large states; monitor memory and disk. Keep enough disk and use host-level memory/CPU limits where appropriate.
- Optional private config keys in osm_resource_import: python (executable), max_download_bytes (100 MiB to 8 GiB), max_state_rows (1 to 200,000). No secrets are required beyond the existing database configuration. Do not increase limits until a smaller state succeeds.
- Local state jobs use fixed Geofabrik URLs, batch IDs, a private working directory, explicit SQL validation, and authenticated Admin/CSRF controls. There is no remote write API. The retired endpoint returns HTTP 410 even if an older FTP deploy left its file behind.
- The public ODbL export continues to contain only the separate source reference layer. Resource candidate review, ownership/deduplication against Scout Resources and public publication remain a separate phase.
