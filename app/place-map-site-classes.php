<?php

declare(strict_types=1);

/*
 * Camping-site pricing classes.
 *
 * These sit between a Camping area and an individual campsite:
 *
 * Camping area rates
 *     -> Site pricing class rates
 *         -> Individual campsite overrides
 *
 * A campground can therefore price "Standard Back-In" and
 * "Large Pull-Through" differently without duplicating those rates
 * on every mapped campsite.
 */

function llama_place_map_validate_site_classes(
    mixed $classes
): array {
    if (!is_array($classes)) {
        return [];
    }

    if (count($classes) > 50) {
        throw new InvalidArgumentException(
            'A Camping area can have up to 50 site pricing classes.'
        );
    }

    $clean = [];
    $names = [];

    foreach (array_values($classes) as $class) {
        if (!is_array($class)) {
            continue;
        }

        $id =
            max(
                0,
                (int) (
                    $class['id']
                    ?? 0
                )
            );

        $name =
            trim(
                (string) (
                    $class['name']
                    ?? ''
                )
            );

        if ($name === '') {
            throw new InvalidArgumentException(
                'Every site pricing class needs a name.'
            );
        }

        if (mb_strlen($name) > 80) {
            throw new InvalidArgumentException(
                'Site pricing class names can be up to 80 characters.'
            );
        }

        $nameKey =
            mb_strtolower(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $name
                )
                ?? $name
            );

        if (isset($names[$nameKey])) {
            throw new InvalidArgumentException(
                'Site pricing class names must be unique within a Camping area.'
            );
        }

        $names[$nameKey] = true;

        $description =
            trim(
                (string) (
                    $class['description']
                    ?? ''
                )
            );

        if (mb_strlen($description) > 240) {
            throw new InvalidArgumentException(
                'Site pricing class descriptions can be up to 240 characters.'
            );
        }

        $rates =
            llama_place_map_feature_validate_rates(
                'camping_area',
                $class['rates']
                ?? []
            );

        $clean[] = [
            'id' => $id,
            'name' => $name,
            'description' =>
                $description !== ''
                    ? $description
                    : null,
            'rates' => $rates,
        ];
    }

    return $clean;
}

function llama_place_map_site_class_rates_save(
    PDO $db,
    int $classId,
    int $userId,
    array $rates
): void {
    $stmt = $db->prepare(
        'DELETE FROM place_map_camping_site_class_rates
         WHERE site_class_id = ?'
    );

    $stmt->execute([
        $classId,
    ]);

    if (!$rates) {
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO place_map_camping_site_class_rates
        (
            site_class_id,
            rate_type,
            label,
            amount,
            currency,
            season_start,
            season_end,
            notes,
            sort_order,
            is_active,
            created_by,
            updated_by,
            created_at,
            updated_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            1,
            ?,
            ?,
            UTC_TIMESTAMP(),
            UTC_TIMESTAMP()
        )'
    );

    foreach ($rates as $index => $rate) {
        $stmt->execute([
            $classId,
            $rate['rate_type'],
            $rate['label'],
            $rate['amount'],
            $rate['currency'],
            $rate['season_start'],
            $rate['season_end'],
            $rate['notes'],
            ($index + 1) * 10,
            $userId,
            $userId,
        ]);
    }
}

function llama_place_map_site_classes_save(
    PDO $db,
    int $campingAreaFeatureId,
    int $userId,
    string $featureType,
    array $classes
): void {
    if ($featureType !== 'camping_area') {
        return;
    }

    $existingStmt = $db->prepare(
        'SELECT id
         FROM place_map_camping_site_classes
         WHERE camping_area_feature_id = ?
           AND is_active = 1'
    );

    $existingStmt->execute([
        $campingAreaFeatureId,
    ]);

    $existingIds =
        array_map(
            'intval',
            $existingStmt->fetchAll(
                PDO::FETCH_COLUMN
            )
            ?: []
        );

    $keptIds = [];

    foreach ($classes as $index => $class) {
        $classId =
            (int) (
                $class['id']
                ?? 0
            );

        if (
            $classId > 0
            && in_array(
                $classId,
                $existingIds,
                true
            )
        ) {
            $stmt = $db->prepare(
                'UPDATE place_map_camping_site_classes
                 SET
                    name = ?,
                    description = ?,
                    sort_order = ?,
                    updated_by = ?,
                    updated_at = UTC_TIMESTAMP()
                 WHERE id = ?
                   AND camping_area_feature_id = ?
                 LIMIT 1'
            );

            $stmt->execute([
                $class['name'],
                $class['description'],
                ($index + 1) * 10,
                $userId,
                $classId,
                $campingAreaFeatureId,
            ]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO place_map_camping_site_classes
                (
                    camping_area_feature_id,
                    name,
                    description,
                    sort_order,
                    is_active,
                    created_by,
                    updated_by,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    1,
                    ?,
                    ?,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )'
            );

            $stmt->execute([
                $campingAreaFeatureId,
                $class['name'],
                $class['description'],
                ($index + 1) * 10,
                $userId,
                $userId,
            ]);

            $classId =
                (int) $db->lastInsertId();
        }

        $keptIds[] =
            $classId;

        llama_place_map_site_class_rates_save(
            $db,
            $classId,
            $userId,
            (array) (
                $class['rates']
                ?? []
            )
        );
    }

    $removeIds =
        array_values(
            array_diff(
                $existingIds,
                $keptIds
            )
        );

    if ($removeIds) {
        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($removeIds),
                    '?'
                )
            );

        $stmt = $db->prepare(
            'DELETE FROM place_map_camping_site_classes
             WHERE camping_area_feature_id = ?
               AND id IN ('
            . $placeholders
            . ')'
        );

        $stmt->execute(
            array_merge(
                [
                    $campingAreaFeatureId,
                ],
                $removeIds
            )
        );
    }
}

