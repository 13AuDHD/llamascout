<?php

declare(strict_types=1);

require_once __DIR__ . '/printify.php';

function llama_printify_webhook_version_key(): string
{
    $secret = llama_printify_webhook_secret();

    if ($secret === '') {
        return '';
    }

    return substr(
        hash_hmac(
            'sha256',
            'llama-scout-printify-webhook-url-v1',
            $secret
        ),
        0,
        24
    );
}

function llama_printify_webhook_url(): string
{
    $versionKey =
        llama_printify_webhook_version_key();

    $url =
        'https://llamascout.com/printify-webhook.php';

    return $versionKey !== ''
        ? $url . '?v=' . rawurlencode($versionKey)
        : $url;
}

function llama_printify_webhook_topics(): array
{
    return [
        'order:created',
        'order:updated',
        'order:sent-to-production',
        'order:shipment:created',
        'order:shipment:delivered',
    ];
}

function llama_printify_webhook_secret(): string
{
    $config = llama_printify_config();

    $configured = trim(
        (string) (
            $config['webhook_secret']
            ?? ''
        )
    );

    if ($configured !== '') {
        return $configured;
    }

    $token = llama_printify_token();

    if ($token === '') {
        return '';
    }

    /*
     * A one-purpose secret derived from the private API token.
     * The API token itself never leaves /private and is never used
     * directly as the webhook secret.
     */
    return hash_hmac(
        'sha256',
        'llama-scout-printify-webhook-v1',
        $token
    );
}

function llama_printify_expected_signature(
    string $rawBody
): string {
    $secret = llama_printify_webhook_secret();

    if ($secret === '') {
        return '';
    }

    return 'sha256=' . hash_hmac(
        'sha256',
        $rawBody,
        $secret
    );
}

function llama_printify_verify_webhook_signature(
    string $rawBody,
    string $providedSignature
): bool {
    $expected =
        llama_printify_expected_signature(
            $rawBody
        );

    $providedSignature = trim(
        $providedSignature
    );

    return
        $expected !== ''
        && $providedSignature !== ''
        && hash_equals(
            $expected,
            $providedSignature
        );
}

function llama_printify_webhooks(): array
{
    $response = llama_printify_request(
        'GET',
        'shops/' .
            rawurlencode(llama_printify_shop_id()) .
            '/webhooks.json'
    );

    return array_values(
        array_filter(
            $response,
            'is_array'
        )
    );
}

function llama_printify_configure_secure_webhooks(): array
{
    $shopId = llama_printify_shop_id();
    $url = llama_printify_webhook_url();
    $secret = llama_printify_webhook_secret();

    if ($secret === '') {
        throw new RuntimeException(
            'Printify webhook security secret is unavailable.'
        );
    }

    $existing = llama_printify_webhooks();
    $configured = [];

    foreach (
        llama_printify_webhook_topics()
        as $topic
    ) {
        /*
         * Printify does not expose an existing webhook secret back to us,
         * so a matching URL alone cannot prove that signature protection
         * is actually configured. Replace only Llama Scout's own exact
         * topic + URL subscriptions, then recreate them with the current
         * shared secret.
         */
        foreach ($existing as $webhook) {
            $existingUrl = trim(
                (string) (
                    $webhook['url']
                    ?? ''
                )
            );

            $existingHost = strtolower(
                trim(
                    (string) (
                        parse_url(
                            $existingUrl,
                            PHP_URL_HOST
                        )
                        ?? ''
                    )
                )
            );

            $existingPath = trim(
                (string) (
                    parse_url(
                        $existingUrl,
                        PHP_URL_PATH
                    )
                    ?? ''
                )
            );

            if (
                (string) ($webhook['topic'] ?? '')
                    !== $topic
                || $existingHost !== 'llamascout.com'
                || $existingPath !== '/printify-webhook.php'
            ) {
                continue;
            }

            $webhookId = trim(
                (string) (
                    $webhook['id']
                    ?? ''
                )
            );

            if ($webhookId === '') {
                continue;
            }

            llama_printify_request(
                'DELETE',
                'shops/' .
                    rawurlencode($shopId) .
                    '/webhooks/' .
                    rawurlencode($webhookId) .
                    '.json',
                null,
                [
                    'host' => 'llamascout.com',
                ]
            );
        }

        $created = llama_printify_request(
            'POST',
            'shops/' .
                rawurlencode($shopId) .
                '/webhooks.json',
            [
                'topic' => $topic,
                'url' => $url,
                'secret' => $secret,
            ]
        );

        $configured[] = $created;
    }

    return $configured;
}

function llama_printify_secure_webhooks_active(): bool
{
    if (!llama_printify_configured()) {
        return false;
    }

    $url = llama_printify_webhook_url();
    $topics = array_fill_keys(
        llama_printify_webhook_topics(),
        false
    );

    foreach (llama_printify_webhooks() as $webhook) {
        $topic = (string) ($webhook['topic'] ?? '');
        $remoteUrl = trim(
            (string) ($webhook['url'] ?? '')
        );

        if (
            array_key_exists($topic, $topics)
            && $remoteUrl === $url
        ) {
            $topics[$topic] = true;
        }
    }

    return !in_array(
        false,
        $topics,
        true
    );
}

function llama_printify_system_health_card(): array
{
    if (!llama_printify_configured()) {
        return [
            'key' => 'printify_webhook',
            'label' => 'Printify webhook',
            'status' => 'down',
            'value' => 'Not configured',
            'detail' =>
                'The private Printify API token is missing.',
            'icon' => 'shield',
        ];
    }

    try {
        if (llama_printify_secure_webhooks_active()) {
            return [
                'key' => 'printify_webhook',
                'label' => 'Printify webhook',
                'status' => 'good',
                'value' => 'Installed',
                'detail' =>
                    'All required Printify order webhook topics point to the signed Llama Scout callback.',
                'icon' => 'shield',
            ];
        }

        return [
            'key' => 'printify_webhook',
            'label' => 'Printify webhook',
            'status' => 'attention',
            'value' => 'Needs setup',
            'detail' =>
                'Open Integrations > Printify Webhook and install the signed order webhooks.',
            'icon' => 'shield',
        ];
    } catch (Throwable $exception) {
        if (function_exists('llama_log_caught_exception')) {
            llama_log_caught_exception(
                $exception,
                'admin.system_health.printify_webhook'
            );
        }

        return [
            'key' => 'printify_webhook',
            'label' => 'Printify webhook',
            'status' => 'down',
            'value' => 'Check failed',
            'detail' =>
                'Llama Scout could not verify the Printify webhook configuration.',
            'icon' => 'shield',
        ];
    }
}
