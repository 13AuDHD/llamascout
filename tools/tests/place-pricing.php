<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/place-pricing.php';
require_once dirname(__DIR__, 2) . '/app/place-report.php';
set_error_handler(static function (int $level, string $message, string $file, int $line): never { throw new ErrorException($message, 0, $level, $file, $line); });
function check(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
function bad(callable $fn, string $why): void { try { $fn(); } catch (InvalidArgumentException) { return; } throw new RuntimeException($why); }
$doc = llama_pricing_empty();
$doc['holidays'][] = ['name' => 'Holiday', 'start' => '2027-07-02', 'end' => '2027-07-05'];
$doc['rows'][] = ['name' => 'Single pull-through', 'match' => ['category' => 'single', 'layout' => 'pull_through'], 'price' => 24, 'weekend_price' => 28, 'holiday_price' => 32, 'seasons' => [['name' => 'Summer', 'start' => '06-01', 'end' => '08-31', 'recurring' => true, 'price' => 30, 'weekend_price' => 34, 'holiday_price' => 38]]];
$doc = llama_pricing_validate($doc);
$site = ['id' => 2, 'raw_site_type' => 'STANDARD NONELECTRIC', 'parking_style' => 'pull_through', 'features' => [['feature_key' => 'capacity_size_rating', 'feature_value' => 'Single']]];
check(llama_pricing_for_site($doc, $site, '2027-07-03')['amount'] === 38.0, 'Holiday must replace weekend, never add');
check(llama_pricing_for_site($doc, $site, '2027-06-04')['amount'] === 34.0, 'Seasonal weekend');
check(llama_pricing_for_site($doc, $site, '2027-05-04')['amount'] === 24.0, 'Base schedule');
check(llama_pricing_for_site($doc, $site)['label'] === '$24 to $38 / night', 'Published range');
check(llama_pricing_place_summary($doc) === 'From: $24 / night', 'Place card shows the lowest rate');
check(llama_pricing_money(59.8) === '$59.80', 'Prices with cents keep both digits');
$zero = $doc; $zero['rows'][0]['price'] = 0.0;
check(llama_pricing_for_site($zero, $site, '2027-05-04')['label'] === 'Free', 'Explicit zero');
$inherit = $doc; $inherit['rows'][0]['seasons'][0]['weekend_price'] = null;
check(llama_pricing_for_site($inherit, $site, '2027-06-04')['amount'] === 28.0, 'Blank cell inherits same base-schedule cell');
$pullIn = $doc; $pullIn['rows'][0]['match']['layout'] = 'back_pull_in';
$front = $site; $front['parking_style'] = ''; $front['features'][] = ['feature_key' => 'parking_type', 'feature_value' => 'Front-In'];
check(llama_pricing_for_site($pullIn, $front, '2027-05-04')['amount'] === 24.0, 'RIDB Front-In matches back/pull-in rate');
$missing = $site; $missing['features'] = []; $missing['double_driveway'] = true;
check(llama_pricing_for_site($doc, $missing)['state'] === 'needs_review', 'Unknown size is not Single or Double');
$conflict = $doc; $conflict['rows'][] = $doc['rows'][0];
check(llama_pricing_for_site($conflict, $site)['state'] === 'conflict', 'Overlapping matching rows');
$conflict = $doc; $conflict['rows'][0]['seasons'][] = $doc['rows'][0]['seasons'][0];
check(llama_pricing_for_site($conflict, $site, '2027-06-04')['state'] === 'conflict', 'Overlapping dates');
$specific = $doc; $specific['rows'][] = ['name' => 'Site override', 'match' => ['site_ids' => [2]], 'unit' => 'night', 'price' => 99];
check(llama_pricing_for_site($specific, $site, '2027-07-03')['amount'] === 99, 'Explicit Site overrides category schedule');
check(llama_pricing_place_summary($specific) === llama_pricing_place_summary($doc), 'Do not leak individual prices into Place summaries');
$scoped = llama_pricing_empty();
$scoped['rows'] = [
    ['match' => ['site_ids' => [2]], 'seasons' => [['name' => 'Winter', 'start' => '11-16', 'end' => '03-20', 'recurring' => true, 'price' => 15]]],
    ['match' => ['site_ids' => [3]], 'price' => 1],
];
$scoped = llama_pricing_validate($scoped);
check(llama_pricing_place_overview($scoped) === ['label' => '', 'status' => 'unknown'], 'Site prices require public Site matches');
check(llama_pricing_place_overview($scoped, [llama_pricing_for_site($scoped, $site)]) === ['label' => 'From: $15 / night', 'status' => 'paid'], 'Public seasonal Site rates drive the starting price and Paid status');
check(llama_pricing_place_overview($zero) === ['label' => 'From: $0 / night', 'status' => 'mixed'], 'Free and paid nights are mixed');
check(llama_pricing_place_overview(['rows' => [['price' => 0]]]) === ['label' => 'Free', 'status' => 'free'], 'Only zero-price nights are Free');
$weekly = $doc; $weekly['rows'][0]['unit'] = 'week';
check(llama_pricing_for_site($weekly, $site)['state'] === 'unknown', 'Weekly price never divided into nightly');
check(llama_pricing_for_site($weekly, $site, null, 'week')['label'] === '$24 to $38 / week', 'Published weekly unit');
$wrap = ['start' => '11-01', 'end' => '02-28', 'recurring' => true];
check(llama_pricing_in_period($wrap, '2027-01-01') && !llama_pricing_in_period($wrap, '2027-06-01'), 'Recurring season across New Year');
bad(fn() => llama_pricing_amount('garbage'), 'Bad amount must not become zero');
bad(fn() => llama_pricing_amount(-1), 'Negative amount');
bad(fn() => llama_pricing_amount(INF), 'Infinite amount');
bad(fn() => llama_pricing_validate('{broken'), 'Malformed JSON');
bad(fn() => llama_pricing_period(['start' => '2027-02-30', 'end' => '2027-03-01']), 'Invalid dates');
$bad = $doc; $bad['rows'][0]['source_url'] = 'javascript:alert(1)'; bad(fn() => llama_pricing_validate($bad), 'Unsafe source URL');
$bad = $doc; $bad['rows'][0]['match']['site_selection'] = true; $bad['rows'][0]['match']['site_ids'] = []; bad(fn() => llama_pricing_validate($bad), 'An empty explicit Site selection must not apply to all Sites');
$bad = $doc; $bad['rows'][0]['match']['site_ids'] = ['not-a-site']; bad(fn() => llama_pricing_validate($bad), 'Invalid ID');
check(llama_pricing_decode('{broken')['invalid'] === true, 'Persisted invalid data fails closed');
$legacy = llama_pricing_validate([['name' => 'Summer', 'price' => 10, 'season' => 'peak']]);
check($legacy['rows'][0]['needs_review'], 'Undated legacy season not a flat rate');
$event = $doc; $event['rows'][0]['holiday_prices'] = ['holiday-0' => 80];
$event = llama_pricing_validate($event);
check(llama_pricing_for_site($event, $site, '2027-07-03')['amount'] === 80.0, 'Specific event overrides generic summer holiday');
$winter = $event;
$winter['rows'][0]['seasons'][] = ['season_id' => 7, 'price' => 0, 'holiday_price' => null];
$winter = llama_pricing_validate($winter);
$winter['_season_id'] = 7;
check(llama_pricing_for_site($winter, $site, '2027-07-03')['amount'] === 0.0, 'Linked season replaces legacy rates including holiday and weekend prices');
$winter['_season_id'] = 8;
check(llama_pricing_for_site($winter, $site, '2027-07-03')['amount'] === 80.0, 'Another season retains the legacy date schedule');
$winter['rows'][] = [
    'id' => 'another-season', 'name' => 'Another season', 'unit' => 'night',
    'match' => ['site_ids' => [$site['id']]],
    'seasons' => [['season_id' => 9, 'price' => 25]],
];
check(llama_pricing_for_site($winter, $site, '2027-07-03')['amount'] === 80.0, 'Out-of-season rows cannot mask an active price');
$fields = llama_place_report_fields();
check($fields['place_rate_cards_json']['report_scopes'] === ['place'], 'Place owns rates');
check($fields['place_rate_cards_json']['layout_section'] === 'place', 'Editor belongs on Place page');
check(!$fields['fee']['approved_question'], 'No competing base-fee editor');
$parsed = llama_place_report_parse_answer($fields['place_rate_cards_json'], json_encode($doc));
check(json_decode($parsed['value'], true)['version'] === 2, 'Shared parser validates and preserves document');
$built = llama_place_report_build_data(['name' => 'Test Place', 'place_rate_cards_json' => json_encode($doc)]);
check(json_decode($built['rules']['rate_cards_json'], true)['version'] === 2, 'Full report builder retains pricing document');
bad(fn() => llama_place_report_parse_answer($fields['place_rate_cards_json'], '{broken'), 'All canonical writes validate');

// Real SQLite integration checks document persistence reads and batched Site
// attributes without mutating the public official Site facts.
$db = new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE place_rules (place_id INTEGER PRIMARY KEY, rate_cards_json TEXT, fee REAL)');
$db->exec('CREATE TABLE place_report_current_answers (place_id INT, target_scope TEXT, target_source TEXT, target_id INT, question_key TEXT, answer_state TEXT, value_json TEXT)');
$q = $db->prepare('INSERT INTO place_rules VALUES (1, ?, 500)'); $q->execute([json_encode($doc)]);
$loaded = llama_pricing_document($db, ['id' => 1]);
check($loaded === $doc, 'Saved document supersedes obsolete scalar');
$db->exec("INSERT INTO place_report_current_answers VALUES (1, 'site', 'campsite_record', 2, 'capacity_size_rating', 'answered', '\"Single\"')");
$public = ['id' => 2, 'raw_site_type' => 'STANDARD', 'parking_style' => 'pull_through'];
$result = llama_pricing_enrich_sites($db, ['id' => 1], [$public], $loaded, '2027-07-03')[0];
check($result['pricing']['amount'] === 38.0, 'Approved Site attribute selects rate');
check(!isset($result['pricing_category']), 'No raw community attributes in public output');
$db->exec("UPDATE place_report_current_answers SET answer_state = 'unknown', value_json = 'null'");
check(llama_pricing_enrich_sites($db, ['id' => 1], [$site], $loaded)[0]['pricing']['state'] === 'needs_review', 'Explicit Unknown blocks stale imported category');
$many = array_map(static fn($i) => array_merge($site, ['id' => $i]), range(3, 402));
check(count(llama_pricing_enrich_sites($db, ['id' => 1], $many, $loaded)) === 400, '400 Sites share one rate card');
$unresolved = llama_pricing_enrich_sites($db, ['id' => 1], [$site], $loaded)[0];
check($unresolved['pricing_summary'] === '', 'Public Site summary omits the internal pricing review message');
echo "PASS: shared pricing rules, canonical validation, storage reads, public projection, and 400-Site matching\n";
