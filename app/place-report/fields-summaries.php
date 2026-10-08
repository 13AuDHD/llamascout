<?php

declare(strict_types=1);

/* Summary questions and contributor helper text. */

function llama_place_report_add_summary_fields(
    array &$f,
    callable $add
): void {
    /* Summaries */
    /*
     * Summaries stay canonically grouped under `summaries` so moderation,
     * history, and any scoring/grouping logic continue treating them as one
     * summary group. `display_section` only controls where the questions are
     * rendered in the form and Scout Report.
     */
    $add('access_summary', 'Access summary', 'summaries', 'textarea', 'access_summary', [
        'wide' => true,
        'rows' => 4,
        'min_characters' =>
            llama_place_report_requirement_int(
                'place_report_access_summary_min_characters',
                1500
            ),
        'display_section' => 'road_access',
        'placeholder' => 'Summarize the road, vehicle requirements, turnaround, leveling, and mobility access.',
    ]);
    $add('sensory_summary', 'Sensory summary', 'summaries', 'textarea', 'sensory_summary', [
        'wide' => true,
        'rows' => 4,
        'min_characters' =>
            llama_place_report_requirement_int(
                'place_report_sensory_summary_min_characters',
                1500
            ),
        'display_section' => 'sensory',
        'placeholder' => 'Describe the overall sensory experience and any major day/night differences.',
    ]);
    $add('contributor_notes', 'Notes for the reviewer', 'summaries', 'textarea', 'contributor_notes', [
        'wide' => true,
        'rows' => 4,
        'placeholder' => 'Anything uncertain, unusual, temporary, or important for the moderator to know.',
        'hide_public' => true,
        'counts_toward_completion' => false,
    ]);

    /*
     * Optional contributor helper text.
     *
     * These explanations are deliberately kept on the canonical field
     * definitions so Add Place can show an info popup without duplicating
     * question-specific guidance in the template. Other consumers may choose
     * whether or not to display it.
     */
    $fieldHelp = [
        'type' =>
            'Choose the kind of place someone would recognize when planning a stay. For example: dispersed camping for an undeveloped public-land site, retail parking for an overnight store lot, or travel center for a Loves-style stop.',
        'region' =>
            'Use the local administrative area when one exists. Examples include Pagosa Ranger District, Moab Field Office, a national park district, or a named city neighborhood. For an urban place with no useful region, leave this blank.',
        'land_manager' =>
            'Choose the specific agency, business, membership program, organization, or owner that manages or operates this Place. The available choices narrow automatically from the state, Place type, and property type. Choose Other when the correct organization is not listed.',
        'land_type' =>
            'Describe the property or land system the Place is on. Examples include National Forest, State Park, Water Management District, retail property, travel center property, medical property, hosted membership property, roadside right-of-way, or private land.',
        'vehicle_capacity' =>
            'Estimate how many normal vehicles can fit without blocking the road, entrance, turnaround, or neighboring sites. Do not count sketchy edge parking just because a vehicle could technically squeeze there.',
        'campsite_count' =>
            'Enter the total number of campsites or camping spaces at the campground or developed camping property. Choose ? when you could not determine the count.',
        'site_number' =>
            'If this Scout report describes a specific numbered or named campsite, enter its site identifier here. Leave this blank for a campground-level report or an unnumbered site.',
        'site_hookups_available' =>
            'Choose Yes when the campsite being reported has one or more utility hookups directly at the site. Property-wide electricity, potable water, or a dump station in Amenities does not count as a campsite hookup.',
        'hookup_electric' =>
            'Choose Yes when electrical service is available directly at the campsite.',
        'hookup_electric_service' =>
            'Choose the electrical service available at this campsite. Select the combined option when the pedestal offers more than one receptacle size.',
        'hookup_water' =>
            'Choose Yes when potable-water service connects directly at the campsite.',
        'hookup_sewer' =>
            'Choose Yes when the campsite has its own sewer connection. A shared campground dump station belongs in Amenities instead.',
        'max_vehicle_length_feet' =>
            'Estimate the longest single vehicle that could reasonably enter, park, and maneuver here. Think about the entire approach and parking area, not just whether a long vehicle physically fits in one spot.',
        'max_rv_length_feet' =>
            'Enter the longest self-propelled RV or motorhome that can safely use this location. This can differ from the maximum trailer length and general vehicle length.',
        'max_trailer_length_feet' =>
            'Estimate the longest trailer that could reasonably reach the Place and maneuver into position. Consider tight turns, backing room, turnaround space, and the approach road.',
        'ground_condition' =>
            'Describe the ground where someone would actually park or camp, not the access road. Examples include firm and level, rocky, sandy, grassy, soft, mud-prone, or mixed.',
        'leveling_required' =>
            'Choose Yes when most campers would probably need leveling blocks, ramps, or careful positioning to get reasonably level. This is different from simply noticing that the ground is not perfectly flat.',
        'site_open_sky' =>
            'Rate how open the sky is directly over and around the site. Heavy tree canopy, canyon walls, buildings, or other obstructions reduce open sky and may affect solar or satellite use.',
        'site_access_difficulty' =>
            'Rate the final entry into the actual parking or camping spot. A road can be easy while the last turn, slope, rut, or entrance into the site itself is difficult.',
        'road_overall_difficulty' =>
            'Rate the access road as a whole. Consider surface, rocks, ruts, grades, width, water crossings, and other obstacles from the normal road network to the Place.',
        'road_stress' =>
            'This is about how stressful the drive feels, not just technical difficulty. Narrow roads, exposure, blind corners, drop-offs, traffic, or nowhere to pass can make an otherwise easy road stressful.',
        'sensory_smoke_risk' =>
            'Rate the chance that smoke from nearby campsites or campfires will drift into this site. Closer neighboring campsites, frequent campfires, and normal wind direction can increase the risk; more separation generally lowers it.',
        'sensory_strong_odors' =>
            'Rate the likelihood or intensity of strong unpleasant odors at the site. Examples include animal waste, dumpsters, sewage, factories or industrial activity, livestock, or other persistent smells.',
        'daytime_sensory_comfort' =>
            'Give an overall daytime sensory rating using what you observed: noise, traffic, people, light, smells, movement, and unpredictability. This is broader than any one sensory question.',
        'nighttime_sensory_comfort' =>
            'Give an overall nighttime sensory rating using what you observed: noise, traffic, people, lighting, smells, movement, and unpredictability. Day and night can be very different.',
        'landscape_primary' =>
            'Choose the one setting that best describes the Place itself. Use Mixed / transitional when two major settings genuinely overlap.',
        'critter_activity' =>
            'Rate rodents and other small animals that are likely to get into vehicles, food, trash, or gear. This is separate from dangerous wildlife risk.',
        'hurricane_risk' =>
            'Rate how much hurricanes or hurricane-force tropical systems affect the normal seasonal usability or access risk at this Place.',
        'heat_season_risk' =>
            'Rate how much extreme seasonal heat affects normal use or safety here. Consider dangerous daytime temperatures, lack of shade, vehicle or equipment stress, and seasonal closures caused by heat.',
        'seasonal_closure' =>
            'Choose Yes when this Place normally closes during any recurring part of the year, whether because of winter snow, spring mud, summer heat, fire season, flooding, wildlife management, or another seasonal rule.',
        'sensory_visual_exposure' =>
            'How visually exposed does the site feel to roads, neighboring campers, pedestrians, homes, or businesses? A high rating means people can easily see into or through the site.',
        'sensory_predictability' =>
            'Rate how consistent the environment is. Low means sudden or irregular traffic, people, noises, lights, or other surprises are common. High means the environment is steady and easy to anticipate.',
        'mobility_device_friendly' =>
            'Think about mobility aids such as a cane, walker, rollator, crutches, or mobility scooter. Wheelchair access has its own question. Consider rocks, roots, mud, slopes, and other obstacles around the site.',
        'wildfire_risk' =>
            'Consider the wildfire danger around the Place and, especially, whether dense trees, brush, or fire between the site and the exit could make leaving difficult or unsafe. This is about the setting and escape risk, not a current fire forecast.',
        'wildlife_risk' =>
            'Choose Yes when potentially dangerous wildlife is a realistic concern here. Examples include bears, mountain lions, bison, venomous snakes, or other animals that could seriously injure someone.',
        'traffic_hazard' =>
            'Choose Yes when the site is close enough to a road that passing vehicles could create a safety hazard, such as vehicles passing immediately beside the campsite, parking area, or people outside the vehicle.',
        'emergency_access' =>
            'Can an ambulance, fire engine, or other normal emergency vehicle reasonably reach this location? A Place may be drivable in your own vehicle but still be unsuitable for an ambulance or rescue vehicle. If evacuation would realistically require an airlift, answer No.',
        'warning_motorized_recreation_traffic' =>
            'Choose Yes when OHVs, side-by-sides, ATVs, dirt bikes, or similar recreational vehicles regularly travel through or immediately around the Place.',
        'warning_blind_turn_traffic_nearby' =>
            'Choose Yes when a nearby curve or blind turn limits visibility of the campsite, entrance, or parked vehicles, making it harder for approaching traffic to see the Place in time.',
        'road_exposure' =>
            'How exposed is the campsite itself to the nearby road and passing traffic? Consider how close vehicles pass, how visible the site is from the road, and whether traffic feels intrusive.',
        'designated_sites_only' =>
            'Choose Yes when camping is legally limited to marked, numbered, or otherwise designated sites. This is stricter than merely encouraging people to reuse existing disturbed campsites.',
        'food_storage_required' =>
            'Choose Yes when food, trash, coolers, toiletries, or other scented items must be stored in a specific way, such as a bear box, approved bear-resistant container, or hard-sided vehicle.',
        'generator_restrictions' =>
            'Choose Yes when generator use has any special restriction, such as quiet hours, limited operating hours, generator-free loops, seasonal limits, or a complete prohibition.',
        'generator_quiet_hours' =>
            'Choose Yes when generator use is specifically restricted during a recurring quiet-hours window. Choose No when the generator restriction is something else, such as a generator-free loop or seasonal prohibition.',
        'generator_quiet_hours_begin' =>
            'Choose when generator quiet hours normally begin. Use Sunset when the rule begins at sunset rather than a fixed clock time.',
        'generator_quiet_hours_end' =>
            'Choose when generator quiet hours normally end. Use Sunrise when the rule ends at sunrise rather than a fixed clock time.',
        'existing_sites_encouraged' =>
            'Choose Yes when the land manager asks campers to use already-disturbed or established sites when possible, but does not strictly require camping in marked designated sites.',
        'residential_use_prohibited' =>
            'This refers to rules against using the Place as a residence or long-term living location. Temporary overnight camping may still be allowed even when residential use is prohibited.',
        'monsoon_risk' =>
            'During monsoon season or other intense summer storms, consider whether heavy rain could flood the site or access road, create washes, turn the road to mud, or leave a vehicle stuck or unable to get out.',
        'stay_limit_days' =>
            'Choose the normal stay limit that applies here. Use Permit Limit when the permit itself controls how long someone may remain, or Varies by season when the limit changes during the year.',
        'reservation_required' =>
            'Choose Yes when a reservation must be made before staying overnight. Choose No when overnight use is first-come, first-served or otherwise does not require a reservation.',
        'membership_required' =>
            'Choose Yes when overnight use requires membership in a program, club, campground network, hosted-stay service, or similar organization.',
        'membership_url' =>
            'Use the official membership or enrollment page when membership is required.',
        'membership_fee' =>
            'Enter the required membership cost that applies to using this Place. Enter 0.00 when membership is required but free.',
        'check_in_required' =>
            'Choose Yes when someone must formally check in with a host, office, kiosk, desk, app, or other process before using the overnight space.',
        'reservation_url' =>
            'Use the official reservation or booking page for this Place when a reservation is required.',
        'reservation_fee' =>
            'Enter any separate reservation or booking fee. Enter 0.00 when there is no reservation fee. Do not include the camping, entrance, facility, or parking fee here.',
        'check_in_begins' =>
            'Choose the earliest normal check-in time. Use Anytime when check-in is required but arrival is allowed at any hour.',
        'check_out_required' =>
            'Choose Yes when the Place has a formal checkout requirement or departure deadline.',
        'checkout_ends' =>
            'Choose the latest normal checkout time. Use Anytime when checkout is required but departure is unrestricted by clock time.',
        'fee' =>
            'Enter the camping or overnight-stay fee. Enter 0.00 when overnight camping is free. Do not include entrance, facility, or parking fees here.',
        'entrance_facility_fee' =>
            'Enter any separate entrance, day-use, access, or facility fee. Enter 0.00 when there is no such fee.',
        'parking_fee' =>
            'Enter any separate parking fee or pass cost. Enter 0.00 when parking does not require a paid fee or pass.',
    ];

    foreach ($fieldHelp as $fieldKey => $helpText) {
        if (isset($f[$fieldKey])) {
            $f[$fieldKey]['help'] = $helpText;
        }
    }

}
