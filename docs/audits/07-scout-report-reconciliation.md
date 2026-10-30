# V3-007: Approved Scout Report Reconciliation

**Audit date:** 2026-10-29

**Authoritative source:** `Scout Report Final.pdf`

**Repository base:** `v3-development` after merged V3-006

**Method:** Static reconciliation of the approved Scout Report against the finalized canonical registry and its approved-question projection. No application behavior, schema, or production data was changed.

## 1. Result summary

The approved PDF contains **266 questionnaire entries** when all visible questions are counted, including:

- 4 imported/official Basic Information entries
- 3 Place History entries
- 1 questionnaire-only moderator note

The current `llama_place_report_approved_question_groups()` inventory contains **263 approved question slots**.

The difference is explained by a small number of structural mismatches rather than a broad missing-question problem:

1. Four official Basic Information entries are rendered outside the canonical approved-question registry.
2. Seasonal Access has two approved date questions but the registry currently represents them as four month/day questions.
3. Site Overhead Clearance and Parking Clearance are two approved questions but currently share one implementation key.
4. `Flat Walking Surface` is an extra approved-registry question not present in the PDF.
5. `Notes for Moderator` exists in the workflow as contributor/moderator metadata but is intentionally not in the public approved-question groups.

Most approved questions already have a stable canonical implementation key.

## 2. Authoritative group order

The approved PDF order is:

1. Basic Information
2. Location
3. Amenities
4. Operating Hours
5. Road Conditions
6. Vehicle Access
7. Seasonal Access
8. Gate Access
9. Nearest Services
10. Site Info
11. Fees and Reservations
12. Setting and Environment
13. Parking and Maneuvering
14. Accessibility
15. Site Amenities
16. Connectivity
17. Daytime
18. Nighttime
19. Specific Sensory
20. Safety and Hazards
21. Rules and Restrictions
22. Experience and Recommendations
23. Notes
24. Place History
25. Questionnaire Only

The current approved-question registry follows this order closely, with the exceptions documented below.

## 3. Complete question trace

Status meanings:

- **Mapped**: approved question has a direct implementation key.
- **Mapped with mismatch**: implementation exists but grouping, type, options, or semantics differ.
- **Outside approved registry**: implemented/presented elsewhere but not an approved-question slot.
- **Missing distinct key**: approved question has no independent canonical identity.
- **Extra registry question**: current approved inventory contains a question absent from the PDF.
- **Workflow metadata**: intentionally questionnaire-only or history metadata rather than a normal public card.

### Basic Information

| Approved question | Current implementation | Status |
|---|---|---|
| Place Name | `name` | Mapped |
| Place Type | `type` | Mapped |
| Description | `description` | Mapped |
| Official Address | official source/contact presentation cards | Outside approved registry |
| Phone Number | official source/contact presentation cards | Outside approved registry |
| Email Address | official source/contact presentation cards | Outside approved registry |
| Official Links | official source/contact presentation cards | Outside approved registry |

The four official fields are visible data concepts but are not part of `llama_place_report_approved_question_groups()`.

### Location

| Approved question | Current key |
|---|---|
| Latitude | `latitude` |
| Longitude | `longitude` |
| Elevation | `elevation_feet` |
| Road/Route | `road` |
| Nearest City | `city` |
| County/Parish/Municipality | `county` |
| State | `state` |
| Land Type | `land_type` |
| Land manager/owner | `land_manager` |
| Region/Ranger District | `region` |
| Activities | `activities` |

All are mapped.

### Amenities

| Approved question | Current key | Status |
|---|---|---|
| Toilets | `amenity_toilets` | Mapped with answer-option mismatch |
| Potable Water | `amenity_potable_water` | Mapped |
| Trash Bins | `amenity_trash` | Mapped |
| Dump Station | `amenity_dump_station` | Mapped |
| Showers | `amenity_showers` | Mapped |
| Picnic Pavilion/Area | `amenity_picnic_shelter` | Mapped |
| WiFi | `amenity_wifi` | Mapped |
| Laundry | `amenity_laundry` | Mapped |
| Dishwashing Station | `amenity_dishwashing_station` | Mapped |
| Playground | `amenity_playground` | Mapped |
| Dog Park | `amenity_dog_park` | Mapped |
| Amphitheatre | `amenity_amphitheater` | Mapped |
| Camp Host | `amenity_campground_host` | Mapped with answer-option mismatch |
| Camp Store | `amenity_camp_store` | Mapped |
| Trailhead | `amenity_trailhead` | Mapped |
| Fishing Pier | `amenity_fishing_pier` | Mapped |

