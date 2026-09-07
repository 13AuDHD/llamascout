<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/scout-onboarding.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$db = db();

$config = llama_config();
$geoapifyApiKey =
    trim(
        (string) (
            $config['geoapify']['api_key']
            ?? ''
        )
    );

$profile =
    llama_scout_onboarding_profile(
        $db,
        $userId
    );

if (!$profile) {
    header('Location: /', true, 303);
    exit;
}

$status =
    (string) $profile['status'];

if ($status === 'invited') {
    header('Location: /scout-invite.php', true, 303);
    exit;
}

if (
    in_array(
        $status,
        [
            'application_submitted',
            'training',
            'pending_approval',
        ],
        true
    )
) {
    header('Location: /scout-training.php', true, 303);
    exit;
}

if ($status !== 'application_started') {
    header('Location: /', true, 303);
    exit;
}

$application =
    llama_scout_onboarding_application(
        $db,
        (int) $profile['id'],
        $userId
    );

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !llama_scout_onboarding_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            llama_scout_save_application(
                $db,
                $userId,
                $_POST
            );

            header(
                'Location: /scout-training.php',
                true,
                303
            );
            exit;
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'account.scout_application',
                ['user_id' => $userId],
                [InvalidArgumentException::class, RuntimeException::class]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The Scout application could not be submitted.',
                    $reference
                );
        }
    }
}

$pageTitle =
    'Scout Application | Llama Scout';

require dirname(__DIR__) . '/partials/header.php';

function scout_app_value(
    array $application,
    string $key
): string {
    return (string) (
        $_POST[$key]
        ?? $application[$key]
        ?? ''
    );
}
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/account/features/scout-onboarding.css"
>

<section class="account-scout-page">

<div class="account-scout-shell">

<a class="account-scout-back" href="/">
    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
    My account
</a>

<header class="account-scout-hero">
    <p class="account-eyebrow">Step 2 of 5</p>
    <h1>About You</h1>
    <p>
        This information helps Scout Basecamp understand who is joining
        the Scout team and how your experience may contribute.
    </p>

    <div class="account-scout-private-note">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <div>
            <strong>Private Scout information</strong>
            <span>
                Your answers are used for Scout onboarding and administration only.
                They are not shown on your public profile and are not visible to
                other Llama Scout members.
            </span>
        </div>
    </div>
</header>

<?php if ($error !== ''): ?>
    <div class="account-scout-notice is-error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (
    $application
    && !empty($application['review_notes'])
): ?>
    <div class="account-scout-notice is-attention">
        <strong>Basecamp returned your onboarding for changes.</strong>
        <p>
            <?= nl2br(
                htmlspecialchars(
                    (string) $application['review_notes'],
                    ENT_QUOTES,
                    'UTF-8'
                )
            ) ?>
        </p>
    </div>
<?php endif; ?>

