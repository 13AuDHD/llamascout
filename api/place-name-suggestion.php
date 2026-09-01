<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_verified_email();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

$db = db();

$approvedNames = [
    'Alpine Camp',
    'Alpine Haven',
    'Alpine Hideaway',
    'Alpine Hollow',
    'Alpine Meadow Camp',
    'Alpine Rest Camp',
    'Alpine Ridge Camp',
    'Alpine Sky Camp',
    'Aspen Camp',
    'Aspen Grove',
    'Aspen Haven',
    'Aspen Hideaway',
    'Aspen Hollow',
    'Aspen Meadow Camp',
    'Aspen Rest Camp',
    'Aspen Ridge Camp',
    'Backcountry Camp',
    'Backcountry Haven',
    'Backcountry Hideaway',
    'Backcountry Rest Camp',
    'Boulder Camp',
    'Boulder Haven',
    'Boulder Hideaway',
    'Boulder Hollow',
    'Boulder Rest Camp',
    'Boulder Ridge Camp',
    'Canyon Camp',
    'Canyon Haven',
    'Canyon Hideaway',
    'Canyon Hollow',
    'Canyon Rest Camp',
    'Canyon Rim Camp',
    'Canyon Shade Camp',
    'Cedar Camp',
    'Cedar Grove',
    'Cedar Haven',
    'Cedar Hideaway',
    'Cedar Hollow',
    'Cedar Rest Camp',
    'Cedar Shade Camp',
    'Cloudline Camp',
    'Cloudline Hideaway',
    'Cloudline Rest Camp',
    'Cloudline Ridge Camp',
    'Creekside Camp',
    'Creekside Haven',
    'Creekside Hideaway',
    'Creekside Rest Camp',
    'Deep Forest Camp',
    'Deep Forest Hideaway',
    'Deep Forest Rest Camp',
    'Deep Woods Camp',
    'Deep Woods Campsite',
    'Deep Woods Hideaway',
    'Desert Camp',
    'Desert Haven',
    'Desert Hideaway',
    'Desert Oasis',
    'Desert Rest Camp',
    'Desert Sky Camp',
    'Distant Ridge Camp',
    'Distant Ridge Hideaway',
    'Distant Ridge Rest Camp',
    'Dry Creek Camp',
    'Dry Creek Hideaway',
    'Dry Creek Rest Camp',
    'Evergreen Camp',
    'Evergreen Grove',
    'Evergreen Haven',
    'Evergreen Hideaway',
    'Evergreen Hollow',
    'Evergreen Rest Camp',
    'Forest Edge Camp',
    'Forest Haven',
    'Forest Hideaway',
    'Forest Hollow',
    'Forest Rest Camp',
    'Forest Shade Camp',
    'High Country Camp',
    'High Country Haven',
    'High Country Hideaway',
    'High Country Rest Camp',
    'High Desert Camp',
    'High Desert Haven',
    'High Desert Hideaway',
    'High Desert Rest Camp',
    'High Meadow Camp',
    'High Meadow Haven',
    'High Meadow Hideaway',
    'High Meadow Rest Camp',
    'High Ridge Camp',
    'High Ridge Haven',
    'High Ridge Hideaway',
    'High Ridge Rest Camp',
    'Hidden Canyon Camp',
    'Hidden Canyon Haven',
    'Hidden Canyon Hideaway',
    'Hidden Creek Camp',
    'Hidden Creek Haven',
    'Hidden Creek Hideaway',
    'Hidden Forest Camp',
    'Hidden Forest Haven',
    'Hidden Forest Hideaway',
    'Hidden Meadow Camp',
    'Hidden Meadow Haven',
    'Hidden Meadow Hideaway',
    'Hidden Ridge Camp',
    'Hidden Ridge Haven',
    'Hidden Ridge Hideaway',
    'Juniper Camp',
    'Juniper Grove',
    'Juniper Haven',
    'Juniper Hideaway',
    'Juniper Hollow',
    'Juniper Rest Camp',
    'Lakeside Camp',
    'Lakeside Camping',
    'Lakeside Haven',
    'Lakeside Hideaway',
    'Lakeside Rest Camp',
    'Little Canyon Camp',
    'Little Canyon Hideaway',
    'Little Creek Camp',
    'Little Creek Hideaway',
    'Little Meadow Camp',
    'Little Meadow Hideaway',
    'Lone Pine Camp',
    'Lone Pine Haven',
    'Lone Pine Hideaway',
    'Lone Pine Rest Camp',
    'Meadow Edge Camp',
    'Meadow Haven',
    'Meadow Hideaway',
    'Meadow Hollow',
    'Meadow Rest Camp',
    'Meadow Shade Camp',
    'Mesa Camp',
    'Mesa Haven',
    'Mesa Hideaway',
    'Mesa Rest Camp',
    'Mesa Sky Camp',
    'Mountain Camp',
    'Mountain Haven',
    'Mountain Hideaway',
    'Mountain Hollow',
    'Mountain Rest Camp',
    'Open Sky Camp',
    'Open Sky Haven',
    'Open Sky Hideaway',
    'Open Sky Rest Camp',
    'Pine Camp',
    'Pine Grove',
    'Pine Haven',
    'Pine Hideaway',
    'Pine Hollow',
    'Pine Rest Camp',
    'Ponderosa Camp',
    'Ponderosa Grove',
    'Ponderosa Haven',
    'Ponderosa Hideaway',
    'Ponderosa Rest Camp',
    'Quiet Canyon Camp',
    'Quiet Canyon Hideaway',
    'Quiet Creek Camp',
    'Quiet Creek Hideaway',
    'Quiet Forest Camp',
    'Quiet Forest Hideaway',
    'Quiet Meadow Camp',
    'Quiet Meadow Hideaway',
    'Quiet Ridge Camp',
    'Quiet Ridge Hideaway',
    'Ridgetop Camp',
    'Ridgetop Haven',
    'Ridgetop Hideaway',
    'Ridgetop Rest Camp',
    'River Bend Camp',
    'River Bend Haven',
    'River Bend Hideaway',
    'River Bend Rest Camp',
    'Riverbank Camp',
    'Riverbank Haven',
    'Riverbank Hideaway',
    'Riverbank Rest Camp',
    'Rocky Camp',
    'Rocky Haven',
    'Rocky Hideaway',
    'Rocky Hollow',
    'Rocky Rest Camp',
    'Sage Camp',
    'Sage Haven',
    'Sage Hideaway',
    'Sage Rest Camp',
    'Sagebrush Camp',
    'Sagebrush Haven',
    'Sagebrush Hideaway',
    'Sagebrush Rest Camp',
    'Shaded Grove Camp',
    'Shaded Grove Hideaway',
    'Shaded Pine Camp',
    'Shaded Pine Hideaway',
    'Silent Forest Camp',
    'Silent Forest Haven',
    'Silent Forest Hideaway',
    'Stillwater Camp',
    'Stillwater Haven',
    'Stillwater Hideaway',
    'Stillwater Rest Camp',
    'Stone Camp',
    'Stone Haven',
    'Stone Hideaway',
    'Stone Hollow',
    'Stone Rest Camp',
    'Sunset Camp',
    'Sunset Haven',
    'Sunset Hideaway',
    'Sunset Meadow Camp',
    'Sunset Rest Camp',
    'Timber Camp',
    'Timber Grove',
    'Timber Haven',
    'Timber Hideaway',
    'Timber Rest Camp',
    'Tree Line Camp',
    'Tree Line Haven',
    'Tree Line Hideaway',
    'Tree Line Rest Camp',
    'Valley Camp',
    'Valley Haven',
    'Valley Hideaway',
    'Valley Rest Camp',
    'Wildflower Camp',
    'Wildflower Haven',
    'Wildflower Hideaway',
    'Wildflower Meadow',
    'Wildflower Rest Camp',
    'Woodland Camp',
    'Woodland Haven',
    'Woodland Hideaway',
    'Woodland Rest Camp',
    'Windy Ridge Camp',
    'Windy Ridge Hideaway',
    'Windy Ridge Rest Camp',
    'Blue Sky Camp',
    'Blue Sky Haven',
    'Blue Sky Hideaway',
    'Blue Sky Rest Camp',
    'Bright Meadow Camp',
    'Bright Meadow Haven',
    'Bright Meadow Hideaway',
    'Clear Sky Camp',
    'Clear Sky Haven',
    'Clear Sky Hideaway',
    'Cool Pines Camp',
    'Cool Pines Hideaway',
    'Cool Pines Rest Camp',
    'Far Ridge Camp',
    'Far Ridge Hideaway',
    'Far Ridge Rest Camp',
    'Gentle Meadow Camp',
    'Gentle Meadow Haven',
    'Gentle Meadow Hideaway',
    'Green Valley Camp',
    'Green Valley Haven',
    'Green Valley Hideaway',
    'High Plains Camp',
    'High Plains Haven',
    'High Plains Hideaway',
    'Open Meadow Camp',
    'Open Meadow Haven',
    'Open Meadow Hideaway',
    'Pine Shade Camp',
    'Pine Shade Haven',
    'Pine Shade Hideaway',
    'Quiet Pines Camp',
    'Quiet Pines Haven',
    'Quiet Pines Hideaway',
    'Rocky Meadow Camp',
    'Rocky Meadow Haven',
    'Rocky Meadow Hideaway',
    'Sage Meadow Camp',
    'Sage Meadow Haven',
    'Sage Meadow Hideaway',
    'Skyline Camp',
    'Skyline Haven',
    'Skyline Hideaway',
    'Skyline Rest Camp',
    'Soft Pine Camp',
    'Soft Pine Hideaway',
    'Still Meadow Camp',
    'Still Meadow Haven',
    'Still Meadow Hideaway',
    'Sunlit Meadow Camp',
    'Sunlit Meadow Haven',
    'Sunlit Meadow Hideaway',
    'Tall Pines Camp',
    'Tall Pines Haven',
    'Tall Pines Hideaway',
    'Timber Meadow Camp',
    'Timber Meadow Haven',
    'Timber Meadow Hideaway',
    'Valley Meadow Camp',
    'Valley Meadow Haven',
    'Valley Meadow Hideaway',
    'Wide Sky Camp',
    'Wide Sky Haven',
    'Wide Sky Hideaway',
    'Wide Sky Rest Camp',
    'Wooded Hollow',
    'Wooded Hollow Camp',
    'Wooded Hollow Hideaway',
    'Woodland Meadow Camp',
    'Woodland Meadow Haven',
    'Woodland Meadow Hideaway',
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
