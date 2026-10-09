<?php

declare(strict_types=1);

/* Canonical field registry assembled from smaller section files. */

function llama_place_report_fields(): array
{
    $distance = llama_place_report_distance_options();
    $f = [];

    $add = static function (
        string $key,
        string $label,
        string $section,
        string $type,
        string $storage,
        array $extra = []
    ) use (&$f): void {
        $f[$key] = array_merge(
            [
                'key' => $key,
                'label' => $label,
                'section' => $section,
                'type' => $type,
                'storage' => $storage,
                'points_categories' => [],
                'allow_unknown' => false,
                'counts_toward_completion' => true,
                'completion_group' => null,
                'min_characters' => 0,
                'applicable_if' => [],
                'derived' => false,
            ],
            $extra
        );
    };

    llama_place_report_add_basic_location_fields($add);
    llama_place_report_add_site_fields($add);
    llama_place_report_add_experience_fields($f, $add, $distance);

    // These short categorical answers use the standard three-column card
    // width, just like the ratings immediately below them. Other long text
    // and multiselect fields retain their normal wide presentation.
    foreach (['landscape_primary', 'landscape_details', 'landscape_views'] as $key) {
        if (isset($f[$key])) {
            $f[$key]['wide'] = false;
        }
    }

    // The same canonical fields serve Add a Place, updates, and Scout Reports.
    require_once __DIR__ . '/fields-seasons.php';
    llama_place_report_add_season_fields($f, $add);

    llama_place_report_add_summary_fields($f, $add);
    llama_place_report_apply_applicability($f);

    return $f;
}
