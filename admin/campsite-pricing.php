<?php

declare(strict_types=1);

/**
 * Single-Site pricing editor, visual integration pass.
 * Reads existing Places, canonical Sites, Place seasons and pricing v2.
 * Writes deliberately stay in the established Place pricing workflows
 * until the per-Site seasonal save adapter is implemented.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
require_once dirname(__DIR__) . '/app/place-pricing.php';
require_once dirname(__DIR__) . '/app/place-report/season-definitions.php';
require_once dirname(__DIR__) . '/app/admin-campsite-pricing.php';
require_once dirname(__DIR__) . '/app/place-report/season-public.php';

// Apply the Admin permission check before either a JSON reader or writer.
$adminUser = moderation_require_admin();
$db = db();
$actorId = (int) ($adminUser['id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    try {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || strlen($raw) > 24000) {
            throw new InvalidArgumentException('The pricing change is too large.');
        }
        $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($input) || !in_array(
            (string) ($input['action'] ?? ''), ['save', 'save-holidays'], true
        )) {
            throw new InvalidArgumentException('Invalid pricing save request.');
        }
        if (!moderation_verify_csrf((string) ($input['csrf_token'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Your Admin session expired. Reload before saving.']);
            exit;
        }
        if ($input['action'] === 'save-holidays') {
            if (!isset($input['holidays']) || !is_array($input['holidays'])
                || !array_is_list($input['holidays'])) {
                throw new InvalidArgumentException('Invalid holiday calendar.');
            }
            $result = admin_cpr_save_holidays(
                $db,
                (int) ($input['place_id'] ?? 0),
                (int) ($input['site_id'] ?? 0),
                $actorId,
                (string) ($input['version'] ?? ''),
                $input['holidays']
            );
        } else {
            $result = admin_cpr_save(
                $db,
                (int) ($input['place_id'] ?? 0),
                (int) ($input['site_id'] ?? 0),
                $actorId,
                (string) ($input['version'] ?? ''),
                (array) ($input['changes'] ?? [])
            );
        }
        echo json_encode($result, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        $status = $exception instanceof RuntimeException
            && (int) $exception->getCode() === 409 ? 409
            : ($exception instanceof InvalidArgumentException || $exception instanceof JsonException ? 422 : 500);
        http_response_code($status);
        if ($status === 500) {
            llama_log_caught_exception($exception, 'admin.campsite_pricing.save');
        }
        echo json_encode([
            'success' => false,
            'message' => $status === 500
                ? 'The pricing change could not be saved. Existing rates were not changed.'
                : $exception->getMessage(),
        ], JSON_UNESCAPED_SLASHES);
    }
    exit;
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    $respond = static function (array $data, int $status = 200): never {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    };

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        $respond(['ok' => false, 'error' => 'Read-only request required.'], 405);
    }

    try {
        $action = (string) $_GET['ajax'];

        if ($action === 'search') {
            $query = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
            $where = '';
            $values = [];
            if ($query !== '') {
                $where = 'WHERE p.name LIKE ? OR p.slug LIKE ? OR p.city LIKE ? OR p.state LIKE ? OR CAST(p.id AS CHAR) = ?';
                $like = '%' . $query . '%';
                $values = [$like, $like, $like, $like, $query];
            }
            $stmt = $db->prepare(
                'SELECT p.id, p.name, p.slug, p.city, p.state
                 FROM places p ' . $where . '
                 ORDER BY p.name ASC, p.id ASC LIMIT 18'
            );
            $stmt->execute($values);
            $respond(['ok' => true, 'places' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
        }

        $placeId = filter_var($_GET['place_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$placeId || $placeId < 1) {
            $respond(['ok' => false, 'error' => 'Choose an existing Place.'], 400);
        }
        $stmt = $db->prepare('SELECT id, name, slug, city, state FROM places WHERE id = ? LIMIT 1');
        $stmt->execute([$placeId]);
        $place = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($place)) {
            $respond(['ok' => false, 'error' => 'Place not found.'], 404);
        }

        if ($action === 'place') {
            $sites = [];
            foreach (llama_place_campsites($db, $placeId) as $site) {
                $siteId = (int) ($site['id'] ?? 0);
                if ($siteId < 1) continue;
                $number = trim((string) ($site['site_code'] ?? ''));
                $name = trim((string) ($site['site_name'] ?? ''));
                $label = $name !== '' ? $name : ($number !== '' ? 'Site ' . $number : 'Site #' . $siteId);
                $sites[] = [
                    'id' => $siteId,
                    'label' => $label,
                    'number' => $number,
                    'name' => $name,
                    'type' => (string) ($site['site_type'] ?? ''),
                    'layout' => (string) ($site['parking_style'] ?? ''),
                    'hookups' => (string) ($site['hookup_status'] ?? ''),
                    'electric' => (string) ($site['electric_service'] ?? ''),
                    'max_people' => $site['max_people'] ?? null,
                    'max_vehicles' => $site['max_vehicles'] ?? null,
                ];
            }
            $seasons = llama_seasons_for_place($db, $placeId);
            $placeClosed = [];
            foreach ($seasons as $season) {
                $conditions = llama_public_season_place_conditions(
                    $db, $placeId, (int) $season['id']
                );
                $placeClosed[(string) $season['id']] =
                    ($conditions['seasonal_place_closed']['value'] ?? null) === true;
            }
            $respond([
                'ok' => true,
                'place' => $place,
                'sites' => $sites,
                'place_closed_by_season' => $placeClosed,
                'seasons' => array_map(static fn(array $s): array => [
                    'id' => (int) $s['id'],
                    'name' => (string) $s['name'],
                    'start' => (string) ($s['start_mmdd'] ?? ''),
                    'end' => (string) ($s['end_mmdd'] ?? ''),
                    'default' => (bool) ($s['is_default'] ?? false),
                ], $seasons),
            ]);
        }

        if ($action === 'site') {
            $siteId = filter_var($_GET['site_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$siteId || $siteId < 1) {
                $respond(['ok' => false, 'error' => 'Choose an existing Site.'], 400);
            }
            $snapshot = admin_cpr_snapshot($db, $placeId, $siteId);
            $rate = $snapshot['rate'];
            $respond([
                'ok' => true,
                'site_id' => $siteId,
                'version' => $snapshot['version'],
                'rate_conflict' => $snapshot['rate_conflict'],
                'rate' => $rate === null ? null : [
                    'name' => (string) ($rate['name'] ?? ''),
                    'weekday' => $rate['price'] ?? null,
                    'weekend' => $rate['weekend_price'] ?? null,
                    'holiday' => $rate['holiday_price'] ?? null,
                    'seasons' => array_values((array) ($rate['seasons'] ?? [])),
                ],
                'availability' => $snapshot['availability'],
                'rates_by_season' => $snapshot['rates_by_season'],
                'weekend_days' => (array) ($snapshot['document']['weekend_days'] ?? [5, 6]),
                'holidays' => (array) ($snapshot['document']['holidays'] ?? []),
            ]);
        }

        $respond(['ok' => false, 'error' => 'Unknown data request.'], 400);
    } catch (Throwable $exception) {
        llama_log_caught_exception($exception, 'admin.campsite_pricing', [
            'action' => (string) ($_GET['ajax'] ?? ''),
            'place_id' => (int) ($_GET['place_id'] ?? 0),
        ]);
        $respond(['ok' => false, 'error' => 'Pricing data could not be loaded.'], 500);
    }
}

$adminPageTitle = 'Campsite Pricing';
$adminPageEyebrow = 'Place Administration';
$adminActiveNav = 'campsite_pricing';

require __DIR__ . '/_header.php';
?>
<div class="cpr" id="cpr" data-cpr-endpoint="/campsite-pricing.php">
    <input type="hidden" id="cpr-csrf"
           value="<?= htmlspecialchars(moderation_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <p class="cpr-intro">Search for a Place, then select a Site to manage seasonal availability and rates.</p>

    <div class="cpr-card cpr-search-panel">
        <div class="cpr-search-row">
            <label for="cpr-place-search">Search place</label>
            <div class="cpr-place-search-wrap">
                <span class="cpr-search-icon" aria-hidden="true"><?= llama_icon('search') ?></span>
                <input id="cpr-place-search" type="search" autocomplete="off"
                       placeholder="Find a Place by name, slug, or location"
                       role="combobox" aria-autocomplete="list"
                       aria-expanded="false" aria-controls="cpr-place-results">
                <div id="cpr-place-results" class="cpr-search-results" role="listbox" hidden></div>
            </div>
            <span class="cpr-search-hint">Choose a Place first</span>
        </div>
        <div class="cpr-search-row cpr-site-row">
            <label for="cpr-site">Select site</label>
            <div class="cpr-site-select">
                <span aria-hidden="true"><?= llama_icon('tent') ?></span>
                <select id="cpr-site" disabled><option value="">Select a Place first</option></select>
            </div>
            <div class="cpr-quick-actions">
                <button type="button" class="cpr-ghost" id="cpr-copy-season" disabled>
                    <?= llama_icon('copy') ?> Copy season
                </button>
                <button type="button" class="cpr-ghost" id="cpr-apply-sites" disabled
                        title="Bulk Site pricing will be connected after individual Site saving">
                    <?= llama_icon('layers') ?> Apply to other sites
                </button>
            </div>
        </div>
    </div>

    <div class="cpr-card cpr-site-banner" id="cpr-site-banner">
        <div class="cpr-site-identity">
            <div class="cpr-site-icon" aria-hidden="true"><?= llama_icon('tent') ?></div>
            <div><strong id="cpr-site-title">Choose a Site</strong>
                <span id="cpr-site-subtitle">Select a Place and Site above to see its details.</span>
            </div>
        </div>
        <div class="cpr-site-stat"><?= llama_icon('users') ?><span>Max People<strong id="cpr-max-people">—</strong></span></div>
        <div class="cpr-site-stat"><?= llama_icon('car') ?><span>Max Vehicles<strong id="cpr-max-vehicles">—</strong></span></div>
        <div class="cpr-site-stat"><?= llama_icon('arrows-left-right') ?><span>Site Type<strong id="cpr-layout">—</strong></span></div>
        <div class="cpr-site-stat"><?= llama_icon('bolt') ?><span>Hookups<strong id="cpr-hookups">—</strong></span></div>
    </div>

    <div class="cpr-view-tabs" role="group" aria-label="Site pricing views">
        <button type="button" class="is-selected" aria-pressed="true" data-cpr-view="pricing">
            <?= llama_icon('tag') ?> Pricing &amp; Seasons
        </button>
        <button type="button" data-cpr-view="details"><?= llama_icon('info-circle') ?> Site Details</button>
        <button type="button" data-cpr-view="photos"><?= llama_icon('photo') ?> Photos</button>
    </div>

    <div class="cpr-editor-layout" id="cpr-editor-layout">
        <aside class="cpr-card cpr-seasons">
            <h2><?= llama_icon('calendar') ?> Seasons</h2>
            <p>Define seasons for this Place and set availability and rates for the selected Site.</p>
            <button type="button" id="cpr-add-season" class="cpr-ghost cpr-add-season" disabled>
                <?= llama_icon('plus') ?> Add Season
            </button>
            <div id="cpr-seasons-list" class="cpr-seasons-list" aria-label="Seasons">
                <p class="cpr-muted">Select a Place to load its seasons.</p>
            </div>
        </aside>
        <div class="cpr-main-column">
            <section class="cpr-card cpr-season-editor" id="cpr-season-editor">
                <h2 id="cpr-season-title">Select a Site</h2>
                <div class="cpr-season-top">
                    <label>Start date<input type="text" id="cpr-start" placeholder="MM-DD" maxlength="5" disabled></label>
                    <span class="cpr-date-dash" aria-hidden="true">−</span>
                    <label>End date<input type="text" id="cpr-end" placeholder="MM-DD" maxlength="5" disabled></label>
                    <fieldset class="cpr-status-field" id="cpr-status-field" disabled>
                        <legend>Availability for this Site <span title="These states are separate from Place-wide closure."><?= llama_icon('info-circle') ?></span></legend>
                        <div class="cpr-segments" id="cpr-availability">
                            <button type="button" data-availability="available" aria-pressed="false">Available</button>
                            <button type="button" data-availability="reservable" aria-pressed="false">Reservable</button>
                            <button type="button" data-availability="fcfs" aria-pressed="false">FCFS</button>
                            <button type="button" data-availability="unavailable" aria-pressed="false">Unavailable</button>
                        </div>
                    </fieldset>
                </div>

                <div class="cpr-rate-panel">
                    <h3 id="cpr-rates-title">Rates for selected Site</h3>
                    <p>Set separate prices for weekday, weekend, and holiday stays in this season.</p>
                    <div class="cpr-rate-types">
                        <span>Site / Rate Type</span>
                        <div class="cpr-segments" role="group" aria-label="Site capacity">
                            <button type="button" data-capacity="single" aria-pressed="false">Single</button>
                            <button type="button" data-capacity="double" aria-pressed="false">Double</button>
                            <button type="button" data-capacity="group" aria-pressed="false">Group</button>
                        </div>
                        <div class="cpr-segments" role="group" aria-label="Parking style">
                            <button type="button" data-layout="back_in" aria-pressed="false">Back-in</button>
                            <button type="button" data-layout="pull_through" aria-pressed="false">Pull-through</button>
                        </div>
                        <div class="cpr-segments" role="group" aria-label="Utilities">
                            <button type="button" data-utility="electric" aria-pressed="false">Electric</button>
                            <button type="button" data-utility="full" aria-pressed="false">Hookups</button>
                        </div>
                    </div>
                    <div class="cpr-rate-rows">
                        <?php foreach ([
                            ['weekday', 'calendar', 'Weekday Rate', 'Typically Sunday through Thursday'],
                            ['weekend', 'calendar', 'Weekend Rate', 'Typically Friday and Saturday'],
                            ['holiday', 'star', 'Holiday Rate', 'Designated holidays, separate from weekends'],
                            ['vehicle', 'car', 'Extra Vehicle', 'Per additional vehicle, per night'],
                            ['group', 'users', 'Group Surcharge', 'Per night, when used as a group Site'],
                        ] as [$key, $icon, $label, $description]): ?>
                            <label class="cpr-rate-row" for="cpr-rate-<?= $key ?>">
                                <span class="cpr-rate-label"><?= llama_icon($icon) ?>
                                    <span><strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></small>
                                    </span>
                                </span>
                                <span class="cpr-money"><b>$</b><input id="cpr-rate-<?= $key ?>"
                                     data-cpr-rate="<?= $key ?>" type="number" min="0" max="999999.99"
                                     step="0.01" inputmode="decimal" placeholder="—" disabled></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <div class="cpr-holiday-calendar-action">
                <span>Holiday dates are shared by this Place; holiday rates are set per Site.</span>
                <button type="button" id="cpr-manage-holidays" class="cpr-ghost" disabled>
                    <?= llama_icon('calendar') ?> Manage holiday dates
                </button>
            </div>

            <div class="cpr-preview" aria-live="polite">
                <?= llama_icon('eye') ?>
                <div><strong>Public Pricing Preview</strong>
                    <span>How this Site will appear to campers for the selected season.</span>
                    <b id="cpr-preview">Select a Place, Site and season to preview rates.</b>
                </div>
            </div>
            <div class="cpr-closure-note"><?= llama_icon('info-circle') ?>
                Place seasonal closure is managed separately at the Place level.
                <a href="#" id="cpr-place-seasons-link" hidden>Manage Place seasons</a>
            </div>
        </div>
    </div>
    <footer class="cpr-footer">
        <p class="cpr-save-status" id="cpr-save-status" role="status">
            Choose a Place, Site, and season to manage pricing. Changes save to existing Llama Scout records.
        </p>
        <div>
            <button type="button" class="cpr-ghost" id="cpr-cancel">Cancel</button>
            <button type="button" class="cpr-save" id="cpr-save" disabled>Save Pricing</button>
        </div>
    </footer>
</div>
<dialog class="cpr-holiday-dialog" id="cpr-holiday-dialog" aria-labelledby="cpr-holiday-title">
    <div class="cpr-holiday-modal">
        <header>
            <h2 id="cpr-holiday-title">Place holiday calendar</h2>
            <p>Define the dates that use each Site's holiday rate. This calendar is shared by the entire Place.</p>
        </header>
        <div id="cpr-holiday-rows" class="cpr-holiday-rows"></div>
        <div class="cpr-holiday-actions">
            <button type="button" class="cpr-ghost" id="cpr-holiday-add">
                <?= llama_icon('plus') ?> Add holiday dates
            </button>
            <div>
                <button type="button" class="cpr-ghost" id="cpr-holiday-cancel">Cancel</button>
                <button type="button" class="cpr-save" id="cpr-holiday-save">Save holiday dates</button>
            </div>
        </div>
        <p class="cpr-save-status" id="cpr-holiday-status" role="status"></p>
    </div>
</dialog>
<script defer src="https://llamascout.com/js/admin/campsite-pricing.js?v=<?= (int) filemtime(dirname(__DIR__) . '/js/admin/campsite-pricing.js') ?>"></script>
<?php require __DIR__ . '/_footer.php'; ?>
