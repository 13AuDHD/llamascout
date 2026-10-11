<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/place-report.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

function shared_amenities_json_response(
    array $payload,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    shared_amenities_json_response(
        [
            'ok' => false,
            'error' => 'Method not allowed.',
        ],
        405
    );
}

$adminUser = moderation_require_admin();

if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
    shared_amenities_json_response(
        [
            'ok' => false,
            'error' => 'Your session expired. Reload the page and try again.',
        ],
        403
    );
}

$placeId = (int) ($_POST['place_id'] ?? 0);

if ($placeId < 1) {
    shared_amenities_json_response(
        [
            'ok' => false,
            'error' => 'Invalid Place.',
        ],
        422
    );
}

$db = db();

$stmt = $db->prepare(
    'SELECT 1
     FROM places
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute([$placeId]);

if (!$stmt->fetchColumn()) {
    shared_amenities_json_response(
        [
            'ok' => false,
            'error' => 'Place not found.',
        ],
        404
    );
}

$unknownToken = llama_place_report_unknown_token();
$unansweredToken = llama_place_report_unanswered_token();

$normalizeBool =
    static function (
        mixed $raw
    ) use (
        $unknownToken,
        $unansweredToken
    ): ?int {
        if (is_array($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        if (
            $value === ''
            || $value === $unknownToken
            || $value === $unansweredToken
        ) {
            return null;
        }

        if ($value === '1') {
            return 1;
        }

        if ($value === '0') {
            return 0;
        }

        return null;
    };

$hostRaw = trim(
    (string) (
        $_POST['amenity_campground_host']
        ?? ''
    )
);

$campgroundHost = in_array(
    $hostRaw,
    ['1', '0', 'seasonal'],
    true
)
    ? $hostRaw
    : null;

$accessibleTypes = [];

foreach (
    (array) (
        $_POST['shared_accessible_facility_types']
        ?? []
    )
    as $value
) {
    $value = trim((string) $value);

    if (
        in_array(
            $value,
            [
                'toilets',
                'showers',
                'laundry',
                'picnic_area',
                'water',
                'camp_store',
                'other',
            ],
            true
        )
    ) {
        $accessibleTypes[$value] = true;
    }
}

$accessibleTypesJson =
    $accessibleTypes
        ? json_encode(
            array_keys($accessibleTypes),
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        )
        : null;

$values = [
    'campground_host' => $campgroundHost,
    'camp_store' => $normalizeBool(
        $_POST['amenity_camp_store']
        ?? null
    ),
    'water_filling_station' => $normalizeBool(
        $_POST['amenity_water_filling_station']
        ?? null
    ),
    'dishwashing_station' => $normalizeBool(
        $_POST['amenity_dishwashing_station']
        ?? null
    ),
    'accessible_showers' => $normalizeBool(
        $_POST['amenity_accessible_showers']
        ?? null
    ),
    'shared_accessible_facilities' => $normalizeBool(
        $_POST['shared_accessible_facilities']
        ?? null
    ),
    'shared_accessible_facility_types' =>
        $accessibleTypesJson,
];

try {
    $stmt = $db->prepare(
        'INSERT INTO place_amenities (
            place_id,
            campground_host,
            camp_store,
            water_filling_station,
            dishwashing_station,
            accessible_showers,
            shared_accessible_facilities,
            shared_accessible_facility_types
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            campground_host = VALUES(campground_host),
            camp_store = VALUES(camp_store),
            water_filling_station = VALUES(water_filling_station),
            dishwashing_station = VALUES(dishwashing_station),
            accessible_showers = VALUES(accessible_showers),
            shared_accessible_facilities = VALUES(shared_accessible_facilities),
            shared_accessible_facility_types = VALUES(shared_accessible_facility_types)'
    );

    $stmt->execute([
        $placeId,
        $values['campground_host'],
        $values['camp_store'],
        $values['water_filling_station'],
        $values['dishwashing_station'],
        $values['accessible_showers'],
        $values['shared_accessible_facilities'],
        $values['shared_accessible_facility_types'],
    ]);

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            (int) ($adminUser['id'] ?? 0),
            null,
            'place.shared_amenities_updated',
            'Updated Place shared facilities and accessibility.',
            [
                'place_id' => $placeId,
            ]
        );
    }
} catch (PDOException $exception) {
    shared_amenities_json_response(
        [
            'ok' => false,
            'error' => 'Shared facilities could not be saved. Make sure the shared-amenity database columns have been installed.',
        ],
        500
    );
}

shared_amenities_json_response(
    [
        'ok' => true,
    ]
);
