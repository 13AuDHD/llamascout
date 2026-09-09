<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

function place_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function place_yes_no(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return (int) $value === 1 ? 'Yes' : 'No';
}

function place_report_rating_item(string $label, mixed $value): void
{
    if ($value === null || $value === '') {
        return;
    }

    $rating = (int) $value;

    if ($rating < 1 || $rating > 5) {
        return;
    }
    ?>
    <div class="scout-report-item scout-report-rating-item">
        <div class="scout-rating-content">
            <span><?= place_h($label) ?></span>
            <strong><?= $rating ?>/5</strong>
        </div>

        <div class="scout-rating-dots" aria-label="<?= $rating ?> out of 5">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span
                    class="scout-rating-dot<?= $i <= $rating ? ' is-filled' : '' ?>"
                    aria-hidden="true"
                ></span>
            <?php endfor; ?>
        </div>
    </div>
    <?php
}

function place_report_item(string $label, mixed $value, ?string $icon = null): void
{
    if ($value === null || $value === '') {
        return;
    }
    ?>
    <div class="scout-report-item scout-report-value-item">
        <div class="scout-report-value-content">
            <span><?= place_h($label) ?></span>
            <strong><?= place_h($value) ?></strong>
        </div>

        <?php if ($icon): ?>
            <i
                class="fa-solid <?= place_h($icon) ?> scout-report-value-icon"
                aria-hidden="true"
            ></i>
        <?php endif; ?>
    </div>
    <?php
}

function demo_weather_info(mixed $code, bool $isDay = true): array
{
    $code = (int) $code;

    return match (true) {
        $code === 0 => ['Clear', $isDay ? 'fa-sun' : 'fa-moon'],
        in_array($code, [1, 2], true) => ['Partly cloudy', $isDay ? 'fa-cloud-sun' : 'fa-cloud-moon'],
        $code === 3 => ['Overcast', 'fa-cloud'],
        in_array($code, [45, 48], true) => ['Fog', 'fa-smog'],
        in_array($code, [51, 53, 55, 56, 57], true) => ['Drizzle', 'fa-cloud-rain'],
        in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true) => ['Rain', 'fa-cloud-showers-heavy'],
        in_array($code, [71, 73, 75, 77, 85, 86], true) => ['Snow', 'fa-snowflake'],
        in_array($code, [95, 96, 99], true) => ['Thunderstorms', 'fa-cloud-bolt'],
        default => ['Conditions unavailable', 'fa-cloud'],
    };
}

function demo_round(mixed $value): ?int
{
    return is_numeric($value) ? (int) round((float) $value) : null;
}

/* Real location anchor: Durango Post Office. */
$headquarters = [
    'address' => '222 W 8th St',
    'city' => 'Durango',
    'county' => 'La Plata',
    'state' => 'Colorado',
    'postal_code' => '81301',
    'latitude' => 37.272488,
    'longitude' => -107.882478,
    'elevation_feet' => 6512,
];

/* Everything below this point is intentionally fictional demo content. */
$place = [
    'name' => 'Llama Scout Headquarters',
    'slug' => 'llama-scout-headquarters-demo',
    'type' => 'Demo Scout Report',
    'city' => 'Durango',
    'county' => 'La Plata',
    'state' => 'Colorado',
    'land_manager' => 'Llama Scout Demo Department',
    'land_type' => 'Private Owner',
    'road' => 'Demo Trail 13',
    'latitude' => $headquarters['latitude'],
    'longitude' => $headquarters['longitude'],
    'elevation_feet' => $headquarters['elevation_feet'],
    'description' =>
        'Welcome to Llama Scout Headquarters. The geographic anchor used for weather '
        . 'and coordinates is real, but the campsite, photos, observations, ratings, '
        . 'and recommendations on this page are fictional. This example exists to show '
        . 'what a complete Scout Report can tell you before visiting a real Place.',
    'access_summary' =>
        'Demo information: imagine a short forest road with several rocky sections, '
        . 'a narrow final approach, and enough room to turn around once you reach the '
        . 'site. These notes are fictional and demonstrate how a real Scout Report '
        . 'describes access instead of asking you to guess.',
    'sensory_summary' =>
        'Demo information: the fictional site is calm after sunset, has moderate '
        . 'daytime activity, low artificial light, and enough natural sound to feel '
        . 'remote without being completely isolated.',
    'notes' => [
        ['note' => 'Demo Scout note: the fictional view opens dramatically just before sunset.'],
        ['note' => 'Demo Scout note: arriving before dark would make the imaginary final approach easier to inspect.'],
        ['note' => 'This sample does not describe camping conditions at the Durango Post Office.'],
    ],
];

