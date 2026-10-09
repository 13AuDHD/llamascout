<?php

declare(strict_types=1);

/**
 * Phase 3A: non-destructive Scout Report flow metadata.
 *
 * This file intentionally does not change field values, stored data,
 * scoring, completion, validation or current form visibility.
 * Consumer-specific display logic will be wired after scope/schema review.
 */
function llama_place_report_flow_metadata(): array
{
    return [
        'overnight_camping_allowed' => ['flow_group' => 'camping', 'flow_order' => 10],
        'dispersed_camping_allowed' => ['flow_group' => 'camping', 'flow_order' => 20],
        'designated_sites_only' => ['flow_group' => 'camping', 'flow_order' => 30, 'flow_independent_of' => ['dispersed_camping_allowed']],
        'existing_sites_encouraged' => ['flow_group' => 'camping', 'flow_order' => 40],
        'stay_limit_days' => ['flow_group' => 'camping', 'flow_order' => 50],
        'reservation_required' => ['flow_group' => 'booking', 'flow_order' => 10],
        'reservation_fee' => ['flow_group' => 'booking', 'flow_order' => 20],
        'reservation_url' => ['flow_group' => 'booking', 'flow_order' => 90],
        'membership_required' => ['flow_group' => 'membership', 'flow_order' => 10],
        'membership_fee' => ['flow_group' => 'membership', 'flow_order' => 20],
        'membership_url' => ['flow_group' => 'membership', 'flow_order' => 90],
        'check_in_required' => ['flow_group' => 'arrival', 'flow_order' => 10],
        'check_in_begins' => ['flow_group' => 'arrival', 'flow_order' => 20],
        'check_out_required' => ['flow_group' => 'arrival', 'flow_order' => 30],
        'checkout_ends' => ['flow_group' => 'arrival', 'flow_order' => 40],
        'fee' => ['flow_group' => 'camping_fees', 'flow_order' => 10],
        'entrance_facility_fee' => ['flow_group' => 'place_fees', 'flow_order' => 10, 'flow_scope' => ['place']],
        'parking_fee' => ['flow_group' => 'place_fees', 'flow_order' => 20, 'flow_scope' => ['place']],
        'current_fire_restrictions_url' => ['flow_group' => 'fire_safety', 'flow_order' => 90, 'flow_independent_of' => ['campfire_allowed']],
        'campfire_allowed' => ['flow_group' => 'fire_safety', 'flow_order' => 10],
        'collecting_firewood' => ['flow_group' => 'fire_safety', 'flow_order' => 20],
        'generator_restrictions' => ['flow_group' => 'generator', 'flow_order' => 10],
        'generator_quiet_hours' => ['flow_group' => 'generator', 'flow_order' => 20],
        'generator_quiet_hours_begin' => ['flow_group' => 'generator', 'flow_order' => 30],
        'generator_quiet_hours_end' => ['flow_group' => 'generator', 'flow_order' => 40],
        'four_wheel_drive_recommended' => ['flow_group' => 'road_access', 'flow_order' => 30, 'flow_future_options' => ['no', 'recommended', 'required']],
        'site_hookups_available' => ['flow_group' => 'site_hookups', 'flow_order' => 10],
        'hookup_electric' => ['flow_group' => 'site_hookups', 'flow_order' => 20],
        'hookup_electric_service' => ['flow_group' => 'site_hookups', 'flow_order' => 30],
        'hookup_water' => ['flow_group' => 'site_hookups', 'flow_order' => 40],
        'hookup_sewer' => ['flow_group' => 'site_hookups', 'flow_order' => 50],
        'tent_pad' => ['flow_group' => 'site_layout', 'flow_order' => 10],
        'tent_pad_length_feet' => ['flow_group' => 'site_layout', 'flow_order' => 20],
        'tent_pad_width_feet' => ['flow_group' => 'site_layout', 'flow_order' => 30],
    ];
}

function llama_place_report_apply_flow_metadata(array &$fields): void
{
    foreach (llama_place_report_flow_metadata() as $key => $metadata) {
        if (isset($fields[$key])) {
            $fields[$key] = array_merge($fields[$key], $metadata);
        }
    }
}