Approved Toilets options are pit / flush / portable / no / unknown. Current `amenity_toilets` is a tri-state Yes/No/Unknown question, so toilet type is lost.

Approved Camp Host is Yes/No. Current implementation additionally supports `Seasonal`.

### Operating Hours

| Approved question | Current key |
|---|---|
| Open Year Round | `place_open_year_round` |
| Seasonal Closure | `seasonal_closure` |
| Season Opens | `season_begins` |
| Season Closes | `season_ends` |
| Open 24 Hours | `place_open_24_hours` |
| Daily Open | `place_opening_time` |
| Daily Closed | `place_closing_time` |

All seven are directly mapped.

### Road Conditions

| Approved question | Current key |
|---|---|
| Road Type | `approach_road_type` |
| Permit Required | `approach_permit_required` |
| Fee Required | `approach_fee_required` |
| Fee | `approach_road_fee` |
| Road Surface | `road_surface` |
| Road Width | `road_width` |
| Road Condition | `approach_road_condition` |
| Road Hazards | `approach_road_hazards` |
| Deep Ruts | `approach_deep_ruts` |
| Rocks | `rocks` |
| Washboards | `washboards` |
| Mud | `mud_risk` |
| Steep Grades | `steep_grades` |
| Drop-offs | `drop_off_exposure` |
| Water Crossings | `water_crossings` |
| Downed Tree Risk | `downed_tree_risk` |
| Road Accessibility | `approach_access_restrictions` |

All are mapped.

### Vehicle Access

| Approved question | Current key |
|---|---|
| Low Branches/Clearance | `approach_overhead_obstacles` |
| Sedan Accessible | `sedan_accessible` |
| High Clearance | `high_clearance_recommended` |
| 4WD | `four_wheel_drive_recommended` |
| Turnaround Available | `approach_turnaround_available` |

All are mapped.

### Seasonal Access

| Approved question | Current implementation | Status |
|---|---|---|
| Best Months | `best_months` | Mapped |
| Seasonal Road Closure | `approach_seasonal_closure` | Mapped |
| Season Begins | `approach_closure_month` + `approach_closure_day` | Mapped with structural mismatch |
| Season Ends | `approach_reopening_month` + `approach_reopening_day` | Mapped with structural mismatch |
| Hurricane Season Risk | `hurricane_risk` | Mapped |
| Monsoon Season Risk | `monsoon_risk` | Mapped |
| Mud Season Risk | `mud_season_risk` | Mapped |
| Snow Season Risk | `snow_risk` | Mapped |
| Winter Road Maintenance | `approach_winter_maintenance` | Mapped |
| Snow Chains Required | `approach_snow_chains` | Mapped |
| Seasonal Access Restrictions | `approach_seasonal_restrictions` | Mapped |

The PDF defines two date questions. The current approved registry exposes four separate month/day questions.

### Gate Access

| Approved question | Current key |
|---|---|
| Road Gated | `approach_gate_present` |
| Road Gated Access Type | `approach_gate_access_type` |
| Road Gate Opens Time | `approach_gate_opens` |
| Road Gate Closes Time | `approach_gate_closes` |
| Site Gated | `place_entrance_gate` |
| Site Gated Access Type | `place_gate_access_type` |
| Site Gate Opens Time | `place_gate_opening_time` |
| Site Gate Closes Time | `place_gate_closing_time` |

All are mapped. Naming uses Place entrance gate internally for the approved "Site Gated" question.

### Nearest Services

| Approved question | Current key |
|---|---|
| Nearest Town | `nearest_town` |
| Nearest Fuel | `nearest_fuel` |
| Nearest EV Charging | `nearest_ev_charging` |
| Nearest Propane | `nearest_propane` |
| Nearest Alcohol Sales | `nearest_alcohol_sales` |
| Nearest Grocery | `nearest_grocery` |
| Nearest Potable Water | `nearest_water` |
| Nearest Public Toilet | `nearest_toilet` |
| Nearest Hospital/Emergency | `nearest_hospital` |

All are mapped.

