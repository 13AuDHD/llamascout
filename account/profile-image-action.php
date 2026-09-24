<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
$accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
$isJson = str_contains($contentType, 'application/json')
    || str_contains($accept, 'application/json');

function profile_image_action_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function profile_image_action_redirect(): never
{
    header('Location: /profile.php', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isJson) {
        profile_image_action_json(405, [
            'ok' => false,
            'message' => 'This profile-photo action requires a POST request.',
        ]);
    }

    profile_image_action_redirect();
}

$payload = $_POST;

if (str_contains($contentType, 'application/json')) {
    $decoded = json_decode((string) file_get_contents('php://input'), true);

    if (!is_array($decoded)) {
        profile_image_action_json(400, [
            'ok' => false,
            'message' => 'That profile-photo request could not be read.',
        ]);
    }

    $payload = $decoded;
}

$expected = (string) ($_SESSION['profile_image_csrf'] ?? '');
$submitted = (string) (
    $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? $payload['csrf_token']
    ?? ''
);
$action = trim((string) ($payload['action'] ?? ''));
$imageId = (int) ($payload['image_id'] ?? 0);

if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
    if ($isJson) {
        profile_image_action_json(419, [
            'ok' => false,
            'message' => 'Your profile-photo session expired. Reload the page and try again.',
        ]);
    }

    $_SESSION['profile_flash_error'] = 'Your session expired. Reload the profile page and try again.';
    profile_image_action_redirect();
}

try {
    if ($action === 'reorder') {
        $imageIds = $payload['image_ids'] ?? null;

        if (!is_array($imageIds)) {
            throw new InvalidArgumentException('A complete photo order is required.');
        }

        $featuredId = profile_images_reorder($userId, $imageIds);

        if ($isJson) {
            profile_image_action_json(200, [
                'ok' => true,
                'message' => 'Photo order saved.',
                'featured_image_id' => $featuredId,
                'count' => count($imageIds),
            ]);
        }

        $_SESSION['profile_flash_success'] = 'Photo order saved.';
        profile_image_action_redirect();
    }

    if ($action === 'primary') {
        if ($imageId < 1) {
            throw new InvalidArgumentException('Choose a valid profile photo.');
        }

        $images = profile_images_for_user($userId);
        $ids = array_map(
            static fn (array $image): int => (int) ($image['id'] ?? 0),
            $images
        );

        if (!in_array($imageId, $ids, true)) {
            throw new InvalidArgumentException('That profile image could not be found.');
        }

        $ids = array_values(array_filter(
            $ids,
            static fn (int $id): bool => $id !== $imageId
        ));
        array_unshift($ids, $imageId);
        profile_images_reorder($userId, $ids);

        $_SESSION['profile_flash_success'] = 'Featured profile photo updated.';
        profile_image_action_redirect();
    }

    if ($action === 'delete') {
        if ($imageId < 1) {
            throw new InvalidArgumentException('Choose a valid profile photo.');
        }

        profile_images_delete($userId, $imageId);

        $remaining = profile_images_for_user($userId);
        $remainingIds = array_map(
            static fn (array $image): int => (int) ($image['id'] ?? 0),
            $remaining
        );

        $featuredId = profile_images_reorder($userId, $remainingIds);

        if ($isJson) {
            profile_image_action_json(200, [
                'ok' => true,
                'message' => 'Profile photo removed.',
                'featured_image_id' => $featuredId,
                'count' => count($remainingIds),
            ]);
        }

        $_SESSION['profile_flash_success'] = 'Profile photo removed.';
        profile_image_action_redirect();
    }

    throw new InvalidArgumentException('Unknown profile-photo action.');
} catch (Throwable $exception) {
    $reference = llama_log_caught_exception(
        $exception,
        'account.profile_image_action',
        [
            'user_id' => $userId,
            'image_id' => $imageId,
            'action' => $action,
        ],
        [InvalidArgumentException::class]
    );

    $message = $reference === null
        ? $exception->getMessage()
        : llama_error_message_with_reference(
            'The profile photo action could not be completed.',
            $reference
        );

    if ($isJson) {
        profile_image_action_json(
            $exception instanceof InvalidArgumentException ? 409 : 500,
            [
                'ok' => false,
                'message' => $message,
            ]
        );
    }

    $_SESSION['profile_flash_error'] = $message;
    profile_image_action_redirect();
}
