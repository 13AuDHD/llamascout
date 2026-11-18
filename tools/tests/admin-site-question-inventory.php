<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/admin-place-sites.php';

$assert = static function (bool $ok, string $why): void {
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $why);
    }
};

$scopedSite = llama_report_questions_for_scope('site', true);
$adminSite = array_fill_keys(admin_place_campsite_field_keys(), true);
$siteShared = admin_place_site_shared_question_keys();
$gap = admin_place_site_inventory_gap();

$assert($scopedSite !== [], 'Canonical Site registry is missing.');
$assert(count($siteShared) === 29,
    'Expected the 29 audited shared Site policy questions to have independent controls.');
$assert($gap === [], 'All canonical Site questions eligible for this Admin editor must be available.');
$assert(isset($adminSite['pets_allowed']) && isset($adminSite['overnight_camping_allowed'])
    && isset($adminSite['generator_prohibited']),
    'Inherited rules must now be editable on the individual Site without changing Place data.');

foreach ($siteShared as $key) {
    $assert(isset($adminSite[$key]) && isset($scopedSite[$key]),
        'Shared Site key is missing from the allowed canonical Site writer: ' . $key);
    $assert(($scopedSite[$key]['layout_section'] ?? '') !== 'site',
        'A Site-section question was incorrectly duplicated in the shared policy panel.');
}
$assert(!isset($adminSite['site_number']) && !isset($adminSite['visited_at']),
    'Site identity and visit provenance remain on their separate managed paths.');

$valid = admin_place_validate_site_answer_keys([
    'pets_allowed' => '0', 'generator_prohibited' => llama_place_report_unknown_token()
]);
$assert(($valid['pets_allowed'] ?? null) === '0',
    'Explicit No must be allowed as a Site-specific rule.');
$assert(($valid['generator_prohibited'] ?? null) === llama_place_report_unknown_token(),
    'Unknown must remain distinct from unanswered Site rules.');

$rejects = static function (array $answers) use ($assert): void {
    $blocked = false;
    try {
        admin_place_validate_site_answer_keys($answers);
    } catch (InvalidArgumentException) {
        $blocked = true;
    }
    $assert($blocked, 'Admin Site must reject an unsupported question.');
};
$rejects(['not_registered_at_all' => '1']);
$rejects(['site_number' => '12']);
$rejects(['pets_allowed' => '1', 'visited_at' => '2026-11-18']);

echo 'Admin Site canonical=' . count($scopedSite)
    . ', editable=' . count($adminSite)
    . ', dedicated shared policy controls=' . count($siteShared)
    . ', uncovered=' . count($gap) . PHP_EOL;
echo "PASS: Individual Admin Site reports accept all 29 shared policies, without Place identity fields.\n";
