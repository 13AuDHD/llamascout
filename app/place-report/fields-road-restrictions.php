<?php

declare(strict_types=1);

/** Restrictions on reaching the property, not the property's own entry gate. */
function llama_place_report_add_road_restriction_questions(callable $add): void
{
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['road_access'],
    ];
    $add('approach_road_type', 'Access road type', 'road_access', 'select',
        'details.approach_road_type', $common + [
            'options' => [
                'public' => 'Public road',
                'usfs' => 'Forest Service road',
                'blm' => 'BLM road',
                'private' => 'Private road',
                'other' => 'Other',
            ],
        ]);
    $add('approach_permit_required', 'Road access permit required?',
        'road_access', 'tri', 'details.approach_permit_required', $common);
    $add('approach_fee_required', 'Road access fee required?',
        'road_access', 'tri', 'details.approach_fee_required', $common);
    $add('approach_road_fee', 'Road access fee ($)', 'road_access',
        'number', 'details.approach_road_fee', $common + [
            'step' => '.01',
            'min' => '0',
            'placeholder' => '0.00',
            'format' => 'currency',
            'applicable_if' => [[
                'field' => 'approach_fee_required', 'operator' => 'equals', 'value' => '1',
            ]],
        ]);
    $add('approach_gate_present', 'Road gated?', 'road_access',
        'tri', 'details.approach_gate_present', $common);

    $gateYes = ['applicable_if' => [[
        'field' => 'approach_gate_present', 'operator' => 'equals', 'value' => '1',
    ]]];
    $add('approach_gate_access_type', 'Gate access type', 'road_access',
        'select', 'details.approach_gate_access_type', $common + $gateYes + [
            'options' => [
                'always_open' => 'Always open',
                'scheduled' => 'Scheduled hours',
                'code_key' => 'Code or key required',
                'staff' => 'Staff-controlled',
            ],
        ]);
    $scheduled = ['applicable_if' => [[
        'field' => 'approach_gate_access_type', 'operator' => 'equals', 'value' => 'scheduled',
    ]]];
    $hours = [];
    for ($hour = 0; $hour < 24; $hour++) {
        $hours[(string) $hour] = date('g A', mktime($hour, 0));
    }
    $add('approach_gate_opens', 'Gate opening time', 'road_access',
        'select', 'details.approach_gate_opens', $common + $scheduled + ['options' => $hours]);
    $add('approach_gate_closes', 'Gate closing time', 'road_access',
        'select', 'details.approach_gate_closes', $common + $scheduled + ['options' => $hours]);

    $add('approach_restriction_details', 'Access restrictions', 'road_access',
        'textarea', 'details.approach_restriction_details', $common + [
            'rows' => 3,
            'wide' => true,
            'counts_toward_completion' => false,
            'applicable_if' => [['any' => [
                ['field' => 'approach_permit_required', 'operator' => 'equals', 'value' => '1'],
                ['field' => 'approach_fee_required', 'operator' => 'equals', 'value' => '1'],
                ['field' => 'approach_gate_present', 'operator' => 'equals', 'value' => '1'],
            ]]],
        ]);
    $add('approach_access_info_url', 'Official access information URL',
        'road_access', 'url', 'details.approach_access_info_url', $common + [
            'counts_toward_completion' => false,
        ]);
}
