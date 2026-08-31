<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/photo-upload.php';
require_once dirname(__DIR__) . '/app/photo-staging.php';
require_once dirname(__DIR__) . '/app/profile-images.php';

require_login();
start_llama_session();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /profile.php', true, 303);
    exit;
}

$expected = (string) ($_SESSION['profile_image_csrf'] ?? '');
$submitted = (string) ($_POST['csrf_token'] ?? '');

if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
    http_response_code(403);
    exit('Your session expired. Return to your profile and try again.');
}

try {
    $photos = llama_photo_decode_form_photos($_POST['photos_json'] ?? '[]');
    $token = trim((string) ($_POST['photo_stage_token'] ?? ''));

    if (!$photos) {
        throw new InvalidArgumentException('Choose at least one profile image.');
    }

    profile_images_add_staged($userId, $token, $photos);

    header('Location: /profile.php?photos=updated', true, 303);
    exit;
} catch (InvalidArgumentException | RuntimeException $exception) {
    $_SESSION['profile_image_upload_error'] = $exception->getMessage();
    header('Location: /profile.php?photos=error', true, 303);
    exit;
}