function llama_place_map_site_class_belongs_to_area(
    PDO $db,
    int $classId,
    int $campingAreaFeatureId
): bool {
    if (
        $classId < 1
        || $campingAreaFeatureId < 1
    ) {
        return false;
    }

    $stmt = $db->prepare(
        'SELECT 1
         FROM place_map_camping_site_classes
         WHERE id = ?
           AND camping_area_feature_id = ?
           AND is_active = 1
         LIMIT 1'
    );

    $stmt->execute([
        $classId,
        $campingAreaFeatureId,
    ]);

    return
        (bool) $stmt->fetchColumn();
}

function llama_place_map_rates_with_source(
    array $rates,
    string $source,
    string $sourceLabel = ''
): array {
    return array_map(
        static function (
            array $rate
        ) use (
            $source,
            $sourceLabel
        ): array {
            $rate['inheritance_source'] =
                $source;

            if ($sourceLabel !== '') {
                $rate['inheritance_label'] =
                    $sourceLabel;
            }

            return $rate;
        },
        $rates
    );
}

function llama_place_map_merge_rate_layers(
    array ...$layers
): array {
    $result = [];

    foreach ($layers as $layer) {
        foreach ($layer as $rate) {
            $type =
                (string) (
                    $rate['rate_type']
                    ?? ''
                );

            if ($type === '') {
                continue;
            }

            /*
             * Preserve the existing V1 rate rule: a more-specific rate
             * of the same type replaces the less-specific rate.
             */
            $result =
                array_values(
                    array_filter(
                        $result,
                        static fn (
                            array $existing
                        ): bool =>
                            (string) (
                                $existing['rate_type']
                                ?? ''
                            ) !== $type
                    )
                );

            $result[] =
                $rate;
        }
    }

    return $result;
}

