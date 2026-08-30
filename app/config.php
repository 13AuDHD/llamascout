<?php

declare(strict_types=1);

function app_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $privateConfig = dirname(__DIR__, 2) . '/private/config.php';

    if (!is_file($privateConfig)) {
        throw new RuntimeException('Private configuration file not found.');
    }

    $loaded = require $privateConfig;

    if (!is_array($loaded)) {
        throw new RuntimeException('Private configuration file is invalid.');
    }

    $config = $loaded;

    return $config;
}
