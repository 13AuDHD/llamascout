<?php

declare(strict_types=1);

/**
 * Shared Place / Area / Site selection context.
 *
 * Geometry is metadata attached to these records. It is never exposed as a
 * second user-facing identity.
 */
$placeContextTargets = [
    'place' => [
        'place_id' => $placeId,
        'map_feature_id' => null,
        'label' => (string) ($place['name'] ?? 'Place'),
    ],
    'areas' => [],
    'sites' => [],
];

$placeBoundaryStmt = $db->prepare(
    'SELECT id
     FROM place_map_features
     WHERE place_id = ?
       AND feature_type = "place_boundary"
       AND is_active = 1
     ORDER BY sort_order ASC, id ASC
     LIMIT 1'
);

$placeBoundaryStmt->execute([
    $placeId,
]);

$placeBoundaryId =
    (int) (
        $placeBoundaryStmt->fetchColumn()
        ?: 0
    );

if ($placeBoundaryId > 0) {
    $placeContextTargets['place']['map_feature_id'] =
        $placeBoundaryId;
}

foreach (
    llama_report_targets(
        $db,
        $placeId
    )
    as $target
) {
    $scope =
        (string) (
            $target['scope']
            ?? ''
        );

    $source =
        (string) (
            $target['source']
            ?? ''
        );

    $targetId =
        (int) (
            $target['id']
            ?? 0
        );

    if ($targetId < 1) {
        continue;
    }

    if (
        $scope === 'area'
        && $source === 'map_feature'
    ) {
        $selectorValue =
            'map_feature:'
            . $targetId;

        $placeContextTargets['areas'][
            $selectorValue
        ] = [
            'selector_value' =>
                $selectorValue,

            'area_id' =>
                $targetId,

            'map_feature_id' =>
                (int) (
                    $target['map_feature_id']
                    ?? $targetId
                ),

            'label' =>
                (string) (
                    $target['label']
                    ?? ('Area ' . $targetId)
                ),
        ];

        continue;
    }

    if (
        $scope !== 'site'
        || $source !== 'campsite_record'
    ) {
        continue;
    }

    $selectorValue =
        $source
        . ':'
        . $targetId;

    $placeContextTargets['sites'][
        $selectorValue
    ] = [
        'selector_value' =>
            $selectorValue,

        'site_id' =>
            $targetId,

        'source' =>
            $source,

        'map_feature_id' =>
            (int) (
                $target['map_feature_id']
                ?? 0
            ),

        'parent_area_id' =>
            (int) (
                $target['parent_area_id']
                ?? 0
            ),

        'label' =>
            (string) (
                $target['label']
                ?? ('Site ' . $targetId)
            ),
    ];
}
?>

<script
    type="application/json"
    data-admin-place-context
><?= json_encode(
    $placeContextTargets,
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_THROW_ON_ERROR
) ?></script>
