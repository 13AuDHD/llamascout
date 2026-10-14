<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb.php';
require_once __DIR__ . '/ridb-canonical.php';

function llama_ridb_report_fill_if_empty(
    PDO $db,
    string $table,
    int $placeId,
    string $column,
    mixed $value
): bool {
    if (
        $placeId < 1
        || $value === null
        || $value === ''
        || !in_array(
            $table,
            [
                'place_details',
                'place_rules',
            ],
            true
        )
        || preg_match(
            '/^[a-z0-9_]+$/',
            $column
        ) !== 1
    ) {
        return false;
    }

    $stmt =
        $db->prepare(
            'SELECT `'
            . $column
            . '`
             FROM `'
            . $table
            . '`
             WHERE place_id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $placeId,
    ]);

    $current =
        $stmt->fetchColumn();

    if (
        $current !== false
        && $current !== null
        && $current !== ''
    ) {
        return false;
    }

    $update =
        $db->prepare(
            'UPDATE `'
            . $table
            . '`
             SET `'
            . $column
            . '` = ?
             WHERE place_id = ?'
        );

    $update->execute([
        $value,
        $placeId,
    ]);

    return true;
}

function llama_ridb_report_stay_limit(
    mixed $value
): ?string {
    $text =
        strtolower(
            trim(
                (string) $value
            )
        );

    if ($text === '') {
        return null;
    }

    if (str_contains($text, 'permit')) {
        return 'permit-limit';
    }

    if (
        str_contains($text, 'varies')
        || str_contains($text, 'season')
    ) {
        return 'varies-by-season';
    }

    if (
        preg_match(
            '/\b(1|3|5|7|10|14|16|21|28|30)\s*days?\b/i',
            $text,
            $match
        ) === 1
    ) {
        return (string) $match[1];
    }

    return null;
}

function llama_ridb_report_activity_key(
    mixed $value
): ?string {
    $key =
        llama_ridb_normalization_key(
            (string) $value
        );

    return match ($key) {
        'CAMPING' =>
            'camping',

        'HIKING' =>
            'hiking',

        'HORSEBACK RIDING',
        'HORSEBACKRIDING' =>
            'horseback-riding',

        'HISTORIC CULTURAL SITE',
        'HISTORIC AND CULTURAL SITE',
        'CULTURAL ACTIVITIES' =>
            'historic-cultural',

        'WILDLIFE VIEWING' =>
            'wildlife-viewing',

        'BIRDING',
        'BIRD WATCHING',
        'BIRDWATCHING' =>
            'birding',

        'FISHING' =>
            'fishing',

        'BOATING',
        'BOATING MOTORBOAT' =>
            'boating',

        'PADDLING',
        'KAYAKING',
        'CANOEING' =>
            'paddling',

        'SWIMMING SITE',
        'SWIMMING' =>
            'swimming',

        'BIKING',
        'BICYCLE RIDING' =>
            'biking',

        'MOUNTAIN BIKING' =>
            'mountain-biking',

        'OFF HIGHWAY VEHICLE',
        'OHV RIDING',
        'AUTO TOURING' =>
            'ohv',

        'CLIMBING',
        'ROCK CLIMBING' =>
            'climbing',

        'HUNTING' =>
            'hunting',

        'PICNICKING',
        'PICNIC' =>
            'picnicking',

        'PHOTOGRAPHY' =>
            'photography',

        'STARGAZING' =>
            'stargazing',

        'WINTER SPORTS',
        'SNOWMOBILING',
        'CROSS COUNTRY SKIING',
        'SKIING' =>
            'winter-sports',

        'INTERPRETIVE PROGRAMS',
        'INTERPRETIVE ACTIVITIES',
        'EDUCATIONAL PROGRAMS' =>
            'interpretive',

        default =>
            null,
    };
}

