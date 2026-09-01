<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_verified_email();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

$db = db();

$approvedNames = [
    'Alpine Hideaway',
    'Alpine Meadow Camp',
    'Aspen Hollow',
    'Aspen Rest Camp',
    'Backcountry Haven',
    'Boulder Haven',
    'Boulder Rest Camp',
    'Canyon Hideaway',
    'Canyon Rest Camp',
    'Cedar Hollow',
    'Cedar Shade Camp',
    'Cloudline Camp',
    'Creekside Camp',
    'Creekside Hideaway',
    'Deep Forest Camp',
    'Deep Woods Campsite',
    'Desert Hideaway',
    'Desert Oasis',
    'Desert Rest Camp',
    'Distant Ridge Camp',
    'Dry Creek Camp',
    'Evergreen Hideaway',
    'Evergreen Rest Camp',
    'Forest Edge Camp',
    'Forest Haven',
    'Forest Hollow',
    'Forest Rest Camp',
    'High Country Camp',
    'High Desert Camp',
    'High Meadow Camp',
    'High Ridge Camp',
    'Hidden Canyon Camp',
    'Hidden Creek Camp',
    'Hidden Forest Camp',
    'Hidden Meadow Camp',
    'Hidden Ridge Camp',
    'Juniper Camp',
    'Juniper Hideaway',
    'Juniper Hollow',
    'Juniper Rest Camp',
    'Lakeside Camping',
    'Lakeside Hideaway',
    'Lakeside Rest Camp',
    'Little Canyon Camp',
    'Little Creek Camp',
    'Little Meadow Camp',
    'Lone Pine Camp',
    'Lone Pine Hideaway',
    'Meadow Edge Camp',
    'Meadow Haven',
    'Meadow Rest Camp',
    'Mesa Hideaway',
    'Mesa Rest Camp',
    'Mountain Haven',
    'Mountain Rest Camp',
    'Open Sky Camp',
    'Open Sky Hideaway',
    'Pine Hollow',
    'Pine Rest Camp',
    'Ponderosa Camp',
    'Ponderosa Hideaway',
    'Quiet Canyon Camp',
    'Quiet Creek Camp',
    'Quiet Forest Camp',
    'Quiet Meadow Camp',
    'Quiet Ridge Camp',
    'Ridgetop Camp',
    'Ridgetop Hideaway',
    'River Bend Camp',
    'River Bend Hideaway',
    'Riverbank Camp',
    'Riverbank Hideaway',
    'Rocky Hollow',
    'Rocky Rest Camp',
    'Sagebrush Camp',
    'Sagebrush Hideaway',
    'Shaded Grove Camp',
    'Shady Pine Camp',
    'Silent Forest Camp',
    'Stillwater Camp',
    'Stillwater Hideaway',
    'Stone Hollow',
    'Sunset Meadow Camp',
    'Timber Haven',
    'Timber Rest Camp',
    'Tree Line Camp',
    'Valley Hideaway',
    'Valley Rest Camp',
    'Wildflower Camp',
    'Wildflower Meadow',
    'Woodland Haven',
    'Woodland Rest Camp',
];

$usedNames = [];

try {
    $stmt = $db->query(
        'SELECT LOWER(TRIM(name))
         FROM places
         WHERE name IS NOT NULL
           AND TRIM(name) <> ""'
    );

    foreach (
        $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []
        as $name
    ) {
        $usedNames[(string) $name] = true;
    }
} catch (Throwable) {
    // A suggestion can still be returned if the canonical lookup fails.
}

try {
    $stmt = $db->query(
        'SELECT LOWER(TRIM(place_name))
         FROM place_submissions
         WHERE place_name IS NOT NULL
           AND TRIM(place_name) <> ""
           AND status NOT IN ("rejected")'
    );

    foreach (
        $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []
        as $name
    ) {
        $usedNames[(string) $name] = true;
    }
} catch (Throwable) {
    // Do not make the feature unusable if historical submissions are unavailable.
}

$available = array_values(
    array_filter(
        $approvedNames,
        static function (
            string $name
        ) use ($usedNames): bool {
            return !isset(
                $usedNames[
                    strtolower(
                        trim($name)
                    )
                ]
            );
        }
    )
);

if (!$available) {
    $available = $approvedNames;
}

try {
    $index = random_int(
        0,
        count($available) - 1
    );
} catch (Throwable) {
    $index = array_rand($available);
}

echo json_encode(
    [
        'success' => true,
        'name' => $available[$index],
    ],
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
);
