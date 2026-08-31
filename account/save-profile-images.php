<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /profile.php', true, 303);
    exit;
}

$expected = (string) ($_SESSION['profile_image_csrf'] ?? '');
$submitted = (string) ($_POST['csrf_token'] ?? '');

if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
    $_SESSION['profile_flash_error'] = 'Your session expired. Reload the profile page and try again.';
    header('Location: /profile.php', true, 303);
    exit;
}

try {
    $photos = llama_photo_decode_form_photos($_POST['photos_json'] ?? '[]');
    $token = trim((string) ($_POST['photo_stage_token'] ?? ''));

    if (!$photos) {
        throw new InvalidArgumentException('Choose at least one profile photo.');
    }

    profile_images_add_staged($userId, $token, $photos);
    $_SESSION['profile_flash_success'] = 'Profile photos saved.';
} catch (Throwable $exception) {
    $_SESSION['profile_flash_error'] = $exception->getMessage();
}

header('Location: /profile.php', true, 303);
exit;
