<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

require_verified_email();

function checkin_h(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function checkin_viewer_date(
    ?string $value,
    string $format = 'M j, Y'
): string {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (function_exists('llama_format_viewer_date')) {
        return llama_format_viewer_date(
            $value,
            $format
        );
    }

    $timestamp = strtotime($value);

    return $timestamp
        ? date($format, $timestamp)
        : $value;
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$db = db();
$pageRobots = 'noindex,nofollow';

$slug = trim(
    (string) (
        $_GET['place']
        ?? $_POST['place_slug']
        ?? ''
    )
);

$place =
    llama_place_checkin_place_by_slug(
        $db,
        $slug
    );

if (!$place) {
    http_response_code(404);
    exit('Place not found.');
}

$access =
    llama_place_checkin_user_access(
        $db,
        $userId
    );

if (empty($access['allowed'])) {
    http_response_code(403);
    $pageTitle = 'Check In | Llama Scout';
    $pageStyles = [
        'site/pages/check-in.css',
    ];
    require __DIR__ . '/partials/header.php';
    ?>
    <main class="place-checkin-page" id="main-content">
        <section class="place-checkin-card">
            <p class="place-checkin-eyebrow">Place check-in</p>
            <h1>Check-in unavailable</h1>
            <p>
                Your account is not currently eligible to submit Place check-ins.
            </p>
            <a
                class="place-checkin-button"
                href="/place.php?slug=<?= rawurlencode($slug) ?>"
            >
                Back to Place
            </a>
        </section>
    </main>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$policy =
    llama_place_checkin_policy($db);

$cooldown =
    llama_place_checkin_cooldown_status(
        $db,
        $userId,
        (int) $place['id'],
        (int) $policy['cooldown_days']
    );

$completeAccess =
    !empty($access['complete_access']);

$contributionLabel =
    (string) ($access['level_label'] ?? 'Community Contributed');

$contributionShortLabel =
    (string) ($access['short_label'] ?? 'Community');

$placeUrl =
    '/place.php?slug=' .
    rawurlencode($slug);

$reportUrl =
    $placeUrl .
    '&report=1#report-place';

$updateUrl =
    'https://account.llamascout.com/update-place.php?slug=' .
    rawurlencode($slug);

/* =========================================================
   LOCATION PREFLIGHT
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    (string) ($_POST['checkin_action'] ?? '')
        === 'locate'
) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');

    if (
        !llama_place_checkin_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' =>
                'Your session token expired. Reload this page and try again.',
        ]);
        exit;
    }

    try {
        $latitude =
            filter_var(
                $_POST['latitude']
                ?? null,
                FILTER_VALIDATE_FLOAT
            );
        $longitude =
            filter_var(
                $_POST['longitude']
                ?? null,
                FILTER_VALIDATE_FLOAT
            );
        $accuracy =
            filter_var(
                $_POST['accuracy']
                ?? null,
                FILTER_VALIDATE_FLOAT
            );

        if (
            $latitude === false
            || $longitude === false
            || $accuracy === false
        ) {
            throw new InvalidArgumentException(
                'Your device did not provide a usable location.'
            );
        }

        $result =
            llama_place_checkin_preflight(
                $db,
                $userId,
                $place,
                (float) $latitude,
                (float) $longitude,
                (float) $accuracy
            );

        echo json_encode([
            'ok' => true,
            'result' => $result,
        ]);
        exit;

    } catch (Throwable $exception) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' =>
                $exception->getMessage(),
        ]);
        exit;
    }
}

/* =========================================================
   FINAL CHECK IN
   ========================================================= */

$submitError = '';
$success = null;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    (string) ($_POST['checkin_action'] ?? '')
        === 'submit'
) {
    if (
        !llama_place_checkin_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $submitError =
            'Your session token expired. Reload this page and try again.';
    } else {
        try {
            $success =
                llama_place_checkin_submit(
                    $db,
                    $userId,
                    $place,
                    (string) (
                        $_POST['proof_token']
                        ?? ''
                    ),
                    $_POST
                );

            $cooldown =
                llama_place_checkin_cooldown_status(
                    $db,
                    $userId,
                    (int) $place['id'],
                    (int) $policy['cooldown_days']
                );

        } catch (Throwable $exception) {
            $submitError =
                $exception->getMessage();
        }
    }
}

$pageTitle =
    'Check In at ' .
    (string) $place['name'] .
    ' | Llama Scout';

$pageDescription =
    'Confirm that you are physically at ' .
    (string) $place['name'] .
    ' and help keep Llama Scout Place information fresh.';

$pageStyles = [
    'site/pages/check-in.css',
];

require __DIR__ . '/partials/header.php';
?>

<main
    class="place-checkin-page"
    id="main-content"
    data-checkin-page
    data-place-slug="<?= checkin_h($slug) ?>"
    data-place-url="<?= checkin_h($placeUrl) ?>"
    data-report-url="<?= checkin_h($reportUrl) ?>"
>

    <header class="place-checkin-heading">
        <a href="<?= checkin_h($placeUrl) ?>">
            <i aria-hidden="true"><?= llama_icon('arrow-left') ?></i>
            Back to Place
        </a>

        <p class="place-checkin-eyebrow">
            Place check-in
        </p>

        <h1>
            <?= checkin_h($place['name']) ?>
        </h1>

        <div class="place-checkin-level">
            <i aria-hidden="true"><?= llama_icon(
                llama_contribution_level_icon(
                    (string) $access['level']
                )
            ) ?></i>
            <span>
                Checking in as
                <strong><?= checkin_h($contributionShortLabel) ?></strong>
            </span>
        </div>

        <p>
            A check-in records that you were physically at this Place. It keeps
            the history fresh without forcing you to submit an update when
            nothing changed.
        </p>
    </header>

    <?php if ($success): ?>
        <section class="place-checkin-card place-checkin-success">
            <i aria-hidden="true"><?= llama_icon('circle-check') ?></i>

            <div>
                <p class="place-checkin-eyebrow">Checked in</p>
                <h2>Your on-site check-in was recorded.</h2>
                <p>
                    This check-in was recorded as
                    <strong><?= checkin_h(
                        (string) ($success['contribution_label'] ?? $contributionLabel)
                    ) ?></strong>.
                    <?php if ((int) ($success['points_awarded'] ?? 0) > 0): ?>
                        You earned
                        <strong>
                            +<?= number_format((int) $success['points_awarded']) ?> points
                        </strong>.
                    <?php endif; ?>
                </p>
            </div>

            <a
                class="place-checkin-button"
                href="<?= checkin_h($placeUrl) ?>"
            >
                Return to Place
            </a>
        </section>

    <?php elseif (empty($cooldown['eligible'])): ?>
        <section class="place-checkin-card">
            <i aria-hidden="true"><?= llama_icon('calendar-check') ?></i>

            <div>
                <p class="place-checkin-eyebrow">Already checked in</p>
                <h2>You recently checked in here.</h2>
                <p>
                    To prevent repeated point farming from the same stay, each
                    member can earn one check-in for this Place every
                    <?= number_format((int) $policy['cooldown_days']) ?> days.
                </p>

                <?php if (!empty($cooldown['next_eligible_at'])): ?>
                    <p>
                        Your next rewarded check-in is available
                        <strong>
                            <?= checkin_h(
                                checkin_viewer_date(
                                    (string) $cooldown['next_eligible_at']
                                )
                            ) ?>
                        </strong>.
                    </p>
                <?php endif; ?>

                <p>
                    You can always submit a Place update or report a problem
                    during the cooldown.
                </p>
            </div>

            <div class="place-checkin-actions">
                <a
                    class="place-checkin-button"
                    href="<?= checkin_h($placeUrl) ?>"
                >
                    Back to Place
                </a>

                <a
                    class="place-checkin-button is-secondary"
                    href="<?= checkin_h($updateUrl) ?>"
                >
                    Suggest Update
                </a>

                <a
                    class="place-checkin-button is-danger"
                    href="<?= checkin_h($reportUrl) ?>"
                >
                    Report a Problem
                </a>
            </div>
        </section>

    <?php else: ?>

        <?php if ($submitError !== ''): ?>
            <div class="place-checkin-message is-error" role="alert">
                <i aria-hidden="true"><?= llama_icon('alert-triangle') ?></i>
                <p><?= checkin_h($submitError) ?></p>
            </div>
        <?php endif; ?>

        <section
            class="place-checkin-card"
            data-checkin-location-card
        >
            <i aria-hidden="true"><?= llama_icon('current-location') ?></i>

            <div>
                <p class="place-checkin-eyebrow">Step 1</p>
                <h2>Make sure you're actually there.</h2>
                <p>
                    Llama Scout will ask your device for its current location and
                    compare it with this Place's stored coordinates.
                </p>
                <p class="place-checkin-privacy">
                    Your exact device coordinates are used only for this location
                    check. Llama Scout stores the resulting distance and GPS
                    accuracy, not your exact device location.
                </p>
            </div>

            <button
                class="place-checkin-button"
                type="button"
                data-checkin-locate
                data-csrf="<?= checkin_h(llama_place_checkin_csrf_token()) ?>"
            >
                <i aria-hidden="true"><?= llama_icon('current-location') ?></i>
                Check my location
            </button>

            <div
                class="place-checkin-location-status"
                data-checkin-location-status
                aria-live="polite"
            ></div>
        </section>

        <section
            class="place-checkin-card place-checkin-confirmation"
            data-checkin-confirmation
            hidden
        >
            <div>
                <p class="place-checkin-eyebrow">Step 2</p>
                <h2>What can you confirm while you're here?</h2>

                <?php if ($completeAccess): ?>
                    <p>
                        You can see the Complete Place Report, so this check-in
                        confirms both physical presence and that the current road,
                        access, and site information still generally matches.
                    </p>
                <?php else: ?>
                    <p>
                        Your Free account does not expose the Complete Place Report.
                        This Community check-in therefore confirms physical presence,
                        that the Place exists here, and that you did not encounter an
                        obvious major problem. It does not certify hidden report details.
                    </p>
                <?php endif; ?>
            </div>

            <form method="post" class="place-checkin-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= checkin_h(llama_place_checkin_csrf_token()) ?>"
                >
                <input type="hidden" name="checkin_action" value="submit">
                <input type="hidden" name="place_slug" value="<?= checkin_h($slug) ?>">
                <input type="hidden" name="proof_token" value="" data-checkin-proof>

                <label>
                    <input type="checkbox" name="pin_confirmed" value="1" required>
                    <span>
                        I am physically at the Place I intended to check in to.
                    </span>
                </label>

                <label>
                    <input type="checkbox" name="place_exists_confirmed" value="1" required>
                    <span>
                        This Place exists here and I was able to identify it on site.
                    </span>
                </label>

                <?php if ($completeAccess): ?>
                    <label>
                        <input type="checkbox" name="access_confirmed" value="1" required>
                        <span>
                            The road and access information still generally matches what I found.
                        </span>
                    </label>

                    <label>
                        <input type="checkbox" name="site_confirmed" value="1" required>
                        <span>
                            The site details and listed amenities still generally match what I found.
                        </span>
                    </label>
                <?php endif; ?>

                <label>
                    <input type="checkbox" name="no_major_issue" value="1" required>
                    <span>
                        I did not encounter an obvious closure, access, location, or safety problem that needs attention.
                    </span>
                </label>

                <p class="place-checkin-form-help">
                    If any statement is not true, do not confirm this Place. Submit
                    an update for ordinary changes or report a serious problem instead.
                </p>

                <button class="place-checkin-button" type="submit">
                    <i aria-hidden="true"><?= llama_icon('check') ?></i>
                    Confirm Check In
                    <?php if ((int) $policy['points'] > 0): ?>
                        <span>+<?= number_format((int) $policy['points']) ?> points</span>
                    <?php endif; ?>
                </button>
            </form>

            <div class="place-checkin-actions">
                <a
                    class="place-checkin-button is-secondary"
                    href="<?= checkin_h($updateUrl) ?>"
                >
                    Suggest Update
                </a>

                <a
                    class="place-checkin-button is-danger"
                    href="<?= checkin_h($reportUrl) ?>"
                >
                    Report a Problem
                </a>
            </div>
        </section>

        <section
            class="place-checkin-card place-checkin-too-far"
            data-checkin-too-far
            hidden
        >
            <i aria-hidden="true"><?= llama_icon('alert-triangle') ?></i>

            <div>
                <p class="place-checkin-eyebrow">Location doesn't match</p>
                <h2>It doesn't quite look like you're there yet.</h2>
                <p data-checkin-too-far-message>
                    Your device location is outside the allowed check-in area.
                </p>
                <p>
                    If the stored coordinates are wrong, the road is closed, the
                    Place is inaccessible, or the Place does not exist, report the
                    problem instead of checking in.
                </p>
            </div>

            <div class="place-checkin-actions">
                <a
                    class="place-checkin-button"
                    href="<?= checkin_h($placeUrl) ?>"
                >
                    Back to Place
                </a>

                <a
                    class="place-checkin-button is-danger"
                    href="<?= checkin_h($reportUrl) ?>"
                >
                    Report a Problem
                </a>
            </div>
        </section>

    <?php endif; ?>

</main>

<?php if (!$success && !empty($cooldown['eligible'])): ?>
    <script src="/js/check-in.js"></script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
