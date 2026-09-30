<?php

declare(strict_types=1);

/* =========================================================
   LLAMA SCOUT PLACE ACCESS ALERTS

   Access condition is intentionally separate from publication status.
   A Place can stay Active / Featured while visitors are warned that
   recent reports indicate access may be limited.
   ========================================================= */

function llama_place_access_alert_states(): array
{
    return [
        'reported' => 'Access reported',
        'temporarily-unavailable' => 'Temporarily unavailable',
        'confirmed-closed' => 'Confirmed closed',
    ];
}

function llama_place_access_alert_reasons(): array
{
    return [
        'road-closed' => 'Road closed',
        'gate-closed' => 'Gate closed',
        'road-impassable' => 'Road impassable',
        'seasonal-closure' => 'Seasonal closure',
        'land-manager-closure' => 'Land manager closure',
        'weather' => 'Weather conditions',
        'wildfire' => 'Wildfire / fire closure',
        'construction' => 'Construction / road work',
        'unknown' => 'Unknown / not yet verified',
        'other' => 'Other access issue',
    ];
}

function llama_place_access_alert_report_types(): array
{
    return [
        'road-closed',
        'place-inaccessible',
        'closure-status',
        'location-access',
        'safety',
    ];
}

function llama_place_access_alert_is_report_type(string $problemType): bool
{
    return in_array(
        $problemType,
        llama_place_access_alert_report_types(),
        true
    );
}

