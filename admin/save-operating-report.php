<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

function operating_report_response(
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

function operating_report_scalar(
    mixed $value
): ?string {
    $value =
        trim((string) $value);

    if (
        $value === ''
        || $value === '__LLAMA_UNKNOWN__'
        || $value === '__LLAMA_UNANSWERED__'
    ) {
        return null;
    }

    return $value;
}

function operating_report_bool(
    mixed $value
): ?int {
    $value =
        operating_report_scalar($value);

    if ($value === null) {
        return null;
    }

    if ($value === '1') {
        return 1;
    }

    if ($value === '0') {
        return 0;
    }

    return null;
}

function operating_report_date(
    mixed $value
): ?string {
    $value =
        operating_report_scalar($value);

    if ($value === null) {
        return null;
    }

    $date =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

    if (
        !$date
        || $date->format('Y-m-d') !== $value
    ) {
        throw new InvalidArgumentException(
            'One of the operating dates is invalid.'
        );
    }

    return $value;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    !== 'POST'
) {
    operating_report_response(
        [
            'ok' => false,
            'error' => 'Method not allowed.',
        ],
        405
    );
}

$adminUser =
    moderation_require_admin();

if (
    !moderation_verify_csrf(
        (string) ($_POST['csrf_token'] ?? '')
    )
) {
    operating_report_response(
        [
            'ok' => false,
            'error' =>
                'Your session expired. Reload the page and try again.',
        ],
        403
    );
}

$placeId =
    (int) ($_POST['place_id'] ?? 0);

if ($placeId < 1) {
    operating_report_response(
        [
            'ok' => false,
            'error' => 'Invalid Place.',
        ],
        422
    );
}

try {
    $status =
        operating_report_scalar(
            $_POST['place_reported_operating_status']
            ?? null
        );

    if (
        $status !== null
        && !in_array(
            $status,
            [
                'open',
                'temporary',
                'seasonal',
                'permanent',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid observed operating status.'
        );
    }

    $reason =
        operating_report_scalar(
            $_POST['place_closure_reason']
            ?? null
        );

    if (
        $reason !== null
        && !in_array(
            $reason,
            [
                'unknown',
                'maintenance',
                'construction',
                'weather',
                'fire',
                'flooding',
                'road_damage',
                'administrative',
                'other',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid closure reason.'
        );
    }

    $gateAccess =
        operating_report_scalar(
            $_POST['place_gate_access_type']
            ?? null
        );

    if (
        $gateAccess !== null
        && !in_array(
            $gateAccess,
            [
                'always_open',
                'scheduled',
                'code_key',
                'staff',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid property gate access type.'
        );
    }

    $source =
        operating_report_scalar(
            $_POST['place_closure_source_url']
            ?? null
        );

    if (
        $source !== null
        && (
            strlen($source) > 2048
            || !filter_var(
                $source,
                FILTER_VALIDATE_URL
            )
            || !in_array(
                strtolower(
                    (string) parse_url(
                        $source,
                        PHP_URL_SCHEME
                    )
                ),
                ['http', 'https'],
                true
            )
        )
    ) {
        throw new InvalidArgumentException(
            'Enter a valid HTTP(S) closure source URL.'
        );
    }

    $notes =
        operating_report_scalar(
            $_POST['place_closure_notes']
            ?? null
        );

    if (
        $notes !== null
        && mb_strlen($notes) > 10000
    ) {
        throw new InvalidArgumentException(
            'Closure notes are too long.'
        );
    }

    $values = [
        'place_open_year_round' =>
            operating_report_bool(
                $_POST['place_open_year_round']
                ?? null
            ),
        'place_operating_start_month' =>
            operating_report_scalar(
                $_POST['place_operating_start_month']
                ?? null
            ),
        'place_operating_start_day' =>
            operating_report_scalar(
                $_POST['place_operating_start_day']
                ?? null
            ),
        'place_operating_end_month' =>
            operating_report_scalar(
                $_POST['place_operating_end_month']
                ?? null
            ),
        'place_operating_end_day' =>
            operating_report_scalar(
                $_POST['place_operating_end_day']
                ?? null
            ),
        'place_open_24_hours' =>
            operating_report_bool(
                $_POST['place_open_24_hours']
                ?? null
            ),
        'place_opening_time' =>
            operating_report_scalar(
                $_POST['place_opening_time']
                ?? null
            ),
        'place_closing_time' =>
            operating_report_scalar(
                $_POST['place_closing_time']
                ?? null
            ),
        'place_entrance_gate' =>
            operating_report_bool(
                $_POST['place_entrance_gate']
                ?? null
            ),
        'place_gate_access_type' =>
            $gateAccess,
        'place_gate_opening_time' =>
            operating_report_scalar(
                $_POST['place_gate_opening_time']
                ?? null
            ),
        'place_gate_closing_time' =>
            operating_report_scalar(
                $_POST['place_gate_closing_time']
                ?? null
            ),
        'place_reported_operating_status' =>
            $status,
        'place_closure_reason' =>
            $reason,
        'place_closure_began' =>
            operating_report_date(
                $_POST['place_closure_began']
                ?? null
            ),
        'place_expected_reopening' =>
            operating_report_date(
                $_POST['place_expected_reopening']
                ?? null
            ),
        'place_closure_source_url' =>
            $source,
        'place_closure_review_date' =>
            operating_report_date(
                $_POST['place_closure_review_date']
                ?? null
            ),
        'place_closure_notes' =>
            $notes,
    ];

    /*
     * Clear dependent values which are no longer applicable. This prevents
     * old schedule/closure answers from silently surviving a parent change.
     */
    if ($values['place_open_year_round'] === 1) {
        $values['place_operating_start_month'] = null;
        $values['place_operating_start_day'] = null;
        $values['place_operating_end_month'] = null;
        $values['place_operating_end_day'] = null;
    }

    if ($values['place_open_24_hours'] === 1) {
        $values['place_opening_time'] = null;
        $values['place_closing_time'] = null;
    }

    if ($values['place_entrance_gate'] !== 1) {
        $values['place_gate_access_type'] = null;
        $values['place_gate_opening_time'] = null;
        $values['place_gate_closing_time'] = null;
    } elseif (
        $values['place_gate_access_type']
        !== 'scheduled'
    ) {
        $values['place_gate_opening_time'] = null;
        $values['place_gate_closing_time'] = null;
    }

    if ($values['place_reported_operating_status'] === 'open') {
        $values['place_closure_reason'] = null;
        $values['place_closure_began'] = null;
        $values['place_expected_reopening'] = null;
        $values['place_closure_review_date'] = null;
    }

    if ($values['place_reported_operating_status'] === 'permanent') {
        $values['place_expected_reopening'] = null;
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
        operating_report_response(
            [
                'ok' => false,
                'error' => 'Place not found.',
            ],
            404
        );
    }

    $columns =
        array_keys($values);

    $quotedColumns =
        array_map(
            static fn (string $column): string =>
                '`' . $column . '`',
            $columns
        );

    $updates =
        array_map(
            static fn (string $column): string =>
                '`' . $column . '` = VALUES(`' . $column . '`)',
            $columns
        );

    $stmt = $db->prepare(
        'INSERT INTO place_rules (
            place_id,
            ' . implode(', ', $quotedColumns) . '
         ) VALUES (
            ?,
            ' . implode(
                ', ',
                array_fill(
                    0,
                    count($columns),
                    '?'
                )
            ) . '
         )
         ON DUPLICATE KEY UPDATE
            ' . implode(', ', $updates)
    );

    $stmt->execute(
        array_merge(
            [$placeId],
            array_values($values)
        )
    );

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            (int) ($adminUser['id'] ?? 0),
            null,
            'place.operating_report_updated',
            'Updated Place operating hours and closure observation.',
            [
                'place_id' => $placeId,
                'reported_operating_status' => $status,
            ]
        );
    }

    operating_report_response([
        'ok' => true,
        'reported_operating_status' => $status,
        'needs_closure_review' =>
            in_array(
                $status,
                [
                    'temporary',
                    'seasonal',
                    'permanent',
                ],
                true
            ),
    ]);
} catch (InvalidArgumentException $exception) {
    operating_report_response(
        [
            'ok' => false,
            'error' => $exception->getMessage(),
        ],
        422
    );
} catch (PDOException $exception) {
    operating_report_response(
        [
            'ok' => false,
            'error' =>
                'Operating information could not be saved. '
                . 'Make sure the Place operating database fields are installed.',
        ],
        500
    );
}
