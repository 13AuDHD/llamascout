(() => {
    'use strict';

    const weatherSection = document.querySelector('[data-place-weather]');

    if (!weatherSection) {
        return;
    }

    const content = weatherSection.querySelector('[data-place-weather-content]');
    const slug = weatherSection.dataset.placeSlug || '';

    if (!content || !slug) {
        return;
    }

    const escapeHtml = (value) => {
        const element = document.createElement('div');
        element.textContent = value ?? '';
        return element.innerHTML;
    };

    const number = (value) => {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    };

    const round = (value) => {
        const parsed = number(value);
        return parsed === null ? null : Math.round(parsed);
    };

    const weatherInfo = (code, isDay = true) => {
        const value = Number(code);

        if (value === 0) {
            return {
                label: 'Clear',
                icon: isDay ? 'fa-sun' : 'fa-moon'
            };
        }

        if ([1, 2].includes(value)) {
            return {
                label: 'Partly cloudy',
                icon: isDay ? 'fa-cloud-sun' : 'fa-cloud-moon'
            };
        }

        if (value === 3) {
            return {
                label: 'Overcast',
                icon: 'fa-cloud'
            };
        }

        if ([45, 48].includes(value)) {
            return {
                label: 'Fog',
                icon: 'fa-smog'
            };
        }

        if ([51, 53, 55, 56, 57].includes(value)) {
            return {
                label: 'Drizzle',
                icon: 'fa-cloud-rain'
            };
        }

        if ([61, 63, 65, 66, 67, 80, 81, 82].includes(value)) {
            return {
                label: 'Rain',
                icon: 'fa-cloud-showers-heavy'
            };
        }

        if ([71, 73, 75, 77, 85, 86].includes(value)) {
            return {
                label: 'Snow',
                icon: 'fa-snowflake'
            };
        }

        if ([95, 96, 99].includes(value)) {
            return {
                label: 'Thunderstorms',
                icon: 'fa-cloud-bolt'
            };
        }

        return {
            label: 'Conditions unavailable',
            icon: 'fa-cloud'
        };
    };

    const dayLabel = (date, index) => {
        if (index === 0) {
            return 'Today';
        }

        const parsed = new Date(`${date}T12:00:00`);

        if (Number.isNaN(parsed.getTime())) {
            return date;
        }

        return new Intl.DateTimeFormat('en-US', {
            weekday: 'short'
        }).format(parsed);
    };

    const renderUnavailable = () => {
        content.innerHTML = `
            <div class="place-weather-unavailable">
                <i class="fa-solid fa-cloud" aria-hidden="true"></i>
                <p>Weather is temporarily unavailable.</p>
            </div>
        `;
    };

    const renderWeather = (data) => {
        const weather = data.weather || {};
        const forecast = weather.forecast || {};
        const current = forecast.current || {};
        const daily = forecast.daily || {};

        const isMember =
            data.forecastType === 'campsite';

        const currentTemperature =
            round(current.temperature_2m);

        const apparentTemperature =
            round(current.apparent_temperature);

        const humidity =
            round(current.relative_humidity_2m);

        const windSpeed =
            round(current.wind_speed_10m);

        const precipitation =
            number(current.precipitation);

        const currentWeather = weatherInfo(
            current.weather_code,
            Number(current.is_day) !== 0
        );

        let locationLabel = 'Nearby city';

        if (isMember) {
            locationLabel = 'Exact campsite forecast';
        } else if (data.weatherLocation) {
            locationLabel = [
                data.weatherLocation.city,
                data.weatherLocation.state
            ]
                .filter(Boolean)
                .join(', ');
        }

        const currentHtml = `
            <div class="place-weather-current">

                <div class="place-weather-condition-icon">
                    <i
                        class="fa-solid ${currentWeather.icon}"
                        aria-hidden="true"
                    ></i>
                </div>

                <div class="place-weather-current-main">

                    <div class="place-weather-temperature">
                        ${
                            currentTemperature === null
                                ? '—'
                                : `${currentTemperature}°F`
                        }
                    </div>

                    <strong>
                        ${escapeHtml(currentWeather.label)}
                    </strong>

                    <span>
                        ${escapeHtml(locationLabel)}
                    </span>

                </div>

                <div class="place-weather-facts">

                    ${
                        apparentTemperature === null
                            ? ''
                            : `
                                <div>
                                    <span>Feels like</span>
                                    <strong>
                                        ${apparentTemperature}°F
                                    </strong>
                                </div>
                            `
                    }

                    ${
                        humidity === null
                            ? ''
                            : `
                                <div>
                                    <span>Humidity</span>
                                    <strong>
                                        ${humidity}%
                                    </strong>
                                </div>
                            `
                    }

                    ${
                        windSpeed === null
                            ? ''
                            : `
                                <div>
                                    <span>Wind</span>
                                    <strong>
                                        ${windSpeed} mph
                                    </strong>
                                </div>
                            `
                    }

                    ${
                        precipitation === null
                            ? ''
                            : `
                                <div>
                                    <span>Precipitation</span>
                                    <strong>
                                        ${precipitation.toFixed(2)} in
                                    </strong>
                                </div>
                            `
                    }

                </div>

            </div>
        `;

        if (!isMember) {
            content.innerHTML = `
                ${currentHtml}

                <p class="place-weather-note">
                    Today’s weather is shown for the nearby city.
                    Members receive a campsite-specific forecast
                    using the exact location and elevation.
                </p>
            `;

            return;
        }

        const dates =
            Array.isArray(daily.time)
                ? daily.time.slice(0, 5)
                : [];

        const cards = dates
            .map((date, index) => {
                const code =
                    daily.weather_code?.[index];

                const info =
                    weatherInfo(code, true);

                const high =
                    round(
                        daily.temperature_2m_max?.[index]
                    );

                const low =
                    round(
                        daily.temperature_2m_min?.[index]
                    );

                const rainChance =
                    round(
                        daily.precipitation_probability_max?.[index]
                    );

                const maxWind =
                    round(
                        daily.wind_speed_10m_max?.[index]
                    );

                return `
                    <article class="place-weather-day">

                        <strong class="place-weather-day-name">
                            ${escapeHtml(
                                dayLabel(date, index)
                            )}
                        </strong>

                        <i
                            class="fa-solid ${info.icon}"
                            aria-hidden="true"
                        ></i>

                        <span class="place-weather-day-condition">
                            ${escapeHtml(info.label)}
                        </span>

                        <div class="place-weather-day-temperatures">

                            <strong>
                                ${
                                    high === null
                                        ? '—'
                                        : `${high}°`
                                }
                            </strong>

                            <span>
                                ${
                                    low === null
                                        ? '—'
                                        : `${low}°`
                                }
                            </span>

                        </div>

                        ${
                            rainChance === null
                                ? ''
                                : `
                                    <span class="place-weather-day-detail">

                                        <i
                                            class="fa-solid fa-droplet"
                                            aria-hidden="true"
                                        ></i>

                                        ${rainChance}%

                                    </span>
                                `
                        }

                        ${
                            maxWind === null
                                ? ''
                                : `
                                    <span class="place-weather-day-detail">

                                        <i
                                            class="fa-solid fa-wind"
                                            aria-hidden="true"
                                        ></i>

                                        ${maxWind} mph

                                    </span>
                                `
                        }

                    </article>
                `;
            })
            .join('');

        content.innerHTML = `
            ${currentHtml}

            ${
                cards
                    ? `
                        <div class="place-weather-forecast">

                            <h3>5-day forecast</h3>

                            <div class="place-weather-forecast-grid">
                                ${cards}
                            </div>

                        </div>
                    `
                    : ''
            }

            <p class="place-weather-note">
                Forecast calculated for this campsite’s exact
                location and recorded elevation. Mountain weather
                can change quickly.
            </p>
        `;
    };

    const loadWeather = async () => {
        try {
            const response = await fetch(
                `/api/weather.php?place=${encodeURIComponent(slug)}`,
                {
                    cache: 'no-store',
                    credentials: 'same-origin'
                }
            );

            if (!response.ok) {
                throw new Error(
                    'Weather request failed.'
                );
            }

            const payload =
                await response.json();

            if (
                !payload.ok
                || !payload.data
            ) {
                throw new Error(
                    'Weather response was invalid.'
                );
            }

            renderWeather(payload.data);

        } catch (error) {
            console.error(
                'Llama Scout weather error:',
                error
            );

            renderUnavailable();
        }
    };

    loadWeather();
})();
