<?php

require_once dirname(__DIR__, 2) . '/app/place-pricing.php';

$seasonRateDocument = llama_pricing_document($db, $place);
$seasonRateVersion = hash('sha256', json_encode($seasonRateDocument, JSON_THROW_ON_ERROR));
$seasonRateDraft = $seasonRateDocument;
if (($error ?? '') !== '' && isset($_POST['season_rate_version'], $_POST['place_rate_cards_json'])) {
    $seasonRateDraft = llama_pricing_decode($_POST['place_rate_cards_json']);
    $seasonRateVersion = (string) $_POST['season_rate_version'];
}
?>
<h4>Campsite rates</h4>
<input type="hidden" name="season_rate_version" value="<?= $e($seasonRateVersion) ?>">
<div class="contribution-field">
    <textarea name="place_rate_cards_json" data-managed-season
              data-season-dirty="<?= isset($_POST['season_rate_version'], $_POST['place_rate_cards_json']) && ($error ?? '') !== '' ? '1' : '0' ?>"
              data-season-choice="<?= $e(json_encode($selectedSeason ?? null, JSON_THROW_ON_ERROR)) ?>"
              aria-label="Campsite rates" hidden><?= $e(json_encode($seasonRateDraft, JSON_THROW_ON_ERROR)) ?></textarea>
</div>