### Site Info

| Approved question | Current implementation | Status |
|---|---|---|
| Site name/number | `site_number` | Mapped |
| Site Accessible | `site_accessible` | Mapped |
| Tent suitable | `tent_camping_suitable` | Mapped |
| RV/Trailer suitable | `rv_suitable` | Mapped |
| Site size/capacity | `capacity_size_rating` | Mapped |
| Maximum People | `max_people` | Mapped |
| Maximum Vehicles | `vehicle_capacity` | Mapped |
| Site Length | `site_length_feet` | Mapped |
| Site Width | `site_width_feet` | Mapped |
| Site Overhead Clearance | `overhead_clearance_feet` | Missing distinct key |
| Site Ground Condition | `ground_condition` | Mapped |
| Site Ground Levelness | `levelness` | Mapped |
| Parking Driveway Grade | `parking_grade` | Mapped |

`overhead_clearance_feet` is currently assigned to Parking and Maneuvering as Parking Clearance. The approved PDF contains both Site Overhead Clearance and Parking Clearance as separate questions. One canonical key cannot preserve two independent answers.

### Fees and Reservations

| Approved question | Current key |
|---|---|
| Maximum Stay | `stay_limit_days` |
| Base Nightly Camping | `fee` |
| Extra Vehicle Fee | `extra_vehicle_fee` |
| Reservation Required | `reservation_required` |
| Reservation Fee | `reservation_fee` |
| Reservation URL | `reservation_url` |
| Membership Required | `membership_required` |
| Membership Fee | `membership_fee` |
| Membership URL | `membership_url` |
| Parking/Entrance Required | `parking_entrance_required` |
| Parking/Entrance Fee | `parking_entrance_fee` |
| Check-in Required | `check_in_required` |
| Check-in Time | `check_in_begins` |
| Check-out Required | `check_out_required` |
| Check-out Time | `checkout_ends` |
| Discounts Available | `discounts_available` |

All are mapped.

### Setting and Environment

| Approved question | Current key |
|---|---|
| Primary Setting | `landscape_primary` |
| Setting Details | `landscape_details` |
| Views | `landscape_views` |
| Water Nearby | `environment_water_nearby` |
| Wildlife Common | `environment_wildlife` |
| Critter Activity | `critter_activity` |
| Bugs significant | `environment_bugs` |
| Sun Exposure | `environment_sun_exposure` |
| Environmental Shade | `environment_shade` |
| Tree Cover | `tree_cover` |
| Open Sky | `environment_open_sky` |
| Setting/Environment Notes | `environment_notes` |

All are mapped.

### Parking and Maneuvering

| Approved question | Current implementation | Status |
|---|---|---|
| Parking Surface | `parking_surface` | Mapped |
| Back-in | `back_in` | Mapped |
| Pull through | `pull_through` | Mapped |
| Turnaround Space | `turnaround_space` | Mapped |
| Parking Size | `parking_size` | Mapped |
| Max Parking length | `parking_length_feet` | Mapped |
| Max Vehicle length | `max_vehicle_length_feet` | Mapped |
| Max RV/Trailer length | `max_rv_length_feet` | Mapped |
| Parking Clearance | `overhead_clearance_feet` | Mapped with shared-key conflict |
| Leveling required | `leveling_required` | Mapped |
| Parking/Maneuvering Notes | `parking_notes` | Mapped |

The same `overhead_clearance_feet` key is also the only implementation candidate for approved Site Overhead Clearance.

### Accessibility

| Approved question | Current implementation | Status |
|---|---|---|
| Accessible Picnic Table | `accessible_picnic_table` | Mapped |
| Accessible Toilet | `accessible_toilet` | Mapped |
| Wheelchair Useable | `wheelchair_friendly` | Mapped |
| Outdoor Mobility Device Friendly | `mobility_device_friendly` | Mapped |
| Step Free parking to site | `step_free_access` | Mapped |
| Distance parking to site | `walking_distance_from_vehicle` | Mapped with answer-type mismatch |
| Accessibility Summary | `accessibility_notes` | Mapped |
| Flat Walking Surface | `flat_walking_surface` | Extra registry question |

The PDF asks for distance in feet. Current `walking_distance_from_vehicle` is a categorical range selector.

`flat_walking_surface` is currently in the approved registry but is not present in the approved PDF.

