# Map Data > Resources: state import and refresh

This is the first stage of the Resource import workflow. All 50 US states and DC are tracked in Admin, including Colorado/Oregon snapshots imported manually using the older workflow. The existing reference export and nearest-service calculations keep working. Added source categories: RV dump stations, showers, laundromats, and waste disposal; existing potable water, propane, toilets, fuel, groceries, and EV charging are included. Priority order is the owner's camper-usefulness order.

## One-time setup

1. Merge and deploy the PR. Run `tools/migrations/20261127_osm_resource_import.sql` in the primary Llama Scout database (NOT RIDB, NPS, or cell coverage). Requires the existing `osm_nearest_services` table; does not modify existing imported rows.
2. In GitHub, create a fine-grained personal access token restricted to the Llama Scout repository with Actions read/write and the required Metadata read permission. Do not give it Contents write or wider account permissions. The server uses it only to dispatch the fixed workflow on main. See https://docs.github.com/en/rest/actions/workflows#create-a-workflow-dispatch-event .
3. Generate a long random worker key (e.g. 64 hex characters) in a password manager. Add a repository Actions secret named `LLAMA_OSM_WORKER_KEY` containing that key. See https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets .
4. In the existing non-public `private/config.php` configuration array, add:

```php
'osm_resource_import' => [
    'github_token' => 'YOUR_FINE_GRAINED_TOKEN',
    'worker_key' => 'YOUR_RANDOM_WORKER_KEY',
],
```

Use the same worker key in the secret and the server config. Never commit either value, paste them into chat or expose them through client-side JavaScript. PHP cURL must be available. The worker only sends validated source reference rows to the fixed HTTPS Llama Scout endpoint.

5. Open Admin > Map Data > Resources. Select Colorado and choose Import / refresh selected, then verify a successful snapshot replacement and the new category counts. Repeat with Oregon. Then Import all missing states, or select smaller sets to manage Actions quota. Refresh imported states queues all states that currently have snapshots. Page can be closed. Refresh status shows persisted progress. Restart selected jobs supersedes their batch identifiers; old workers cannot replace the new snapshot. Restarting does not cancel the old GitHub runner and may use extra minutes.

## Safety and scope

- Extraction runs on GitHub rather than cPanel to avoid downloading gigabyte-scale raw PBF files into shared hosting. No manual artifact downloads or per-state SQL uploads. Two state jobs run concurrently per dispatch. GitHub Actions usage limits/billing and shared-host request limits still apply. The pre-existing manual workflow is a fallback.
- Worker requests are authenticated, limited to 250 records and 1 MB. They transfer deduplicated source identities in sequence, retry transient errors, and stage rows separately. The complete regional snapshot replaces the live one transactionally only after row count and snapshot-date checks. Failure preserves older data. Late requests from superseded batches are rejected. Restart jobs that never start because credentials, workflow quotas or hosting restrictions prevent the worker from reaching Llama Scout.
- Resource candidates are grouped by OSM feature type/id, not blindly combined by spatial proximity. Counts are mapped service rows, not verified Resources. A separate publication/duplicate-review workflow must connect candidates with existing native Resources, handle on-site Facilities, and allow Scout confirmation. No imported candidate is publicly published as a new Resource by this PR. No inferred access, operating status, fees or Scout verification.
- Source data remains in the separate ODbL reference layer. https://llamascout.com/api/osm-nearest-data.php continues to provide regional source data. Preserve provenance and the ODbL obligations when designing publication; separate tables alone do not settle all share-alike questions.

No automatic recurring refresh has been enabled. The Admin controls initiate imports/refreshes explicitly.
