<?php

declare(strict_types=1);

/* Basic information and location questions. */

function llama_place_report_add_basic_location_fields(
    callable $add
): void {
    /* Basic information */
    $add('name', 'Place name *', 'basic', 'text', 'name', [
        'required' => true,
        'maxlength' => 200,
        'wide' => true,
        'placeholder' => 'A suggested name will appear here',
        'name_suggestion' => true,
    ]);
    $add('type', 'Place type', 'basic', 'select', 'type', [
        'options' => llama_place_report_place_types(),
    ]);
    $add('visited_at', 'Date visited', 'basic', 'date', 'visited_at');
    $add('description', 'Description', 'basic', 'textarea', 'description', [
        'wide' => true,
        'rows' => 5,
        'min_characters' =>
            llama_place_report_requirement_int(
                'place_report_description_min_characters',
                1500
            ),
        'placeholder' => 'What is this place, what is it like, and why would someone use it?',
    ]);

    /* Location */
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
    ]);
    $add('road', 'Road', 'location', 'text', 'road', [
        'location_field' => true,
        'placeholder' => 'Example Creek Rd. / FR 813',
    ]);
    $add('city', 'Nearest city / town', 'location', 'text', 'city', [
        'location_field' => true,
    ]);
    $add('county', 'County / Parish / Municipality', 'location', 'text', 'county', [
        'location_field' => true,
    ]);
    $stateList = llama_place_report_states();
    $add('state', 'State', 'location', 'select', 'state', [
        'location_field' => true,
        'options' => array_combine($stateList, $stateList) ?: [],
    ]);
    $add('region', 'Region / ranger district', 'location', 'text', 'region', [
        'allow_unknown' => true,
        'location_field' => true,
    ]);
        $add('land_type', 'Land type', 'location', 'select', 'land_type', [
        'options' => llama_place_report_land_types(),
        'allow_unknown' => true,
        'location_field' => true,
    ]);
    $add('land_manager', 'Manager / operator / owner', 'location', 'select', 'land_manager', [
        'options' => llama_place_report_land_managers(),
        'allow_unknown' => true,
        'location_field' => true,
    ]);

}
