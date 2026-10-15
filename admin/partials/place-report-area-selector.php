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
>
    <header>
        <h3 id="admin-place-area-heading">
            Area
        </h3>

        <p>
            Choose an Area to edit information that applies to that part of the Place.
        </p>
    </header>

    <label for="admin-place-area-target">
        Area
    </label>

    <select
        id="admin-place-area-target"
        data-admin-area-selector
    >
        <option value="">
            Place
        </option>

        <?php foreach ($areas as $area): ?>
            <option
                value="<?= $encode(
                    (string) (
                        $area['id']
                        ?? ''
                    )
                ) ?>"
            >
                <?= $encode(
                    (string) (
                        $area['label']
                        ?? 'Unnamed Area'
                    )
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input
        type="hidden"
        name="selected_area_id"
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