### Site Amenities

| Approved question | Current key |
|---|---|
| Fire Ring | `amenity_fire_ring` |
| Grill | `amenity_grill` |
| Picnic Table | `amenity_picnic_table` |
| Lantern Post | `amenity_lantern_post` |
| Potable Water | `site_potable_water` |
| Hookups | `site_hookups_available` |
| Electrical Service | `hookup_electric_service` |
| Water | `hookup_water` |
| Sewer | `hookup_sewer` |
| Tent Pad | `tent_pad` |
| Tent Pad Width | `tent_pad_width_feet` |
| Tent Pad Length | `tent_pad_length_feet` |
| Food Storage/Bear Box | `amenity_bear_box` |
| Lake Access | `site_lake_access` |
| River Access | `site_river_access` |
| Trail Access | `site_trail_access` |
| WiFi | `site_wifi_rating` |
| Amenities Notes | `site_amenities_notes` |

All are mapped.

### Connectivity

| Approved question | Current key |
|---|---|
| Overall Cell Service | `connectivity_overall` |
| AT&T | `connectivity_att` |
| T-Mobile | `connectivity_t_mobile` |
| Verizon | `connectivity_verizon` |
| Other Cellular | `connectivity_other_cell` |
| Satellite Internet Service | `connectivity_satellite` |
| Service Tested | `connectivity_services_tested` |
| Connectivity Summary | `connectivity_starlink_note` |

All are mapped. The internal key name `connectivity_starlink_note` is narrower than the approved label, but final label is Connectivity Summary.

### Daytime

| Approved question | Current key |
|---|---|
| Social Interaction | `daytime_social_interaction` |
| Noise | `daytime_noise` |
| Traffic | `daytime_traffic` |
| People | `daytime_crowds` |
| Privacy | `daytime_privacy` |
| Natural Light | `daytime_light_pollution` |
| Sensory Comfort | `daytime_sensory_comfort` |
| Felt safe | `felt_safe_daytime_rating` |

All are mapped. The legacy storage/key name for Natural Light still says light pollution.

### Nighttime

| Approved question | Current key |
|---|---|
| Social Interaction | `nighttime_social_interaction` |
| Noise | `nighttime_noise` |
| Traffic | `nighttime_traffic` |
| People | `nighttime_crowds` |
| Privacy | `nighttime_privacy` |
| Artificial Light | `nighttime_light_pollution` |
| Sensory Comfort | `nighttime_sensory_comfort` |
| Felt safe | `felt_safe_nighttime_rating` |

All are mapped.

### Specific Sensory

| Approved question | Current key |
|---|---|
| Dust from Traffic | `sensory_dust_from_traffic` |
| Generator Noise | `sensory_generator_noise` |
| Aircraft Noise | `sensory_aircraft_noise` |
| Train Noise | `sensory_train_noise` |
| Dog(s) Barking | `sensory_dog_barking` |
| Wildlife Noise | `sensory_wildlife_noise` |
| Wind Noise | `sensory_wind_noise` |
| Strong Odors | `sensory_strong_odors` |
| Smoke Risk | `sensory_smoke_risk` |
| Road Exposure | `road_exposure` |
| Visual Exposure | `sensory_visual_exposure` |
| Predictability | `sensory_predictability` |
| Sensory Summary | `sensory_summary` |

All are mapped.

Legacy registered sensory fields such as road noise, human activity, and motorized recreation traffic exist outside this approved inventory and must remain compatibility-only unless explicitly added to a future approved specification.

### Safety and Hazards

| Approved question | Current key |
|---|---|
| Downed Tree Risk | `warning_possible_downed_trees` |
| Trip/Fall Hazard | `fall_hazard` |
| Wildlife Risk | `wildlife_risk` |
| Wildfire Risk | `wildfire_risk` |
| Flash Flood Risk | `flash_flood_risk` |
| Rockfall Risk | `rockfall_risk` |
| Traffic Risk | `traffic_hazard` |
| Blind Turn Nearby | `warning_blind_turn_traffic_nearby` |
| Emergency Vehicle Access | `emergency_access` |

All are mapped.

The separate Road Conditions question `downed_tree_risk` and Safety question `warning_possible_downed_trees` correctly remain distinct approved facts.

### Rules and Restrictions

