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
    string $details,
    string $photoToken = '',
    array $submittedPhotos = []
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

    if ($submittedPhotos && trim($photoToken) === '') {
        throw new InvalidArgumentException('The photo upload session is missing. Please upload the photos again.');
    }

    $db = db();
    $reportId = 0;

    try {
        $db->beginTransaction();

        $stmt = $db->prepare(
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

        $reportId = (int) $db->lastInsertId();

        if (trim($photoToken) !== '') {
            $photos = llama_photo_commit_stage(
                'place-report',
                $userId,
                $photoToken,
                $submittedPhotos,
                '/uploads/place-reports/' . $reportId
            );

            if ($photos) {
                $imageStmt = $db->prepare(
                    'INSERT INTO place_report_images
                        (report_id, file_path, original_name, mime_type, file_size, sort_order)
                     VALUES
                        (:report_id, :file_path, :original_name, :mime_type, :file_size, :sort_order)'
                );

                foreach ($photos as $index => $photo) {
                    $imageStmt->execute([
                        ':report_id' => $reportId,
                        ':file_path' => (string) ($photo['path'] ?? ''),
                        ':original_name' => (string) ($photo['original_name'] ?? ''),
                        ':mime_type' => (string) ($photo['mime_type'] ?? 'image/jpeg'),
                        ':file_size' => (int) ($photo['size'] ?? 0),
                        ':sort_order' => $index,
                    ]);
                }
            }
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if ($reportId > 0) {
            llama_photo_remove_tree(dirname(__DIR__) . '/uploads/place-reports/' . $reportId);
        }

        throw $exception;
    }

    return $reportId;
}