function llama_place_access_alert(
    PDO $db,
    int $placeId
): ?array {
    if ($placeId < 1) {
        return null;
    }

    try {
        $stmt = $db->prepare(
            'SELECT *
             FROM place_access_alerts
             WHERE place_id = ?
               AND is_active = 1
             LIMIT 1'
        );

        $stmt->execute([$placeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable) {
        /*
         * Public Place pages must remain available if the migration has not
         * been installed yet or the alert table is temporarily unavailable.
         */
        return null;
    }
}

function llama_place_access_alert_state_label(string $state): string
{
    return llama_place_access_alert_states()[$state]
        ?? 'Access alert';
}

function llama_place_access_alert_reason_label(string $reason): string
{
    return llama_place_access_alert_reasons()[$reason]
        ?? 'Access issue';
}

function llama_place_access_alert_public_title(string $state): string
{
    return match ($state) {
        'temporarily-unavailable' =>
            'This Place may be temporarily unavailable',
        'confirmed-closed' =>
            'This Place is reported closed',
        default =>
            'Access may be limited',
    };
}

function llama_place_access_alert_public_copy(array $alert): string
{
    $custom = trim((string) ($alert['public_note'] ?? ''));

    if ($custom !== '') {
        return $custom;
    }

    return match ((string) ($alert['state'] ?? 'reported')) {
        'temporarily-unavailable' =>
            'Recent information indicates that access to this Place may currently be unavailable. Conditions can change, so check current access before relying on this location.',
        'confirmed-closed' =>
            'Recent information indicates that this Place is closed or cannot currently be accessed. Do not rely on this location until access has been confirmed again.',
        default =>
            'A recent report indicates that this Place may currently be closed or inaccessible. Conditions can change, so check current access before relying on this location.',
    };
}

function llama_place_access_alert_validate_review_date(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Review date must use YYYY-MM-DD.');
    }

    return $value;
}

function llama_place_access_alert_save(
    PDO $db,
    int $placeId,
    int $actorUserId,
    string $state,
    string $reason,
    ?int $sourceReportId = null,
    string $publicNote = '',
    string $internalNote = '',
    ?string $reviewAfter = null
): array {
    $states = llama_place_access_alert_states();
    $reasons = llama_place_access_alert_reasons();

    if ($placeId < 1) {
        throw new InvalidArgumentException('Place not found.');
    }

    if (!isset($states[$state])) {
        throw new InvalidArgumentException('Invalid access alert state.');
    }

    if (!isset($reasons[$reason])) {
        throw new InvalidArgumentException('Invalid access alert reason.');
    }

    $publicNote = trim($publicNote);
    $internalNote = trim($internalNote);
    $reviewAfter = llama_place_access_alert_validate_review_date($reviewAfter);

    if (mb_strlen($publicNote) > 600) {
        throw new InvalidArgumentException('Public access note is too long.');
    }

    if (mb_strlen($internalNote) > 4000) {
        throw new InvalidArgumentException('Internal access note is too long.');
    }

    $sourceReportId = $sourceReportId && $sourceReportId > 0
        ? $sourceReportId
        : null;

    $db->beginTransaction();

    try {
        $existingStmt = $db->prepare(
            'SELECT *
             FROM place_access_alerts
             WHERE place_id = ?
             LIMIT 1
             FOR UPDATE'
        );
        $existingStmt->execute([$placeId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $firstReportedAt = !empty($existing['first_reported_at'])
            ? (string) $existing['first_reported_at']
            : gmdate('Y-m-d H:i:s');

        $stmt = $db->prepare(
            'INSERT INTO place_access_alerts (
                place_id,
                state,
                reason,
                source_report_id,
                public_note,
                internal_note,
                first_reported_at,
                last_confirmed_at,
                review_after,
                is_active,
                created_by,
                updated_by,
                created_at,
                updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                state = VALUES(state),
                reason = VALUES(reason),
                source_report_id = VALUES(source_report_id),
                public_note = VALUES(public_note),
                internal_note = VALUES(internal_note),
                first_reported_at = VALUES(first_reported_at),
                last_confirmed_at = VALUES(last_confirmed_at),
                review_after = VALUES(review_after),
                is_active = 1,
                updated_by = VALUES(updated_by),
                updated_at = UTC_TIMESTAMP()'
        );

        $lastConfirmedAt = $state === 'confirmed-closed'
            ? gmdate('Y-m-d H:i:s')
            : ($existing['last_confirmed_at'] ?? null);

        $stmt->execute([
            $placeId,
            $state,
            $reason,
            $sourceReportId,
            $publicNote !== '' ? $publicNote : null,
            $internalNote !== '' ? $internalNote : null,
            $firstReportedAt,
            $lastConfirmedAt,
            $reviewAfter,
            $actorUserId > 0 ? $actorUserId : null,
            $actorUserId > 0 ? $actorUserId : null,
        ]);

        $history = $db->prepare(
            'INSERT INTO place_access_alert_history (
                place_id,
                source_report_id,
                action,
                old_state,
                new_state,
                reason,
                public_note,
                internal_note,
                changed_by,
                created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
        );

        $history->execute([
            $placeId,
            $sourceReportId,
            !empty($existing['is_active']) ? 'updated' : 'activated',
            !empty($existing['is_active'])
                ? (string) ($existing['state'] ?? '')
                : null,
            $state,
            $reason,
            $publicNote !== '' ? $publicNote : null,
            $internalNote !== '' ? $internalNote : null,
            $actorUserId > 0 ? $actorUserId : null,
        ]);

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }

    return llama_place_access_alert($db, $placeId) ?? [];
}

function llama_place_access_alert_clear(
    PDO $db,
    int $placeId,
    int $actorUserId,
    ?int $sourceReportId = null,
    string $internalNote = ''
): void {
    if ($placeId < 1) {
        throw new InvalidArgumentException('Place not found.');
    }

    $internalNote = trim($internalNote);

    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'SELECT *
             FROM place_access_alerts
             WHERE place_id = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$placeId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if (!$existing || empty($existing['is_active'])) {
            $db->commit();
            return;
        }

        $db->prepare(
            'UPDATE place_access_alerts
             SET
                is_active = 0,
                updated_by = ?,
                updated_at = UTC_TIMESTAMP()
             WHERE place_id = ?'
        )->execute([
            $actorUserId > 0 ? $actorUserId : null,
            $placeId,
        ]);

        $history = $db->prepare(
            'INSERT INTO place_access_alert_history (
                place_id,
                source_report_id,
                action,
                old_state,
                new_state,
                reason,
                public_note,
                internal_note,
                changed_by,
                created_at
             ) VALUES (?, ?, "cleared", ?, NULL, ?, ?, ?, ?, UTC_TIMESTAMP())'
        );

        $history->execute([
            $placeId,
            $sourceReportId && $sourceReportId > 0
                ? $sourceReportId
                : ($existing['source_report_id'] ?? null),
            (string) ($existing['state'] ?? ''),
            (string) ($existing['reason'] ?? 'unknown'),
            $existing['public_note'] ?? null,
            $internalNote !== ''
                ? $internalNote
                : ($existing['internal_note'] ?? null),
            $actorUserId > 0 ? $actorUserId : null,
        ]);

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}
