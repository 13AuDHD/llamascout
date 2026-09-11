<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3)
    . '/app/place-report.php';

$publishedUnknown = [];

try {
    $publishedUnknown =
        llama_place_report_published_answer_state(
            db(),
            (int) ($place['id'] ?? 0)
        );
} catch (Throwable $exception) {
    /*
     * This fallback prevents an old published Place from white-screening
     * if the explicit answer-state migration has not been installed yet.
     * New approvals require the migration and will fail safely instead.
     */
    $publishedUnknown = [];
}

$placeReportData =
    llama_place_report_data_from_published_place(
        $place,
        $publishedUnknown
    );

$placeReportReadMode =
    'scout-report';
?>

<link
    rel="stylesheet"
    href="/css/site/features/place-report-form.css"
>

<section
    class="scout-report"
    aria-labelledby="scout-report-heading"
>
    <header class="scout-report-header">
        <p class="eyebrow">Member details</p>
        <h2 id="scout-report-heading">Scout Report</h2>
    </header>

    <?php
    require dirname(__DIR__, 2)
        . '/place-report/read-only.php';
    ?>

    <?php if (!empty($place['notes'])): ?>
        <section class="scout-report-section">
            <h3>
                <i
                    class="fa-solid fa-clipboard-list"
                    aria-hidden="true"
                ></i>
                Scout notes
            </h3>

            <ul class="scout-report-notes-list">
                <?php foreach ($place['notes'] as $note): ?>
                    <?php if (!empty($note['note'])): ?>
                        <li>
                            <?= place_h($note['note']) ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</section>
