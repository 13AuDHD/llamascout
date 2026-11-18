<?php

declare(strict_types=1);

/**
 * Original Resource-only Experience question definitions, moved unchanged
 * from the shared Resource Experience renderer.
 *
 * This is the source of truth for the EXISTING Resource and linked Facility
 * Experience form. It is NOT an approval to publish new keys through the
 * Place/Site/Area canonical registry or immutable Scout Report tables.
 *
 * @return array<string,array{0:string,1:string,2?:array<string,string>}>
 */
function llama_resource_service_question_definitions(): array
{
    return [
        'service_water_type' => ['Water classification', 'select', [
            'potable' => 'Potable (safe to drink)', 'non_potable' => 'Non-potable',
            'do_not_drink' => 'Do not drink', 'unmarked' => 'Not marked / unknown',
        ]],
        'service_water_key_required' => ['Key needed for water access?', 'tri'],
        'service_water_key_type' => ['Type of key', 'select', [
            'standard' => 'Standard key', 'hose_bib' => 'Hose bib / sillcock key',
            'coded' => 'Code / keypad', 'staff' => 'Ask staff', 'other' => 'Other',
        ]],
        'service_water_fill_points' => ['Number of fill points', 'number'],
        'service_shower_minutes' => ['Minutes per timed shower', 'number'],
        'service_toilet_stalls' => ['Number of stalls', 'number'],
        'service_toilet_urinals' => ['Number of urinals', 'number'],
        'service_laundry_machine_types' => ['Washing machine types', 'multiselect', [
            'front' => 'Front-loading', 'top' => 'Top-loading',
            'oversized' => 'Oversized / commercial',
        ]],
        'service_fuel_grades' => ['Fuel grades available', 'multiselect', [
            '85' => '85', '87' => '87', '88' => '88', '89' => '89',
            '91' => '91', '93' => '93', 'diesel' => 'Diesel',
            'diesel_2' => 'Diesel #2', 'biodiesel' => 'Biodiesel',
            'e85' => 'E85', 'ethanol_free' => 'Ethanol-free gasoline',
        ]],
        'service_fuel_pumps' => ['Number of fuel pumps', 'number'],
        'service_fuel_pay_at_pump' => ['Pay at the pump?', 'tri'],
        'service_ice_types' => ['Type of ice', 'multiselect', [
            'cubed' => 'Cubed', 'crushed' => 'Crushed', 'pellet' => 'Pellet / nugget',
            'block' => 'Block',
        ]],
        'service_ice_bag_sizes' => ['Ice bag sizes', 'multiselect', [
            '5_lb' => '5 lb', '7_lb' => '7 lb', '8_lb' => '8 lb',
            '10_lb' => '10 lb', '16_lb' => '16 lb', '20_lb' => '20 lb',
            'other' => 'Other size',
        ]],
        'service_ice_self_serve' => ['Self-serve ice machine?', 'tri'],
        'service_dump_hose_provided' => ['Dump hose provided?', 'tri'],
        'service_propane_method' => ['Propane service type', 'multiselect', [
            'refill' => 'Tank refill', 'exchange' => 'Tank exchange',
            'bulk' => 'Bulk / vehicle fill',
        ]],
        'service_propane_staff' => ['Staff assistance required?', 'tri'],
        'service_ev_connectors' => ['Charging connectors', 'multiselect', [
            'j1772' => 'J1772', 'ccs' => 'CCS', 'nacs' => 'NACS / Tesla',
            'chademo' => 'CHAdeMO',
        ]],
        'service_ev_stalls' => ['Number of charging stalls', 'number'],
        'service_air_cost' => ['Air cost ($)', 'number'],
        'service_air_gauge' => ['Pressure gauge provided?', 'tri'],
        'service_firewood_bundle' => ['Firewood sold by', 'multiselect', [
            'bundle' => 'Bundle', 'armload' => 'Armload', 'cord' => 'Cord / fraction of cord',
        ]],
        'service_recycling_types' => ['Recyclables accepted', 'multiselect', [
            'paper' => 'Paper / cardboard', 'glass' => 'Glass',
            'plastic' => 'Plastic', 'metal' => 'Cans / metal',
        ]],
        'service_trash_dumpster' => ['Dumpster available?', 'tri'],
        'service_groceries_refrigerated' => ['Refrigerated groceries?', 'tri'],
        'service_access_notes' => ['Experience notes', 'textarea'],
    ];
}

/**
 * Candidate central-registry definitions, still awaiting explicit approval.
 * The keys/labels/choices are from the existing Resource Experience form.
 *
 * @return array<string,array<string,mixed>>
 */
function llama_resource_service_question_candidates(): array
{
    $candidates = [];
    foreach (llama_resource_service_question_definitions() as $key => $definition) {
        if (!preg_match('/^service_[a-z0-9_]+$/D', (string) $key)
            || !in_array($definition[1] ?? '', [
                'tri', 'select', 'multiselect', 'number', 'textarea', 'text'
            ], true)) {
            throw new LogicException('Invalid Resource-only question candidate: ' . $key);
        }
        $candidates[$key] = [
            'key' => (string) $key,
            'label' => (string) $definition[0],
            'type' => (string) $definition[1],
            'options' => (array) ($definition[2] ?? []),
            'existing_definition_source' => 'llama_resource_experience_manifest',
            'review_status' => 'candidate_not_approved',
            'current_storage' => 'resources.experience_json',
            'intended_targets' => ['resource', 'resource_backed_facility'],
        ];
    }
    return $candidates;
}
