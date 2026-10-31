<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/access.php';

function photo_access_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$publicPlace = [
    'id' => 10,
    'type' => 'developed-campground',
];

$restrictedPlace = [
    'id' => 20,
    'type' => 'dispersed-camping',
];

$guest = llama_place_photo_permissions($publicPlace, 0);
photo_access_assert(
    $guest['can_preview'] && !$guest['can_open'],
    'Guests must receive public Place previews without lightbox access.'
);

$freeMember = llama_place_photo_permissions($publicPlace, 101);
photo_access_assert(
    $freeMember['can_preview'] && $freeMember['can_open'],
    'Signed-in Free Members must be able to open a public Place gallery.'
);

$unauthorizedRestricted = llama_place_photo_permissions(null, 101);
photo_access_assert(
    !$unauthorizedRestricted['can_preview']
        && !$unauthorizedRestricted['can_open'],
    'A Place rejected by the visibility boundary must expose no photos.'
);

$earnedAccess = llama_place_photo_permissions($restrictedPlace, 102);
photo_access_assert(
    $earnedAccess['can_preview'] && $earnedAccess['can_open'],
    'An earned-access Member must retain the authorized Place gallery.'
);

$fullMember = llama_place_photo_permissions($restrictedPlace, 103);
photo_access_assert(
    $fullMember['can_preview'] && $fullMember['can_open'],
    'A Full Member must retain the authorized restricted Place gallery.'
);

$placeSource = file_get_contents(dirname(__DIR__) . '/place.php');
$placesSource = file_get_contents(dirname(__DIR__) . '/app/places.php');

photo_access_assert(
    is_string($placeSource)
        && strpos($placeSource, 'place_gallery_images(') > strpos($placeSource, 'if (!$place)'),
    'Place photos must be queried only after the authorized Place lookup succeeds.'
);

photo_access_assert(
    is_string($placesSource)
        && str_contains($placesSource, "llama_place_visibility_sql('p')"),
    'Restricted Place visibility must remain enforced by the shared lookup.'
);

echo "public Place photo access smoke: passed\n";
