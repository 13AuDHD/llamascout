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
$imageId = (int) ($_POST['image_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');

if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted) || $imageId < 1) {
    $_SESSION['profile_flash_error'] = 'That profile-photo request was not valid.';
    header('Location: /profile.php', true, 303);
    exit;
}

try {
    if ($action === 'primary') {
        profile_images_set_primary($userId, $imageId);
        $_SESSION['profile_flash_success'] = 'Profile picture updated.';
    } elseif ($action === 'delete') {
        profile_images_delete($userId, $imageId);
        $_SESSION['profile_flash_success'] = 'Profile photo removed.';
    } else {
        throw new InvalidArgumentException('Unknown profile-photo action.');
    }
} catch (Throwable $exception) {
    $reference = llama_log_caught_exception(
        $exception,
        'account.profile_image_action',
        ['user_id' => $userId, 'image_id' => $imageId, 'action' => $action],
        [InvalidArgumentException::class]
    );

    $_SESSION['profile_flash_error'] = $reference === null
        ? $exception->getMessage()
        : llama_error_message_with_reference('The profile photo action could not be completed.', $reference);
}

header('Location: /profile.php', true, 303);
exit;
