<?php

declare(strict_types=1);

/**
 * Permanent location galleries share one photo limit.
 *
 * Photo staging is deliberately separate: Update and Report attachments may
 * use smaller per-submission limits and do not redefine a location gallery.
 * Area photo policy remains unchanged until Area work is reviewed.
 */
const LLAMA_LOCATION_GALLERY_PHOTO_LIMIT = 10;

function llama_location_gallery_photo_limit(string $scope): int
{
    if (!in_array($scope, ['place', 'site', 'resource'], true)) {
        throw new InvalidArgumentException('Unsupported location photo gallery.');
    }

    return LLAMA_LOCATION_GALLERY_PHOTO_LIMIT;
}
