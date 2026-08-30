<?php

declare(strict_types=1);

$privateConfig = dirname(__DIR__, 2) . '/private/config.php';

if (!is_file($privateConfig)) {
    throw new RuntimeException('Private configuration file not found.');
}

require_once $privateConfig;
