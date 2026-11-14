<?php

declare(strict_types=1);

// Scheduled via cPanel Cron Jobs. This script must never run over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/database.php';
require_once dirname(__DIR__) . '/app/support-inbox.php';

try {
    $result = llama_support_inbox_sync(db(), 15, false);
    if ($result['error'] !== '') {
        fwrite(STDERR, 'Llama Scout Support inbox: ' . $result['error'] . PHP_EOL);
        exit(1);
    }
    if ($result['ran']) {
        fwrite(
            STDOUT,
            'Support inbox: checked=' . $result['checked']
            . ' imported=' . $result['imported']
            . ' review=' . $result['review']
            . ' ignored=' . $result['ignored']
            . PHP_EOL
        );
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Support inbox worker failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
