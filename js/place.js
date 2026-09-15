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

    const iconHtml = (name, extraClass = '') => {

        const className = [

            'place-weather-svg-icon',

            extraClass

        ]
            .filter(Boolean)
            .join(' ');

        return `

            <span

                class="${className}"

                style="--place-weather-icon:url('/assets/icons/${escapeHtml(name)}.svg')"

                aria-hidden="true"

            ></span>

        `;

    };

    const forecastTime = (value) => {

        const match = String(value ?? '').match(

            /T(\d{2}):(\d{2})/

        );

        if (!match) {

            return '';

        }

        let hour = Number(match[1]);

        const minute = match[2];

        const suffix = hour >= 12 ? 'PM' : 'AM';

        hour %= 12;

        if (hour === 0) {

            hour = 12;

        }

        return `${hour}:${minute} ${suffix}`;

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

                icon: isDay ? 'at-sunny' : 'at-moon'

            };

        }

        if (value === 1) {

            return {

                label: 'Mainly clear',

                icon: isDay ? 'at-day-cloudy' : 'at-cloudy-night'

            };

        }

        if (value === 2) {

            return {

                label: 'Partly cloudy',

                icon: isDay ? 'at-partly-cloudy' : 'at-cloudy-night'

            };

        }

        if (value === 3) {

            return {

                label: 'Overcast',

                icon: 'at-cloudy'

            };

        }

        if ([45, 48].includes(value)) {

            return {

                label: 'Fog',

                icon: 'at-misty-cloud'

            };

        }

        if ([51, 53, 55].includes(value)) {

            return {

                label: 'Drizzle',

                icon: isDay ? 'at-rain-drop-sun' : 'at-rain-drop-moon'

            };

        }

        if ([56, 57].includes(value)) {

            return {

                label: 'Freezing drizzle',

                icon: 'at-freeze'

            };

        }

        if ([61, 63].includes(value)) {

            return {

                label: 'Rain',

                icon: isDay ? 'at-raining-day' : 'at-raining-night'

            };

        }

        if (value === 65) {

            return {

                label: 'Heavy rain',

                icon: isDay ? 'at-strong-raining-day' : 'at-strong-rain-night'

            };

        }

        if ([66, 67].includes(value)) {

            return {

                label: 'Freezing rain',

                icon: 'at-freeze'

            };

        }

        if (value === 80) {

            return {

                label: 'Rain showers',

                icon: 'at-partly-cloudy-rain'

            };

        }

        if (value === 81) {

            return {

                label: 'Rain showers',

                icon: 'at-rain-storm'

            };

        }

        if (value === 82) {

            return {

                label: 'Heavy rain showers',

                icon: 'at-heavy-rain'

            };

        }

        if ([71, 73, 75].includes(value)) {

            return {

                label: 'Snow',

                icon: 'at-snowing'

            };

        }

        if (value === 77) {

            return {

                label: 'Snow grains',

                icon: 'at-snowing-snowflakes'

            };

        }

        if ([85, 86].includes(value)) {

            return {

                label: 'Snow showers',

                icon: 'at-snowing-snowflakes'

            };

        }

        if (value === 95) {

            return {

                label: 'Thunderstorms',

                icon: 'at-electric-storm'

            };

        }

        if ([96, 99].includes(value)) {

            return {

                label: 'Thunderstorms with hail',

                icon: 'at-strong-wind-hail'

            };

        }

        return {

            label: 'Conditions unavailable',

            icon: 'at-clouds'

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

                ${iconHtml('at-clouds')}

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

        const windGusts =

            round(current.wind_gusts_10m);

        const currentWeather = weatherInfo(

            current.weather_code,

            Number(current.is_day) !== 0

        );

        const sunrise = isMember

            ? forecastTime(daily.sunrise?.[0])

            : '';

        const sunset = isMember

            ? forecastTime(daily.sunset?.[0])

            : '';

        let locationLabel = 'Nearby city';

        if (isMember) {

            locationLabel = 'Campsite forecast';

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

                    ${iconHtml(currentWeather.icon)}

                </div>

                <div class="place-weather-current-main">

                    <div class="place-weather-temperature">

                        ${

                            currentTemperature === null

                                ? '&mdash;'

                                : `${currentTemperature}&#176;F`

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

                                        ${apparentTemperature}&#176;F

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

                        windGusts === null

                            ? ''

                            : `

                                <div>

                                    <span>Wind gusts</span>

                                    <strong>

                                        ${windGusts} mph

                                    </strong>

                                </div>

                            `

                    }

                    ${

                        sunrise === ''

                            ? ''

                            : `

                                <div>

                                    <span class="place-weather-fact-label">

                                        ${iconHtml('sunrise')}

                                        Sunrise

                                    </span>

                                    <strong>${sunrise}</strong>

                                </div>

                            `

                    }

                    ${

                        sunset === ''

                            ? ''

                            : `

                                <div>

                                    <span class="place-weather-fact-label">

                                        ${iconHtml('sunset')}

                                        Sunset

                                    </span>

                                    <strong>${sunset}</strong>

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

                        ${iconHtml(info.icon)}

                        <span class="place-weather-day-condition">

                            ${escapeHtml(info.label)}

                        </span>

                        <div class="place-weather-day-temperatures">

                            <strong>

                                ${

                                    high === null

                                        ? '&mdash;'

                                        : `${high}&#176;`

                                }

                            </strong>

                            <span>

                                ${

                                    low === null

                                        ? '&mdash;'

                                        : `${low}&#176;`

                                }

                            </span>

                        </div>

                        ${

                            rainChance === null

                                ? ''

                                : `

                                    <span class="place-weather-day-detail">

                                        ${iconHtml('at-rain-drops')}

                                        ${rainChance}%

                                    </span>

                                `

                        }

                        ${

                            maxWind === null

                                ? ''

                                : `

                                    <span class="place-weather-day-detail">

                                        ${iconHtml('at-wind-strength')}

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

                location and recorded elevation.

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