$details = [
    'vehicle_capacity' => '2 vehicles',
    'max_vehicle_length_feet' => 24,
    'tent_camping_suitable' => 1,
    'rv_suitable' => 0,
    'trailer_suitable' => 0,
    'parking_surface' => 'Packed dirt',
    'ground_condition' => 'Firm with scattered rock',
    'levelness' => 4,
    'leveling_required' => 0,
    'turnaround_space' => 1,
    'pull_through' => 0,
    'back_in' => 1,
    'site_open_sky' => 4,
    'tree_cover' => 3,
    'site_shade' => 3,
    'site_access_difficulty' => 2,
    'road_overall_difficulty' => 3,
    'road_stress' => 2,
    'road_surface' => 'Dirt and rock',
    'road_width' => 'Mostly one lane',
    'sedan_accessible' => 0,
    'high_clearance_recommended' => 1,
    'four_wheel_drive_recommended' => 1,
    'rocks' => 3,
    'washboards' => 2,
    'potholes' => 2,
    'mud_risk' => 3,
    'steep_grades' => 2,
    'drop_off_exposure' => 1,
    'water_crossings' => 0,
    'downed_tree_risk' => 1,
    'seasonal_closure' => 0,
];

$connectivity = [
    'overall' => 4,
    't_mobile' => 4,
    'verizon' => 3,
    'att' => 2,
    'other_cell' => 2,
    'starlink' => 5,
    'starlink_tested' => 1,
    'starlink_note' => 'Demo information: the fictional parking area has a broad southern sky with minimal obstruction.',
];

$sensory = [
    'daytime' => [
        'noise' => 2, 'traffic' => 2, 'crowds' => 2, 'privacy' => 4,
        'light_pollution' => 1, 'sensory_comfort' => 4, 'social_interaction_likelihood' => 2,
    ],
    'nighttime' => [
        'noise' => 1, 'traffic' => 1, 'crowds' => 1, 'privacy' => 5,
        'light_pollution' => 1, 'sensory_comfort' => 5, 'social_interaction_likelihood' => 1,
    ],
];

$sensoryDetails = [
    'dust_from_traffic' => 2, 'generator_noise' => 1, 'aircraft_noise' => 1,
    'road_noise' => 1, 'human_activity' => 2, 'wildlife_noise' => 3,
    'wind_noise' => 2, 'smoke_risk' => 2, 'strong_odors' => 1,
    'visual_exposure' => 2, 'predictability' => 4,
];

$rules = [
    'seasonal_access_note' =>
        'Demo information: this fictional site is shown as most comfortable from '
        . 'late spring through early fall. These values demonstrate how seasonal '
        . 'conditions, rules, and nearby services can be summarized.',
    'best_months' => 'May through October',
    'recommended_travel_season' => 'Late spring to fall',
    'winter_access' => 0,
    'snow_risk' => 4,
    'mud_season_risk' => 4,
    'monsoon_risk' => 3,
    'overnight_camping_allowed' => 1,
    'dispersed_camping_allowed' => 1,
    'stay_limit_days' => 14,
    'permit_required' => 0,
    'fee' => 0,
    'campfire_allowed' => 1,
    'pack_it_in_pack_it_out' => 1,
    'existing_sites_encouraged' => 1,
    'nearest_town' => 'Durango, CO',
    'nearest_fuel' => 'About 3 miles',
    'nearest_grocery' => 'About 2 miles',
    'nearest_water' => 'About 2 miles',
    'nearest_toilet' => 'About 1 mile',
    'nearest_hospital' => 'About 5 miles',
    'current_fire_restrictions_url' => '',
];

$experience = [
    'sunrise_view' => 4, 'sunset_view' => 5, 'mountain_view' => 5,
    'forest_view' => 4, 'night_sky' => 5, 'stargazing' => 5,
    'quiet_evening' => 5, 'overnight_comfort' => 4, 'extended_stay_comfort' => 4,
    'sensory_retreat' => 5, 'remote_work' => 4, 'overall_scenery' => 5,
    'recommended_overnight_stop' => 5, 'recommended_quiet_evening' => 5,
    'recommended_extended_stay' => 4, 'recommended_sensory_retreat' => 5,
    'recommended_stargazing' => 5, 'recommended_remote_work' => 4,
    'recommended_solo_travel' => 1,
];

