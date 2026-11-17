<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report.php';

$expectInvalid = static function (callable $action, string $label): void {
    try {
        $action();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('FAIL: expected rejection of ' . $label);
};

if (llama_new_place_submission_kind([]) !== 'legacy-bundle') {
    throw new RuntimeException('Older pending Place submissions must retain their legacy mode.');
}
if (llama_new_place_submission_kind(['submission_target_kind' => 'place']) !== 'place') {
    throw new RuntimeException('Place-only submission marker was not honored.');
}
if (llama_new_place_submission_kind([], ['_submission_target_kind' => 'place']) !== 'place') {
    throw new RuntimeException('Place-only target kind did not survive a revision.');
}
$expectInvalid(
    static fn () => llama_new_place_submission_kind(
        ['submission_target_kind' => 'place'], []
    ),
    'changing a legacy pending submission to Place-only'
);
$expectInvalid(
    static fn () => llama_new_place_submission_kind(
        ['submission_target_kind' => 'legacy-bundle'], ['_submission_target_kind' => 'place']
    ),
    'changing an existing Place-only submission back to a bundle'
);
$expectInvalid(
    static fn () => llama_new_place_submission_kind(['submission_target_kind' => 'site']),
    'routing Site creation through the Place submission handler'
);

$place = [
    'name' => 'Example Place',
    'type' => 'campground',
    'visited_at' => '2026-11-17',
    'latitude' => 37.25222,
    'longitude' => -107.2192,
    'description' => 'Observed Place',
];
llama_new_place_assert_single_target($place);
llama_new_place_assert_single_target_input([
    'site_coordinates' => '', 'site_photos_json' => '[]', 'area_bundle_json' => ''
]);

$expectInvalid(
    static fn () => llama_new_place_assert_single_target($place + ['site_number' => '22']),
    'Site answers in a Place-only report'
);
$expectInvalid(
    static fn () => llama_new_place_assert_single_target($place + [
        '_first_site_coordinates' => ['latitude' => 37.2, 'longitude' => -107.2],
    ]),
    'first Site coordinates in a Place-only report'
);
$expectInvalid(
    static fn () => llama_new_place_assert_single_target($place + [
        '_first_area' => ['area_name' => 'Camp Loop'],
    ]),
    'first Area in a Place-only report'
);
$expectInvalid(
    static fn () => llama_new_place_assert_single_target($place + [
        'site_photos' => [['path' => '/uploads/example.jpg']],
    ]),
    'child Site photos in a Place-only report'
);
$expectInvalid(
    static fn () => llama_new_place_assert_single_target_input(['site_coordinates' => '37.2, -107.2']),
    'Site location in a Place-only submit'
);
$expectInvalid(
    static fn () => llama_new_place_assert_single_target_input([
        'site_photos_json' => '[{"path":"/uploads/example.jpg"}]',
    ]),
    'Site photos in a Place-only submit'
);
$expectInvalid(
    static fn () => llama_new_place_assert_single_target_input(['area_bundle_json' => '{"area_name":"Loop"}']),
    'Area bundle in a Place-only submit'
);

// The Place-only path must not use the legacy publisher that creates a Site.
$moderation = (string) file_get_contents(dirname(__DIR__, 2) . '/app/moderation.php');
if (!str_contains($moderation, 'if ($placeOnlySubmission)')
    || !str_contains($moderation, 'llama_scout_report_record_approved_new_place_only(')
    || !str_contains($moderation, 'llama_place_report_publish_data(')) {
    throw new RuntimeException('Place-only approval must use one immutable target and Place-only publication.');
}

echo "PASS: Place-only submission rejects child records and preserves legacy pending reports.\n";
