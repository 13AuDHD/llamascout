<?php
declare(strict_types=1);

/**
 * Resolve ground elevation in whole international feet. USGS 3DEP is
 * preferred; retain the existing global Copernicus lookup as fallback.
 * The injected JSON transport keeps provider failure tests offline.
 */
function llama_location_elevation(float $lat, float $lng, callable $lookup): array
{
    $metersToFeet = static function (mixed $value): ?int {
        if (!is_numeric($value)) return null;
        $meters = (float) $value;
        // Reject missing-data sentinels, non-finite values, and impossible
        // terrestrial elevations. Zero and below-sea-level land are valid.
        if (!is_finite($meters) || $meters < -500 || $meters > 9000) return null;
        return (int) round($meters / 0.3048);
    };

    $latitude = number_format($lat, 7, '.', '');
    $longitude = number_format($lng, 7, '.', '');
    $usgs = $lookup('https://epqs.nationalmap.gov/v1/json?' . http_build_query([
        'x' => $longitude,
        'y' => $latitude,
        'wkid' => 4326,
        'units' => 'Meters',
        'includeDate' => 'false',
    ]), [], 8);
    $feet = $metersToFeet($usgs['value'] ?? null);
    if ($feet !== null) {
        return ['feet' => $feet, 'source' => 'usgs_3dep'];
    }

    $fallback = $lookup('https://api.open-meteo.com/v1/elevation?' . http_build_query([
        'latitude' => $latitude,
        'longitude' => $longitude,
    ]), [], 8);
    $elevation = $fallback['elevation'] ?? null;
    $feet = $metersToFeet(is_array($elevation) ? ($elevation[0] ?? null) : $elevation);
    return ['feet' => $feet, 'source' => $feet === null ? null : 'open_meteo'];
}