$amenityLabels = [
    'toilets' => ['fa-restroom', 'Toilets'],
    'potable_water' => ['fa-faucet-drip', 'Potable water'],
    'trash' => ['fa-trash-can', 'Trash'],
    'fire_ring' => ['fa-fire', 'Fire ring'],
    'picnic_table' => ['fa-table-picnic', 'Picnic table'],
    'bear_box' => ['fa-box', 'Bear box'],
    'showers' => ['fa-shower', 'Showers'],
    'electricity' => ['fa-bolt', 'Electricity'],
];

$demoAmenities = [
    'toilets' => false,
    'potable_water' => false,
    'trash' => false,
    'fire_ring' => true,
    'picnic_table' => false,
    'bear_box' => false,
    'showers' => false,
    'electricity' => false,
];

/* Demo images are optional. The page works before they exist. */
$demoImageCandidates = [
    ['file' => __DIR__ . '/images/demo/llama-scout-headquarters-hero.jpeg', 'url' => '/images/demo/llama-scout-headquarters-hero.jpeg', 'alt' => 'Fictional Llama Scout Headquarters campsite'],
    ['file' => __DIR__ . '/images/demo/llama-scout-headquarters-2.jpeg', 'url' => '/images/demo/llama-scout-headquarters-2.jpeg', 'alt' => 'Fictional campsite view used for the Llama Scout demo'],
    ['file' => __DIR__ . '/images/demo/llama-scout-headquarters-3.jpeg', 'url' => '/images/demo/llama-scout-headquarters-3.jpeg', 'alt' => 'Fictional forest campsite used for the Llama Scout demo'],
    ['file' => __DIR__ . '/images/demo/llama-scout-headquarters-4.jpeg', 'url' => '/images/demo/llama-scout-headquarters-4.jpeg', 'alt' => 'Fictional campsite scenery used for the Llama Scout demo'],
];

$demoImages = array_values(array_filter(
    $demoImageCandidates,
    static fn(array $image): bool => is_file((string) $image['file'])
));

$heroImage = $demoImages[0] ?? null;

/* Live weather uses the real Headquarters anchor, server-side. */
$demoWeather = null;

try {
    $demoWeather = weather_fetch_campsite_forecast(
        (float) $headquarters['latitude'],
        (float) $headquarters['longitude'],
        (float) $headquarters['elevation_feet'],
        5
    );
} catch (Throwable $exception) {
    error_log('Llama Scout demo weather error: ' . $exception->getMessage());
}

$pageTitle = 'Example Scout Report | Llama Scout';
$pageDescription =
    'Explore a complete fictional Llama Scout Scout Report using Llama Scout '
    . 'Headquarters in Durango as a real weather and location anchor.';
$pageRobots = 'noindex,follow';

$pageStyles = [
    'place-detail.css',
    'site/features/place-shared.css',
    'contributor-attribution.css',
    'share.css',
    'scout-report-cards.css',
];

require __DIR__ . '/partials/header.php';
?>

