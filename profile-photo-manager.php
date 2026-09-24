<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

auth_start_session();
header('Content-Type: application/json; charset=utf-8');

function profile_photo_manager_respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function profile_photo_manager_active_ids(PDO $db, int $userId): array
{
    $stmt = $db->prepare(
        'SELECT id
         FROM user_profile_images
         WHERE user_id = :user_id
           AND is_active = 1
         ORDER BY sort_order ASC, created_at ASC, id ASC'
    );
    $stmt->execute([':user_id' => $userId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function profile_photo_manager_reorder(PDO $db, int $userId, array $imageIds): int
{
    $normalized = [];

    foreach ($imageIds as $imageId) {
        $imageId = (int) $imageId;
        if ($imageId <= 0) {
            throw new InvalidArgumentException('The photo order contains an invalid image ID.');
        }
        if (in_array($imageId, $normalized, true)) {
            throw new InvalidArgumentException('The photo order contains a duplicate image ID.');
        }
        $normalized[] = $imageId;
    }

    $activeIds = profile_photo_manager_active_ids($db, $userId);

    $expected = $activeIds;
    $received = $normalized;
    sort($expected, SORT_NUMERIC);
    sort($received, SORT_NUMERIC);

    if ($expected !== $received) {
        throw new InvalidArgumentException('The photo gallery changed before this order could be saved. Refresh the page and try again.');
    }

    if ($normalized === []) {
        return 0;
    }

    $db->beginTransaction();

    try {
        $reset = $db->prepare(
            'UPDATE user_profile_images
             SET is_primary = 0,
                 updated_at = NOW()
             WHERE user_id = :user_id
               AND is_active = 1'
        );
        $reset->execute([':user_id' => $userId]);

        $update = $db->prepare(
            'UPDATE user_profile_images
             SET sort_order = :sort_order,
                 is_primary = :is_primary,
                 updated_at = NOW()
             WHERE id = :id
               AND user_id = :user_id
               AND is_active = 1'
        );

        foreach ($normalized as $index => $imageId) {
            $update->execute([
                ':sort_order' => ($index + 1) * 10,
                ':is_primary' => $index === 0 ? 1 : 0,
                ':id' => $imageId,
                ':user_id' => $userId,
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    return $normalized[0];
}

function profile_photo_manager_state(PDO $db, int $userId): array
{
    $images = profile_images_for_user($db, $userId, false);
    $photos = [];

    foreach ($images as $image) {
        $photos[] = [
            'id' => (int) ($image['id'] ?? 0),
            'sort_order' => (int) ($image['sort_order'] ?? 0),
            'is_featured' => ((int) ($image['is_primary'] ?? 0)) === 1,
            'url' => (string) ($image['url'] ?? ''),
        ];
    }

    return [
        'photos' => $photos,
        'featured_image_id' => isset($photos[0]) ? (int) $photos[0]['id'] : 0,
        'featured_url' => isset($photos[0]) ? (string) $photos[0]['url'] : '',
    ];
}

if (!llama_is_logged_in()) {
    profile_photo_manager_respond(401, [
        'ok' => false,
        'message' => 'Please sign in again before changing profile photos.',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profile_photo_manager_respond(405, [
        'ok' => false,
        'message' => 'This endpoint only accepts POST requests.',
    ]);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);

if (!is_array($payload)) {
    profile_photo_manager_respond(400, [
        'ok' => false,
        'message' => 'The request could not be read.',
    ]);
}

$sessionCsrf = (string) ($_SESSION['profile_image_csrf'] ?? '');
$requestCsrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['csrf_token'] ?? ''));

if ($sessionCsrf === '' || $requestCsrf === '' || !hash_equals($sessionCsrf, $requestCsrf)) {
    profile_photo_manager_respond(419, [
        'ok' => false,
        'message' => 'Your profile photo session expired. Refresh the page and try again.',
    ]);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    profile_photo_manager_respond(401, [
        'ok' => false,
        'message' => 'Please sign in again before changing profile photos.',
    ]);
}

$db = db();
profile_images_install($db);
$action = trim((string) ($payload['action'] ?? ''));

try {
    if ($action === 'reorder') {
        $imageIds = $payload['image_ids'] ?? null;
        if (!is_array($imageIds)) {
            throw new InvalidArgumentException('A complete photo order is required.');
        }

        profile_photo_manager_reorder($db, $userId, $imageIds);
        $state = profile_photo_manager_state($db, $userId);

        profile_photo_manager_respond(200, [
            'ok' => true,
            'message' => 'Photo order saved.',
            ...$state,
        ]);
    }

    if ($action === 'delete') {
        $imageId = (int) ($payload['image_id'] ?? 0);
        if ($imageId <= 0) {
            throw new InvalidArgumentException('A valid profile photo is required.');
        }

        $exists = $db->prepare(
            'SELECT id
             FROM user_profile_images
             WHERE id = :id
               AND user_id = :user_id
               AND is_active = 1
             LIMIT 1'
        );
        $exists->execute([
            ':id' => $imageId,
            ':user_id' => $userId,
        ]);

        if (!$exists->fetchColumn()) {
            profile_photo_manager_respond(404, [
                'ok' => false,
                'message' => 'That profile photo could not be found.',
            ]);
        }

        $localPath = profile_images_delete($db, $userId, $imageId);

        $remainingIds = profile_photo_manager_active_ids($db, $userId);
        profile_photo_manager_reorder($db, $userId, $remainingIds);

        if ($localPath && is_file($localPath)) {
            @unlink($localPath);
        }

        $state = profile_photo_manager_state($db, $userId);

        profile_photo_manager_respond(200, [
            'ok' => true,
            'message' => 'Profile photo removed.',
            ...$state,
        ]);
    }

    profile_photo_manager_respond(422, [
        'ok' => false,
        'message' => 'Unknown profile photo action.',
    ]);
} catch (InvalidArgumentException $e) {
    profile_photo_manager_respond(409, [
        'ok' => false,
        'message' => $e->getMessage(),
    ]);
} catch (Throwable $e) {
    error_log('Profile photo manager failed: ' . $e->getMessage());
    profile_photo_manager_respond(500, [
        'ok' => false,
        'message' => 'Llama Scout could not save that profile photo change. Please try again.',
    ]);
}