<form
    method="post"
    class="account-scout-form"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            llama_scout_onboarding_csrf_token(),
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <section class="account-scout-panel">
        <p class="account-eyebrow">Contact</p>
        <h2>Basic information</h2>

        <p class="account-scout-panel-intro">
            We use this information to contact you about Scout matters and,
            from time to time, to mail Scout recognition, stickers, gear, or
            other thank-you items for your field work.
        </p>

        <div class="account-scout-form-grid">

            <label class="is-wide">
                <span>Legal name</span>
                <input
                    type="text"
                    name="legal_name"
                    maxlength="150"
                    autocomplete="name"
                    value="<?= htmlspecialchars(
                        scout_app_value($application ?: [], 'legal_name'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >
            </label>

            <label class="is-wide account-scout-address-field">
                <span class="account-scout-address-label">
                    Mailing address
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                </span>

                <div class="account-scout-address-row">
                    <div class="account-scout-address-input-wrap">
                    <input
                        type="text"
                        name="address_line_1"
                        maxlength="150"
                        autocomplete="address-line1"
                        data-scout-mailing-address
                        value="<?= htmlspecialchars(
                            scout_app_value($application ?: [], 'address_line_1'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Start typing your street address"
                        aria-autocomplete="list"
                        aria-controls="scout-address-suggestions"
                        aria-expanded="false"
                        required
                    >

                    <div
                        id="scout-address-suggestions"
                        class="account-scout-address-suggestions"
                        data-scout-address-suggestions
                        role="listbox"
                        hidden
                    ></div>
                    </div>

                    <div class="account-scout-address-tools">
                    <button
                        type="button"
                        class="account-scout-address-location"
                        data-scout-address-location
                    >
                        <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
                        <span>Use my location</span>
                    </button>

                    <small
                        class="account-scout-address-status"
                        data-scout-address-status
                        role="status"
                        aria-live="polite"
                    ></small>
                    </div>
                </div>
            </label>

            <label class="is-wide">
                <span>Apartment, suite, unit, etc. (optional)</span>
                <input
                    type="text"
                    name="address_line_2"
                    maxlength="150"
                    data-scout-address-line-2
                    autocomplete="address-line2"
                    value="<?= htmlspecialchars(
                        scout_app_value($application ?: [], 'address_line_2'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
            </label>

            <div class="account-scout-address-meta is-wide">
                <label>
                    <span>City</span>
                    <input
                        type="text"
                        name="city"
                        maxlength="100"
                        data-scout-address-city
                        autocomplete="address-level2"
                        value="<?= htmlspecialchars(
                            scout_app_value($application ?: [], 'city'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </label>

                <label>
                    <span>State / region</span>
                    <input
                        type="text"
                        name="state_region"
                        maxlength="100"
                        data-scout-address-state
                        autocomplete="address-level1"
                        value="<?= htmlspecialchars(
                            scout_app_value($application ?: [], 'state_region'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </label>

                <label>
                    <span>Postal code</span>
                    <input
                        type="text"
                        name="postal_code"
                        maxlength="30"
                        data-scout-address-postal
                        autocomplete="postal-code"
                        value="<?= htmlspecialchars(
                            scout_app_value($application ?: [], 'postal_code'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </label>

                <label>
                    <span>Country</span>
                    <input
                        type="text"
                        name="country"
                        maxlength="100"
                        data-scout-address-country
                        autocomplete="country-name"
                        value="<?= htmlspecialchars(
                            scout_app_value($application ?: [], 'country')
                                ?: 'United States',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </label>
            </div>

            <label class="is-wide account-scout-phone-field">
                <span>Phone (optional)</span>
                <input
                    type="tel"
                    name="phone"
                    maxlength="25"
                    autocomplete="tel"
                    inputmode="tel"
                    data-scout-phone
                    placeholder="(970) 555-0123"
                    value="<?= htmlspecialchars(
                        scout_app_value($application ?: [], 'phone'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
                <small class="account-scout-field-help">
                    We format U.S. phone numbers automatically.
                </small>
            </label>

        </div>
    </section>


    <section class="account-scout-panel">
        <p class="account-eyebrow">Experience</p>
        <h2>Tell us about your perspective</h2>

        <div class="account-scout-form-stack">

            <label>
                <span>Why would you like to become a Llama Scout?</span>
                <textarea
                    name="why_scout"
                    rows="5"
                    maxlength="4000"
                ><?= htmlspecialchars(
                    scout_app_value($application ?: [], 'why_scout'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>
            </label>

            <label>
                <span>Travel, camping, overlanding, or outdoor experience</span>
                <textarea
                    name="travel_experience"
                    rows="5"
                    maxlength="4000"
                ><?= htmlspecialchars(
                    scout_app_value($application ?: [], 'travel_experience'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>
            </label>

            <label>
                <span>Experience evaluating roads, campsites, or outdoor conditions</span>
                <textarea
                    name="field_experience"
                    rows="5"
                    maxlength="4000"
                ><?= htmlspecialchars(
                    scout_app_value($application ?: [], 'field_experience'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>
            </label>

            <label>
                <span>Accessibility experience or perspective (optional)</span>
                <textarea
                    name="accessibility_experience"
                    rows="4"
                    maxlength="4000"
                ><?= htmlspecialchars(
                    scout_app_value($application ?: [], 'accessibility_experience'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>
            </label>

            <label>
                <span>Sensory experience or perspective (optional)</span>
                <textarea
                    name="sensory_experience"
                    rows="4"
                    maxlength="4000"
                ><?= htmlspecialchars(
                    scout_app_value($application ?: [], 'sensory_experience'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>
            </label>

        </div>
    </section>


    <section class="account-scout-panel">
        <p class="account-eyebrow">Commitments</p>
        <h2>Scout expectations</h2>

        <div class="account-scout-check-list">

            <label>
                <input
                    type="checkbox"
                    name="agrees_accuracy"
                    value="1"
                    <?= isset($_POST['agrees_accuracy'])
                        || !empty($application['agrees_accuracy'])
                        ? 'checked'
                        : '' ?>
                    required
                >
                <span>
                    <strong>Accuracy</strong>
                    I will distinguish what I personally observed from
                    what I do not know, and I will not intentionally
                    submit misleading Place information.
                </span>
            </label>

            <label>
                <input
                    type="checkbox"
                    name="agrees_safety"
                    value="1"
                    <?= isset($_POST['agrees_safety'])
                        || !empty($application['agrees_safety'])
                        ? 'checked'
                        : '' ?>
                    required
                >
                <span>
                    <strong>Safety</strong>
                    I understand that access, road, weather, fire,
                    and other outdoor conditions can change and must
                    be described responsibly.
                </span>
            </label>

            <label>
                <input
                    type="checkbox"
                    name="agrees_conduct"
                    value="1"
                    <?= isset($_POST['agrees_conduct'])
                        || !empty($application['agrees_conduct'])
                        ? 'checked'
                        : '' ?>
                    required
                >
                <span>
                    <strong>Community conduct</strong>
                    I will use Scout access in good faith and follow
                    Llama Scout community and moderation policies.
                </span>
            </label>

        </div>
    </section>

    <div class="account-scout-form-actions">
        <button
            class="account-scout-button"
            type="submit"
        >
            Submit and continue to training
        </button>
    </div>

</form>

</div>
</section>

<?php if ($geoapifyApiKey !== ''): ?>

<script>
(() => {
    const addressInput =
        document.querySelector('[data-scout-mailing-address]');

    if (!addressInput) {
        return;
    }

    const suggestionsBox =
        document.querySelector('[data-scout-address-suggestions]');

    const status =
        document.querySelector('[data-scout-address-status]');

    const city =
        document.querySelector('[data-scout-address-city]');

    const state =
        document.querySelector('[data-scout-address-state]');

    const postal =
        document.querySelector('[data-scout-address-postal]');

    const country =
        document.querySelector('[data-scout-address-country]');

    const locationButton =
        document.querySelector('[data-scout-address-location]');

    let debounceTimer = null;
    let requestController = null;
    let biasLatitude = null;
    let biasLongitude = null;

    const setStatus = (message, kind = '') => {
        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle(
            'is-good',
            kind === 'good'
        );
        status.classList.toggle(
            'is-error',
            kind === 'error'
        );
    };

    const closeSuggestions = () => {
        if (!suggestionsBox) {
            return;
        }

        suggestionsBox.replaceChildren();
        suggestionsBox.hidden = true;

        addressInput.setAttribute(
            'aria-expanded',
            'false'
        );
    };

    const fillAddress = (result) => {
        const line1 =
            String(
                result.address_line_1
                ?? ''
            ).trim();

        const locality =
            String(
                result.city
                ?? ''
            ).trim();

        const region =
            String(
                result.state
                ?? ''
            ).trim();

        const postcode =
            String(
                result.postal_code
                ?? ''
            ).trim();

        const countryName =
            String(
                result.country
                ?? ''
            ).trim();

        if (line1 !== '') {
            addressInput.value = line1;
        }

        if (city && locality !== '') {
            city.value = locality;
        }

        if (state && region !== '') {
            state.value = region;
        }

        if (postal && postcode !== '') {
            postal.value = postcode;
        }

        if (country && countryName !== '') {
            country.value = countryName;
        }

        closeSuggestions();

        setStatus(
            'Address selected.',
            'good'
        );
    };

    const renderSuggestions = (results) => {
        if (!suggestionsBox) {
            return;
        }

        suggestionsBox.replaceChildren();

        for (const result of results) {
            const label =
                String(
                    result.label
                    ?? ''
                ).trim();

            if (label === '') {
                continue;
            }

            const button =
                document.createElement(
                    'button'
                );

            button.type = 'button';
            button.className =
                'account-scout-address-suggestion';
            button.setAttribute(
                'role',
                'option'
            );
            button.textContent = label;

            button.addEventListener(
                'click',
                () => {
                    fillAddress(result);
                }
            );

            suggestionsBox.appendChild(
                button
            );
        }

        const hasResults =
            suggestionsBox
                .childElementCount > 0;

        suggestionsBox.hidden =
            !hasResults;

        addressInput.setAttribute(
            'aria-expanded',
            hasResults
                ? 'true'
                : 'false'
        );

        if (!hasResults) {
            setStatus(
                'No match yet. Keep typing.'
            );
        }
    };

    const findSuggestions = async () => {
        const query =
            addressInput.value.trim();

        if (query.length < 3) {
            closeSuggestions();

            if (query.length > 0) {
                setStatus(
                    'Keep typing...'
                );
            } else {
                setStatus('');
            }

            return;
        }

        if (requestController) {
            requestController.abort();
        }

        requestController =
            new AbortController();

        try {
            const params =
                new URLSearchParams({
                    q: query,
                });

            if (
                Number.isFinite(biasLatitude)
                && Number.isFinite(biasLongitude)
            ) {
                params.set(
                    'lat',
                    String(biasLatitude)
                );
                params.set(
                    'lon',
                    String(biasLongitude)
                );
            }

            const response =
                await fetch(
                    '/api/address-autocomplete.php?'
                    + params.toString(),
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                        },
                        signal:
                            requestController.signal,
                    }
                );

            const payload =
                await response.json();

            if (
                !response.ok
                || !payload.success
            ) {
                throw new Error(
                    payload.message
                    || 'Address lookup failed.'
                );
            }

            renderSuggestions(
                Array.isArray(payload.results)
                    ? payload.results
                    : []
            );
        } catch (error) {
            if (
                error
                && error.name === 'AbortError'
            ) {
                return;
            }

            closeSuggestions();

            setStatus(
                'Search unavailable.',
                'error'
            );
        }
    };

    locationButton?.addEventListener(
        'click',
        () => {
            if (!navigator.geolocation) {
                setStatus(
                    'Location unavailable.',
                    'error'
                );
                return;
            }

            locationButton.disabled = true;
            locationButton.classList.add('is-loading');

            const buttonLabel =
                locationButton.querySelector('span');

            if (buttonLabel) {
                buttonLabel.textContent =
                    'Finding location...';
            }

            setStatus(
                'Finding your location...'
            );

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    biasLatitude =
                        Number(
                            position.coords.latitude
                        );

                    biasLongitude =
                        Number(
                            position.coords.longitude
                        );

                    locationButton.disabled = false;
                    locationButton.classList.remove('is-loading');
                    locationButton.classList.add('is-ready');

                    if (buttonLabel) {
                        buttonLabel.textContent =
                            'Nearby results enabled';
                    }

                    setStatus(
                        'Nearby results enabled.',
                        'good'
                    );

                    if (
                        addressInput.value.trim().length >= 3
                    ) {
                        void findSuggestions();
                    }
                },
                (error) => {
                    locationButton.disabled = false;
                    locationButton.classList.remove('is-loading');

                    if (buttonLabel) {
                        buttonLabel.textContent =
                            'Use my location';
                    }

                    let message =
                        'Location unavailable.';

                    if (
                        error
                        && error.code === 1
                    ) {
                        message =
                            'Location not allowed.';
                    }

                    setStatus(
                        message,
                        'error'
                    );
                },
                {
                    enableHighAccuracy: false,
                    timeout: 8000,
                    maximumAge: 300000,
                }
            );
        }
    );

    addressInput.addEventListener(
        'input',
        () => {
            setStatus('');

            window.clearTimeout(
                debounceTimer
            );

            debounceTimer =
                window.setTimeout(
                    () => {
                        void findSuggestions();
                    },
                    350
                );
        }
    );

    addressInput.addEventListener(
        'keydown',
        (event) => {
            if (event.key === 'Escape') {
                closeSuggestions();
            }

            if (
                event.key === 'ArrowDown'
                && suggestionsBox
                && !suggestionsBox.hidden
            ) {
                const first =
                    suggestionsBox
                        .querySelector(
                            'button'
                        );

                if (first) {
                    event.preventDefault();
                    first.focus();
                }
            }
        }
    );

    document.addEventListener(
        'click',
        (event) => {
            if (
                !event.target.closest(
                    '.account-scout-address-field'
                )
            ) {
                closeSuggestions();
            }
        }
    );
})();
</script>

<?php endif; ?>


<script>
(() => {
    const phone =
        document.querySelector('[data-scout-phone]');

    const country =
        document.querySelector('[data-scout-address-country]');

    if (!phone) {
        return;
    }

    const isUnitedStates = () => {
        const value =
            String(
                country?.value
                ?? ''
            )
            .trim()
            .toLowerCase();

        return [
            'united states',
            'united states of america',
            'usa',
            'us',
            'u.s.',
            'u.s.a.',
        ].includes(value);
    };

    const formatUsPhone = (value) => {
        let digits =
            String(value)
                .replace(/\D/g, '');

        if (
            digits.length === 11
            && digits.startsWith('1')
        ) {
            digits =
                digits.slice(1);
        }

        digits =
            digits.slice(0, 10);

        if (digits.length === 0) {
            return '';
        }

        if (digits.length < 4) {
            return '(' + digits;
        }

        if (digits.length < 7) {
            return '('
                + digits.slice(0, 3)
                + ') '
                + digits.slice(3);
        }

        return '('
            + digits.slice(0, 3)
            + ') '
            + digits.slice(3, 6)
            + '-'
            + digits.slice(6);
    };

    const applyFormat = () => {
        if (!isUnitedStates()) {
            return;
        }

        phone.value =
            formatUsPhone(
                phone.value
            );
    };

    phone.addEventListener(
        'input',
        applyFormat
    );

    phone.addEventListener(
        'blur',
        applyFormat
    );

    country?.addEventListener(
        'change',
        applyFormat
    );

    applyFormat();
})();
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
