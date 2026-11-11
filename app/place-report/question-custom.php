<?php

declare(strict_types=1);

/**
 * V3: edit this single file to add new Scout Report questions.
 *
 * Example:
 * $add('example_key', 'Example question?', 'site_vehicle', 'tri',
 *      'details.example_key', [
 *          'scope_group' => 'site', // place, area, place_area, site, all
 *          'layout_section' => 'site',
 *          'layout_group' => 'selection_type',
 *          'layout_order' => 50, // position within the layout group
 *          'report_group' => '<an existing approved report group key>',
 *          'report_visibility' => 'community', // public or community
 *          'report_order' => 50, // position among new questions in report group
 *          'allow_unknown' => true,
 *      ]);
 *
 * Field keys must remain stable after publishing. To retire a question,
 * add its key to llama_place_report_retired_question_keys() in fields.php.
 * Existing approved questions retain their authoritative PDF ordering.
 * New fields should specify scope_group, layout_section, layout_group,
 * report_group and report_visibility here, without editing flow-scopes.php,
 * flow-layout.php, or presentation pages.
 *
 * New questions can use V3 storage without a new legacy SQL column.
 * Use scope_group=area for mapped-Area-only facts, not place_area.
 * Type-specific facility questions declare facility_kinds as an array of
 * keys from llama_place_facility_kind_options(). Only verified Area targets
 * may authorize those answers. UI filtering is a separate implementation
 * step and must precede exposing any new facility-specific questions.
 * Actual page permissions and membership gating remain server-side.
 */
function llama_report_register_custom_questions(callable $add): void
{
    /*
     * Site accommodations describe the physical overnight space, regardless
     * of the parent Place Type. They are not booking inventory, availability,
     * RIDB raw site types, or permission to stay overnight.
     *
     * Existing normalized place_campsites.site_type and raw_site_type remain
     * unchanged. These canonical Site answers are approved observations and
     * use the shared scoped/current-answer store, not new legacy columns.
     */
    $siteAccommodationMeta = [
        'scope_group' => 'site',
        'layout_section' => 'site',
        'layout_group' => 'selection_type',
        'report_group' => 'site_info',
        'report_visibility' => 'public',
        'scoped_only_storage' => true,
        'counts_toward_completion' => false,
        'points_categories' => [],
        'allow_unknown' => true,
        'applicable_if' => [],
    ];

    $add(
        'site_accommodation_type',
        'Accommodation Type',
        'site_vehicle',
        'select',
        'site_accommodation.type',
        array_merge($siteAccommodationMeta, [
            'report_order' => 100,
            'layout_order' => 100,
            'options' => [
                'tent_campsite' => 'Tent campsite',
                'tent_platform' => 'Tent platform',
                'rv_space' => 'RV space',
                'vehicle_van_space' => 'Vehicle / van space',
                'mixed_campsite' => 'Mixed-use campsite',
                'cabin' => 'Cabin',
                'yurt' => 'Yurt',
                'glamping_tent' => 'Glamping tent',
                'tiny_home' => 'Tiny home',
                'treehouse' => 'Treehouse',
                'other_lodging' => 'Other furnished lodging',
                'other' => 'Other Site type',
            ],
            'help' => 'Describe what is physically provided at this Site, not the Place Type or its reservation method. A campground may have several different accommodation types.',
        ])
    );

    $add(
        'site_allowed_setups',
        'Allowed Camping Setups',
        'site_vehicle',
        'multiselect',
        'site_accommodation.allowed_setups',
        array_merge($siteAccommodationMeta, [
            'report_order' => 101,
            'layout_order' => 101,
            'wide' => true,
            'options' => [
                'ground_tent' => 'Ground tent',
                'rooftop_tent' => 'Rooftop tent',
                'car_suv' => 'Car / SUV camping',
                'campervan' => 'Van / campervan',
                'truck_camper' => 'Truck camper',
                'motorhome' => 'Motorhome / RV',
                'travel_trailer' => 'Travel trailer',
                'fifth_wheel' => 'Fifth-wheel trailer',
                'pop_up_camper' => 'Pop-up camper',
                'not_applicable' => 'Not applicable (provided lodging only)',
            ],
            'exclusive_options' => ['not_applicable'],
            'summary' => '+ Choose setups',
            'help' => 'Select only guest-owned camping setups explicitly permitted at this Site. For a furnished cabin or yurt with no camping setup allowed, select Not applicable. Do not infer allowed setups from the accommodation type or RV suitability.',
        ])
    );
}
