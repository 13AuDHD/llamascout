<?php

declare(strict_types=1);

function llama_place_map_area_use_options(
    string $featureType
): array {
    return match (
        strtolower(
            trim($featureType)
        )
    ) {
        'camping_area' => [
            'developed_campground' =>
                'Developed campground',
            'designated_camping' =>
                'Designated camping area',
            'dispersed_camping' =>
                'Dispersed camping area',
        ],

        'parking_area' => [
            'general_parking' =>
                'General parking',
            'overnight_vehicle_parking' =>
                'Overnight vehicle parking',
        ],

        default => [],
    };
}

function llama_place_map_fee_status_options(): array
{
    return [
        'free' => 'Free',
        'paid' => 'Paid',
        'varies' => 'Varies',
        'unknown' => 'Unknown',
    ];
}

function llama_place_map_overnight_status_options(): array
{
    return [
        'allowed' => 'Allowed',
        'prohibited' => 'Not allowed',
        'varies' => 'Varies',
        'unknown' => 'Unknown',
    ];
}

function llama_place_map_feature_validate_area_details(
    string $featureType,
    mixed $details
): array {
    if (!is_array($details)) {
        $details = [];
    }

    $featureType =
        strtolower(
            trim($featureType)
        );

    if (
        $featureType === 'place_boundary'
        || $featureType === 'camping_site'
    ) {
        return [];
    }

    $areaUse =
        trim(
            (string) (
                $details['area_use']
                ?? ''
            )
        );

    $feeStatus =
        $featureType === 'parking_area'
            ? trim(
                (string) (
                    $details['fee_status']
                    ?? ''
                )
            )
            : '';

    $overnightStatus =
        $featureType === 'parking_area'
            ? trim(
                (string) (
                    $details['overnight_status']
                    ?? ''
                )
            )
            : '';

    $useOptions =
        llama_place_map_area_use_options(
            $featureType
        );

    if (
        $areaUse !== ''
        && !array_key_exists(
            $areaUse,
            $useOptions
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid mapped-area use.'
        );
    }

    if (
        $feeStatus !== ''
        && !array_key_exists(
            $feeStatus,
            llama_place_map_fee_status_options()
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid parking cost status.'
        );
    }

    if (
        $overnightStatus !== ''
        && !array_key_exists(
            $overnightStatus,
            llama_place_map_overnight_status_options()
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid overnight vehicle-stay status.'
        );
    }

    return array_filter(
        [
            'area_use' =>
                $areaUse !== ''
                    ? $areaUse
                    : null,

            'fee_status' =>
                $feeStatus !== ''
                    ? $feeStatus
                    : null,

            'overnight_status' =>
                $overnightStatus !== ''
                    ? $overnightStatus
                    : null,
        ],
        static fn (
            mixed $value
        ): bool =>
            $value !== null
    );
}

function llama_place_map_feature_area_details_for_read(
    string $featureType,
    mixed $details
): array {
    if (!is_array($details)) {
        return [];
    }

    $featureType =
        strtolower(
            trim($featureType)
        );

    if (
        $featureType !== 'camping_area'
        && $featureType !== 'parking_area'
    ) {
        return [];
    }

    $clean = [];

    $areaUse =
        trim(
            (string) (
                $details['area_use']
                ?? ''
            )
        );

    if (
        $areaUse !== ''
        && array_key_exists(
            $areaUse,
            llama_place_map_area_use_options(
                $featureType
            )
        )
    ) {
        $clean['area_use'] =
            $areaUse;
    }

    if ($featureType !== 'parking_area') {
        return $clean;
    }

    $feeStatus =
        trim(
            (string) (
                $details['fee_status']
                ?? ''
            )
        );

    if (
        $feeStatus !== ''
        && array_key_exists(
            $feeStatus,
            llama_place_map_fee_status_options()
        )
    ) {
        $clean['fee_status'] =
            $feeStatus;
    }

    $overnightStatus =
        trim(
            (string) (
                $details['overnight_status']
                ?? ''
            )
        );

    if (
        $overnightStatus !== ''
        && array_key_exists(
            $overnightStatus,
            llama_place_map_overnight_status_options()
        )
    ) {
        $clean['overnight_status'] =
            $overnightStatus;
    }

    return $clean;
}

function llama_place_map_feature_area_details_save(
    PDO $db,
    int $featureId,
    int $userId,
    string $featureType,
    mixed $details
): void {
    $details =
        llama_place_map_feature_validate_area_details(
            $featureType,
            $details
        );

    if (!$details) {
        $stmt = $db->prepare(
            'DELETE FROM place_map_area_details
             WHERE feature_id = ?'
        );

        $stmt->execute([
            $featureId,
        ]);

        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO place_map_area_details
        (
            feature_id,
            area_use,
            fee_status,
            overnight_status,
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
            UTC_TIMESTAMP(),
            UTC_TIMESTAMP()
        )
        ON DUPLICATE KEY UPDATE
            area_use = VALUES(area_use),
            fee_status = VALUES(fee_status),
            overnight_status = VALUES(overnight_status),
            updated_by = VALUES(updated_by),
            updated_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        $featureId,
        $details['area_use']
            ?? null,
        $details['fee_status']
            ?? null,
        $details['overnight_status']
            ?? null,
        $userId,
        $userId,
    ]);
}
