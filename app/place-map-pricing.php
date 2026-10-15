<?php

declare(strict_types=1);

function llama_place_map_feature_rate_type_options(): array
{
    return [
        'standard' => 'Standard nightly',
        'weekday' => 'Weekday',
        'weekend' => 'Weekend',
        'holiday' => 'Holiday',
        'seasonal' => 'Seasonal',
    ];
}

function llama_place_map_feature_rate_feature_type_supported(
    string $featureType
): bool {
    return in_array(
        strtolower(
            trim($featureType)
        ),
        [
            'camping_area',
            'camping_site',
        ],
        true
    );
}

function llama_place_map_feature_validate_month_day(
    mixed $value,
    string $label
): ?string {
    $value =
        trim(
            (string) $value
        );

    if ($value === '') {
        return null;
    }

    if (
        !preg_match(
            '/^(\d{2})-(\d{2})$/',
            $value,
            $matches
        )
    ) {
        throw new InvalidArgumentException(
            $label
            . ' must use MM-DD format.'
        );
    }

    $month =
        (int) $matches[1];

    $day =
        (int) $matches[2];

    if (
        !checkdate(
            $month,
            $day,
            2000
        )
    ) {
        throw new InvalidArgumentException(
            $label
            . ' is not a valid month and day.'
        );
    }

    return sprintf(
        '%02d-%02d',
        $month,
        $day
    );
}

function llama_place_map_feature_validate_rates(
    string $featureType,
    mixed $rates
): array {
    if (
        !llama_place_map_feature_rate_feature_type_supported(
            $featureType
        )
    ) {
        return [];
    }

    if (!is_array($rates)) {
        return [];
    }

    if (count($rates) > 50) {
        throw new InvalidArgumentException(
            'A mapped area can have up to 50 rates.'
        );
    }

    $typeOptions =
        llama_place_map_feature_rate_type_options();

    $clean = [];
    $identities = [];

    foreach (
        array_values($rates)
        as $rate
    ) {
        if (!is_array($rate)) {
            continue;
        }

        $rateType =
            strtolower(
                trim(
                    (string) (
                        $rate['rate_type']
                        ?? 'standard'
                    )
                )
            );

        if (
            !array_key_exists(
                $rateType,
                $typeOptions
            )
        ) {
            throw new InvalidArgumentException(
                'Choose a valid rate type.'
            );
        }

        $label =
            trim(
                (string) (
                    $rate['label']
                    ?? ''
                )
            );

        if (mb_strlen($label) > 80) {
            throw new InvalidArgumentException(
                'Rate labels can be up to 80 characters.'
            );
        }

        if (
            $rateType === 'holiday'
            && $label === ''
        ) {
            throw new InvalidArgumentException(
                'Holiday rates need a holiday name.'
            );
        }

        $amount =
            $rate['amount']
            ?? null;

        if (
            $amount === null
            || $amount === ''
            || !is_numeric($amount)
        ) {
            throw new InvalidArgumentException(
                'Every rate needs a valid nightly amount.'
            );
        }

        $amount =
            round(
                (float) $amount,
                2
            );

        if (
            $amount < 0
            || $amount > 999999.99
        ) {
            throw new InvalidArgumentException(
                'A rate amount is outside the supported range.'
            );
        }

        $seasonStart =
            llama_place_map_feature_validate_month_day(
                $rate['season_start']
                ?? null,
                'Season start'
            );

        $seasonEnd =
            llama_place_map_feature_validate_month_day(
                $rate['season_end']
                ?? null,
                'Season end'
            );

        if (
            $rateType === 'seasonal'
            && (
                $seasonStart === null
                || $seasonEnd === null
            )
        ) {
            throw new InvalidArgumentException(
                'Seasonal rates need both a season start and season end.'
            );
        }

        if ($rateType !== 'seasonal') {
            $seasonStart = null;
            $seasonEnd = null;
        }

        $notes =
            trim(
                (string) (
                    $rate['notes']
                    ?? ''
                )
            );

        if (mb_strlen($notes) > 240) {
            throw new InvalidArgumentException(
                'Rate notes can be up to 240 characters.'
            );
        }

        $cleanRate = [
            'rate_type' =>
                $rateType,

            'label' =>
                $label !== ''
                    ? $label
                    : null,

            'amount' =>
                $amount,

            'currency' =>
                'USD',

            'season_start' =>
                $seasonStart,

            'season_end' =>
                $seasonEnd,

            'notes' =>
                $notes !== ''
                    ? $notes
                    : null,
        ];

        $identity =
            llama_place_map_rate_identity(
                $cleanRate
            );

        if (
            $identity !== ''
            && isset(
                $identities[
                    $identity
                ]
            )
        ) {
            throw new InvalidArgumentException(
                match ($rateType) {
                    'holiday' =>
                        'Holiday rate names must be unique within the same pricing layer.',

                    'seasonal' =>
                        'Seasonal date ranges must be unique within the same pricing layer.',

                    default =>
                        'Only one '
                        . strtolower(
                            $typeOptions[
                                $rateType
                            ]
                        )
                        . ' rate can exist within the same pricing layer.',
                }
            );
        }

        if ($identity !== '') {
            $identities[
                $identity
            ] = true;
        }

        $clean[] =
            $cleanRate;
    }

    return $clean;
}