<article class="place-page">

    <section
        class="place-section"
        style="margin-top:28px;padding:18px 20px;border:1px solid var(--border);border-radius:12px;background:var(--surface);"
    >
        <p class="place-detail-eyebrow">Example Scout Report</p>
        <h1 style="margin:0;font-size:clamp(1.6rem,4vw,2.3rem);">
            This Place is intentionally fictional.
        </h1>
        <p style="margin:10px 0 0;line-height:1.65;">
            Llama Scout Headquarters is set as the location
            so this demo can show real location and weather
            behavior. The campsite, photos, descriptions, ratings, access
            conditions, amenities, and recommendations are fictional examples.
        </p>
    </section>

    <header class="place-detail-hero<?= $heroImage ? ' has-image' : ' no-image' ?>">
        <?php if ($heroImage): ?>
            <img
                class="place-detail-hero-image"
                src="<?= place_h($heroImage['url']) ?>"
                alt="<?= place_h($heroImage['alt']) ?>"
            >
        <?php endif; ?>

        <div class="place-detail-hero-shade" aria-hidden="true"></div>

        <div class="place-detail-hero-inner">
            <a class="place-detail-back" href="/membership.php">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Membership
            </a>

            <div class="place-detail-hero-content">
                <div class="place-detail-title-block">
                    <p class="place-detail-eyebrow">Demo Scout Report</p>
                    <h1>Llama Scout Headquarters</h1>

                    <p class="place-detail-location">
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        Durango, La Plata County, Colorado
                    </p>

                    <p class="place-detail-land">
                        Private Owner
                    </p>
                </div>

                <div class="place-detail-actions">
                    <button class="place-detail-action-button" type="button" disabled>
                        <i class="fa-regular fa-bookmark" aria-hidden="true"></i>
                        Save Place
                    </button>

                    <button class="place-detail-action-button" type="button" disabled>
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        Suggest Update
                    </button>

                    <button class="place-detail-action-button" type="button" disabled>
                        <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                        Share
                    </button>
                </div>
            </div>
        </div>
    </header>

    <?php if ($demoImages): ?>
        <section class="place-photo-gallery-section">
            <div class="place-detail-container">
                <div class="place-photo-gallery-heading">
                    <div>
                        <p class="place-detail-eyebrow">Demo photos</p>
                        <h2>Fictional campsite gallery</h2>
                    </div>
                    <span><?= count($demoImages) ?> photos</span>
                </div>

                <div class="place-photo-gallery">
                    <?php foreach ($demoImages as $image): ?>
                        <div class="place-photo-thumb">
                            <img
                                src="<?= place_h($image['url']) ?>"
                                alt="<?= place_h($image['alt']) ?>"
                                loading="lazy"
                            >
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="place-photo-gallery-help">
                    These images depict a fictional campsite created for this demonstration.
                </p>
            </div>
        </section>
    <?php endif; ?>

    <section class="place-facts" aria-label="Demo Place details">
        <div class="place-fact">
            <i class="fa-solid fa-mountain" aria-hidden="true"></i>
            <span>Elevation</span>
            <strong><?= number_format((int) $headquarters['elevation_feet']) ?> ft</strong>
        </div>

        <div class="place-fact">
            <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
            <span>GPS coordinates</span>
            <strong>
                <?= place_h($headquarters['latitude']) ?>,
                <?= place_h($headquarters['longitude']) ?>
            </strong>
        </div>

        <div class="place-fact">
            <i class="fa-solid fa-building" aria-hidden="true"></i>
            <span>Real-world anchor</span>
            <strong>
                <?= place_h($headquarters['address']) ?>,
                <?= place_h($headquarters['city']) ?>
            </strong>
        </div>
    </section>

    <section class="place-section place-weather" aria-labelledby="weather-heading">
        <div class="place-weather-heading">
            <div>
                <p class="eyebrow">Live weather</p>
                <h2 id="weather-heading">Llama Scout Headquarters weather</h2>
            </div>

            <i class="fa-solid fa-cloud-sun place-weather-heading-icon" aria-hidden="true"></i>
        </div>

        <?php
        $forecast = is_array($demoWeather['forecast'] ?? null)
            ? $demoWeather['forecast']
            : [];

        $current = is_array($forecast['current'] ?? null)
            ? $forecast['current']
            : [];

        $daily = is_array($forecast['daily'] ?? null)
            ? $forecast['daily']
            : [];
        ?>

        <?php if (!$forecast): ?>
            <div class="place-weather-unavailable">
                <i class="fa-solid fa-cloud" aria-hidden="true"></i>
                <p>Weather is temporarily unavailable.</p>
            </div>
        <?php else: ?>
            <?php
            [$currentLabel, $currentIcon] = demo_weather_info(
                $current['weather_code'] ?? null,
                (int) ($current['is_day'] ?? 1) !== 0
            );

            $currentTemp = demo_round($current['temperature_2m'] ?? null);
            $feels = demo_round($current['apparent_temperature'] ?? null);
            $humidity = demo_round($current['relative_humidity_2m'] ?? null);
            $wind = demo_round($current['wind_speed_10m'] ?? null);
            ?>

            <div class="place-weather-current">
                <div class="place-weather-condition-icon">
                    <i class="fa-solid <?= place_h($currentIcon) ?>" aria-hidden="true"></i>
                </div>

                <div class="place-weather-current-main">
                    <div class="place-weather-temperature">
                        <?= $currentTemp === null ? '° ' : $currentTemp . '°F' ?>
                    </div>
                    <strong><?= place_h($currentLabel) ?></strong>
                    <span>Headquarters, Durango</span>
                </div>

                <div class="place-weather-facts">
                    <?php if ($feels !== null): ?>
                        <div><span>Feels like</span><strong><?= $feels ?>°F</strong></div>
                    <?php endif; ?>

                    <?php if ($humidity !== null): ?>
                        <div><span>Humidity</span><strong><?= $humidity ?>%</strong></div>
                    <?php endif; ?>

                    <?php if ($wind !== null): ?>
                        <div><span>Wind</span><strong><?= $wind ?> mph</strong></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php $dates = is_array($daily['time'] ?? null) ? array_slice($daily['time'], 0, 5) : []; ?>

            <?php if ($dates): ?>
                <div class="place-weather-forecast">
                    <h3>5-day forecast</h3>

                    <div class="place-weather-forecast-grid">
                        <?php foreach ($dates as $index => $date): ?>
                            <?php
                            [$dayLabel, $dayIcon] = demo_weather_info(
                                $daily['weather_code'][$index] ?? null,
                                true
                            );

                            $high = demo_round($daily['temperature_2m_max'][$index] ?? null);
                            $low = demo_round($daily['temperature_2m_min'][$index] ?? null);
                            $rain = demo_round($daily['precipitation_probability_max'][$index] ?? null);
                            $maxWind = demo_round($daily['wind_speed_10m_max'][$index] ?? null);

                            $dayName = $index === 0
                                ? 'Today'
                                : date('D', strtotime((string) $date . ' 12:00:00'));
                            ?>

                            <article class="place-weather-day">
                                <strong class="place-weather-day-name"><?= place_h($dayName) ?></strong>
                                <i class="fa-solid <?= place_h($dayIcon) ?>" aria-hidden="true"></i>
                                <span class="place-weather-day-condition"><?= place_h($dayLabel) ?></span>

                                <div class="place-weather-day-temperatures">
                                    <strong><?= $high === null ? '—' : $high . '°' ?></strong>
                                    <span><?= $low === null ? '—' : $low . '°' ?></span>
                                </div>

                                <?php if ($rain !== null): ?>
                                    <span class="place-weather-day-detail">
                                        <i class="fa-solid fa-droplet" aria-hidden="true"></i>
                                        <?= $rain ?>%
                                    </span>
                                <?php endif; ?>

                                <?php if ($maxWind !== null): ?>
                                    <span class="place-weather-day-detail">
                                        <i class="fa-solid fa-wind" aria-hidden="true"></i>
                                        <?= $maxWind ?> mph
                                    </span>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
