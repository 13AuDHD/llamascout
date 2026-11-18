# Resource and linked-Facility reporting field reconciliation

Tracked under issue #179. This describes the **existing** Resource storage contract so that later one-target report writes can be designed without losing data.

## Run the complete, question-by-question inventory

```bash
php tools/audits/resource-report-field-map.php > resource-field-map.md
```

The audit is generated from the actual PHP Resource field registry and `llama_resource_experience_manifest()`. The full output includes every Resource field, every Service card field, every enabled Service type, and **every Service / group / question occurrence**. It also identifies which Experience questions already reuse the approved central Area catalog.

The automated CI contract is `php tools/tests/resource-report-field-reconciliation.php`. It fails if the Resource submission allow-list, read projection, registered Experience options, or required identity distinctions drift.

## Storage ownership today

| Existing data | Authority | What it means | Automatic migration? |
|---|---|---|---|
| Resource ID, name/type, status, coordinates and contact | `resources` | Identity, location, contact and operational metadata | No |
| Parent Place association | `resources.parent_place_id` | Resource-backed Facility link, not an Area target | No |
| Description/notes | `resources.notes` | Resource-level description; question ownership needs a decision | No |
| Service availability, fees, access notes | `resource_services` | Facts about one specific Service, not a Place-wide rule | No |
| Service hours | `resource_weekly_hours` and Resource hour columns | Operational schedules | No |
| Existing Experience answers | `resources.experience_json` | Compound `service/group/question` observations | No |
| Facility Experience question reused from central Area catalog | `resources.experience_json` | Already approved wording/options, but stored for a different verified target | No |
| Resource-only Experience question | `resources.experience_json` | Approved existing Resource/linked-Facility question from the shared Resource Experience manifest; not a Place/Site/Area question | No |
| Legacy mapped Facility | Place map Area + scoped report store | Distinct persisted identity from Resource-backed Facility | No |

## Decisions required before changing persistence

1. Decide whether `resources.notes` is a Scout observation or independent description metadata.
2. Decide whether `resource_services` availability, fee and access details become report questions or remain verified operational facts. A per-Service status must not overwrite the Resource's overall status.
3. **Resolved 2026-11-18:** The existing 26 Service-only Experience questions remain approved for Resources and linked Facilities exactly as written, with unchanged types, options and Service/group ownership. No Place/Site/Area expansion is authorized. History writer activation remains a separate gated step.
4. Define an immutable report identity that includes both the Resource/Facility target **and** the Service/group/question path. The same question key can be independently answered for men's, women's and all-gender restroom groups, and across unrelated Services.
5. Define provenance rules for historical Resource records where authorship, visit dates or moderation evidence are missing. Do not infer contributor observations from Admin update timestamps.

**No table, question, option, history or parent relationship changes are authorized by this audit.** The Resource form still saves the same allow-listed fields; the public read projection still exposes the same legacy detail fields. Resource/Facility data remains in the existing Resource tables until the reviewed persistence design is ready.
