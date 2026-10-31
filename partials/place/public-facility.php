<?php

declare(strict_types=1);

// Only officially sourced campground-wide information is public here.
// Dispersed camping and vehicle pulloffs never enter this view.
if (
    !empty($hasMemberAccess)
    || !in_array(
        strtolower((string) ($place['type'] ?? '')),
        ['developed-campground', 'rv-park-resort'],
        true
    )
) {
    return;
}

require_once dirname(__DIR__, 2) . '/app/ridb-facility-details.php';

try {
    $official = llama_place_facility_public_data(db(), (int) ($place['id'] ?? 0));
} catch (Throwable $exception) {
    llama_log_caught_exception($exception, 'place.public_facility');
    $official = [];
}

$e = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$cards = [
    ['Official address', 'address', 'map-pin'],
    ['Phone number', 'phone', 'phone'],
    ['Email address', 'email', 'mail'],
    ['Published fees', 'fee_description', 'receipt'],
];

$officialLinks = [];
$reservationUrl = trim((string) ($official['reservation_url'] ?? ''));
if (preg_match('~^https?://~i', $reservationUrl) === 1) {
    $officialLinks[] = ['title' => 'Reservations', 'url' => $reservationUrl];
}
foreach ((array) ($official['links'] ?? []) as $link) {
    $url = trim((string) ($link['url'] ?? ''));
    if (preg_match('~^https?://~i', $url) !== 1) {
        continue;
    }
    $officialLinks[] = [
        'title' => trim((string) ($link['title'] ?? '')) ?: 'Official link',
        'url' => $url,
    ];
}

$visibleCards = array_values(array_filter(
    $cards,
    static fn (array $card): bool =>
        trim((string) ($official[$card[1]] ?? '')) !== ''
));
if (!$visibleCards && !$officialLinks) {
    return;
}
?>
<section class="place-section scout-report"
    aria-labelledby="place-public-facility-heading">
    <header class="scout-report-header">
        <div>
            <p class="eyebrow">Public campground information</p>
            <h2 id="place-public-facility-heading">Official information</h2>
        </div>
    </header>
    <div class="scout-report-grid">
        <?php foreach ($visibleCards as [$label, $key, $icon]): ?>
            <?php $value = trim((string) $official[$key]); ?>
            <div class="scout-report-item scout-report-value-item">
                <div class="scout-report-value-content">
                    <span><?= $e($label) ?></span>
                    <strong>
                        <?php if ($key === 'phone'): ?>
                            <a href="tel:<?= $e(preg_replace('/[^0-9+]/', '', $value)) ?>"><?= $e($value) ?></a>
                        <?php elseif ($key === 'email'): ?>
                            <a href="mailto:<?= $e($value) ?>"><?= $e($value) ?></a>
                        <?php else: ?>
                            <?= $e($value) ?>
                        <?php endif; ?>
                    </strong>
                </div>
                <?= llama_icon($icon, ['class' => 'scout-report-value-icon']) ?>
            </div>
        <?php endforeach; ?>
        <?php foreach ($officialLinks as $link): ?>
            <div class="scout-report-item scout-report-value-item">
                <div class="scout-report-value-content">
                    <span>Official link</span>
                    <strong>
                        <a href="<?= $e($link['url']) ?>" target="_blank"
                            rel="noopener noreferrer"><?= $e($link['title']) ?></a>
                    </strong>
                </div>
                <?= llama_icon('external-link', ['class' => 'scout-report-value-icon']) ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
