<?php

declare(strict_types=1);

/* Basic Place information and canonical location questions. */

function llama_place_report_add_basic_location_fields(
    callable $add
): void {
    /* Identity */
    $add('name', 'Place name *', 'basic', 'text', 'name', [
        'required' => true,
        'maxlength' => 200,
        'wide' => true,
        'placeholder' => 'A suggested name will appear here',
        'name_suggestion' => true,
        'help' => 'Use the official or commonly recognized Place name when one exists. For unnamed dispersed locations, use a simple location-neutral name.',
    ]);

    $add('type', 'Place type', 'basic', 'select', 'type', [
        'options' => llama_place_report_place_types(),
        'help' => 'Choose what the Place is, not the land-management agency that owns or manages it.',
    ]);

    $add('description', 'Description', 'basic', 'textarea', 'description', [
        'wide' => true,
        'rows' => 5,
        'min_characters' =>
            llama_place_report_requirement_int(
                'place_report_description_min_characters',
                1500
            ),
        'placeholder' => 'What is this place, what is it like, and why would someone use it?',
        'help' => 'Describe the Place itself. Access conditions, sensory details, rules, pricing, and individual campsite facts have their own questions below.',
    ]);

    /* Coordinates and nearby location */
    $add('latitude', 'Latitude', 'location', 'number', 'latitude', [
        'step' => 'any',
        'min' => '-90',
        'max' => '90',
        'location_field' => true,
        'placeholder' => '37.272376',
    ]);

    $add('longitude', 'Longitude', 'location', 'number', 'longitude', [
        'step' => 'any',
        'min' => '-180',
        'max' => '180',
        'location_field' => true,
        'placeholder' => '-107.882456',
    ]);

    $add('elevation_feet', 'Elevation (ft)', 'location', 'number', 'elevation_feet', [
        'step' => '1',
        'min' => '-1500',
        'max' => '30000',
        'location_field' => true,
        'help' => 'Use the Place elevation rather than the elevation of the nearest town or approach road.',
    ]);

    $add('road', 'Road / route', 'location', 'text', 'road', [
        'location_field' => true,
        'placeholder' => 'Example Creek Rd. / FR 813',
        'help' => 'Enter the road, forest road, route, or access road that directly serves the Place when known.',
    ]);

    $add('city', 'Nearest town / city', 'location', 'text', 'city', [
        'location_field' => true,
        'help' => 'Use the nearest practical community for orientation and services, even when the Place is outside city limits.',
    ]);

    $add('county', 'County / parish / municipality', 'location', 'text', 'county', [
        'location_field' => true,
    ]);

    $stateList = llama_place_report_states();
    $add('state', 'State', 'location', 'select', 'state', [
        'location_field' => true,
        'options' => array_combine($stateList, $stateList) ?: [],
    ]);

    /* Land-management hierarchy: type first, then manager, then local unit. */
    $add('land_type', 'Land type', 'location', 'select', 'land_type', [
        'options' => llama_place_report_land_types(),
        'allow_unknown' => true,
        'location_field' => true,
        'help' => 'Choose the broad ownership or land category first, such as National Forest, BLM, National Park, National Grassland, state land, local land, or private property.',
    ]);

    $add('land_manager', 'Land manager / operator / owner', 'location', 'select', 'land_manager', [
        'options' => llama_place_report_land_managers(),
        'allow_unknown' => true,
        'location_field' => true,
        'help' => 'Choose the agency, organization, business, or owner responsible for this Place.',
    ]);

    $add('region', 'Region / ranger district / local unit', 'location', 'text', 'region', [
        'allow_unknown' => true,
        'location_field' => true,
        'wide' => true,
        'help' => 'Use the most useful local management subdivision when one exists, such as a ranger district, recreation area, park unit, preserve, or regional district.',
    ]);

    /* Observation provenance belongs at the end of the Place facts. */
    $add('visited_at', 'Date visited', 'basic', 'date', 'visited_at', [
        'help' => 'Enter the date you personally visited or field-checked this Place. This is not the date an external source was last updated.',
    ]);
}
