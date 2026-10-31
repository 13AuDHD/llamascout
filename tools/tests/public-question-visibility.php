<?php

declare(strict_types=1);

// Run: php tools/tests/public-question-visibility.php
require_once dirname(__DIR__, 2) . '/app/place-report/approved-questions.php';

$visibility = llama_place_report_approved_question_visibility();

$expectedPublic = [
    'amenity_toilets',
    'place_open_year_round',
    'place_open_24_hours',
    'season_begins',
    'season_ends',
    'fee',
    'reservation_required',
    'pets_allowed',
    'campfire_allowed',
    'place_quiet_hours',
];
$expectedPrivate = [
    'latitude',
    'longitude',
    'connectivity_att',
    'daytime_noise',
    'nighttime_privacy',
    'felt_safe_nighttime_rating',
    'scout_note_1',
    'site_length_feet',
    'experience_summary',
];

$failed = 0;
foreach ($expectedPublic as $key) {
    if (($visibility[$key] ?? null) !== 'public') {
        fwrite(STDERR, "Expected public: {$key}\n");
        $failed++;
    }
}
foreach ($expectedPrivate as $key) {
    if (($visibility[$key] ?? null) !== 'community') {
        fwrite(STDERR, "Expected community-only: {$key}\n");
        $failed++;
    }
}
if ($failed) {
    exit(1);
}
echo "Public question visibility checks passed.\n";