| Approved question | Current key | Status |
|---|---|---|
| Overnight Stay Legal | `overnight_camping_allowed` | Mapped with options mismatch |
| Dispersed Camping | `dispersed_camping_allowed` | Mapped with options mismatch |
| Designated Sites Only | `designated_sites_only` | Mapped |
| Pets Allowed | `pets_allowed` | Mapped |
| Leash Required | `dogs_required_to_be_leashed` | Mapped |
| Generator Restrictions | `generator_restrictions` | Mapped |
| Generators Prohibited | `generator_prohibited` | Mapped |
| Generator Quiet Hours | `generator_quiet_hours` | Mapped |
| Quiet Hours Begin | `generator_quiet_hours_begin` | Mapped |
| Quiet Hours End | `generator_quiet_hours_end` | Mapped |
| Run-Time Restrictions | `generator_run_restrictions` | Mapped |
| Hours | `generator_max_run_hours` | Mapped |
| Restriction Details | `generator_restriction_details` | Mapped |
| Quiet Hours | `place_quiet_hours` | Mapped |
| Quiet Hours Begin | `place_quiet_hours_begin` | Mapped |
| Quiet Hours End | `place_quiet_hours_end` | Mapped |
| Campfires Allowed | `campfire_allowed` | Mapped with options mismatch |
| Collecting Firewood | `collecting_firewood` | Mapped with options mismatch |
| Charcoal Grills | `place_charcoal_grills_allowed` | Mapped with options mismatch |
| Propane Fire Pits | `place_propane_fire_pits_allowed` | Mapped with options mismatch |
| Conditions/Permit URL | `fire_activity_permit_url` | Mapped |
| Fire Restriction URL | `current_fire_restrictions_url` | Mapped |
| Food Storage Required | `food_storage_required` | Mapped |
| Pack it in/Pack it out | `pack_it_in_pack_it_out` | Mapped |
| Alcohol Allowed | `place_alcohol_permitted` | Mapped with legacy option mismatch |
| Drone Use | `drone_use_legal` | Mapped with options mismatch |
| Target Shooting | `target_shooting_allowed` | Mapped with options mismatch |
| Rules Summary | `place_additional_rules` | Mapped |

The approved PDF says Overnight Stay Legal and Dispersed Camping are Yes/No. Current permission semantics also allow Permit required and Conditional.

For Campfires, Firewood, Drone, and Target Shooting, the PDF allows Unknown / Yes / With permit / No. Current permission semantics also add Conditional.

Charcoal Grills and Propane Fire Pits similarly support Conditional in current code.

Alcohol includes a current `Conditional (legacy)` option that is not in the approved PDF.

### Experience and Recommendations

| Approved question | Current key |
|---|---|
| Site Rating | `site_rating` |
| Condition Rating | `condition_rating` |
| Location Rating | `location_rating` |
| Sunrise View | `experience_sunrise_view` |
| Sunset View | `experience_sunset_view` |
| Overall Scenery | `experience_overall_scenery` |
| Stargazing | `experience_stargazing` |
| Sensory Retreat | `experience_sensory_retreat` |
| Quiet Evening | `experience_quiet_evening` |
| Remote Work | `recommended_remote_work` |
| Overnight Stop | `recommended_overnight_stop` |
| Extended Stay | `recommended_extended_stay` |
| Solo Travelers | `recommended_solo_travel` |
| Families | `recommended_families` |
| Large Groups | `recommended_large_groups` |
| Not recommended for | `not_recommended_for` |
| Experience Summary | `experience_summary` |

All are mapped.

These ratings/recommendations remain part of the approved PDF even though later V3 product direction favors objective facts over review-style content. They must not be silently removed. Any retirement requires an explicit product decision and compatibility plan.

### Notes

| Approved question | Current key |
|---|---|
| Scout Note 1 | `scout_note_1` |
| Scout Note 2 | `scout_note_2` |
| Scout Note 3 | `scout_note_3` |

All are mapped.

### Place History

| Approved question | Current key |
|---|---|
| Date Visited | `visited_at` |
| Length of Stay | `visit_length_of_stay` |
| Type of Setup | `visit_setup_types` |

All are mapped.

These are historical/report metadata and should not be treated as ordinary mutable current Place facts.

### Questionnaire Only