function llama_place_map_hydrate_site_classes(
    PDO $db,
    array &$features
): void {
    if (!$features) {
        return;
    }

    $campingAreaIds = [];

    foreach ($features as $feature) {
        if (
            (string) (
                $feature['feature_type']
                ?? ''
            ) === 'camping_area'
        ) {
            $campingAreaIds[] =
                (int) $feature['id'];
        }
    }

    $classesByArea = [];
    $classesById = [];
    $classRateGroups = [];

    if ($campingAreaIds) {
        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($campingAreaIds),
                    '?'
                )
            );

        $stmt = $db->prepare(
            'SELECT
                id,
                camping_area_feature_id,
                name,
                description,
                sort_order
             FROM place_map_camping_site_classes
             WHERE is_active = 1
               AND camping_area_feature_id IN ('
            . $placeholders
            . ')
             ORDER BY
                camping_area_feature_id ASC,
                sort_order ASC,
                id ASC'
        );

        $stmt->execute(
            $campingAreaIds
        );

        foreach (
            $stmt->fetchAll(PDO::FETCH_ASSOC)
            ?: []
            as $row
        ) {
            $classId =
                (int) $row['id'];

            $areaId =
                (int) $row[
                    'camping_area_feature_id'
                ];

            $class = [
                'id' => $classId,
                'camping_area_feature_id' =>
                    $areaId,
                'name' =>
                    (string) $row['name'],
                'description' =>
                    (string) (
                        $row['description']
                        ?? ''
                    ),
                'rates' => [],
                'rate_summary' => [],
            ];

            $classesByArea[$areaId][] =
                $class;

            $classesById[$classId] =
                $class;
        }

        $classIds =
            array_keys(
                $classesById
            );

        if ($classIds) {
            $classPlaceholders =
                implode(
                    ',',
                    array_fill(
                        0,
                        count($classIds),
                        '?'
                    )
                );

            $rateStmt = $db->prepare(
                'SELECT
                    id,
                    site_class_id,
                    rate_type,
                    label,
                    amount,
                    currency,
                    season_start,
                    season_end,
                    notes,
                    sort_order
                 FROM place_map_camping_site_class_rates
                 WHERE is_active = 1
                   AND site_class_id IN ('
                . $classPlaceholders
                . ')
                 ORDER BY
                    site_class_id ASC,
                    sort_order ASC,
                    id ASC'
            );

            $rateStmt->execute(
                $classIds
            );

            foreach (
                $rateStmt->fetchAll(PDO::FETCH_ASSOC)
                ?: []
                as $rateRow
            ) {
                $classId =
                    (int) $rateRow[
                        'site_class_id'
                    ];

                $classRateGroups[$classId][] = [
                    'id' =>
                        (int) $rateRow['id'],
                    'rate_type' =>
                        (string) $rateRow['rate_type'],
                    'label' =>
                        (string) (
                            $rateRow['label']
                            ?? ''
                        ),
                    'amount' =>
                        (float) $rateRow['amount'],
                    'currency' =>
                        (string) (
                            $rateRow['currency']
                            ?? 'USD'
                        ),
                    'season_start' =>
                        $rateRow['season_start'],
                    'season_end' =>
                        $rateRow['season_end'],
                    'notes' =>
                        (string) (
                            $rateRow['notes']
                            ?? ''
                        ),
                ];
            }
        }
    }

    foreach ($classesByArea as $areaId => &$classes) {
        foreach ($classes as &$class) {
            $classId =
                (int) $class['id'];

            $class['rates'] =
                array_values(
                    $classRateGroups[
                        $classId
                    ]
                    ?? []
                );

            $class['rate_summary'] =
                llama_place_map_feature_rate_summary(
                    $class['rates']
                );

            $classesById[$classId] =
                $class;
        }
        unset($class);
    }
    unset($classes);

    foreach ($features as &$feature) {
        $featureId =
            (int) $feature['id'];

        if (
            (string) (
                $feature['feature_type']
                ?? ''
            ) === 'camping_area'
        ) {
            $feature['site_classes'] =
                array_values(
                    $classesByArea[
                        $featureId
                    ]
                    ?? []
                );

            $allClassRates = [];

            foreach (
                $feature['site_classes']
                as $class
            ) {
                foreach (
                    (array) (
                        $class['rates']
                        ?? []
                    )
                    as $rate
                ) {
                    $allClassRates[] =
                        $rate;
                }
            }

            $feature['site_class_rate_summary'] =
                llama_place_map_feature_rate_summary(
                    $allClassRates
                );

            continue;
        }

        if (
            (string) (
                $feature['feature_type']
                ?? ''
            ) !== 'camping_site'
        ) {
            continue;
        }

        $siteClassId =
            (int) (
                $feature['site_details']['site_class_id']
                ?? 0
            );

        $siteClass =
            $classesById[
                $siteClassId
            ]
            ?? null;

        if ($siteClass) {
            $feature['site_details']['site_class_name'] =
                (string) $siteClass['name'];

            $feature['site_class'] =
                $siteClass;

            $feature['site_class_rates'] =
                array_values(
                    (array) (
                        $siteClass['rates']
                        ?? []
                    )
                );
        } else {
            $feature['site_class'] = null;
            $feature['site_class_rates'] = [];
        }

        $parentLabel =
            (string) (
                $feature['site_details']['parent_area_label']
                ?? 'Camping area'
            );

        $parentRates =
            llama_place_map_rates_with_source(
                (array) (
                    $feature['parent_rates']
                    ?? []
                ),
                'camping_area',
                $parentLabel
            );

        $classRates =
            llama_place_map_rates_with_source(
                $feature['site_class_rates'],
                'site_class',
                (string) (
                    $siteClass['name']
                    ?? ''
                )
            );

        $siteRates =
            llama_place_map_rates_with_source(
                (array) (
                    $feature['rates']
                    ?? []
                ),
                'site',
                (string) (
                    $feature['site_details']['site_code']
                    ?? $feature['label']
                    ?? 'Site'
                )
            );

        $feature['effective_rates'] =
            llama_place_map_merge_rate_layers(
                $parentRates,
                $classRates,
                $siteRates
            );

        if ($siteRates) {
            $feature['effective_rate_source'] =
                'site_override';

        } elseif ($classRates) {
            $feature['effective_rate_source'] =
                'site_class';

        } elseif ($parentRates) {
            $feature['effective_rate_source'] =
                'camping_area';

        } else {
            $feature['effective_rate_source'] =
                '';
        }

        $feature['effective_rate_summary'] =
            llama_place_map_feature_rate_summary(
                $feature['effective_rates']
            );
    }
    unset($feature);
}
