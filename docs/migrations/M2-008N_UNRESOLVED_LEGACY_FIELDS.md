# M2-008N: unresolved legacy fields review

Status: evidence inventory only. **No backfill approved.**

The successful M2-008L GitHub Actions audit reported 231 legacy paths: 138 exact storage matches, 8 normalized-name candidates, and **85 unresolved paths**. This file groups those 85 paths so the next implementation can review them systematically. Classification below is **triage**, not confirmation of equivalent meaning, ownership, or value encoding. Every historical answer remains preserved in the original submission payload until reviewed.

## Review rules

- Never copy a legacy value to a V3 question based only on similar names.
- Validate the question's allowed values, answer-state semantics, historical scope, and Place/Site target.
- Historical Site creation dates are later than submission dates; do not infer original Site ownership from the existence of a newer Site.
- Do not treat group containers such as `sensory.daytime` as scalar answers. Inspect their nested children instead.
- Resolve the approved-inventory versus field-registry discrepancy separately (M2-008L found 68 registry-only keys).
- If a field has no defensible V3 equivalent, preserve its source path and raw value without publishing a fabricated canonical answer.

## Legacy camelCase paths needing explicit aliases (66)

- `access.downedTreeRisk`
- `access.dropOffExposure`
- `access.fourWheelDriveRecommended`
- `access.highClearanceRecommended`
- `access.mudRisk`
- `access.potholes`
- `access.roadDifficulty`
- `access.roadOverallDifficulty`
- `access.roadStress`
- `access.roadSurface`
- `access.roadWidth`
- `access.rocks`
- `access.seasonalClosure`
- `access.sedanAccessible`
- `access.siteAccessDifficulty`
- `access.steepGrades`
- `access.washboards`
- `access.waterCrossings`
- `environment.bugs`
- `environment.forest`
- `environment.forestView`
- `environment.mountains`
- `environment.mountainView`
- `environment.openSky`
- `environment.shade`
- `environment.sunExposure`
- `environment.waterNearby`
- `environment.waterView`
- `environment.wildlife`
- `environment.windExposure`
- `safety.cliffExposure`
- `safety.emergencyAccess`
- `safety.fallHazard`
- `safety.feltSafeDaytime`
- `safety.feltSafeNighttime`
- `safety.flashFloodRisk`
- `safety.rockfallRisk`
- `safety.trafficHazard`
- `safety.wildfireRisk`
- `safety.wildlifeRisk`
- `site.backIn`
- `site.groundCondition`
- `site.levelingRequired`
- `site.levelness`
- `site.maxVehicleLengthFeet`
- `site.openSky`
- `site.parkingSurface`
- `site.pullThrough`
- `site.rvSuitable`
- `site.shade`
- `site.tentCampingSuitable`
- `site.trailerSuitable`
- `site.treeCover`
- `site.turnaroundSpace`
- `site.vehicleCapacity`
- `sensory.aircraftNoise`
- `sensory.dustFromTraffic`
- `sensory.generatorNoise`
- `sensory.humanActivity`
- `sensory.predictability`
- `sensory.roadNoise`
- `sensory.smokeRisk`
- `sensory.strongOdors`
- `sensory.visualExposure`
- `sensory.wildlifeNoise`
- `sensory.windNoise`

## Different path or meaning requiring explicit review (16)

- `amenities.foodStorageRequired`
- `amenities.toilets`
- `connectivity.other`
- `connectivity.starlink`
- `details.desert`
- `details.desert_view`
- `details.forest`
- `details.forest_view`
- `details.max_trailer_length_feet`
- `details.mountain_view`
- `details.mountains`
- `details.road_difficulty`
- `details.warning_no_amenities`
- `details.warning_passing_vehicle_dust`
- `details.water_view`
- `rules.recommended_travel_season`

## Nested container keys (not answers themselves) (3)

- `sensory.daytime`
- `sensory.nighttime`
- `sensory.details`

## Next gate

Build an **explicit alias mapping** with per-field status (`confirmed`, `requires_value_review`, `requires_scope_review`, `unmapped`). Confirm equivalents against the canonical PHP definitions and approved question specification. Only then implement dry-run conversion of the 14 approved legacy submissions.