| Approved question | Current implementation | Status |
|---|---|---|
| Notes for Moderator | `contributor_notes` / submission moderation metadata | Workflow metadata |

The approved question is intentionally questionnaire-only and should not be published as a normal Scout Report card.

## 4. Count reconciliation

The approved PDF count and current approved-registry count reconcile as follows:

```text
Approved PDF visible questions                         266
- official address / phone / email / links outside registry   -4
+ Seasonal Access begins/ends split into four keys             +2
- Site Overhead Clearance collapsed into Parking Clearance     -1
+ Flat Walking Surface extra approved-registry question        +1
- Notes for Moderator excluded from public approved groups     -1
------------------------------------------------------------------
Current approved-question slots                                263
```

This explains the current 263-slot inventory without assuming hidden missing questions elsewhere.

## 5. Confirmed structural mismatches

### 5.1 Official Basic Information is outside the approved registry

Official Address, Phone Number, Email Address, and Official Links belong in the approved Basic Information presentation but are currently supplementary official-source cards.

V3 must decide whether these become canonical display-only fields in the shared registry or remain a formally attached official-information projection.

They should not continue as an undocumented parallel presentation system.

### 5.2 Seasonal Access uses four implementation questions for two approved questions

Approved:

- Season Begins
- Season Ends

Current:

- `approach_closure_month`
- `approach_closure_day`
- `approach_reopening_month`
- `approach_reopening_day`

This violates exact approved question identity/order even though the same information can be collected.

### 5.3 Two clearance questions share one key

Approved PDF has both:

- Site Overhead Clearance
- Parking Clearance

Current approved inventory has only:

- `overhead_clearance_feet`

The current key is grouped under Parking and Maneuvering.

A distinct Site-overhead versus parking-clearance model is required if both approved questions remain.

### 5.4 Flat Walking Surface is an extra approved question

`flat_walking_surface` is in the current Accessibility approved group but is absent from the approved PDF.

It may remain as compatibility/additional data, but it should not be an approved-question slot unless the specification is intentionally revised.

### 5.5 Moderator Notes are workflow-only

The approved PDF explicitly says Notes for Moderator is questionnaire-only.

Current separation through contribution/moderation metadata is appropriate, but the approved mapping should document that exception explicitly so it is not mistaken for a missing public field.

## 6. Confirmed answer-option and type mismatches

### Toilets

Approved:

`pit / flush / portable / no / unknown`

Current `amenity_toilets` is a tri-state Yes / No / Unknown field.

Result: availability is stored, but toilet type is not represented by the approved question.

### Camp Host

Approved: Yes / No.

Current: Yes / No / Seasonal.

### Seasonal Access dates

Approved: one date for Season Begins and one date for Season Ends.

Current: separate month and day controls.

### Distance parking to site

Approved: feet.

Current `walking_distance_from_vehicle`: categorical ranges such as under 50 ft, 50-100 ft, and 500+ ft.

### Permission-style rules

Current permission semantics support:

- No
- Yes
- Permit required
- Conditional

The approved PDF is narrower for several questions.

Overnight Stay Legal and Dispersed Camping are approved as Yes/No.

Campfires, Collecting Firewood, Drone Use, and Target Shooting approve a permit state but do not list Conditional.

Charcoal Grills and Propane Fire Pits likewise have a current Conditional state beyond the approved answer vocabulary.

### Alcohol

Approved:

- Unknown
- Yes
- No glass
- No

Current also retains `Conditional (legacy)`.

That legacy value needs compatibility handling without exposing it as a normal approved answer unless intentionally adopted.

## 7. Dependencies

Most obvious approved parent/child dependencies exist in current flow logic:

- Seasonal Closure -> season dates
- Open 24 Hours -> daily times
- Road permit/fee -> fee details
- Road Hazards -> hazard-specific ratings
- Seasonal Road Closure -> closure/reopening values
- Gate present -> gate details
- Reservation Required -> reservation details
- Membership Required -> membership details
- Check-in/check-out required -> times
- Hookups -> hookup detail
- Tent Pad -> dimensions
- WiFi availability -> Site WiFi rating
- Generator restrictions -> generator child rules
- Pets Allowed -> leash requirement
- Quiet Hours -> begin/end times

However, V3-003 confirmed that some interfaces deliberately bypass applicability in reconciliation mode. Therefore a correct canonical dependency does not guarantee identical visible behavior across every current interface.