p>
        <?php endif; ?>
    </section>

    <section class="place-section">
        <h2>Amenities</h2>

        <p class="scout-report-summary">
            Demo information only. These amenities describe the fictional campsite.
        </p>

        <div class="amenity-grid">
            <?php foreach ($amenityLabels as $key => [$icon, $label]): ?>
                <?php $value = $demoAmenities[$key] ?? null; ?>
                <?php if ($value === null) continue; ?>

                <div class="amenity-item <?= $value ? 'is-available' : 'is-unavailable' ?>">
                    <i class="fa-solid <?= place_h($icon) ?>" aria-hidden="true"></i>
                    <span><?= place_h($label) ?></span>
                    <strong><?= $value ? 'Yes' : 'No' ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="place-section">
        <p class="eyebrow">Demo description</p>
        <h2>About this Place</h2>
        <p><?= place_h($place['description']) ?></p>
    </section>

    <?php require __DIR__ . '/partials/place/scout-report/index.php'; ?>

    <section class="place-report-section">
        <details class="place-report">
            <summary>
                <i class="fa-regular fa-flag" aria-hidden="true"></i>
                Report a problem with this place
            </summary>

            <div class="place-report-body">
                <p>
                    This is a demonstration Place, so there is nothing to report or
                    correct. On a real Place, this is where a signed-in member can
                    submit a problem for review.
                </p>

                <button type="button" class="place-report-submit" disabled>
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    Demo only
                </button>
            </div>
        </details>
    </section>

    <section
        class="place-section"
        style="margin-top:24px;margin-bottom:60px;padding:24px;border:1px solid var(--border);border-radius:12px;background:var(--surface);"
    >
        <p class="place-detail-eyebrow">See this for real Places</p>
        <h2 style="margin-top:0;">Know the place before you go.</h2>

        <p style="line-height:1.65;">
            This demo shows the kind of planning detail available inside a
            complete Scout Report. Membership unlocks the actual information
            collected for real Places, including exact locations, full photo
            galleries, access details, sensory conditions, connectivity,
            weather, rules, and recommendations.
        </p>

        <a
            class="place-detail-action-button"
            href="/membership.php"
            style="margin-top:8px;border-color:var(--border);background:var(--background);color:var(--text);"
        >
            <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
            View Membership
        </a>
    </section>

</article>

<?php require __DIR__ . '/partials/footer.php'; ?>
