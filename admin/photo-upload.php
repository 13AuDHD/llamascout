<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/photo-endpoint.php';

moderation_require_admin();

llama_photo_run_endpoint();
