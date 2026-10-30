<?php

declare(strict_types=1);

/*
 * V3 canonical reporting: this legacy supplemental writer is retired.
 * Admin Place Report writes through admin_place_save_shared_report(),
 * which validates fields through the canonical registry and publishes
 * their values and answer states in the same database transaction.
 *
 * Never accept a second direct SQL write here: it would collapse
 * Unknown and Unanswered and could overwrite the canonical save.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
http_response_code(410);
echo json_encode([
    'ok' => false,
    'error' => 'This legacy save route has been retired. Save answers using the Admin Place Report.',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
