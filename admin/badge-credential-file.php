<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/badge-credentials.php';

moderation_require_admin();

$submissionId = (int) ($_GET['id'] ?? 0);
$submission = llama_badge_credential_submission(db(), $submissionId);

if (!$submission) {
    http_response_code(404);
    exit('Credential file not found.');
}

try {
    llama_badge_credential_send_file(
        $submission,
        isset($_GET['inline']) && $_GET['inline'] === '1'
    );
} catch (Throwable) {
    http_response_code(404);
    exit('Credential file not found.');
}
