<?php

declare(strict_types=1);

/**
 * Public Place-wide Scout Report facts from the canonical approved registry.
 * Never publish a Site record or unfiltered member Place payload here.
 */
if (
    !empty($hasMemberAccess)
    || !in_array(
        strtolower(trim((string) ($place['type'] ?? ''))),
        ['developed-campground', 'rv-park-resort'],
        true
    )
) {
    return;
}

require_once dirname(__DIR__, 2) . '/app/place-report.php';

$publicFactGroups = [];
try {
    // Read the same published answers members see, but render only explicitly
    // public, Place-owned questions. No private value enters HTML or JS.
    $publishedPlace = place_member_by_slug((string) ($place['slug'] ?? ''));
    if ($publishedPlace) {
        $placeId = (int) ($place['id'] ?? 0);
        $states = llama_place_report_published_answer_state(db(), $placeId);
        $answers = llama_place_report_data_from_published_place($publishedPlace, $states);
        $fields = llama_place_report_fields();
        $groups = llama_place_report_approved_question_groups();

        foreach ([
            'amenities' => 'Shared amenities',
            'operating' => 'Operating hours and seasons',
            'fees' => 'Fees and reservations',
            'rules_public' => 'Campground rules',
        ] as $groupKey => $heading) {
            $items = [];
            foreach ((array) ($groups[$groupKey][1] ?? []) as $key) {
                $field = $fields[$key] ?? null;
                if (!is_array($field)
                    || !llama_place_report_field_visibility_allows($field, false)
                    || !in_array('place', (array) ($field['report_scopes'] ?? []), true)
                    || !empty($field['hide_public'])) {
                    continue;
                }

                $state = llama_place_report_answer_state($answers, (string) $key);
                if (!in_array($state, ['answered', 'unknown'], true)) {
                    continue;
                }
                $value = llama_place_report_display_value($answers, (string) $key);
                if (!is_scalar($value) || trim((string) $value) === '') {
                    continue;
                }

                $items[] = [
                    'label' => rtrim((string) ($field['label'] ?? $key), '*'),
                    'value' => (string) $value,
                    'type' => (string) ($field['type'] ?? ''),
                    'icon' => llama_place_report_field_icon((string) $key, $field),
                ];
            }

            if ($items) {
                $publicFactGroups[] = ['heading' => $heading, 'items' => $items];
            }
        }
    }
} catch (Throwable $exception) {
    llama_log_caught_exception($exception, 'place.public_scout_facts');
    $publicFactGroups = [];
}

if (!$publicFactGroups) {
    return;
}

$e = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="place-section scout-report" aria-labelledby="place-public-facts-heading">
    <header class="scout-report-header">
        <div>
            <p class="eyebrow">Public campground details</p>
            <h2 id="place-public-facts-heading">Campground information</h2>
        </div>
    </header>

    <?php foreach ($publicFactGroups as $group): ?>
        <section class="scout-report-section">
            <h3><?= $e($group['heading']) ?></h3>
            <div class="scout-report-grid">
                <?php foreach ($group['items'] as $item): ?>
                    <div class="scout-report-item scout-report-value-item">
                        <div class="scout-report-value-content">
                            <span><?= $e($item['label']) ?></span>
                            <strong>
                                <?php if ($item['type'] === 'url'
                                    && preg_match('~^https?://~i', $item['value']) === 1): ?>
                                    <a href="<?= $e($item['value']) ?>" target="_blank"
                                        rel="noopener noreferrer"><?= $e($item['value']) ?></a>
                                <?php else: ?>
                                    <?= $e($item['value']) ?>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <?= llama_icon($item['icon'], ['class' => 'scout-report-value-icon']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</section>
