<?php

declare(strict_types=1);

require_once __DIR__ . '/printful.php';

function llama_printful_webhook_key(): string
{
    $config =
        llama_printful_config();

    $configured =
        trim(
            (string) (
                $config['webhook_key']
                ?? ''
            )
        );

    if ($configured !== '') {
        return $configured;
    }

    $token =
        llama_printful_token();

    if ($token === '') {
        return '';
    }

    /*
     * One-purpose credential derived from the private Printful token.
     * It cannot be used as the Printful API token itself.
     */
    return hash_hmac(
        'sha256',
        'llama-scout-printful-webhook-v1',
        $token
    );
}

function llama_printful_verify_webhook_key(
    string $provided
): bool {
    $expected =
        llama_printful_webhook_key();

    return
        $expected !== ''
        && $provided !== ''
        && hash_equals(
            $expected,
            $provided
        );
}

function llama_printful_keyed_webhook_url(): string
{
    $key =
        llama_printful_webhook_key();

    if ($key === '') {
        throw new RuntimeException(
            'Printful webhook security key is unavailable.'
        );
    }

    return
        'https://llamascout.com/printful-webhook.php?key=' .
        rawurlencode($key);
}

function llama_printful_configure_secure_webhook(): array
{
    /*
     * Keep the current v1 event set while protecting the callback URL.
     * This can later be replaced by Printful v2 signed webhooks as a
     * separate integration migration.
     */
    $types = [
        'package_shipped',
        'package_returned',
        'order_created',
        'order_failed',
        'order_canceled',
        'order_put_hold',
        'order_remove_hold',
    ];

    $response =
        llama_printful_request(
            'POST',
            'webhooks',
            [
                'url' =>
                    llama_printful_keyed_webhook_url(),
                'types' =>
                    $types,
            ]
        );

    $result =
        $response['result']
        ?? [];

    return is_array($result)
        ? $result
        : [];
}

function llama_printful_secure_webhook_active(): bool
{
    $response =
        llama_printful_request(
            'GET',
            'webhooks'
        );

    $result =
        $response['result']
        ?? [];

    if (!is_array($result)) {
        return false;
    }

    $configuredUrl =
        trim(
            (string) (
                $result['url']
                ?? ''
            )
        );

    if ($configuredUrl === '') {
        return false;
    }

    return hash_equals(
        llama_printful_keyed_webhook_url(),
        $configuredUrl
    );
}

function llama_printful_system_health_card(): array
{
    if (!llama_printful_configured()) {
        return [
            'key' => 'printful_webhook',
            'label' => 'Printful webhook',
            'status' => 'down',
            'value' => 'Not configured',
            'detail' =>
                'The private Printful API token is missing. '
                . 'Printful fulfillment and webhook verification cannot operate.',
            'icon' => 'fa-shield-halved',
        ];
    }

    try {
        $secureActive =
            llama_printful_secure_webhook_active();

        if ($secureActive) {
            return [
                'key' => 'printful_webhook',
                'label' => 'Printful webhook',
                'status' => 'good',
                'value' => 'Protected',
                'detail' =>
                    'Printful is reachable and its configured webhook '
                    . 'matches the protected keyed Llama Scout callback.',
                'icon' => 'fa-shield-halved',
            ];
        }

        return [
            'key' => 'printful_webhook',
            'label' => 'Printful webhook',
            'status' => 'attention',
            'value' => 'Needs protection',
            'detail' =>
                'Printful is connected, but its configured webhook '
                . 'does not match the protected Llama Scout callback. '
                . 'Open Integrations > Printful Webhook and activate protection.',
            'icon' => 'fa-shield-halved',
        ];
    } catch (Throwable $exception) {
        if (
            function_exists(
                'llama_log_caught_exception'
            )
        ) {
            llama_log_caught_exception(
                $exception,
                'admin.system_health.printful_webhook'
            );
        }

        return [
            'key' => 'printful_webhook',
            'label' => 'Printful webhook',
            'status' => 'down',
            'value' => 'Check failed',
            'detail' =>
                'Llama Scout could not verify the Printful webhook with Printful. '
                . 'Check the Printful connection in Integrations.',
            'icon' => 'fa-shield-halved',
        ];
    }
}

