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

        $clean[] = [
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
