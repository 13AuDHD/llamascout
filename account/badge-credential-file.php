<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/badge-credentials.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$submissionId = (int) ($_GET['id'] ?? 0);

$submission = llama_badge_credential_submission_for_user(
    db(),
    $submissionId,
    $userId
);

if (!$submission) {
    http_response_code(404);
    exit('Credential file not found.');
}

try {
    llama_badge_credential_send_file($submission, false);
} catch (Throwable) {
    http_response_code(404);
    exit('Credential file not found.');
}