function llama_ridb_report_activities(
    PDO $ridbDb,
    string $facilityId
): array {
    $stmt =
        $ridbDb->prepare(
            'SELECT source_json
             FROM ridb_activities
             WHERE ridb_facility_id = ?
             ORDER BY source_record_id ASC'
        );

    $stmt->execute([
        $facilityId,
    ]);

    $values = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_COLUMN
        )
        ?: []
        as $json
    ) {
        $row =
            json_decode(
                (string) $json,
                true
            );

        if (!is_array($row)) {
            continue;
        }

        $mapped =
            llama_ridb_report_activity_key(
                llama_ridb_record_value(
                    $row,
                    [
                        'ActivityName',
                        'activityName',
                    ],
                    ''
                )
            );

        if ($mapped !== null) {
            $values[$mapped] =
                true;
        }
    }

    return array_keys($values);
}

function llama_ridb_report_consolidation_sync(
    PDO $mainDb,
    PDO $ridbDb,
    int $placeId,
    string $facilityId
): array {
    $facilityId =
        trim(
            $facilityId
        );

    if (
        $placeId < 1
        || $facilityId === ''
    ) {
        return [
            'report_fields_imported' => 0,
        ];
    }

    $facilityStmt =
        $ridbDb->prepare(
            'SELECT source_json
             FROM ridb_facilities
             WHERE ridb_facility_id = ?
             LIMIT 1'
        );

    $facilityStmt->execute([
        $facilityId,
    ]);

    $facility =
        json_decode(
            (string) (
                $facilityStmt->fetchColumn()
                ?: ''
            ),
            true
        );

    if (!is_array($facility)) {
        return [
            'report_fields_imported' => 0,
        ];
    }

    $written = 0;

    $ada =
        llama_ridb_canonical_bool(
            llama_ridb_record_value(
                $facility,
                [
                    'FacilityAdaAccess',
                    'FacilityADAAccess',
                    'facilityAdaAccess',
                ]
            )
        );

    if (
        $ada !== null
        && llama_ridb_report_fill_if_empty(
            $mainDb,
            'place_details',
            $placeId,
            'place_accessible',
            $ada
                ? 1
                : 0
        )
    ) {
        $written++;
    }

    $accessibilityText =
        trim(
            (string) llama_ridb_record_value(
                $facility,
                [
                    'FacilityAccessibilityText',
                    'facilityAccessibilityText',
                ],
                ''
            )
        );

    if (
        $accessibilityText !== ''
        && llama_ridb_report_fill_if_empty(
            $mainDb,
            'place_details',
            $placeId,
            'accessibility_notes',
            $accessibilityText
        )
    ) {
        $written++;
    }

    $mapUrl =
        trim(
            (string) llama_ridb_record_value(
                $facility,
                [
                    'FacilityMapURL',
                    'FacilityMapUrl',
                    'facilityMapURL',
                ],
                ''
            )
        );

    if (
        $mapUrl !== ''
        && preg_match(
            '#^https?://#i',
            $mapUrl
        ) === 1
        && llama_ridb_report_fill_if_empty(
            $mainDb,
            'place_details',
            $placeId,
            'facility_map_url',
            $mapUrl
        )
    ) {
        $written++;
    }

    $activities =
        llama_ridb_report_activities(
            $ridbDb,
            $facilityId
        );

    if (
        $activities
        && llama_ridb_report_fill_if_empty(
            $mainDb,
            'place_details',
            $placeId,
            'activities',
            json_encode(
                $activities,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            )
        )
    ) {
        $written++;
    }

    $stayLimit =
        llama_ridb_report_stay_limit(
            llama_ridb_record_value(
                $facility,
                [
                    'StayLimit',
                    'stayLimit',
                ],
                ''
            )
        );

    if (
        $stayLimit !== null
        && llama_ridb_report_fill_if_empty(
            $mainDb,
            'place_rules',
            $placeId,
            'stay_limit_days',
            $stayLimit
        )
    ) {
        $written++;
    }

    $reservationUrl =
        trim(
            (string) llama_ridb_record_value(
                $facility,
                [
                    'FacilityReservationURL',
                    'facilityReservationURL',
                ],
                ''
            )
        );

    if (
        $reservationUrl !== ''
        && llama_ridb_report_fill_if_empty(
            $mainDb,
            'place_rules',
            $placeId,
            'reservation_required',
            1
        )
    ) {
        $written++;
    }

    return [
        'report_fields_imported' =>
            $written,
    ];
}