## 8. Hidden applicable questions

V3-003 established that scoped contributor and Admin reconciliation modes can expose questions without ordinary dependency hiding.

Conversely, legacy/compatibility handling can move questions out of normal presentation.

The approved suitability questions `tent_camping_suitable` and `rv_suitable` are restored as active by `flow-site-selection.php` and should remain visible in the approved Site Info group.

Any future cleanup must preserve that restoration and not treat these approved questions as retired merely because older compatibility logic references them.

## 9. Retired and additional fields

The final registry contains fields not in the approved PDF.

Examples include:

- `flat_walking_surface` currently incorrectly marked approved
- legacy sensory details such as road noise and human activity
- compatibility fit/recommendation fields
- additional Place operating/closure metadata
- extra shared-amenity/source-context fields
- source/import support fields

These fields are not automatically wrong.

V3 should classify every non-approved field as one of:

- compatibility-only
- derived
- imported/source metadata
- Admin-only
- supplemental optional data
- retired
- proposed future question

The approved questionnaire should remain a distinct inventory.

## 10. Ownership findings

The approved PDF strongly separates Place Information from Site Information, but it does not fully define every Place/Area/Site inheritance rule.

Current scope ownership therefore should not be rewritten from visual grouping alone.

Confirmed safe conclusions:

- Basic, Location, shared Amenities, Operating Hours, road/access, and nearest-service facts are Place or Place/Area concepts.
- Site identity, dimensions, parking, Site amenities, Site accessibility, sensory/environment observations, safety observations, and experience questions are Site-centered concepts.
- Place History fields belong to immutable report history, not current Place/Site fact storage.
- Official contact data is provider/source data rather than community observation.
- Rules, fees, and some policy facts require explicit scope decisions because a Place policy may apply to all Sites while some locations can have Area/Site exceptions.

The existing `flow-scopes.php` remains the current ownership authority until Milestone 1 deliberately changes it.

## 11. Historical compatibility requirements

V3 must preserve existing historical answers even when approved definitions change.

Specific requirements from this reconciliation:

1. Do not reuse `overhead_clearance_feet` for two new meanings. Introduce explicit migration/compatibility behavior if the approved questions are separated.
2. Preserve the four current Seasonal Access month/day keys while introducing a canonical approved date representation or an adapter.
3. Preserve `flat_walking_surface` historical data even if it loses approved-question status.
4. Preserve legacy Conditional permission values while preventing them from silently redefining the approved answer set.
5. Preserve existing toilet Yes/No data if the approved toilet-type question becomes multi-valued.
6. Preserve `connectivity_starlink_note` storage if the canonical display identity is Connectivity Summary.
7. Preserve old sensory/storage keys where labels have evolved, such as Natural Light using a legacy light-pollution path.
8. Preserve questionnaire-only moderator notes separately from published current facts.

## 12. Milestone 0 implementation conclusions

The audits support reusing the existing backend rather than replacing it.

### Reuse

- stable canonical field keys where they match approved questions
- report parsing and answer states
- Place/Area/Site scope framework
- partial update behavior
- moderation workflows
- source/import infrastructure
- history/contribution records
- auth/access framework
- shared form components
- published storage mappings where semantics remain correct

### Modify

- approved-question inventory and exact mapping
- official Basic Information integration
- Seasonal Access date identity
- Site Overhead Clearance versus Parking Clearance
- Accessibility approved inventory
- answer-option mismatches
- Admin supplemental persistence paths
- scoped history/evidence retention
- Public/Community field metadata
- first-new-Site earned access
- cross-provider source authority and duplicate reconciliation
- historical definition/version snapshots

## 13. Initial Milestone 1 recommendation

Do not begin by rewriting forms.

The smallest safe first implementation task is to create a machine-readable canonical reconciliation layer that explicitly classifies:

- approved question identity
- approved label
- approved group
- approved order
- scope
- answer type
- approved options
- current key
- compatibility status

Then update `llama_place_report_fields()` to consume that shared metadata without changing persistence in the same task.

This should be implemented as one bounded change with diagnostics proving:

- all approved questions have a mapping
- no approved key appears twice
- no unapproved key is accidentally marked approved
- group/order exactly matches the approved specification
- compatibility fields remain readable

No application implementation was performed in V3-007.
