<?php
declare(strict_types=1);
// Retired remote importer. Keep the old URL inert even on FTP deployments
// that retain deleted files. State imports now run only from local CLI.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['ok'=>false,'error'=>'Remote import endpoint retired.']);
