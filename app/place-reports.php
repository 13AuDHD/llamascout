<?php

declare(strict_types=1);

function place_report_csrf_token(): string
{
    if (empty($_SESSION['place_report_csrf'])) {
        $_SESSION['place_report_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['place_report_csrf'];
}

function place_report_verify_csrf(string $token): bool
{
    $stored = (string) ($_SESSION['place_report_csrf'] ?? '');

    return $stored !== ''
        && $token !== ''
        && hash_equals($stored, $token);
}

function place_report_problem_types(): array
{
    return [
        'incorrect-information' => 'Incorrect information',
        'location-access' => 'Location or access problem',
        'closure-status' => 'Closure or status changed',
        'amenities' => 'Amenities are incorrect',
        'sensory-information' => 'Sensory information is incorrect',
        'photo' => 'Photo problem',
        'safety' => 'Safety concern',
        'duplicate-place' => 'Duplicate place',
        'other' => 'Other',
    ];
}

function submit_place_report(
    int $userId,
    int $placeId,
    string $problemType,
    string $details
): int {
    $problemTypes = place_report_problem_types();

    if (!isset($problemTypes[$problemType])) {
        throw new InvalidArgumentException('Invalid problem type.');
    }

    $details = trim($details);

    if ($details === '') {
        throw new InvalidArgumentException('Please describe the problem.');
    }

    if (mb_strlen($details) > 4000) {
        throw new InvalidArgumentException('Report details are too long.');
    }

    $stmt = db()->prepare(
        "INSERT INTO place_reports
            (place_id, user_id, problem_type, details, status)
         VALUES
            (?, ?, ?, ?, 'open')"
    );

    $stmt->execute([
        $placeId,
        $userId,
        $problemType,
        $details,
    ]);

    return (int) db()->lastInsertId();
}
