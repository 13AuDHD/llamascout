<?php

declare(strict_types=1);

/**
 * Area picker for the shared Admin Place editor.
 *
 * The surrounding form owns submission. This partial only switches the
 * currently edited Place / Area context and serializes Area values.
 */
$areas =
    is_array(
        $adminPlaceAreas
        ?? null
    )
        ? $adminPlaceAreas
        : [];

if (empty($adminFacilityCatalog)) {
    return;
}

$areaFields =
    array_keys(
        admin_place_area_fields()
    );

$encode =
    static fn (string $value): string =>
        htmlspecialchars(
            $value,
            ENT_QUOTES,
            'UTF-8'
        );
?>

<section
    class="admin-place-area-picker"
    aria-labelledby="admin-place-area-heading"
    data-admin-area-picker
    hidden
>
    <header>
        <h3 id="admin-place-area-heading">
            Facility Scout Report
        </h3>

    </header>

    <label for="admin-place-area-target">
        Facility to edit
    </label>

    <select
        id="admin-place-area-target"
        data-admin-area-selector
        data-admin-facility-selector
    >
        <?php foreach ($adminFacilityCatalog as $facility): ?>
            <option
                value="<?= $encode(
                    (string) (
                        $facility['id']
                        ?? ''
                    )
                ) ?>"
                <?= (string) ($facility['id'] ?? '') === $selectedFacility ? 'selected' : '' ?>
            >
                <?= $encode(
                    (string) (
                        $facility['label']
                        ?? 'Unnamed Facility'
                    )
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="admin-place-muted">Select a Facility to edit its information and location. Changes save to the existing record.</p>

    <input
        type="hidden"
        name="selected_area_id"
        form="place-report"
        value=""
    >

    <script
        type="application/json"
        data-admin-place-areas
    ><?= json_encode(
        [
            'areas' =>
                $areas,

            'field_keys' =>
                $areaFields,
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_THROW_ON_ERROR
    ) ?></script>
</section>