function llama_place_map_feature_rates_save(
    PDO $db,
    int $featureId,
    int $userId,
    string $featureType,
    array $rates
): void {
    $deleteStmt =
        $db->prepare(
            'DELETE FROM place_map_feature_rates
             WHERE feature_id = ?'
        );

    $deleteStmt->execute([
        $featureId,
    ]);

    if (
        !llama_place_map_feature_rate_feature_type_supported(
            $featureType
        )
    ) {
        return;
    }

    if (!$rates) {
        return;
    }

    $stmt =
        $db->prepare(
            'INSERT INTO place_map_feature_rates
            (
                feature_id,
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
                ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?,
                UTC_TIMESTAMP(), UTC_TIMESTAMP()
            )'
        );

    foreach (
        $rates
        as $index => $rate
    ) {
        $stmt->execute([
            $featureId,
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

function llama_place_map_feature_rate_summary(
    array $rates
): array {
    if (!$rates) {
        return [];
    }

    $amounts = [];

    foreach ($rates as $rate) {
        if (
            isset($rate['amount'])
            && is_numeric(
                $rate['amount']
            )
        ) {
            $amounts[] =
                (float) $rate['amount'];
        }
    }

    if (!$amounts) {
        return [];
    }

    return [
        'minimum' =>
            min($amounts),

        'maximum' =>
            max($amounts),

        'currency' =>
            'USD',

        'count' =>
            count($rates),
    ];
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

function llama_place_map_rate_identity(
    array $rate
): string {
    $type =
        strtolower(
            trim(
                (string) (
                    $rate['rate_type']
                    ?? ''
                )
            )
        );

    if ($type === '') {
        return '';
    }

    if ($type === 'seasonal') {
        return implode(
            '|',
            [
                $type,
                trim(
                    (string) (
                        $rate['season_start']
                        ?? ''
                    )
                ),
                trim(
                    (string) (
                        $rate['season_end']
                        ?? ''
                    )
                ),
            ]
        );
    }

    if ($type === 'holiday') {
        $label =
            mb_strtolower(
                trim(
                    preg_replace(
                        '/\\s+/',
                        ' ',
                        (string) (
                            $rate['label']
                            ?? ''
                        )
                    )
                    ?? ''
                )
            );

        return implode(
            '|',
            [
                $type,
                $label,
            ]
        );
    }

    return $type;
}

function llama_place_map_merge_rate_layers(
    array ...$layers
): array {
    $result = [];

    foreach ($layers as $layer) {
        foreach ($layer as $rate) {
            if (!is_array($rate)) {
                continue;
            }

            $identity =
                llama_place_map_rate_identity(
                    $rate
                );

            if ($identity === '') {
                continue;
            }

            $result =
                array_values(
                    array_filter(
                        $result,
                        static fn (
                            array $existing
                        ): bool =>
                            llama_place_map_rate_identity(
                                $existing
                            ) !== $identity
                    )
                );

            $result[] =
                $rate;
        }
    }

    return $result;
}

function llama_place_map_place_base_rate(
    mixed $baseFee
): array {
    if (
        $baseFee === null
        || $baseFee === ''
        || !is_numeric($baseFee)
    ) {
        return [];
    }

    $amount =
        round(
            (float) $baseFee,
            2
        );

    if (
        $amount < 0
        || $amount > 100000
    ) {
        return [];
    }

    return [[
        'rate_type' =>
            'standard',

        'label' =>
            'Base camping fee',

        'amount' =>
            $amount,

        'currency' =>
            'USD',

        'season_start' =>
            null,

        'season_end' =>
            null,

        'notes' =>
            null,

        'inheritance_source' =>
            'place',

        'inheritance_label' =>
            'Place base camping fee',
    ]];
}

function llama_place_map_effective_rates_with_place_base(
    array $mappedRates,
    mixed $baseFee
): array {
    return llama_place_map_merge_rate_layers(
        llama_place_map_place_base_rate(
            $baseFee
        ),
        $mappedRates
    );
}

function llama_place_map_rate_amount_label(
    float $amount
): string {
    if (abs($amount) < 0.005) {
        return '$0';
    }

    $formatted =
        number_format(
            $amount,
            2,
            '.',
            ''
        );

    $formatted =
        rtrim(
            rtrim(
                $formatted,
                '0'
            ),
            '.'
        );

    return '$' . $formatted;
}

function llama_place_map_rate_summary_label(
    array $rates
): string {
    $summary =
        llama_place_map_feature_rate_summary(
            $rates
        );

    if (!$summary) {
        return '';
    }

    $minimum =
        (float) (
            $summary['minimum']
            ?? 0
        );

    $maximum =
        (float) (
            $summary['maximum']
            ?? $minimum
        );

    if (
        abs($minimum) < 0.005
        && abs($maximum) < 0.005
    ) {
        return 'Free';
    }

    if (
        abs(
            $minimum
            - $maximum
        ) < 0.005
    ) {
        return
            llama_place_map_rate_amount_label(
                $minimum
            )
            . ' / night';
    }

    return
        llama_place_map_rate_amount_label(
            $minimum
        )
        . '-'
        . llama_place_map_rate_amount_label(
            $maximum
        )
        . ' / night';
}

