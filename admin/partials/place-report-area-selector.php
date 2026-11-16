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
        <h3 id="admin-place-area-heading">Facilities</h3>
        <a class="admin-button is-muted" data-admin-add-facility
           href="/place.php?id=<?= (int) $placeId ?>&amp;mode=facility&amp;facility=new#place-report">
            <?= llama_icon('plus') ?> Add Facility
        </a>

    </header>

    <label for="admin-place-area-target">
        Facility to edit
    </label>

    <select
        id="admin-place-area-target"
        data-admin-area-selector
        data-admin-facility-selector
    >
        <option value="new" <?= $creatingFacility ? 'selected' : '' ?>>+ New Facility</option>
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
    <p class="admin-place-muted">Select an existing Facility to edit it, or add a new Facility here using its coordinates. No map editing required.</p>

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
